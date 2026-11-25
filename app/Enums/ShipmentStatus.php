<?php

namespace App\Enums;

/**
 * Where a shipment stands. It is a draft while the packing list is still being written, and shipped once its departure date is
 * filled in; a cancelled one is over. Receiving the containers at the warehouse comes later and adds its own steps.
 */
enum ShipmentStatus: string
{
    case Draft = 'draft';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Shipped => 'Dikirim',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Only a draft can still be changed or cancelled; once shipped its notas are on the way. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status): array => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
