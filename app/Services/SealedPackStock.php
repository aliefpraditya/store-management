<?php

namespace App\Services;

use App\Exceptions\SaleException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * What one product has on a shelf, as seen by the lines of one sale: a quantity in the base unit, of which some whole
 * sealed packs (a roll of wire) and the rest loose pieces. Each line is taken off in turn and answers with the change in
 * whole packs it causes, to be written to the ledger with the line.
 *
 * A sealed pack sold as such takes whole packs. Anything sold in a smaller unit takes loose pieces first and only then
 * opens a pack, because a pack that has been cut cannot be sold whole again. Taking more than there is throws.
 *
 * Whole packs that ready customer orders have set aside (`$reservedPacks`) are not there for anyone else: they can be
 * neither sold whole nor opened for a smaller unit, so the order can still be collected as the packs it asked for.
 */
final class SealedPackStock
{
    public function __construct(
        private BigDecimal $quantity,
        private int $intactPacks,
        private readonly BigDecimal $packConversion,
        private readonly string $productName,
        private readonly string $packUnitCode,
        private readonly string $baseUnitCode,
        private readonly int $reservedPacks = 0,
    ) {}

    /**
     * @return int the change in whole packs (negative)
     *
     * @throws SaleException
     */
    public function takeSealedPacks(int $packs): int
    {
        if ($packs > $this->freePacks()) {
            throw SaleException::insufficientSealedPacks($this->productName, $this->packUnitCode, $this->freePacks());
        }

        $this->intactPacks -= $packs;
        $this->quantity = $this->quantity->minus($this->packConversion->multipliedBy($packs));

        return -$packs;
    }

    /**
     * @param  BigDecimal  $baseQuantity  how much to take, in the base unit
     * @return int the change in whole packs: 0 when loose pieces are enough, otherwise minus the packs that had to be opened
     *
     * @throws SaleException
     */
    public function takeLoose(BigDecimal $baseQuantity): int
    {
        $shortfall = $baseQuantity->minus($this->looseQuantity());
        $packsToOpen = $shortfall->isPositive() ? $shortfall->dividedBy($this->packConversion, 0, RoundingMode::Up)->toInt() : 0;

        if ($packsToOpen > $this->freePacks()) {
            throw SaleException::insufficientStock($this->productName, SaleException::quantityText($this->quantity, $this->baseUnitCode));
        }

        $this->intactPacks -= $packsToOpen;
        $this->quantity = $this->quantity->minus($baseQuantity);

        return -$packsToOpen;
    }

    /**
     * @return int the whole packs nobody has set aside
     */
    public function freePacks(): int
    {
        return max(0, $this->intactPacks - $this->reservedPacks);
    }

    private function looseQuantity(): BigDecimal
    {
        return $this->quantity->minus($this->packConversion->multipliedBy($this->intactPacks));
    }
}
