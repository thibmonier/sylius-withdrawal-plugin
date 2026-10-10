<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Entity;

use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Resource\Model\ResourceInterface;

interface WithdrawalRequestItemInterface extends ResourceInterface
{
    public function getWithdrawalRequest(): ?WithdrawalRequestInterface;

    public function setWithdrawalRequest(?WithdrawalRequestInterface $withdrawalRequest): void;

    public function getOrderItem(): ?OrderItemInterface;

    public function setOrderItem(?OrderItemInterface $orderItem): void;

    /** Snapshot of the item name, kept if the order item is deleted. */
    public function getLabel(): ?string;

    public function setLabel(string $label): void;

    public function getQuantity(): int;

    public function setQuantity(int $quantity): void;
}
