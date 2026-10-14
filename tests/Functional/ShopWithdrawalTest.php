<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusWithdrawalPlugin\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequest;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequestInterface;

/**
 * Runs against the test application loaded with the default Sylius fixtures.
 */
final class ShopWithdrawalTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager()->createQuery(sprintf('DELETE FROM %s', WithdrawalRequest::class))->execute();
        // Rate limiter state of the previous tests.
        $pool = self::getContainer()->get('cache.tmonier_withdrawal_limiter');
        self::assertInstanceOf(\Psr\Cache\CacheItemPoolInterface::class, $pool);
        $pool->clear();
    }

    public function testTheWithdrawalFunctionIsLinkedFromEveryShopPage(): void
    {
        $crawler = $this->client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        $link = $crawler->filter('[data-test-withdrawal-footer-link]');
        self::assertCount(1, $link);
        self::assertSame('Withdraw from contract here', trim($link->text()));
    }

    public function testAGuestWithdrawsFromAPlacedOrderInTwoSteps(): void
    {
        $order = $this->findPlacedOrder();
        $email = (string) $order->getCustomer()?->getEmail();

        $this->client->request('GET', '/en_US/withdrawal');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('withdrawal-continue', [
            'tmonier_withdrawal_statement[customerName]' => 'Jane Doe',
            'tmonier_withdrawal_statement[email]' => strtoupper($email),
            'tmonier_withdrawal_statement[orderNumber]' => '#' . $order->getNumber(),
        ]);

        // Nothing is recorded before the separate confirmation step (Art. 11a(3)).
        self::assertResponseRedirects('/en_US/withdrawal/confirm');
        self::assertSame(0, $this->countRequests());

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextSame('[data-test-button="withdrawal-confirm"]', 'confirm withdrawal');
        self::assertSame('The whole contract', trim($crawler->filter('dd')->last()->text()));

        $this->client->submitForm('withdrawal-confirm');
        self::assertResponseRedirects('/en_US/withdrawal/done');
        self::assertEmailCount(2);
        $acknowledgement = self::getMailerMessage(0);
        self::assertNotNull($acknowledgement);
        self::assertEmailAddressContains($acknowledgement, 'To', strtoupper($email));
        self::assertEmailHtmlBodyContains($acknowledgement, 'Jane Doe');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test-withdrawal-received]');

        $request = $this->findLastRequest();
        self::assertSame('Jane Doe', $request->getCustomerName());
        self::assertSame($order->getId(), $request->getOrder()?->getId());
        self::assertSame(WithdrawalRequestInterface::STATE_RECEIVED, $request->getState());
        self::assertNotNull($request->getDeadlineAt());
        self::assertNotNull($request->getAcknowledgedAt());
        self::assertSame('en_US', $request->getLocaleCode());
    }

    public function testAStatementIsRecordedEvenWhenNoOrderMatches(): void
    {
        $this->client->request('GET', '/en_US/withdrawal');

        $this->client->submitForm('withdrawal-continue', [
            'tmonier_withdrawal_statement[customerName]' => 'John <b>Doe</b>',
            'tmonier_withdrawal_statement[email]' => 'john@example.com',
            'tmonier_withdrawal_statement[orderNumber]' => 'UNKNOWN-42',
            'tmonier_withdrawal_statement[itemsDescription]' => 'The blue mug',
        ]);
        $this->client->followRedirect();

        $this->client->submitForm('withdrawal-confirm');
        self::assertEmailHtmlBodyContains(self::getMailerMessage(0) ?? self::fail('No e-mail sent.'), 'John &lt;b&gt;Doe&lt;/b&gt;');

        $request = $this->findLastRequest();
        self::assertNull($request->getOrder());
        self::assertNull($request->isSubmittedInTime());
        self::assertFalse($request->isWholeContract());
    }

    public function testInvalidStatementsAreNotAccepted(): void
    {
        $this->client->request('GET', '/en_US/withdrawal');
        $this->client->submitForm('withdrawal-continue', [
            'tmonier_withdrawal_statement[customerName]' => '',
            'tmonier_withdrawal_statement[email]' => 'not-an-email',
            'tmonier_withdrawal_statement[orderNumber]' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->countRequests());
    }

    public function testTheConfirmationRequiresAValidCsrfToken(): void
    {
        $this->client->request('GET', '/en_US/withdrawal');
        $this->client->submitForm('withdrawal-continue', [
            'tmonier_withdrawal_statement[customerName]' => 'Jane Doe',
            'tmonier_withdrawal_statement[email]' => 'jane@example.com',
            'tmonier_withdrawal_statement[orderNumber]' => '000001',
        ]);

        $this->client->request('POST', '/en_US/withdrawal/confirm', ['_csrf_token' => 'forged']);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, $this->countRequests());
    }

    public function testTheConfirmationPageRequiresAStatement(): void
    {
        $this->client->request('GET', '/en_US/withdrawal/confirm');

        self::assertResponseRedirects('/en_US/withdrawal');
    }

    public function testConfirmationsAreLimitedPerEmailAndKeepTheStatement(): void
    {
        // The test application allows 2 confirmations per e-mail (case and "+tag" ignored).
        foreach (['victim@example.com', 'Victim+1@example.com'] as $email) {
            $this->confirmStatement($email);
            self::assertResponseRedirects('/en_US/withdrawal/done');
        }

        $this->confirmStatement('victim+2@example.com');

        self::assertResponseStatusCodeSame(429);
        self::assertSelectorExists('[data-test-button="withdrawal-confirm"]');
        self::assertSame(2, $this->countRequests());
    }

    public function testConfirmationsAreLimitedPerIp(): void
    {
        // The test application allows 4 confirmations per IP.
        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            $this->confirmStatement(sprintf('consumer%d@example.com', $attempt));
            self::assertResponseRedirects('/en_US/withdrawal/done');
        }

        $this->confirmStatement('consumer5@example.com');

        self::assertResponseStatusCodeSame(429);
        self::assertSame(4, $this->countRequests());
    }

    public function testASenderBlockedByIpDoesNotUseTheBudgetOfAnEmail(): void
    {
        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            $this->confirmStatement(sprintf('attacker%d@example.com', $attempt), '203.0.113.7');
        }
        // Refused for the IP: the e-mail budget of the victim is untouched.
        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            $this->confirmStatement('victim@example.com', '203.0.113.7');
            self::assertResponseStatusCodeSame(429);
        }

        foreach (['198.51.100.1', '198.51.100.2'] as $ip) {
            $this->confirmStatement('victim@example.com', $ip);
            self::assertResponseRedirects('/en_US/withdrawal/done');
        }
    }

    private function confirmStatement(string $email, string $ip = '127.0.0.1'): void
    {
        $server = ['REMOTE_ADDR' => $ip];
        $this->client->request('GET', '/en_US/withdrawal', [], [], $server);
        $this->client->submitForm('withdrawal-continue', [
            'tmonier_withdrawal_statement[customerName]' => 'Jane Doe',
            'tmonier_withdrawal_statement[email]' => $email,
            'tmonier_withdrawal_statement[orderNumber]' => 'UNKNOWN-42',
        ], 'POST', $server);
        $this->client->followRedirect();
        $this->client->submitForm('withdrawal-confirm', [], 'POST', $server);
    }

    private function findPlacedOrder(): OrderInterface
    {
        /** @var OrderInterface|null $order */
        $order = $this->entityManager()
            ->createQueryBuilder()
            ->select('o')
            ->from(OrderInterface::class, 'o')
            ->andWhere('o.state = :state')
            ->andWhere('o.customer IS NOT NULL')
            ->setParameter('state', OrderInterface::STATE_NEW)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        self::assertNotNull($order, 'The fixtures should contain a placed order.');

        return $order;
    }

    private function findLastRequest(): WithdrawalRequestInterface
    {
        $this->entityManager()->clear();
        $request = $this->entityManager()->getRepository(WithdrawalRequest::class)->findOneBy([], ['id' => 'DESC']);
        self::assertInstanceOf(WithdrawalRequestInterface::class, $request);

        return $request;
    }

    private function countRequests(): int
    {
        return $this->entityManager()->getRepository(WithdrawalRequest::class)->count([]);
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }
}
