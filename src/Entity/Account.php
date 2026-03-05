<?php

namespace App\Entity;

use App\Repository\AccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'accounts')]
class Account
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100)]
    private string $ownerName;

    #[ORM\Column(type: Types::DECIMAL, precision: 19, scale: 4)]
    private string $balance;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $ownerName, string $balance, string $currency)
    {
        $this->ownerName = $ownerName;
        $this->balance   = $balance;
        $this->currency  = strtoupper($currency);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }
    public function getOwnerName(): string { return $this->ownerName; }
    public function getBalance(): string { return $this->balance; }
    public function getCurrency(): string { return $this->currency; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function debit(string $amount): void
    {
        if (bccomp($this->balance, $amount, 4) < 0) {
            throw new \DomainException('Insufficient funds');
        }
        $this->balance   = bcsub($this->balance, $amount, 4);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function credit(string $amount): void
    {
        $this->balance   = bcadd($this->balance, $amount, 4);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function hasSufficientBalance(string $amount): bool
    {
        return bccomp($this->balance, $amount, 4) >= 0;
    }
}