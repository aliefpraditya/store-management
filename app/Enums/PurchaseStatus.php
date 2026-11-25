<?php

namespace App\Enums;

/**
 * Where a purchase (nota) stands. It starts as a draft, is confirmed into an order, and is received (in whole or in part)
 * once the goods arrive; a short-closed or cancelled one is over. The last steps are set by the container and receiving
 * screens, not by the purchase screen.
 */
enum PurchaseStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ordered => 'Dipesan',
            self::PartiallyReceived => 'Diterima sebagian',
            self::Received => 'Diterima',
            self::Closed => 'Ditutup',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Only a draft is changed in its lines and prices; confirming it locks them. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** A purchase can be cancelled until goods have been received against it. */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Draft, self::Ordered], true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status): array => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
