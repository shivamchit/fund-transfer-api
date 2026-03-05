<?php

namespace App\DataFixtures;

use App\Entity\Account;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $accounts = [
            ['Alice Johnson', '5000.0000', 'EUR'],
            ['Bob Smith',     '3000.0000', 'EUR'],
            ['Carol White',   '10000.0000', 'USD'],
            ['Dave Brown',    '1000.0000', 'USD'],
        ];

        foreach ($accounts as [$name, $balance, $currency]) {
            $account = new Account($name, $balance, $currency);
            $manager->persist($account);
        }

        $manager->flush();
        echo "\n✅ Test accounts created! Run the SQL query to get their UUIDs.\n";
    }
}