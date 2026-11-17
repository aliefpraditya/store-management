<script setup>
import { computed, ref, watch } from 'vue';
import { postJson } from '../Composables/postJson';
import { printSheet } from '../Composables/printSheet';
import { useLabelStock } from '../Composables/useLabelStock';
import { formatRupiah } from '../Composables/useMoneyFormat';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** The items ticked on the item list. @type {number[]} */
    productIds: { type: Array, required: true },
    /** @type {{units: string, sheet: string}} */
    urls: { type: Object, required: true },
    /** @type {{max_copies_per_unit: number, max_labels: number, min_width: number, max_width: number, min_height: number, max_height: number, max_columns: number}} */
    limits: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue']);

const { width, height, columns, labelWidth, problem: stockProblem, save: saveStock } = useLabelStock('price-label-size', () => props.limits);

/** @type {import('vue').Ref<{id: number, product_id: number, name: string, sku: string, unit: string, price: string, is_base: boolean}[]>} */
const units = ref([]);
const isLoading = ref(false);
const loadError = ref(null);

/** How many labels each unit gets, by product unit id; an empty box means none. */
const copies = ref({});

/** The items whose other units are shown, by product id. */
const openedProductIds = ref(new Set());

/**
 * One entry per item: the unit printed by default (the base unit, or the first one when the base is not sold) and the
 * item's other units, which the admin can open to print those prices too.
 */
const products = computed(() => {
    const unitsByProduct = new Map();

    units.value.forEach((unit) => unitsByProduct.set(unit.product_id, [...(unitsByProduct.get(unit.product_id) ?? []), unit]));

    return [...unitsByProduct.values()].map((productUnits) => {
        const primary = productUnits.find((unit) => unit.is_base) ?? productUnits[0];

        return { id: primary.product_id, name: primary.name, sku: primary.sku, primary, others: productUnits.filter((unit) => unit !== primary) };
    });
});

async function loadUnits() {
    isLoading.value = true;
    loadError.value = null;
    units.value = [];
    openedProductIds.value = new Set();

    try {
        units.value = await postJson(props.urls.units, { product_ids: props.productIds });
        copies.value = Object.fromEntries(units.value.map((unit) => [unit.id, null]));
        products.value.forEach(({ primary }) => (copies.value[primary.id] = 1));
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
            loadUnits();
        }
    },
);

function toggleOthers(productId) {
    const opened = new Set(openedProductIds.value);

    if (!opened.delete(productId)) {
        opened.add(productId);
    }

    openedProductIds.value = opened;
}

/** How many of an item's other units are set to print, so a closed list still shows it has some. */
function othersWanted(product) {
    return product.others.filter((unit) => Number(copies.value[unit.id]) > 0).length;
}

const wantedCopies = computed(() => Object.entries(copies.value).map(([unitId, count]) => [unitId, Number(count) || 0]).filter(([, count]) => count > 0));

const totalCopies = computed(() => wantedCopies.value.reduce((sum, [, count]) => sum + count, 0));

const hasBadCount = computed(() => wantedCopies.value.some(([, count]) => count > props.limits.max_copies_per_unit || !Number.isInteger(count)));

const problem = computed(() => {
    if (hasBadCount.value) {
        return `Jumlah per satuan harus bilangan bulat, paling banyak ${props.limits.max_copies_per_unit}.`;
    }

    if (totalCopies.value > props.limits.max_labels) {
        return `Paling banyak ${props.limits.max_labels} label harga sekali cetak. Kurangi jumlahnya atau cetak bertahap.`;
    }

    return stockProblem.value;
});

const canPrint = computed(() => totalCopies.value > 0 && problem.value === null);

/** The sheet prints from a hidden frame; the sizes and counts travel in its address so it can be reloaded. */
function print() {
    const query = new URLSearchParams();

    wantedCopies.value.forEach(([unitId, count]) => query.append(`labels[${unitId}]`, count));
    query.set('width', width.value);
    query.set('height', height.value);
    query.set('columns', columns.value);

    saveStock();

    printSheet(() => `${props.urls.sheet}?${query}`);
}
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card class="labels">
            <q-card-section>
                <div class="labels__title">Cetak label harga {{ productIds.length }} barang</div>
                <small class="labels__hint">Label berisi nama barang, harga, dan tanggal cetak. Yang dicetak satuan dasar; buka "Satuan lain" untuk mencetak harga satuan lainnya.</small>
            </q-card-section>

            <q-card-section class="labels__body">
                <p v-if="loadError" class="labels__note labels__note--bad">{{ loadError }}</p>
                <p v-else-if="isLoading" class="labels__note"><q-spinner size="16px" class="q-mr-sm" />Memuat barang…</p>
                <p v-else-if="products.length === 0" class="labels__note">Barang yang dipilih tidak punya satuan yang dijual.</p>
                <table v-else class="labels__table">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Harga</th>
                            <th class="labels__count">Jumlah cetak</th>
                        </tr>
                    </thead>
                    <tbody v-for="product in products" :key="product.id">
                        <tr>
                            <td>
                                <b>{{ product.name }}</b>
                                <small>{{ product.sku }}</small>
                                <q-btn
                                    v-if="product.others.length > 0"
                                    flat
                                    dense
                                    no-caps
                                    color="primary"
                                    size="sm"
                                    :icon-right="openedProductIds.has(product.id) ? 'expand_less' : 'expand_more'"
                                    :label="`Satuan lain (${product.others.length})${othersWanted(product) > 0 ? ` · ${othersWanted(product)} dicetak` : ''}`"
                                    @click="toggleOthers(product.id)"
                                />
                            </td>
                            <td>{{ formatRupiah(product.primary.price) }}/{{ product.primary.unit }}</td>
                            <td class="labels__count">
                                <q-input v-model.number="copies[product.primary.id]" type="number" min="0" step="1" outlined dense :aria-label="`Jumlah label harga ${product.name} per ${product.primary.unit}`" />
                            </td>
                        </tr>
                        <template v-if="openedProductIds.has(product.id)">
                            <tr v-for="unit in product.others" :key="unit.id" class="labels__row--other">
                                <td>Satuan {{ unit.unit }}</td>
                                <td>{{ formatRupiah(unit.price) }}/{{ unit.unit }}</td>
                                <td class="labels__count">
                                    <q-input v-model.number="copies[unit.id]" type="number" min="0" step="1" outlined dense :aria-label="`Jumlah label harga ${product.name} per ${unit.unit}`" />
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </q-card-section>

            <q-card-section class="labels__size">
                <q-input v-model.number="width" type="number" min="1" outlined dense label="Lebar stok" suffix="mm" />
                <q-input v-model.number="height" type="number" min="1" outlined dense label="Tinggi stok" suffix="mm" />
                <q-input v-model.number="columns" type="number" min="1" :max="limits.max_columns" outlined dense label="Label ke samping" :hint="labelWidth ? `Tiap label ${labelWidth} mm` : undefined" />
            </q-card-section>

            <q-card-actions class="labels__actions">
                <span class="labels__note" :class="{ 'labels__note--bad': problem }">{{ problem ?? `${totalCopies} label harga akan dicetak.` }}</span>
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
    color: var(--app-mute);
    font-size: 12px;
}

.labels__table .labels__count {
    width: 130px;
}

.labels__row--other td {
    background: #f8fafb;
    color: var(--app-mute);
}

.labels__row--other td:first-child {
    padding-left: 24px;
}

.labels__size {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
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
