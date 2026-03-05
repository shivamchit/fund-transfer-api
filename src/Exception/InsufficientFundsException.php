<?php
namespace App\Exception;
class InsufficientFundsException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Insufficient funds in source account', 422);
    }
}