<?php

namespace App\Http\Requests\Tenant;

/**
 * The stock a sticker sheet is printed on, which barcode stickers and price labels share: its size (that of the whole row of
 * stickers) and how many stickers sit side by side on it.
 */
trait ReadsLabelStock
{
    public const MIN_WIDTH_MM = 20;

    public const MAX_WIDTH_MM = 210;

    public const MIN_HEIGHT_MM = 10;

    public const MAX_HEIGHT_MM = 297;

    public const MAX_COLUMNS = 6;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function stockRules(): array
    {
        return [
            'width' => ['required', 'integer', 'between:'.self::MIN_WIDTH_MM.','.self::MAX_WIDTH_MM],
            'height' => ['required', 'integer', 'between:'.self::MIN_HEIGHT_MM.','.self::MAX_HEIGHT_MM],
            'columns' => ['nullable', 'integer', 'between:1,'.self::MAX_COLUMNS],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function stockMessages(): array
    {
        return [
            'width.between' => 'Lebar stok '.self::MIN_WIDTH_MM.' sampai '.self::MAX_WIDTH_MM.' mm.',
            'height.between' => 'Tinggi stok '.self::MIN_HEIGHT_MM.' sampai '.self::MAX_HEIGHT_MM.' mm.',
            'columns.between' => 'Label ke samping 1 sampai '.self::MAX_COLUMNS.'.',
        ];
    }

    public function widthMm(): int
    {
        return (int) $this->validated('width');
    }

    public function heightMm(): int
    {
        return (int) $this->validated('height');
    }

    /**
     * How many stickers share one row of the stock, side by side; the width is that of the whole row.
     */
    public function columns(): int
    {
        return (int) ($this->validated('columns') ?? 1);
    }
}
