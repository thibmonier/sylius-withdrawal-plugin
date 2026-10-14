<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusWithdrawalPlugin\Controller\Shop\WithdrawalController;
use Tmonier\SyliusWithdrawalPlugin\EventListener\AdminMenuListener;
use Tmonier\SyliusWithdrawalPlugin\EventListener\WithdrawalRequestWorkflowListener;
use Tmonier\SyliusWithdrawalPlugin\Form\Type\WithdrawalStatementType;
use Tmonier\SyliusWithdrawalPlugin\Generator\RandomReferenceGenerator;
use Tmonier\SyliusWithdrawalPlugin\Generator\ReferenceGeneratorInterface;
use Tmonier\SyliusWithdrawalPlugin\Mailer\WithdrawalEmailManager;
use Tmonier\SyliusWithdrawalPlugin\Mailer\WithdrawalEmailManagerInterface;
use Tmonier\SyliusWithdrawalPlugin\Order\DeadlineCalculator;
use Tmonier\SyliusWithdrawalPlugin\Order\DeadlineCalculatorInterface;
use Tmonier\SyliusWithdrawalPlugin\Order\OrderMatcher;
use Tmonier\SyliusWithdrawalPlugin\Order\OrderMatcherInterface;
use Tmonier\SyliusWithdrawalPlugin\Submitter\WithdrawalRequestSubmitter;
use Tmonier\SyliusWithdrawalPlugin\Submitter\WithdrawalRequestSubmitterInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('tmonier_withdrawal.generator.reference', RandomReferenceGenerator::class);
    $services->alias(ReferenceGeneratorInterface::class, 'tmonier_withdrawal.generator.reference');

    $services
        ->set('tmonier_withdrawal.order.matcher', OrderMatcher::class)
        ->args([service('sylius.repository.order')])
    ;
    $services->alias(OrderMatcherInterface::class, 'tmonier_withdrawal.order.matcher');

    $services
        ->set('tmonier_withdrawal.order.deadline_calculator', DeadlineCalculator::class)
        ->args([param('tmonier_sylius_withdrawal.period_days')])
    ;
    $services->alias(DeadlineCalculatorInterface::class, 'tmonier_withdrawal.order.deadline_calculator');

    $services
        ->set('tmonier_withdrawal.mailer.email_manager', WithdrawalEmailManager::class)
        ->args([service('sylius.email_sender')])
    ;
    $services->alias(WithdrawalEmailManagerInterface::class, 'tmonier_withdrawal.mailer.email_manager');

    $services
        ->set('tmonier_withdrawal.submitter', WithdrawalRequestSubmitter::class)
        ->args([
            service('tmonier_withdrawal.factory.withdrawal_request'),
            service('tmonier_withdrawal.factory.withdrawal_request_item'),
            service('tmonier_withdrawal.manager.withdrawal_request'),
            service('tmonier_withdrawal.order.matcher'),
            service('tmonier_withdrawal.order.deadline_calculator'),
            service('tmonier_withdrawal.generator.reference'),
            service('tmonier_withdrawal.mailer.email_manager'),
            service('clock'),
            service('logger'),
            param('tmonier_sylius_withdrawal.notify_merchant'),
        ])
    ;
    $services->alias(WithdrawalRequestSubmitterInterface::class, 'tmonier_withdrawal.submitter');

    $services
        ->set('tmonier_withdrawal.form.type.withdrawal_statement', WithdrawalStatementType::class)
        ->tag('form.type')
    ;

    $services
        ->set('tmonier_withdrawal.controller.shop.withdrawal', WithdrawalController::class)
        ->args([
            service('sylius.context.channel'),
            service('sylius.context.locale'),
            service('sylius.context.customer'),
            service('tmonier_withdrawal.order.matcher'),
            service('tmonier_withdrawal.submitter'),
            service('tmonier_withdrawal.repository.withdrawal_request'),
            service('limiter.tmonier_withdrawal_ip')->nullOnInvalid(),
            service('limiter.tmonier_withdrawal_email')->nullOnInvalid(),
        ])
        ->call('setContainer', [service(\Psr\Container\ContainerInterface::class)])
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->public()
    ;

    $services
        ->set('tmonier_withdrawal.listener.workflow', WithdrawalRequestWorkflowListener::class)
        ->args([service('clock')])
        ->tag('kernel.event_listener', ['event' => 'workflow.tmonier_withdrawal_request.completed'])
    ;

    $services
        ->set('tmonier_withdrawal.listener.admin_menu', AdminMenuListener::class)
        ->tag('kernel.event_listener', ['event' => 'sylius.menu.admin.main'])
    ;
};
