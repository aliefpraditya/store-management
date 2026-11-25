<?php

namespace App\Enums;

/**
 * Where a purchase (nota) stands. It starts as a draft, is confirmed into an order (ordered), goes on the way once the carrier
 * has it (shipped), and is completed when the warehouse has received and checked it; a cancelled one is over. There is no
 * "received in part": what arrived short or damaged is settled on the receiving. The last two steps are set by the shipping and
 * receiving screens, not by the purchase screen.
 */
enum PurchaseStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ordered => 'Dipesan',
            self::Shipped => 'Dikirim',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Only a draft is changed in its lines and prices; confirming it locks them. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** A purchase can be cancelled until the goods are on the way. */
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
