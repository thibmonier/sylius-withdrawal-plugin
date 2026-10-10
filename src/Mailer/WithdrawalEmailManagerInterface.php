<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Mailer;

use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequestInterface;

interface WithdrawalEmailManagerInterface
{
    public function sendAcknowledgement(WithdrawalRequestInterface $withdrawalRequest): void;

    public function sendMerchantNotification(WithdrawalRequestInterface $withdrawalRequest): void;
}
