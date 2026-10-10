<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusWithdrawalPlugin\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequest;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequestInterface;

final class AdminAndCustomerWithdrawalTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager()->createQuery(sprintf('DELETE FROM %s', WithdrawalRequest::class))->execute();
    }

    public function testALoggedInCustomerWithdrawsFromSelectedItemsWithPrefilledData(): void
    {
        $order = $this->findPlacedOrderWithShopUser();
        $customer = $order->getCustomer();
        self::assertInstanceOf(CustomerInterface::class, $customer);
        $user = $customer->getUser();
        self::assertInstanceOf(ShopUserInterface::class, $user);
        $this->client->loginUser($user, 'shop');

        $crawler = $this->client->request('GET', sprintf('/en_US/account/orders/%s', $order->getNumber()));
        self::assertResponseIsSuccessful();
        $button = $crawler->filter('[data-test-button="withdrawal-order-button"]');
        self::assertCount(1, $button);

        $crawler = $this->client->click($button->link());
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('withdrawal-continue')->form();
        $values = $form->getPhpValues();
        self::assertIsArray($values['tmonier_withdrawal_statement']);
        self::assertSame($customer->getEmail(), $values['tmonier_withdrawal_statement']['email']);
        self::assertSame($order->getNumber(), $values['tmonier_withdrawal_statement']['orderNumber']);

        $firstItem = $order->getItems()->first();
        self::assertNotFalse($firstItem);
        $firstItemId = $firstItem->getId();
        self::assertIsInt($firstItemId);
        $values['tmonier_withdrawal_statement']['orderItemIds'] = [(string) $firstItemId];
        $this->client->request('POST', $form->getUri(), $values);
        $this->client->followRedirect();
        self::assertSelectorTextContains('dd:last-child', (string) $firstItem->getProductName());

        $this->client->submitForm('withdrawal-confirm');
        $this->client->followRedirect();

        $request = $this->findLastRequest();
        self::assertSame($order->getId(), $request->getOrder()?->getId());
        self::assertSame($customer->getId(), $request->getCustomer()?->getId());
        $item = $request->getItems()->first();
        self::assertNotFalse($item);
        self::assertCount(1, $request->getItems());
        self::assertSame($firstItem->getId(), $item->getOrderItem()?->getId());
    }

    public function testAnAdministratorListsShowsAndAcceptsAWithdrawal(): void
    {
        $request = $this->createRequest();
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/withdrawal-requests');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString((string) $request->getReference(), $crawler->filter('table')->text());
        self::assertSelectorExists('a[href="/admin/withdrawal-requests"]');

        $requestId = $request->getId();
        self::assertIsInt($requestId);
        $this->client->request('GET', sprintf('/admin/withdrawal-requests/%d', $requestId));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test-reference]', (string) $request->getReference());
        self::assertSelectorTextContains('[data-test-timeliness]', 'Unknown');

        $this->client->submitForm('Accept');
        self::assertResponseRedirects();

        $request = $this->findLastRequest();
        self::assertSame(WithdrawalRequestInterface::STATE_ACCEPTED, $request->getState());
        self::assertNotNull($request->getProcessedAt());
    }

    private function createRequest(): WithdrawalRequestInterface
    {
        $channel = $this->entityManager()->getRepository(Channel::class)->findOneBy([]);
        self::assertNotNull($channel);

        $request = new WithdrawalRequest();
        $request->setReference('WTEST' . bin2hex(random_bytes(3)));
        $request->setChannel($channel);
        $request->setLocaleCode('en_US');
        $request->setCustomerName('Jane Doe');
        $request->setEmail('jane@example.com');
        $request->setOrderNumber('UNKNOWN');
        $request->setSubmittedAt(new \DateTimeImmutable());

        $this->entityManager()->persist($request);
        $this->entityManager()->flush();

        return $request;
    }

    private function loginAdmin(): void
    {
        $admin = $this->entityManager()->getRepository(AdminUserInterface::class)->findOneBy([]);
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        $this->client->loginUser($admin, 'admin');
    }

    private function findPlacedOrderWithShopUser(): OrderInterface
    {
        /** @var OrderInterface|null $order */
        $order = $this->entityManager()
            ->createQueryBuilder()
            ->select('o')
            ->from(OrderInterface::class, 'o')
            ->innerJoin('o.customer', 'c')
            ->innerJoin('c.user', 'u')
            ->andWhere('o.state = :state')
            ->setParameter('state', OrderInterface::STATE_NEW)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
        self::assertNotNull($order, 'The fixtures should contain a placed order of a registered customer.');

        return $order;
    }

    private function findLastRequest(): WithdrawalRequestInterface
    {
        $this->entityManager()->clear();
        $request = $this->entityManager()->getRepository(WithdrawalRequest::class)->findOneBy([], ['id' => 'DESC']);
        self::assertInstanceOf(WithdrawalRequestInterface::class, $request);

        return $request;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }
}
