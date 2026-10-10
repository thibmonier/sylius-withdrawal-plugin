<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequestInterface;

class WithdrawalRequestRepository extends EntityRepository implements WithdrawalRequestRepositoryInterface
{
    public function findOneByReference(string $reference): ?WithdrawalRequestInterface
    {
        $result = $this->findOneBy(['reference' => $reference]);

        return $result instanceof WithdrawalRequestInterface ? $result : null;
    }
}
