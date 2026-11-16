<script setup>
import { computed, ref, watch } from 'vue';
import { postJson } from '../Composables/postJson';
import { printSheet } from '../Composables/printSheet';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** The items ticked on the item list. @type {number[]} */
    productIds: { type: Array, required: true },
    /** @type {{products: string, sheet: string}} */
    urls: { type: Object, required: true },
    /** @type {{max_copies_per_product: number, max_labels: number, min_width: number, max_width: number, min_height: number, max_height: number, max_columns: number}} */
    limits: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue']);

const SIZE_STORAGE_KEY = 'barcode-label-size';

/** The stock the store's printer is set up for (99 x 15 mm, three labels across), until another is typed. */
const DEFAULT_SIZE = { width: 99, height: 15, columns: 3 };

/** A sticker roll stays in the printer for weeks, so the size is remembered on this browser instead of asked every time. */
function loadSavedSize() {
    try {
        const saved = JSON.parse(localStorage.getItem(SIZE_STORAGE_KEY));

        return { width: Number(saved.width) || DEFAULT_SIZE.width, height: Number(saved.height) || DEFAULT_SIZE.height, columns: Number(saved.columns) || DEFAULT_SIZE.columns };
    } catch {
        return DEFAULT_SIZE;
    }
}

function saveSize() {
    try {
        localStorage.setItem(SIZE_STORAGE_KEY, JSON.stringify({ width: width.value, height: height.value, columns: columns.value }));
    } catch {
        // Private windows may refuse storage; the size is then simply asked again next time.
    }
}

const savedSize = loadSavedSize();
const width = ref(savedSize.width);
const height = ref(savedSize.height);
const columns = ref(savedSize.columns);

const products = ref([]);
const isLoading = ref(false);
const loadError = ref(null);

/** How many stickers each item gets, by product id; an empty box means none. */
const copies = ref({});

async function loadProducts() {
    isLoading.value = true;
    loadError.value = null;
    products.value = [];

    try {
        products.value = await postJson(props.urls.products, { product_ids: props.productIds });
        copies.value = Object.fromEntries(products.value.map((product) => [product.id, product.is_printable ? 1 : null]));
    } catch (error) {
        loadError.value = error.message;
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            loadProducts();
        }
    },
);

const wantedCopies = computed(() => Object.entries(copies.value).map(([productId, count]) => [productId, Number(count) || 0]).filter(([, count]) => count > 0));

const totalCopies = computed(() => wantedCopies.value.reduce((sum, [, count]) => sum + count, 0));

const hasBadCount = computed(() => wantedCopies.value.some(([, count]) => count > props.limits.max_copies_per_product || !Number.isInteger(count)));

const isSizeValid = computed(
    () => width.value >= props.limits.min_width && width.value <= props.limits.max_width && height.value >= props.limits.min_height && height.value <= props.limits.max_height,
);

const areColumnsValid = computed(() => Number.isInteger(columns.value) && columns.value >= 1 && columns.value <= props.limits.max_columns);

const labelWidth = computed(() => (areColumnsValid.value ? Math.round((width.value / columns.value) * 10) / 10 : null));

const problem = computed(() => {
    if (hasBadCount.value) {
        return `Jumlah per barang harus bilangan bulat, paling banyak ${props.limits.max_copies_per_product}.`;
    }

    if (totalCopies.value > props.limits.max_labels) {
        return `Paling banyak ${props.limits.max_labels} barcode sekali cetak. Kurangi jumlahnya atau cetak bertahap.`;
    }

    if (!isSizeValid.value) {
        return `Lebar stok ${props.limits.min_width}–${props.limits.max_width} mm, tinggi ${props.limits.min_height}–${props.limits.max_height} mm.`;
    }

    if (!areColumnsValid.value) {
        return `Label ke samping 1–${props.limits.max_columns}.`;
    }

    return null;
});

const canPrint = computed(() => totalCopies.value > 0 && problem.value === null);

