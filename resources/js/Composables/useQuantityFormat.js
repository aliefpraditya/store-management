const quantityFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 });

/**
 * @param {string|number} quantity
 * @returns {string} the quantity in Indonesian notation, up to 3 decimals
 */
export function formatQuantity(quantity) {
    return quantityFormat.format(Number(quantity));
}

/**
 * Shows a base-unit quantity in the larger units first, e.g. 30 base units with 1 DUS = 4 become "7 DUS + 2 PAIL".
 * @param {string|number} quantity quantity in the base unit
 * A product with a sealed pack unit (a roll of wire) is the exception: 400 m with 3 whole rolls is "3 ROL + 100 MTR", not
 * "4 ROL", because the loose metres can no longer be sold as a roll.
 * @param {{code: string, conversion_factor: string, is_sealed_pack?: boolean}[]} units the product's units
 * @param {string} baseUnit code of the base unit
 * @param {number|null} intactPacks how many whole sealed packs the quantity holds, null for a product without a sealed pack unit
 * @returns {string}
 */
export function formatStock(quantity, units, baseUnit, intactPacks = null) {
    let rest = Math.round(Number(quantity) * 1000) / 1000;

    if (rest === 0) {
        return `0 ${baseUnit}`;
    }

    const sealedPackUnit = intactPacks === null ? undefined : units.find((unit) => unit.is_sealed_pack);
    const parts = [];

    if (sealedPackUnit) {
        if (intactPacks > 0) {
            parts.push(`${intactPacks} ${sealedPackUnit.code}`);
        }

        rest = Math.round((rest - intactPacks * Number(sealedPackUnit.conversion_factor)) * 1000) / 1000;
    }

    const largerUnits = units
        .filter((unit) => unit !== sealedPackUnit && Number(unit.conversion_factor) > 1)
        .sort((a, b) => b.conversion_factor - a.conversion_factor);

    for (const unit of largerUnits) {
        const factor = Number(unit.conversion_factor);
        const count = Math.floor(rest / factor + 1e-9);

        if (count > 0) {
            parts.push(`${count} ${unit.code}`);
            rest = Math.round((rest - count * factor) * 1000) / 1000;
        }
    }

    if (rest > 0 || parts.length === 0) {
        parts.push(`${formatQuantity(rest)} ${baseUnit}`);
    }

    return parts.join(' + ');
}
