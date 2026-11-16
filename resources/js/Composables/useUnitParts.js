/**
 * One typed quantity in one of a product's units, e.g. 2 ROL or 30 MTR. Several parts add up to a quantity in the base unit.
 * @typedef {{unit_code: string, quantity: string}} UnitPart
 * @typedef {{code: string, conversion_factor: string|number, is_sealed_pack?: boolean}} ProductUnit
 */

const roundToThousandths = (value) => Math.round(value * 1000) / 1000;

/**
 * @param {UnitPart[]} parts
 * @param {ProductUnit[]} units the product's units
 * @returns {number} what the parts add up to in the base unit; blank parts count as zero
 */
export function baseQuantityOf(parts, units) {
    const factors = Object.fromEntries(units.map((unit) => [unit.code, Number(unit.conversion_factor)]));

    return roundToThousandths(parts.reduce((sum, part) => sum + (part.quantity === '' ? 0 : Number(part.quantity)) * (factors[part.unit_code] ?? 0), 0));
}

/**
 * @param {UnitPart[]} parts
 * @param {ProductUnit[]} units the product's units
 * @returns {number|null} how many of the parts are sealed packs (rolls), or null for a product without a sealed pack unit
 */
export function sealedPacksOf(parts, units) {
    const sealedPackUnit = units.find((unit) => unit.is_sealed_pack);

    if (!sealedPackUnit) {
        return null;
    }

    return parts.filter((part) => part.unit_code === sealedPackUnit.code && part.quantity !== '').reduce((sum, part) => sum + Number(part.quantity), 0);
}

/**
 * Writes a base-unit quantity in the larger units first, the way it is shown in stock: 130 m of a wire sold by the roll
 * (1 ROL = 100 MTR) becomes 1 ROL + 30 MTR. A blank value stays one blank part in the base unit.
 *
 * When the number of whole sealed packs is known, exactly that many are written as the sealed pack unit and the rest only in
 * the other units, because loose pieces can no longer be a roll. When it is not known (null), a sealed pack unit is used like
 * any other.
 *
 * @param {string|number|null} baseQuantity
 * @param {ProductUnit[]} units the product's units
 * @param {string} baseUnit code of the base unit
 * @param {number|null} [sealedPacks] how many whole sealed packs the quantity holds, if known
 * @returns {UnitPart[]}
 */
export function splitIntoParts(baseQuantity, units, baseUnit, sealedPacks = null) {
    if (baseQuantity === '' || baseQuantity === null) {
        return [{ unit_code: baseUnit, quantity: '' }];
    }

    let rest = roundToThousandths(Number(baseQuantity));
    const parts = [];
    const sealedPackUnit = sealedPacks === null ? undefined : units.find((unit) => unit.is_sealed_pack);

    if (sealedPackUnit && sealedPacks > 0) {
        parts.push({ unit_code: sealedPackUnit.code, quantity: String(sealedPacks) });
        rest = roundToThousandths(rest - sealedPacks * Number(sealedPackUnit.conversion_factor));
    }

    const largerUnits = units
        .filter((unit) => unit !== sealedPackUnit && Number(unit.conversion_factor) > 1)
        .sort((a, b) => Number(b.conversion_factor) - Number(a.conversion_factor));

    for (const unit of largerUnits) {
        const factor = Number(unit.conversion_factor);
        const count = Math.floor(rest / factor + 1e-9);

        if (count > 0) {
            parts.push({ unit_code: unit.code, quantity: String(count) });
            rest = roundToThousandths(rest - count * factor);
        }
    }

    if (rest > 0 || parts.length === 0) {
        parts.push({ unit_code: baseUnit, quantity: String(rest) });
    }

    return parts;
}
