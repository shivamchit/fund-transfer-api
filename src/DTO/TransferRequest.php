<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class TransferRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'from_account_id is required')]
        #[Assert\Uuid(message: 'from_account_id must be a valid UUID')]
        public readonly string $fromAccountId,

        #[Assert\NotBlank(message: 'to_account_id is required')]
        #[Assert\Uuid(message: 'to_account_id must be a valid UUID')]
        public readonly string $toAccountId,

        #[Assert\NotBlank(message: 'amount is required')]
        #[Assert\Positive(message: 'amount must be greater than 0')]
        #[Assert\Regex(
            pattern: '/^\d+(\.\d{1,4})?$/',
            message: 'amount must be a valid number with up to 4 decimal places'
        )]
        public readonly string $amount,

        #[Assert\NotBlank(message: 'currency is required')]
        #[Assert\Length(exactly: 3, exactMessage: 'currency must be exactly 3 characters (e.g. EUR, USD)')]
        public readonly string $currency,

        #[Assert\NotBlank(message: 'idempotency_key is required')]
        #[Assert\Uuid(message: 'idempotency_key must be a valid UUID')]
        public readonly string $idempotencyKey,
    ) {}
}