<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Submitter;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequestInterface;
use Tmonier\SyliusWithdrawalPlugin\Model\WithdrawalStatement;

interface WithdrawalRequestSubmitterInterface
{
    /**
     * Records a confirmed withdrawal statement and sends the acknowledgement of receipt.
     * Never refuses a statement: timeliness and matching are informational.
     */
    public function submit(
        WithdrawalStatement $statement,
        ChannelInterface $channel,
        string $localeCode,
        ?CustomerInterface $customer = null,
    ): WithdrawalRequestInterface;
}
