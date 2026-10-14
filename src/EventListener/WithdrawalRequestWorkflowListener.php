<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\EventListener;

use Psr\Clock\ClockInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequestInterface;

final readonly class WithdrawalRequestWorkflowListener
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $withdrawalRequest = $event->getSubject();
        if (!$withdrawalRequest instanceof WithdrawalRequestInterface) {
            return;
        }

        $withdrawalRequest->setProcessedAt($this->clock->now());
    }
}
