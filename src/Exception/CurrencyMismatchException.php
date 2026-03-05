<?php
namespace App\Exception;
class CurrencyMismatchException extends \DomainException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct("Currency mismatch: {$from} vs {$to}", 422);
    }
}