/** The sheet prints from a hidden frame; the sizes and counts travel in its address so it can be reloaded. */
function print() {
    const query = new URLSearchParams();

    wantedCopies.value.forEach(([productId, count]) => query.append(`labels[${productId}]`, count));
    query.set('width', width.value);
    query.set('height', height.value);
    query.set('columns', columns.value);

    saveSize();

    printSheet(() => `${props.urls.sheet}?${query}`);
}
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card class="labels">
            <q-card-section>
                <div class="labels__title">Cetak barcode {{ productIds.length }} barang</div>
                <small class="labels__hint">Barcode dibuat dari SKU barang. Isi berapa label yang dicetak untuk tiap barang.</small>
            </q-card-section>

            <q-card-section class="labels__body">
                <p v-if="loadError" class="labels__note labels__note--bad">{{ loadError }}</p>
                <p v-else-if="isLoading" class="labels__note"><q-spinner size="16px" class="q-mr-sm" />Memuat barang…</p>
                <table v-else class="labels__table">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>SKU</th>
                            <th class="labels__count">Jumlah cetak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="product in products" :key="product.id" :class="{ 'labels__row--off': !product.is_printable }">
                            <td><b>{{ product.name }}</b></td>
                            <td>
                                {{ product.sku }}
                                <small v-if="!product.is_printable" class="labels__note--bad">SKU berisi karakter yang tidak bisa dicetak sebagai barcode (hanya huruf, angka, spasi, dan - . $ / + %)</small>
                            </td>
                            <td class="labels__count">
                                <q-input
                                    v-model.number="copies[product.id]"
                                    type="number"
                                    min="0"
                                    step="1"
                                    outlined
                                    dense
                                    :disable="!product.is_printable"
                                    :aria-label="`Jumlah barcode ${product.name}`"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </q-card-section>

            <q-card-section class="labels__size">
                <q-input v-model.number="width" type="number" min="1" outlined dense label="Lebar stok" suffix="mm" />
                <q-input v-model.number="height" type="number" min="1" outlined dense label="Tinggi stok" suffix="mm" />
                <q-input v-model.number="columns" type="number" min="1" :max="limits.max_columns" outlined dense label="Label ke samping" :hint="labelWidth ? `Tiap label ${labelWidth} mm` : undefined" />
            </q-card-section>

            <q-card-actions class="labels__actions">
                <span class="labels__note" :class="{ 'labels__note--bad': problem }">{{ problem ?? `${totalCopies} barcode akan dicetak, satu label tiap barcode.` }}</span>
                <span>
                    <q-btn flat no-caps label="Tutup" @click="emit('update:modelValue', false)" />
                    <q-btn color="primary" unelevated no-caps icon="print" label="Cetak" :disable="!canPrint" @click="print" />
                </span>
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.labels {
    width: 720px;
    max-width: 96vw;
}

.labels__title {
    font-size: 17px;
    font-weight: 700;
}

.labels__hint {
    color: var(--app-mute);
}

.labels__body {
    max-height: 52vh;
    overflow-y: auto;
    padding-top: 0;
}

.labels__table {
    width: 100%;
    border-collapse: collapse;
}

.labels__table th,
.labels__table td {
    padding: 6px 8px;
    border-bottom: 1px solid var(--app-line);
    text-align: left;
    vertical-align: middle;
}

.labels__table th {
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
}

.labels__table small {
    display: block;
    font-size: 12px;
}

.labels__table .labels__count {
    width: 130px;
}

.labels__row--off td:not(:first-child) {
    color: var(--app-mute);
}

.labels__size {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    border-top: 1px solid var(--app-line);
}

.labels__size .q-field {
    width: 140px;
}

.labels__actions {
    justify-content: space-between;
    padding: 8px 16px 16px;
}

.labels__note {
    margin: 0;
    color: var(--app-mute);
    font-size: 12.5px;
}

.labels__note--bad {
    color: #be123c;
}
</style>
