<?php
namespace App\Exception;
class AccountNotFoundException extends \RuntimeException
{
    public function __construct(string $accountId)
    {
        parent::__construct("Account not found: {$accountId}", 404);
    }
}