<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusWithdrawalPlugin\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusWithdrawalPlugin\Generator\RandomReferenceGenerator;

final class RandomReferenceGeneratorTest extends TestCase
{
    public function testReferencesCarry64RandomBits(): void
    {
        $generator = new RandomReferenceGenerator();

        $references = [];
        for ($i = 0; $i < 100; ++$i) {
            $reference = $generator->generate();
            self::assertMatchesRegularExpression('/^W[0-9A-F]{16}$/', $reference);
            $references[$reference] = true;
        }

        self::assertCount(100, $references);
    }
}
