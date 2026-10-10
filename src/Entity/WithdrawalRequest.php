<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;

class WithdrawalRequest implements WithdrawalRequestInterface
{
    protected ?int $id = null;

    protected ?string $reference = null;

    protected string $state = self::STATE_RECEIVED;

    protected ?ChannelInterface $channel = null;

    protected ?string $localeCode = null;

    protected ?string $customerName = null;

    protected ?string $email = null;

    protected ?string $orderNumber = null;

    protected ?OrderInterface $order = null;

    protected ?CustomerInterface $customer = null;

    protected ?string $itemsDescription = null;

    /** @var Collection<array-key, WithdrawalRequestItemInterface> */
    protected Collection $items;

    protected ?\DateTimeImmutable $submittedAt = null;

    protected ?\DateTimeImmutable $deadlineAt = null;

    protected ?\DateTimeImmutable $acknowledgedAt = null;

    protected ?\DateTimeImmutable $processedAt = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): void
    {
        $this->reference = $reference;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    public function getLocaleCode(): ?string
    {
        return $this->localeCode;
    }

    public function setLocaleCode(string $localeCode): void
    {
        $this->localeCode = $localeCode;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): void
    {
        $this->customerName = $customerName;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): void
    {
        $this->orderNumber = $orderNumber;
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function setOrder(?OrderInterface $order): void
    {
        $this->order = $order;
    }

    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    public function getItemsDescription(): ?string
    {
        return $this->itemsDescription;
    }

    public function setItemsDescription(?string $itemsDescription): void
    {
        $this->itemsDescription = $itemsDescription;
    }

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(WithdrawalRequestItemInterface $item): void
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setWithdrawalRequest($this);
        }
    }

    public function removeItem(WithdrawalRequestItemInterface $item): void
    {
        if ($this->items->removeElement($item)) {
            $item->setWithdrawalRequest(null);
        }
    }

    public function hasItems(): bool
    {
        return !$this->items->isEmpty();
    }

    public function isWholeContract(): bool
    {
        return !$this->hasItems() && (null === $this->itemsDescription || '' === trim($this->itemsDescription));
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(\DateTimeImmutable $submittedAt): void
    {
        $this->submittedAt = $submittedAt;
    }

    public function getDeadlineAt(): ?\DateTimeImmutable
    {
        return $this->deadlineAt;
    }

    public function setDeadlineAt(?\DateTimeImmutable $deadlineAt): void
    {
        $this->deadlineAt = $deadlineAt;
    }

    public function isSubmittedInTime(): ?bool
    {
        if (null === $this->deadlineAt || null === $this->submittedAt) {
            return null;
        }

        return $this->submittedAt <= $this->deadlineAt;
    }

    public function getAcknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    public function setAcknowledgedAt(?\DateTimeImmutable $acknowledgedAt): void
    {
        $this->acknowledgedAt = $acknowledgedAt;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): void
    {
        $this->processedAt = $processedAt;
    }
}
