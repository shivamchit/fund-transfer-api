<?php

namespace App\Service;

use App\DTO\TransferRequest;
use App\Entity\AuditLog;
use App\Entity\Transaction;
use App\Exception\AccountNotFoundException;
use App\Exception\CurrencyMismatchException;
use App\Exception\InsufficientFundsException;
use App\Exception\SameAccountException;
use App\Exception\TransferLockException;
use App\Repository\AccountRepository;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Uid\Uuid;

class TransferService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountRepository      $accountRepository,
        private readonly AuditLogRepository     $auditLogRepository,
        private readonly LockFactory            $lockFactory,
        private readonly IdempotencyService     $idempotencyService,
        private readonly LoggerInterface        $logger,
    ) {}

    public function transfer(TransferRequest $request): Transaction
    {
        // ── STEP 1: Idempotency check ─────────────────────────────────────
        // "Have we done this exact request before?"
        $existing = $this->idempotencyService->find($request->idempotencyKey);
        if ($existing !== null) {
            $this->logger->info('Returning existing transaction (idempotent request)', [
                'idempotency_key' => $request->idempotencyKey,
                'transaction_id'  => (string) $existing->getId(),
            ]);
            return $existing;
        }

        $fromId = Uuid::fromString($request->fromAccountId);
        $toId   = Uuid::fromString($request->toAccountId);

        // ── STEP 2: Same account check ────────────────────────────────────
        if ($fromId->equals($toId)) {
            throw new SameAccountException();
        }

        // ── STEP 3: Acquire distributed locks ─────────────────────────────
        // "Put a sign on both accounts: I'm working with these, please wait."
        // We ALWAYS lock lower UUID first — this prevents deadlocks.
        // (Deadlock = two requests each waiting for the other to finish = freeze)
        [$lockId1, $lockId2] = $this->getOrderedLockIds($fromId, $toId);

        $lock1 = $this->lockFactory->createLock("account:{$lockId1}", ttl: 30);
        $lock2 = $this->lockFactory->createLock("account:{$lockId2}", ttl: 30);

        if (!$lock1->acquire()) {
            throw new TransferLockException();
        }

        try {
            if (!$lock2->acquire()) {
                throw new TransferLockException();
            }
            try {
                return $this->executeTransfer($fromId, $toId, $request);
            } finally {
                $lock2->release();
            }
        } finally {
            $lock1->release();
        }
    }

    private function executeTransfer(Uuid $fromId, Uuid $toId, TransferRequest $request): Transaction
    {
        // ── STEP 4: Begin database transaction ────────────────────────────
        // "What happens in this block either ALL succeeds or ALL fails. No half-done state."
        $this->em->beginTransaction();

        try {
            // ── STEP 5: Load accounts with DB-level lock (SELECT FOR UPDATE)
            // This prevents two simultaneous requests from reading the same balance
            $fromAccount = $this->accountRepository->findWithLock($fromId);
            $toAccount   = $this->accountRepository->findWithLock($toId);

            if ($fromAccount === null) {
                throw new AccountNotFoundException($request->fromAccountId);
            }
            if ($toAccount === null) {
                throw new AccountNotFoundException($request->toAccountId);
            }

            // ── STEP 6: Currency validation ───────────────────────────────
            $currency = strtoupper($request->currency);
            if ($fromAccount->getCurrency() !== $currency) {
                throw new CurrencyMismatchException($fromAccount->getCurrency(), $currency);
            }
            if ($toAccount->getCurrency() !== $currency) {
                throw new CurrencyMismatchException($toAccount->getCurrency(), $currency);
            }

            // ── STEP 7: Balance check ─────────────────────────────────────
            if (!$fromAccount->hasSufficientBalance($request->amount)) {
                throw new InsufficientFundsException();
            }

            // ── STEP 8: Create transaction record ─────────────────────────
            $transaction = new Transaction(
                $fromAccount,
                $toAccount,
                $request->amount,
                $currency,
                $request->idempotencyKey,
            );
            $this->em->persist($transaction);

            // ── STEP 9: Move the money ────────────────────────────────────
            $fromAccount->debit($request->amount);   // subtract from sender
            $toAccount->credit($request->amount);    // add to receiver

            // ── STEP 10: Mark done ────────────────────────────────────────
            $transaction->markCompleted();

            // ── STEP 11: Write audit log ──────────────────────────────────
            $this->em->persist(new AuditLog(
                $transaction->getId(),
                'transfer.completed',
                [
                    'from_account_id'    => (string) $fromId,
                    'to_account_id'      => (string) $toId,
                    'amount'             => $request->amount,
                    'currency'           => $currency,
                    'from_balance_after' => $fromAccount->getBalance(),
                    'to_balance_after'   => $toAccount->getBalance(),
                ]
            ));

            // ── STEP 12: Save everything & commit ─────────────────────────
            $this->em->flush();
            $this->em->commit();

            // ── STEP 13: Cache idempotency key in Redis ───────────────────
            $this->idempotencyService->store($request->idempotencyKey, $transaction);

            $this->logger->info('Transfer completed', [
                'transaction_id'  => (string) $transaction->getId(),
                'from_account_id' => (string) $fromId,
                'to_account_id'   => (string) $toId,
                'amount'          => $request->amount,
            ]);

            return $transaction;

        } catch (\Throwable $e) {
            // Something went wrong — undo ALL changes
            $this->em->rollback();
            $this->logger->error('Transfer failed, transaction rolled back', [
                'error'           => $e->getMessage(),
                'from_account_id' => (string) $fromId,
                'to_account_id'   => (string) $toId,
            ]);
            throw $e;
        }
    }

    /**
     * Always lock accounts in the same order (lower UUID string first).
     * This prevents deadlocks when two transfers involve the same accounts simultaneously.
     */
    private function getOrderedLockIds(Uuid $id1, Uuid $id2): array
    {
        $s1 = (string) $id1;
        $s2 = (string) $id2;
        return strcmp($s1, $s2) < 0 ? [$s1, $s2] : [$s2, $s1];
    }
}