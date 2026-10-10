<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusWithdrawalPlugin\Unit\Order;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Shipment;
use Tmonier\SyliusWithdrawalPlugin\Order\DeadlineCalculator;

final class DeadlineCalculatorTest extends TestCase
{
    public function testItCountsFromTheLastShipment(): void
    {
        $order = new Order();
        $order->setCheckoutCompletedAt(new \DateTime('2026-06-01 10:00:00'));
        $order->addShipment($this->shipment('2026-06-03 09:00:00'));
        $order->addShipment($this->shipment('2026-06-05 15:30:00'));
        $order->addShipment($this->shipment(null));

        $deadline = (new DeadlineCalculator(14))->calculate($order);

        self::assertEquals(new \DateTimeImmutable('2026-06-19 23:59:59'), $deadline);
    }

    public function testItFallsBackToCheckoutCompletionDate(): void
    {
        $order = new Order();
        $order->setCheckoutCompletedAt(new \DateTime('2026-06-01 10:00:00'));

        $deadline = (new DeadlineCalculator(30))->calculate($order);

        self::assertEquals(new \DateTimeImmutable('2026-07-01 23:59:59'), $deadline);
    }

    public function testItReturnsNullWithoutReferenceDate(): void
    {
        self::assertNull((new DeadlineCalculator(14))->calculate(new Order()));
    }

    private function shipment(?string $shippedAt): Shipment
    {
        $shipment = new Shipment();
        if (null !== $shippedAt) {
            $shipment->setShippedAt(new \DateTime($shippedAt));
        }

        return $shipment;
    }
}
