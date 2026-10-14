<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Submitter;

use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequestInterface;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequestItemInterface;
use Tmonier\SyliusWithdrawalPlugin\Generator\ReferenceGeneratorInterface;
use Tmonier\SyliusWithdrawalPlugin\Mailer\WithdrawalEmailManagerInterface;
use Tmonier\SyliusWithdrawalPlugin\Model\WithdrawalStatement;
use Tmonier\SyliusWithdrawalPlugin\Order\DeadlineCalculatorInterface;
use Tmonier\SyliusWithdrawalPlugin\Order\OrderMatcherInterface;

final readonly class WithdrawalRequestSubmitter implements WithdrawalRequestSubmitterInterface
{
    /**
     * @param FactoryInterface<WithdrawalRequestInterface> $withdrawalRequestFactory
     * @param FactoryInterface<WithdrawalRequestItemInterface> $withdrawalRequestItemFactory
     */
    public function __construct(
        private FactoryInterface $withdrawalRequestFactory,
        private FactoryInterface $withdrawalRequestItemFactory,
        private ObjectManager $withdrawalRequestManager,
        private OrderMatcherInterface $orderMatcher,
        private DeadlineCalculatorInterface $deadlineCalculator,
        private ReferenceGeneratorInterface $referenceGenerator,
        private WithdrawalEmailManagerInterface $emailManager,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private bool $notifyMerchant = true,
    ) {
    }

    public function submit(
        WithdrawalStatement $statement,
        ChannelInterface $channel,
        string $localeCode,
        ?CustomerInterface $customer = null,
    ): WithdrawalRequestInterface {
        $withdrawalRequest = $this->withdrawalRequestFactory->createNew();
        $withdrawalRequest->setReference($this->referenceGenerator->generate());
        $withdrawalRequest->setSubmittedAt($this->clock->now());
        $withdrawalRequest->setChannel($channel);
        $withdrawalRequest->setLocaleCode($localeCode);
        $withdrawalRequest->setCustomerName(trim((string) $statement->customerName));
        $withdrawalRequest->setEmail(trim((string) $statement->email));
        $withdrawalRequest->setOrderNumber(trim((string) $statement->orderNumber));
        $withdrawalRequest->setCustomer($customer);

        $itemsDescription = null !== $statement->itemsDescription ? trim($statement->itemsDescription) : null;
        $withdrawalRequest->setItemsDescription('' === $itemsDescription ? null : $itemsDescription);

        $order = $this->orderMatcher->match(
            $channel,
            (string) $withdrawalRequest->getOrderNumber(),
            (string) $withdrawalRequest->getEmail(),
            $customer,
        );

        if ($order instanceof \Sylius\Component\Core\Model\OrderInterface) {
            $withdrawalRequest->setOrder($order);
            $withdrawalRequest->setDeadlineAt($this->deadlineCalculator->calculate($order));
            $orderCustomer = $order->getCustomer();
            $withdrawalRequest->setCustomer($customer ?? ($orderCustomer instanceof CustomerInterface ? $orderCustomer : null));
            $this->addItems($withdrawalRequest, $order, $statement->orderItemIds);
        }

        $this->withdrawalRequestManager->persist($withdrawalRequest);
        $this->withdrawalRequestManager->flush();

        $this->sendEmails($withdrawalRequest);

        return $withdrawalRequest;
    }

    /** @param list<int> $orderItemIds */
    private function addItems(WithdrawalRequestInterface $withdrawalRequest, OrderInterface $order, array $orderItemIds): void
    {
        if ([] === $orderItemIds) {
            return;
        }

        /** @var OrderItemInterface $orderItem */
        foreach ($order->getItems() as $orderItem) {
            if (!in_array($orderItem->getId(), $orderItemIds, true)) {
                continue;
            }

            $item = $this->withdrawalRequestItemFactory->createNew();
            $item->setOrderItem($orderItem);
            $item->setLabel(trim(sprintf('%s %s', $orderItem->getProductName() ?? '', $orderItem->getVariantName() ?? '')));
            $item->setQuantity($orderItem->getQuantity());
            $withdrawalRequest->addItem($item);
        }
    }

    private function sendEmails(WithdrawalRequestInterface $withdrawalRequest): void
    {
        try {
            $this->emailManager->sendAcknowledgement($withdrawalRequest);
            $withdrawalRequest->setAcknowledgedAt($this->clock->now());
            $this->withdrawalRequestManager->flush();
        } catch (\Throwable $exception) {
            // The statement is recorded anyway; the missing acknowledgement is visible in the admin.
            $this->logger->error('Could not send withdrawal acknowledgement for request {reference}.', [
                'reference' => $withdrawalRequest->getReference(),
                'exception' => $exception,
            ]);
        }

        if (!$this->notifyMerchant) {
            return;
        }

        try {
            $this->emailManager->sendMerchantNotification($withdrawalRequest);
        } catch (\Throwable $exception) {
            $this->logger->error('Could not send withdrawal merchant notification for request {reference}.', [
                'reference' => $withdrawalRequest->getReference(),
                'exception' => $exception,
            ]);
        }
    }
}
