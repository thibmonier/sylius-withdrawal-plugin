<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusWithdrawalPlugin\Unit\Order;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Tmonier\SyliusWithdrawalPlugin\Order\OrderMatcher;

final class OrderMatcherTest extends TestCase
{
    /** @var OrderRepositoryInterface<OrderInterface>&MockObject */
    private OrderRepositoryInterface&MockObject $orderRepository;

    private Channel $channel;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->channel = new Channel();
    }

    public function testItMatchesAGuestOrderByNumberAndEmailCaseInsensitively(): void
    {
        $order = $this->placedOrder('jane.doe@example.com');
        $this->orderRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['number' => '000123', 'channel' => $this->channel])
            ->willReturn($order)
        ;

        $matcher = new OrderMatcher($this->orderRepository);

        self::assertSame($order, $matcher->match($this->channel, ' #000123 ', 'Jane.Doe@Example.com'));
    }

    public function testItDoesNotMatchWithAnotherEmail(): void
    {
        $this->orderRepository->method('findOneBy')->willReturn($this->placedOrder('jane.doe@example.com'));

        self::assertNull((new OrderMatcher($this->orderRepository))->match($this->channel, '000123', 'attacker@example.com'));
    }

    public function testItDoesNotMatchCarts(): void
    {
        $order = $this->placedOrder('jane.doe@example.com');
        $order->setState(OrderInterface::STATE_CART);
        $this->orderRepository->method('findOneBy')->willReturn($order);

        self::assertNull((new OrderMatcher($this->orderRepository))->match($this->channel, '000123', 'jane.doe@example.com'));
    }

    public function testItIgnoresEmptyNumbers(): void
    {
        $this->orderRepository->expects(self::never())->method('findOneBy');

        self::assertNull((new OrderMatcher($this->orderRepository))->match($this->channel, '  ', 'jane.doe@example.com'));
    }

    private function placedOrder(string $email): Order
    {
        $customer = new Customer();
        $customer->setEmail($email);
        $customer->setEmailCanonical(mb_strtolower($email));

        $order = new Order();
        $order->setCustomer($customer);
        $order->setState(OrderInterface::STATE_NEW);

        return $order;
    }
}
