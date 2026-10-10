<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusWithdrawalPlugin\Unit\Submitter;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Resource\Factory\Factory;
use Symfony\Component\Clock\MockClock;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequest;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequestItem;
use Tmonier\SyliusWithdrawalPlugin\Generator\ReferenceGeneratorInterface;
use Tmonier\SyliusWithdrawalPlugin\Mailer\WithdrawalEmailManagerInterface;
use Tmonier\SyliusWithdrawalPlugin\Model\WithdrawalStatement;
use Tmonier\SyliusWithdrawalPlugin\Order\DeadlineCalculatorInterface;
use Tmonier\SyliusWithdrawalPlugin\Order\OrderMatcherInterface;
use Tmonier\SyliusWithdrawalPlugin\Submitter\WithdrawalRequestSubmitter;

final class WithdrawalRequestSubmitterTest extends TestCase
{
    private OrderMatcherInterface&MockObject $orderMatcher;

    private DeadlineCalculatorInterface&MockObject $deadlineCalculator;

    private WithdrawalEmailManagerInterface&MockObject $emailManager;

    private ObjectManager&MockObject $manager;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->orderMatcher = $this->createMock(OrderMatcherInterface::class);
        $this->deadlineCalculator = $this->createMock(DeadlineCalculatorInterface::class);
        $this->emailManager = $this->createMock(WithdrawalEmailManagerInterface::class);
        $this->manager = $this->createMock(ObjectManager::class);
        $this->clock = new MockClock('2026-06-20 14:00:00');
    }

    public function testItRecordsAnUnmatchedGuestStatementAndAcknowledgesIt(): void
    {
        $this->orderMatcher->method('match')->willReturn(null);
        $this->manager->expects(self::once())->method('persist')->with(self::isInstanceOf(WithdrawalRequest::class));
        $this->emailManager->expects(self::once())->method('sendAcknowledgement');
        $this->emailManager->expects(self::once())->method('sendMerchantNotification');

        $request = $this->createSubmitter()->submit($this->statement(), $channel = new Channel(), 'fr_FR');

        self::assertSame('WREF123', $request->getReference());
        self::assertSame($channel, $request->getChannel());
        self::assertSame('fr_FR', $request->getLocaleCode());
        self::assertSame('Jane Doe', $request->getCustomerName());
        self::assertSame('jane@example.com', $request->getEmail());
        self::assertSame('000123', $request->getOrderNumber());
        self::assertNull($request->getItemsDescription());
        self::assertNull($request->getOrder());
        self::assertNull($request->getDeadlineAt());
        self::assertEquals($this->clock->now(), $request->getSubmittedAt());
        self::assertEquals($this->clock->now(), $request->getAcknowledgedAt());
        self::assertTrue($request->isWholeContract());
    }

    public function testItLinksTheMatchedOrderAndOnlyItsSelectedItems(): void
    {
        $order = new Order();
        $order->setCustomer($customer = new Customer());
        $order->addItem($kept = $this->orderItem(10));
        $order->addItem($this->orderItem(11));
        $this->orderMatcher->method('match')->willReturn($order);
        $this->deadlineCalculator->method('calculate')->willReturn($deadline = new \DateTimeImmutable('2026-06-30 23:59:59'));

        $statement = $this->statement();
        $statement->orderItemIds = [10, 999];

        $request = $this->createSubmitter()->submit($statement, new Channel(), 'en_US');

        self::assertSame($order, $request->getOrder());
        self::assertSame($customer, $request->getCustomer());
        self::assertSame($deadline, $request->getDeadlineAt());
        self::assertTrue($request->isSubmittedInTime());
        self::assertCount(1, $request->getItems());
        self::assertSame($kept, $request->getItems()->first()->getOrderItem());
        self::assertSame('Product 10 Default', $request->getItems()->first()->getLabel());
    }

    public function testItKeepsTheStatementWhenTheAcknowledgementFails(): void
    {
        $this->emailManager->method('sendAcknowledgement')->willThrowException(new \RuntimeException('SMTP down'));
        $this->manager->expects(self::once())->method('persist');

        $request = $this->createSubmitter()->submit($this->statement(), new Channel(), 'en_US');

        self::assertNull($request->getAcknowledgedAt());
    }

    public function testItCanSkipTheMerchantNotification(): void
    {
        $this->emailManager->expects(self::never())->method('sendMerchantNotification');

        $this->createSubmitter(notifyMerchant: false)->submit($this->statement(), new Channel(), 'en_US');
    }

    private function createSubmitter(bool $notifyMerchant = true): WithdrawalRequestSubmitter
    {
        $referenceGenerator = $this->createMock(ReferenceGeneratorInterface::class);
        $referenceGenerator->method('generate')->willReturn('WREF123');

        return new WithdrawalRequestSubmitter(
            new Factory(WithdrawalRequest::class),
            new Factory(WithdrawalRequestItem::class),
            $this->manager,
            $this->orderMatcher,
            $this->deadlineCalculator,
            $referenceGenerator,
            $this->emailManager,
            $this->clock,
            new NullLogger(),
            $notifyMerchant,
        );
    }

    private function statement(): WithdrawalStatement
    {
        $statement = new WithdrawalStatement();
        $statement->customerName = ' Jane Doe ';
        $statement->email = 'jane@example.com ';
        $statement->orderNumber = '000123';
        $statement->itemsDescription = '  ';

        return $statement;
    }

    private function orderItem(int $id): OrderItem
    {
        $item = new OrderItem();
        $item->setProductName(sprintf('Product %d', $id));
        $item->setVariantName('Default');
        (new \ReflectionProperty(OrderItem::class, 'id'))->setValue($item, $id);

        return $item;
    }
}
