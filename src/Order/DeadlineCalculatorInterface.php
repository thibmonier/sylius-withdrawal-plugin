<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Order;

use Sylius\Component\Core\Model\OrderInterface;

interface DeadlineCalculatorInterface
{
    /** Returns null when the order has neither been shipped nor completed. */
    public function calculate(OrderInterface $order): ?\DateTimeImmutable;
}
