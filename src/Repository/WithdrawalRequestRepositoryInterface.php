<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Repository;

use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequestInterface;

/**
 * @extends RepositoryInterface<WithdrawalRequestInterface>
 */
interface WithdrawalRequestRepositoryInterface extends RepositoryInterface
{
    public function findOneByReference(string $reference): ?WithdrawalRequestInterface;
}
