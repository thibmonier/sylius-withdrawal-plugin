<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusWithdrawalPlugin\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequest;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequestInterface;
use Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequestItem;

final class WithdrawalRequestTest extends TestCase
{
    public function testItIsReceivedAndCoversTheWholeContractByDefault(): void
    {
        $request = new WithdrawalRequest();

        self::assertSame(WithdrawalRequestInterface::STATE_RECEIVED, $request->getState());
        self::assertTrue($request->isWholeContract());
        self::assertNull($request->isSubmittedInTime());
    }

    public function testItIsPartialWithItemsOrDescription(): void
    {
        $request = new WithdrawalRequest();
        $request->setItemsDescription('   ');
        self::assertTrue($request->isWholeContract());

        $request->setItemsDescription('The blue mug');
        self::assertFalse($request->isWholeContract());

        $request->setItemsDescription(null);
        $item = new WithdrawalRequestItem();
        $request->addItem($item);
        self::assertFalse($request->isWholeContract());
        self::assertSame($request, $item->getWithdrawalRequest());

        $request->removeItem($item);
        self::assertTrue($request->isWholeContract());
        self::assertNull($item->getWithdrawalRequest());
    }

    public function testItTellsWhetherItWasSubmittedInTime(): void
    {
        $request = new WithdrawalRequest();
        $request->setDeadlineAt(new \DateTimeImmutable('2026-06-19 23:59:59'));

        $request->setSubmittedAt(new \DateTimeImmutable('2026-06-19 23:59:59'));
        self::assertTrue($request->isSubmittedInTime());

        $request->setSubmittedAt(new \DateTimeImmutable('2026-06-20 00:00:00'));
        self::assertFalse($request->isSubmittedInTime());
    }
}
