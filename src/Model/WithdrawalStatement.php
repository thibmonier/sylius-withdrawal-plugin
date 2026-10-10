<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Model;

/**
 * Data entered by the consumer before the confirmation step (Art. 11a(2) CRD).
 */
final class WithdrawalStatement
{
    public ?string $customerName = null;

    public ?string $email = null;

    public ?string $orderNumber = null;

    public ?string $itemsDescription = null;

    /** @var list<int> */
    public array $orderItemIds = [];

    /**
     * @return array{customerName: ?string, email: ?string, orderNumber: ?string, itemsDescription: ?string, orderItemIds: list<int>}
     */
    public function toArray(): array
    {
        return [
            'customerName' => $this->customerName,
            'email' => $this->email,
            'orderNumber' => $this->orderNumber,
            'itemsDescription' => $this->itemsDescription,
            'orderItemIds' => $this->orderItemIds,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $statement = new self();
        $statement->customerName = self::stringOrNull($data['customerName'] ?? null);
        $statement->email = self::stringOrNull($data['email'] ?? null);
        $statement->orderNumber = self::stringOrNull($data['orderNumber'] ?? null);
        $statement->itemsDescription = self::stringOrNull($data['itemsDescription'] ?? null);

        $orderItemIds = $data['orderItemIds'] ?? [];
        $statement->orderItemIds = is_array($orderItemIds) ? array_values(array_map('intval', array_filter($orderItemIds, 'is_numeric'))) : [];

        return $statement;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
