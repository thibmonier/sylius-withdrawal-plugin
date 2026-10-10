<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Order;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;

final class OrderMatcher implements OrderMatcherInterface
{
    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(private readonly OrderRepositoryInterface $orderRepository)
    {
    }

    public function match(ChannelInterface $channel, string $orderNumber, string $email, ?CustomerInterface $customer = null): ?OrderInterface
    {
        $orderNumber = ltrim(trim($orderNumber), '#');
        if ('' === $orderNumber) {
            return null;
        }

        $order = $this->orderRepository->findOneBy(['number' => $orderNumber, 'channel' => $channel]);
        if (!$order instanceof OrderInterface || OrderInterface::STATE_CART === $order->getState()) {
            return null;
        }

        $orderCustomer = $order->getCustomer();
        if (null === $orderCustomer) {
            return null;
        }

        if (null !== $customer && $orderCustomer->getId() === $customer->getId()) {
            return $order;
        }

        $orderEmail = $orderCustomer->getEmailCanonical() ?? $orderCustomer->getEmail();
        if (null === $orderEmail || mb_strtolower(trim($email)) !== mb_strtolower($orderEmail)) {
            return null;
        }

        return $order;
    }
}
