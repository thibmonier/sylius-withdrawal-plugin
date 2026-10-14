<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class TmonierSyliusWithdrawalExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{driver: string, period_days: int, notify_merchant: bool, resources: array<string, mixed>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $this->registerResources('tmonier_withdrawal', $config['driver'], $config['resources'], $container);

        $container->setParameter('tmonier_sylius_withdrawal.period_days', $config['period_days']);
        $container->setParameter('tmonier_sylius_withdrawal.notify_merchant', $config['notify_merchant']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Grids are processed before this extension is loaded, so the model class parameter
        // they reference has to exist at prepend time.
        /** @var array{resources: array{withdrawal_request: array{classes: array{model: string}}}, rate_limit: array{enabled: bool, per_ip: int, per_email: int, interval: string, cache_pool: string}} $config */
        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));
        $container->setParameter(
            'tmonier_withdrawal.model.withdrawal_request.class',
            $config['resources']['withdrawal_request']['classes']['model'],
        );

        if ($config['rate_limit']['enabled'] && class_exists(RateLimiterFactory::class)) {
            $limiter = static fn (int $limit): array => [
                'policy' => 'sliding_window',
                'limit' => $limit,
                'interval' => $config['rate_limit']['interval'],
                'cache_pool' => $config['rate_limit']['cache_pool'],
                // Without a lock, parallel requests read the same count and all get through.
                'lock_factory' => 'lock.factory',
            ];
            // The default lock store (flock) unless the application configures the lock component.
            $container->prependExtensionConfig('framework', ['lock' => ['enabled' => true, 'resources' => ['default' => ['flock']]]]);
            $container->prependExtensionConfig('framework', ['rate_limiter' => [
                'tmonier_withdrawal_ip' => $limiter($config['rate_limit']['per_ip']),
                'tmonier_withdrawal_email' => $limiter($config['rate_limit']['per_email']),
            ]]);
        }

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'TmonierSyliusWithdrawalPlugin' => [
                        'type' => 'xml',
                        'dir' => 'config/doctrine',
                        'prefix' => 'Tmonier\SyliusWithdrawalPlugin\Entity',
                        'is_bundle' => true,
                    ],
                ],
            ],
        ]);

        $this->prependDoctrineMigrations($container);
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Tmonier\SyliusWithdrawalPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@TmonierSyliusWithdrawalPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
