<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Mailer;

final class Emails
{
    public const ACKNOWLEDGEMENT = 'tmonier_withdrawal_acknowledgement';

    public const MERCHANT_NOTIFICATION = 'tmonier_withdrawal_merchant_notification';

    private function __construct()
    {
    }
}
