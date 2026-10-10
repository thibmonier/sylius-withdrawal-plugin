<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Generator;

interface ReferenceGeneratorInterface
{
    public function generate(): string;
}
