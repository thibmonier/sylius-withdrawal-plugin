<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Mailer;

use Sylius\Component\Mailer\Sender\SenderInterface;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequestInterface;

final readonly class WithdrawalEmailManager implements WithdrawalEmailManagerInterface
{
    public function __construct(private SenderInterface $emailSender)
    {
    }

    public function sendAcknowledgement(WithdrawalRequestInterface $withdrawalRequest): void
    {
        $email = $withdrawalRequest->getEmail();
        if (null === $email) {
            return;
        }

        $this->emailSender->send(
            Emails::ACKNOWLEDGEMENT,
            [$email],
            $this->getData($withdrawalRequest),
        );
    }

    public function sendMerchantNotification(WithdrawalRequestInterface $withdrawalRequest): void
    {
        $merchantEmail = $withdrawalRequest->getChannel()?->getContactEmail();
        if (null === $merchantEmail) {
            return;
        }

        $this->emailSender->send(
            Emails::MERCHANT_NOTIFICATION,
            [$merchantEmail],
            $this->getData($withdrawalRequest),
            [],
            array_filter([$withdrawalRequest->getEmail()]),
        );
    }

    /** @return array<string, mixed> */
    private function getData(WithdrawalRequestInterface $withdrawalRequest): array
    {
        return [
            'withdrawalRequest' => $withdrawalRequest,
            'channel' => $withdrawalRequest->getChannel(),
            'localeCode' => $withdrawalRequest->getLocaleCode(),
        ];
    }
}
