import { computed, ref } from 'vue';

/** The stock the store's printer is set up for (99 x 15 mm, three labels across), until another is typed. */
const DEFAULT_STOCK = { width: 99, height: 15, columns: 3 };

/**
 * A sticker roll stays in the printer for weeks, so the size is remembered on this browser instead of asked every time.
 *
 * @param {string} storageKey
 * @returns {{width: number, height: number, columns: number}}
 */
function loadSavedStock(storageKey) {
    try {
        const saved = JSON.parse(localStorage.getItem(storageKey));

        return {
            width: Number(saved.width) || DEFAULT_STOCK.width,
            height: Number(saved.height) || DEFAULT_STOCK.height,
            columns: Number(saved.columns) || DEFAULT_STOCK.columns,
        };
    } catch {
        return DEFAULT_STOCK;
    }
}

/**
 * The stock a sheet of stickers is printed on (its size, and how many stickers sit side by side), for the windows that
 * print barcode stickers and price labels.
 *
 * @param {string} storageKey where this browser remembers the size, one key per kind of sticker as each has its own roll
 * @param {() => {min_width: number, max_width: number, min_height: number, max_height: number, max_columns: number}} limits
 *   a getter, so the limits stay reactive to the dialog's props
 */
export function useLabelStock(storageKey, limits) {
    const savedStock = loadSavedStock(storageKey);
    const width = ref(savedStock.width);
    const height = ref(savedStock.height);
    const columns = ref(savedStock.columns);

    const isSizeValid = computed(() => {
        const { min_width, max_width, min_height, max_height } = limits();

        return width.value >= min_width && width.value <= max_width && height.value >= min_height && height.value <= max_height;
    });

    const areColumnsValid = computed(() => Number.isInteger(columns.value) && columns.value >= 1 && columns.value <= limits().max_columns);

    /** What is wrong with the size or the columns, in words for the user; null when both are fine. */
    const problem = computed(() => {
        const { min_width, max_width, min_height, max_height, max_columns } = limits();

        if (!isSizeValid.value) {
            return `Lebar stok ${min_width}–${max_width} mm, tinggi ${min_height}–${max_height} mm.`;
        }

        return areColumnsValid.value ? null : `Label ke samping 1–${max_columns}.`;
    });

    function save() {
        try {
            localStorage.setItem(storageKey, JSON.stringify({ width: width.value, height: height.value, columns: columns.value }));
        } catch {
            // Private windows may refuse storage; the size is then simply asked again next time.
        }
    }

    return { width, height, columns, problem, save };
}
