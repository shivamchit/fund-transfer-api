<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transactions')]
#[ORM\Index(columns: ['idempotency_key'], name: 'idx_idempotency_key')]
#[ORM\Index(columns: ['from_account_id'], name: 'idx_from_account')]
#[ORM\Index(columns: ['to_account_id'], name: 'idx_to_account')]
class Transaction
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'from_account_id', nullable: false)]
    private Account $fromAccount;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'to_account_id', nullable: false)]
    private Account $toAccount;

    #[ORM\Column(type: Types::DECIMAL, precision: 19, scale: 4)]
    private string $amount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 20)]
    private string $status;

    #[ORM\Column(length: 255, unique: true)]
    private string $idempotencyKey;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Account $fromAccount,
        Account $toAccount,
        string  $amount,
        string  $currency,
        string  $idempotencyKey
    ) {
        $this->fromAccount    = $fromAccount;
        $this->toAccount      = $toAccount;
        $this->amount         = $amount;
        $this->currency       = strtoupper($currency);
        $this->idempotencyKey = $idempotencyKey;
        $this->status         = self::STATUS_PENDING;
        $this->createdAt      = new \DateTimeImmutable();
        $this->updatedAt      = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }
    public function getFromAccount(): Account { return $this->fromAccount; }
    public function getToAccount(): Account { return $this->toAccount; }
    public function getAmount(): string { return $this->amount; }
    public function getCurrency(): string { return $this->currency; }
    public function getStatus(): string { return $this->status; }
    public function getIdempotencyKey(): string { return $this->idempotencyKey; }
    public function getFailureReason(): ?string { return $this->failureReason; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function markCompleted(): void
    {
        $this->status    = self::STATUS_COMPLETED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markFailed(string $reason): void
    {
        $this->status        = self::STATUS_FAILED;
        $this->failureReason = $reason;
        $this->updatedAt     = new \DateTimeImmutable();
    }
}