<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Order;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;

interface OrderMatcherInterface
{
    /**
     * Finds the placed order identified by the consumer. A guest must provide the email
     * used for the order; a logged-in customer must own the order.
     */
    public function match(ChannelInterface $channel, string $orderNumber, string $email, ?CustomerInterface $customer = null): ?OrderInterface;
}
