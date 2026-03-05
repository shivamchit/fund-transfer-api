<?php

namespace App\Tests\Integration;

use App\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class TransferApiTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createAccount(string $balance, string $currency = 'EUR'): Account
    {
        $account = new Account('Test User', $balance, $currency);
        $this->em->persist($account);
        $this->em->flush();
        return $account;
    }

    private function post(array $body): array
    {
        $this->client->request('POST', '/api/v1/transfers', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($body));
        return json_decode($this->client->getResponse()->getContent(), true);
    }

    public function testSuccessfulTransfer(): void
    {
        $from = $this->createAccount('1000.0000');
        $to   = $this->createAccount('500.0000');

        $response = $this->post([
            'from_account_id' => (string) $from->getId(),
            'to_account_id'   => (string) $to->getId(),
            'amount'          => '200.00',
            'currency'        => 'EUR',
            'idempotency_key' => Uuid::v4()->toRfc4122(),
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertEquals('completed', $response['data']['status']);

        // Verify balances changed in DB
        $this->em->clear();
        $this->assertEquals('800.0000', $this->em->find(Account::class, $from->getId())->getBalance());
        $this->assertEquals('700.0000', $this->em->find(Account::class, $to->getId())->getBalance());
    }

    public function testInsufficientFunds(): void
    {
        $from = $this->createAccount('50.0000');
        $to   = $this->createAccount('0.0000');

        $this->post([
            'from_account_id' => (string) $from->getId(),
            'to_account_id'   => (string) $to->getId(),
            'amount'          => '999.00',
            'currency'        => 'EUR',
            'idempotency_key' => Uuid::v4()->toRfc4122(),
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testSameAccountReturnsError(): void
    {
        $account = $this->createAccount('1000.0000');

        $this->post([
            'from_account_id' => (string) $account->getId(),
            'to_account_id'   => (string) $account->getId(),
            'amount'          => '100.00',
            'currency'        => 'EUR',
            'idempotency_key' => Uuid::v4()->toRfc4122(),
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testIdempotencyDoesNotDoubleCharge(): void
    {
        $from          = $this->createAccount('1000.0000');
        $to            = $this->createAccount('0.0000');
        $idempotencyKey = Uuid::v4()->toRfc4122();

        $body = [
            'from_account_id' => (string) $from->getId(),
            'to_account_id'   => (string) $to->getId(),
            'amount'          => '100.00',
            'currency'        => 'EUR',
            'idempotency_key' => $idempotencyKey,
        ];

        $first  = $this->post($body);
        $second = $this->post($body); // same request again

        // Same transaction returned
        $this->assertEquals($first['data']['transaction_id'], $second['data']['transaction_id']);

        // Balance only changed ONCE
        $this->em->clear();
        $this->assertEquals('900.0000', $this->em->find(Account::class, $from->getId())->getBalance());
    }

    public function testValidationErrors(): void
    {
        $this->post(['amount' => '-100', 'currency' => 'XX']);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testHealthEndpoint(): void
    {
        $this->client->request('GET', '/api/v1/health');
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('ok', $data['status']);
    }
}