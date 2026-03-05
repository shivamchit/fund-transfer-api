<?php
namespace App\Exception;
class TransferLockException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Transfer already in progress. Please retry in a moment.', 409);
    }
}