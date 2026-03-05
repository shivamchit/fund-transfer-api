<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class IdempotencyService
{
    private const TTL_SECONDS = 86400; // 24 hours
    private const CACHE_PREFIX = 'idempotency_';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly TransactionRepository  $transactionRepository,
        private readonly LoggerInterface        $logger,
    ) {}

    public function find(string $key): ?Transaction
    {
        // Step 1: Check Redis (super fast — like checking your pocket first)
        try {
            $item = $this->cache->getItem(self::CACHE_PREFIX . $key);
            if ($item->isHit()) {
                $transactionId = $item->get();
                $transaction = $this->transactionRepository->find($transactionId);
                if ($transaction !== null) {
                    return $transaction;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Redis cache miss, falling back to DB', ['error' => $e->getMessage()]);
        }

        // Step 2: Check database (slower — like checking your bag)
        return $this->transactionRepository->findByIdempotencyKey($key);
    }

    public function store(string $key, Transaction $transaction): void
    {
        try {
            $item = $this->cache->getItem(self::CACHE_PREFIX . $key);
            $item->set((string) $transaction->getId());
            $item->expiresAfter(self::TTL_SECONDS);
            $this->cache->save($item);
        } catch (\Throwable $e) {
            // Not fatal — DB unique constraint is our backup safety net
            $this->logger->warning('Failed to cache idempotency key in Redis', ['error' => $e->getMessage()]);
        }
    }
}