<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $transactionId;

    #[ORM\Column(length: 100)]
    private string $event;

    #[ORM\Column(type: Types::JSON)]
    private array $metadata;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(?Uuid $transactionId, string $event, array $metadata = [])
    {
        $this->transactionId = $transactionId;
        $this->event         = $event;
        $this->metadata      = $metadata;
        $this->createdAt     = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }
    public function getEvent(): string { return $this->event; }
    public function getMetadata(): array { return $this->metadata; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}