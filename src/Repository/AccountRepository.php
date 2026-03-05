<?php

namespace App\Repository;

use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function findWithLock(Uuid $id): ?Account
    {
        return $this->getEntityManager()->find(
            Account::class,
            $id,
            LockMode::PESSIMISTIC_WRITE
        );
    }

    public function save(Account $account, bool $flush = false): void
    {
        $this->getEntityManager()->persist($account);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}