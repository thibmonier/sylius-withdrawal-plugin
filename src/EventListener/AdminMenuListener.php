<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\EventListener;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $sales = $event->getMenu()->getChild('sales');
        if (null === $sales) {
            return;
        }

        $sales
            ->addChild('tmonier_withdrawal_requests', ['route' => 'tmonier_withdrawal_admin_withdrawal_request_index'])
            ->setLabel('tmonier_withdrawal.ui.withdrawal_requests')
            ->setLabelAttribute('icon', 'tabler:arrow-back-up')
        ;
    }
}
