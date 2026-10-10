<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Entity;

use Sylius\Component\Core\Model\OrderItemInterface;

class WithdrawalRequestItem implements WithdrawalRequestItemInterface
{
    protected ?int $id = null;

    protected ?WithdrawalRequestInterface $withdrawalRequest = null;

    protected ?OrderItemInterface $orderItem = null;

    protected ?string $label = null;

    protected int $quantity = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWithdrawalRequest(): ?WithdrawalRequestInterface
    {
        return $this->withdrawalRequest;
    }

    public function setWithdrawalRequest(?WithdrawalRequestInterface $withdrawalRequest): void
    {
        $this->withdrawalRequest = $withdrawalRequest;
    }

    public function getOrderItem(): ?OrderItemInterface
    {
        return $this->orderItem;
    }

    public function setOrderItem(?OrderItemInterface $orderItem): void
    {
        $this->orderItem = $orderItem;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }
}
