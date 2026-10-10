<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusWithdrawalPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusWithdrawalPlugin\Model\WithdrawalStatement;

final class WithdrawalStatementTest extends TestCase
{
    public function testItRoundTripsThroughAnArray(): void
    {
        $statement = new WithdrawalStatement();
        $statement->customerName = 'Jane Doe';
        $statement->email = 'jane@example.com';
        $statement->orderNumber = '000123';
        $statement->itemsDescription = 'Mug';
        $statement->orderItemIds = [4, 7];

        self::assertEquals($statement, WithdrawalStatement::fromArray($statement->toArray()));
    }

    public function testItSanitizesUnexpectedSessionData(): void
    {
        $statement = WithdrawalStatement::fromArray([
            'customerName' => ['not a string'],
            'orderItemIds' => ['3', 'abc', 5],
        ]);

        self::assertNull($statement->customerName);
        self::assertSame([3, 5], $statement->orderItemIds);
    }
}
