<?php

namespace App\Controller\Api;

use App\DTO\TransferRequest;
use App\Repository\AccountRepository;
use App\Service\TransferService;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1', name: 'api_v1_')]
class TransferController extends AbstractController
{
    public function __construct(
        private readonly TransferService    $transferService,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface    $logger,
    ) {}

    #[Route('/transfers', name: 'transfer_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $requestId = $request->headers->get('X-Request-ID', uniqid('req_', true));

        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return $this->errorResponse('INVALID_JSON', 'Request body must be valid JSON', 400, $requestId);
        }

        $dto = new TransferRequest(
            fromAccountId:  $body['from_account_id']  ?? '',
            toAccountId:    $body['to_account_id']    ?? '',
            amount:         (string) ($body['amount'] ?? ''),
            currency:       $body['currency']         ?? '',
            idempotencyKey: $body['idempotency_key']  ?? '',
        );

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $v) {
                $errors[$v->getPropertyPath()] = $v->getMessage();
            }
            return $this->json([
                'error'      => 'VALIDATION_ERROR',
                'message'    => 'Invalid request data',
                'details'    => $errors,
                'request_id' => $requestId,
            ], 422);
        }

        try {
            $transaction = $this->transferService->transfer($dto);

            return $this->json([
                'data' => [
                    'transaction_id'  => (string) $transaction->getId(),
                    'from_account_id' => (string) $transaction->getFromAccount()->getId(),
                    'to_account_id'   => (string) $transaction->getToAccount()->getId(),
                    'amount'          => $transaction->getAmount(),
                    'currency'        => $transaction->getCurrency(),
                    'status'          => $transaction->getStatus(),
                    'created_at'      => $transaction->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
                'request_id' => $requestId,
            ], 201);
        } catch (\Throwable $e) {
            return $this->handleException($e, $requestId);
        }
    }

    #[Route('/accounts/{id}/balance', name: 'account_balance', methods: ['GET'])]
    public function balance(string $id, AccountRepository $repo): JsonResponse
    {
        try {
            $uuid    = Uuid::fromString($id);
            $account = $repo->find($uuid);
            if ($account === null) {
                return $this->json(['error' => 'ACCOUNT_NOT_FOUND', 'message' => 'Account not found'], 404);
            }
            return $this->json([
                'data' => [
                    'account_id' => (string) $account->getId(),
                    'owner'      => $account->getOwnerName(),
                    'balance'    => $account->getBalance(),
                    'currency'   => $account->getCurrency(),
                ],
            ]);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'INVALID_UUID', 'message' => 'Invalid account ID format'], 400);
        }
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(Connection $connection): JsonResponse
    {
        $dbOk = false;
        try {
            $connection->executeQuery('SELECT 1');
            $dbOk = true;
        } catch (\Throwable) {}

        $status = $dbOk ? 'ok' : 'degraded';
        return $this->json([
            'status' => $status,
            'checks' => ['database' => $dbOk ? 'ok' : 'fail'],
        ], $dbOk ? 200 : 503);
    }

    private function handleException(\Throwable $e, string $requestId): JsonResponse
    {
        $map = [
            \App\Exception\AccountNotFoundException::class   => ['ACCOUNT_NOT_FOUND',    404],
            \App\Exception\InsufficientFundsException::class => ['INSUFFICIENT_FUNDS',   422],
            \App\Exception\CurrencyMismatchException::class  => ['CURRENCY_MISMATCH',    422],
            \App\Exception\SameAccountException::class       => ['SAME_ACCOUNT',         422],
            \App\Exception\TransferLockException::class      => ['TRANSFER_IN_PROGRESS', 409],
        ];

        foreach ($map as $class => [$code, $http]) {
            if ($e instanceof $class) {
                return $this->errorResponse($code, $e->getMessage(), $http, $requestId);
            }
        }

        $this->logger->critical('Unhandled exception', ['exception' => $e]);
        return $this->errorResponse('INTERNAL_ERROR', 'An unexpected error occurred', 500, $requestId);
    }

    private function errorResponse(string $code, string $message, int $status, string $requestId): JsonResponse
    {
        return $this->json([
            'error'      => $code,
            'message'    => $message,
            'request_id' => $requestId,
        ], $status);
    }
}