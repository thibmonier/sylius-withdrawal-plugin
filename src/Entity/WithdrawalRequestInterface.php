<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Entity;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Resource\Model\ResourceInterface;

interface WithdrawalRequestInterface extends ResourceInterface
{
    public const STATE_RECEIVED = 'received';

    public const STATE_ACCEPTED = 'accepted';

    public const STATE_REJECTED = 'rejected';

    public function getReference(): ?string;

    public function setReference(string $reference): void;

    public function getState(): string;

    public function setState(string $state): void;

    public function getChannel(): ?ChannelInterface;

    public function setChannel(ChannelInterface $channel): void;

    public function getLocaleCode(): ?string;

    public function setLocaleCode(string $localeCode): void;

    public function getCustomerName(): ?string;

    public function setCustomerName(string $customerName): void;

    public function getEmail(): ?string;

    public function setEmail(string $email): void;

    /** Order number as entered by the consumer. */
    public function getOrderNumber(): ?string;

    public function setOrderNumber(string $orderNumber): void;

    /** Order matched by number and email, if any. */
    public function getOrder(): ?OrderInterface;

    public function setOrder(?OrderInterface $order): void;

    public function getCustomer(): ?CustomerInterface;

    public function setCustomer(?CustomerInterface $customer): void;

    /** Free-text description of the concerned goods/services (partial withdrawal). */
    public function getItemsDescription(): ?string;

    public function setItemsDescription(?string $itemsDescription): void;

    /** @return Collection<array-key, WithdrawalRequestItemInterface> */
    public function getItems(): Collection;

    public function addItem(WithdrawalRequestItemInterface $item): void;

    public function removeItem(WithdrawalRequestItemInterface $item): void;

    public function hasItems(): bool;

    /** True when the consumer withdraws from the whole contract. */
    public function isWholeContract(): bool;

    public function getSubmittedAt(): ?\DateTimeImmutable;

    public function setSubmittedAt(\DateTimeImmutable $submittedAt): void;

    public function getDeadlineAt(): ?\DateTimeImmutable;

    public function setDeadlineAt(?\DateTimeImmutable $deadlineAt): void;

    /** Null when the deadline is unknown (no matching order). */
    public function isSubmittedInTime(): ?bool;

    /** When the acknowledgement of receipt was handed to the mailer (Art. 11a(4) CRD). */
    public function getAcknowledgedAt(): ?\DateTimeImmutable;

    public function setAcknowledgedAt(?\DateTimeImmutable $acknowledgedAt): void;

    public function getProcessedAt(): ?\DateTimeImmutable;

    public function setProcessedAt(?\DateTimeImmutable $processedAt): void;
}
