<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Order;

use Sylius\Component\Core\Model\OrderInterface;

/**
 * Withdrawal period starts when the goods are delivered (Art. 9 CRD). Sylius does not track
 * delivery, so the last shipment date is used, falling back to the checkout completion date.
 * The result is only informational: requests are never refused on this basis.
 */
final readonly class DeadlineCalculator implements DeadlineCalculatorInterface
{
    public function __construct(private int $periodDays)
    {
    }

    public function calculate(OrderInterface $order): ?\DateTimeImmutable
    {
        $referenceDate = $this->getLastShippedAt($order) ?? $order->getCheckoutCompletedAt();
        if (!$referenceDate instanceof \DateTimeInterface) {
            return null;
        }

        return \DateTimeImmutable::createFromInterface($referenceDate)
            ->modify(sprintf('+%d days', $this->periodDays))
            ->setTime(23, 59, 59)
        ;
    }

    private function getLastShippedAt(OrderInterface $order): ?\DateTimeInterface
    {
        $lastShippedAt = null;
        foreach ($order->getShipments() as $shipment) {
            $shippedAt = $shipment->getShippedAt();
            if (null !== $shippedAt && (null === $lastShippedAt || $shippedAt > $lastShippedAt)) {
                $lastShippedAt = $shippedAt;
            }
        }

        return $lastShippedAt;
    }
}
