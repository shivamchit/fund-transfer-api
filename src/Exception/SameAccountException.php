<?php
namespace App\Exception;
class SameAccountException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Cannot transfer to the same account', 422);
    }
}