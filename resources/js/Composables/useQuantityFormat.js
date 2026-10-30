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
 * @param {{code: string, conversion_factor: string}[]} units the product's units
 * @param {string} baseUnit code of the base unit
 * @returns {string}
 */
export function formatStock(quantity, units, baseUnit) {
    let rest = Math.round(Number(quantity) * 1000) / 1000;

    if (rest === 0) {
        return `0 ${baseUnit}`;
    }

    const largerUnits = units.filter((unit) => Number(unit.conversion_factor) > 1).sort((a, b) => b.conversion_factor - a.conversion_factor);
    const parts = [];

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
