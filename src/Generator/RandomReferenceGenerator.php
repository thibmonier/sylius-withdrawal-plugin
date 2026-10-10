<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Generator;

/** Non-sequential so that references reveal nothing about request volume. */
final class RandomReferenceGenerator implements ReferenceGeneratorInterface
{
    public function generate(): string
    {
        return 'W' . strtoupper(bin2hex(random_bytes(5)));
    }
}
