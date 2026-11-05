<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import ConversionCalculatorDialog from '../../../Components/ConversionCalculatorDialog.vue';
import PickOrCreateSelect from '../../../Components/PickOrCreateSelect.vue';
import UnitFormDialog from '../../../Components/UnitFormDialog.vue';
import UnitSelect from '../../../Components/UnitSelect.vue';
import { useConfirmDelete } from '../../../Composables/useConfirmDelete';
import { marginFromPrice, priceFromMargin } from '../../../Composables/usePriceMargin';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    product: { type: Object, default: null },
    categories: { type: Array, required: true },
    brands: { type: Array, required: true },
    units: { type: Array, required: true },
    form_action: { type: Object, required: true },
});

const page = usePage();
const $q = useQuasar();
const deleteWithConfirmation = useConfirmDelete();

const isEditing = props.product !== null;
const isLocked = props.product?.is_locked ?? false;

/**
 * @param {boolean} isDefaultSale
 */
const emptyUnitRow = (isDefaultSale = false) => ({
    id: null,
    unit: null,
    conversion_factor: 1,
    barcode: '',
    is_sellable: true,
    cost_price: 0,
    cost_is_manual: false,
    selling_price: 0,
    margin_percent: '',
    is_default_sale: isDefaultSale,
});

/**
 * What a unit costs when it simply follows the base unit: the base cost times the conversion.
 * @param {string|number} baseCost purchase price of the base unit
 * @param {string|number} conversionFactor
 * @returns {number}
 */
const suggestedCost = (baseCost, conversionFactor) => Math.round(Number(baseCost || 0) * Number(conversionFactor || 0) * 100) / 100;

/**
 * The server sends decimals as strings ("116600.00"); the inputs show them as plain numbers. A unit whose cost
 * differs from base cost times conversion was priced by hand, so it stops following the base cost. The margin is not
 * stored: it is worked out from the cost and the selling price, and only lives here to be typed into.
 */
const toNumericRow = (row, index, rows) => ({
    ...row,
    conversion_factor: Number(row.conversion_factor),
    cost_price: Number(row.cost_price),
    cost_is_manual: index > 0 && Math.abs(Number(row.cost_price) - suggestedCost(rows[0].cost_price, row.conversion_factor)) > 0.005,
    selling_price: Number(row.selling_price),
    margin_percent: marginFromPrice(row.cost_price, row.selling_price) ?? '',
});

/** Version is the save counter the form was opened with: a save from an older one is refused, so edits are never silently overwritten. */
const form = useForm({
    version: props.product?.version ?? null,
    name: props.product?.name ?? '',
    sku: props.product?.sku ?? '',
    category: props.product?.category ?? null,
    brand: props.product?.brand ?? null,
    min_stock: Number(props.product?.min_stock ?? 0),
    is_active: props.product?.is_active ?? true,
    units: props.product?.units.map(toNumericRow) ?? [emptyUnitRow(true)],
});

/** Rows that already exist keep their unit and conversion once the product has stock or transactions. */
const isRowLocked = (row) => isLocked && row.id !== null;

/** Which unit select asked for "Tambah satuan baru", so the new unit can be filled into that row. */
const unitDialog = ref({ isOpen: false, rowIndex: 0 });

function openUnitDialog(rowIndex) {
    unitDialog.value = { isOpen: true, rowIndex };
}

/**
 * The unit was created while this form stays open with everything already typed; pick it in the row that asked.
 * @param {string} code code of the unit that was just saved
 */
function selectCreatedUnit(code) {
    const created = props.units.find((unit) => unit.code === code);

    if (created) {
        form.units[unitDialog.value.rowIndex].unit = created;
    }
}

const baseUnitCode = computed(() => form.units[0].unit?.code ?? '');

const isBelowCost = (row) => row.is_sellable && Number(row.selling_price) < Number(row.cost_price || 0);

/**
 * Units not priced by hand follow the base cost, so changing the base cost or a conversion updates them.
 */
watch(
    () => form.units[0].cost_price + '|' + form.units.map((row) => row.conversion_factor).join(','),
    () => {
        form.units.forEach((row, index) => {
            if (index > 0 && !row.cost_is_manual) {
                row.cost_price = suggestedCost(form.units[0].cost_price, row.conversion_factor);
                row.margin_percent = marginFromPrice(row.cost_price, row.selling_price) ?? '';
            }
        });
    },
);

/**
 * A typed cost keeps the selling price where it is, so the margin is what moves.
 * @param {object} row
 * @param {number} index
 * @param {string|number} typedCost
 */
function changeCost(row, index, typedCost) {
    row.cost_is_manual = index > 0;
    row.margin_percent = marginFromPrice(typedCost, row.selling_price) ?? '';
}

/**
 * A typed selling price decides the margin.
 * @param {object} row
 * @param {string|number} typedPrice
 */
function changeSellingPrice(row, typedPrice) {
    row.margin_percent = marginFromPrice(row.cost_price, typedPrice) ?? '';
}

/**
 * A typed margin decides the selling price (margin of the price, never markup on the cost). The margin box keeps what
 * was typed, so the number does not jump under the cursor.
 * @param {object} row
 * @param {string|number} typedMargin
 */
function changeMargin(row, typedMargin) {
    const price = priceFromMargin(row.cost_price, typedMargin);

    if (price !== null) {
        row.selling_price = price;
    }
}

const isMarginOutOfRange = (row) => row.margin_percent !== '' && Number(row.margin_percent) >= 100;

/** The unit being worked out in the conversion calculator. */
const calculator = ref({ isOpen: false, rowIndex: 0 });

function openCalculator(rowIndex) {
    calculator.value = { isOpen: true, rowIndex };
}

/** @param {number} conversion */
function applyCalculatedConversion(conversion) {
    form.units[calculator.value.rowIndex].conversion_factor = conversion;
}

const defaultRowIndex = computed(() => form.units.findIndex((row) => row.is_default_sale));

function makeDefault(index) {
    form.units.forEach((row, rowIndex) => {
        row.is_default_sale = rowIndex === index;
    });
}

/**
 * The cashier default must be a sellable unit, so it moves to the first other sellable unit when needed.
 */
function ensureDefaultIsSellable() {
    const current = form.units[defaultRowIndex.value];

    if (current?.is_sellable) {
        return;
    }

    const replacementIndex = form.units.findIndex((row) => row.is_sellable);

    if (replacementIndex !== -1) {
        makeDefault(replacementIndex);
    }
}

function addUnit() {
    form.units.push(emptyUnitRow());
}

function removeUnit(index) {
    form.units.splice(index, 1);
    ensureDefaultIsSellable();
}

/**
 * @param {number} index
 * @returns {string|undefined} first validation message for any field of the row
 */
function rowError(index) {
    return Object.entries(form.errors).find(([key]) => key.startsWith(`units.${index}.`))?.[1];
}

function confirmDelete() {
    deleteWithConfirmation(props.product.delete_url, {
        title: 'Hapus barang?',
        message: `${props.product.name} akan dihapus permanen.`,
    });
}

function send() {
    form.transform((submittedForm) => ({
        ...submittedForm,
        units: submittedForm.units.map(({ cost_is_manual, margin_percent, ...row }) => row),
    }))[props.form_action.method](props.form_action.url);
}

/**
 * Selling under cost is allowed, but only after the person confirms it.
 */
function submit() {
    const belowCostUnitCodes = form.units.filter(isBelowCost).map((row) => row.unit?.code ?? 'satuan');

    if (belowCostUnitCodes.length === 0) {
        send();

        return;
    }

    $q.dialog({
        title: 'Harga jual di bawah modal',
        message: `Harga jual ${belowCostUnitCodes.join(', ')} lebih rendah dari modalnya. Tetap simpan?`,
        cancel: { label: 'Periksa lagi', flat: true, noCaps: true },
        ok: { label: 'Tetap simpan', color: 'primary', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(send);
}
</script>

<template>
    <StoreLayout :title="isEditing ? 'Edit Barang' : 'Tambah Barang'">
        <template #actions>
            <q-btn v-if="isEditing && !isLocked" flat no-caps color="negative" icon="delete_outline" label="Hapus" class="q-mr-sm" @click="confirmDelete" />
            <q-btn flat no-caps label="Batal" class="q-mr-sm" @click="router.visit(page.props.store.products_url)" />
            <q-btn color="primary" unelevated no-caps label="Simpan" :loading="form.processing" @click="submit" />
        </template>

        <q-form class="product-form" @submit.prevent="submit">
            <section class="card">
                <h2>Info barang</h2>

                <div class="grid grid--info">
                    <q-input v-model="form.sku" label="SKU" outlined :error="!!form.errors.sku" :error-message="form.errors.sku" autofocus />
                    <q-input v-model="form.name" label="Nama" outlined :error="!!form.errors.name" :error-message="form.errors.name" />
                </div>

                <div class="grid grid--four">
                    <PickOrCreateSelect
                        v-model="form.category"
                        :options="categories"
                        label="Kategori"
                        record-name="kategori"
                        hint="Ketik untuk menambah baru"
                        :error="form.errors.category || form.errors['category.name']"
                    />
                    <PickOrCreateSelect v-model="form.brand" :options="brands" label="Merek (opsional)" record-name="merek" clearable :error="form.errors['brand.name']" />
                    <q-input
                        v-model.number="form.min_stock"
                        type="number"
                        step="any"
                        min="0"
                        label="Stok minimum"
                        :suffix="baseUnitCode"
                        outlined
                        :error="!!form.errors.min_stock"
                        :error-message="form.errors.min_stock"
                    />
                    <div class="toggle">
                        <span>Aktif</span>
                        <q-toggle v-model="form.is_active" color="positive" />
                    </div>
                </div>
            </section>

            <section class="card">
                <h2>Satuan &amp; harga</h2>

                <p v-if="isLocked" class="note">Satuan dan konversi terkunci karena barang ini sudah punya stok atau transaksi. Satuan baru tetap bisa ditambah.</p>

                <div class="grid grid--base">
                    <UnitSelect
                        v-model="form.units[0].unit"
                        :units="units"
                        label="Satuan dasar"
                        :disable="isRowLocked(form.units[0])"
                        :error="form.errors['units.0.unit'] || form.errors['units.0.unit.code']"
                        @create="openUnitDialog(0)"
                    />
                    <p class="hint">
                        Modal adalah harga beli per satuan, jadi tiap satuan bisa berbeda. Satuan lain otomatis mengikuti modal satuan dasar × konversi sampai
                        modalnya Anda isi sendiri. Margin dan harga jual saling menghitung: isi salah satunya, yang lain mengikuti. Mengubah modal menjaga harga
                        jual dan menggeser margin.
                    </p>
                </div>

                <div class="table-wrap">
                    <table class="units">
                        <thead>
                            <tr>
                                <th>Satuan</th>
                                <th>Konversi</th>
                                <th>Barcode</th>
                                <th>Modal</th>
                                <th>Margin (%)</th>
                                <th>Harga jual</th>
                                <th class="units__center">Dijual</th>
                                <th class="units__center">Default</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(row, index) in form.units" :key="index">
                                <tr>
                                    <td class="units__unit">
                                        <template v-if="index === 0">
                                            <b>{{ row.unit?.code ?? '—' }}</b>
                                            <span class="tag">dasar</span>
                                        </template>
                                        <template v-else>
                                            <UnitSelect
                                                v-model="row.unit"
                                                :units="units"
                                                label="Satuan"
                                                dense
                                                :disable="isRowLocked(row)"
                                                @create="openUnitDialog(index)"
                                            />
                                        </template>
                                    </td>
                                    <td class="units__narrow">
                                        <span v-if="index === 0">1</span>
                                        <q-input
                                            v-else
                                            v-model.number="row.conversion_factor"
                                            type="number"
                                            step="any"
                                            min="0"
                                            dense
                                            outlined
                                            :disable="isRowLocked(row)"
                                        >
                                            <template #append>
                                                <q-btn
                                                    flat
                                                    round
                                                    dense
                                                    size="sm"
                                                    icon="calculate"
                                                    :disable="isRowLocked(row) || !row.unit || !form.units[0].unit"
                                                    :aria-label="`Hitung konversi ${row.unit?.code ?? ''}`"
                                                    @click="openCalculator(index)"
                                                >
                                                    <q-tooltip>Hitung konversi</q-tooltip>
                                                </q-btn>
                                            </template>
                                        </q-input>
                                    </td>
                                    <td class="units__wide">
                                        <q-input v-model="row.barcode" dense outlined placeholder="Opsional" />
                                    </td>
                                    <td class="units__price">
                                        <q-input
                                            v-model.number="row.cost_price"
                                            type="number"
                                            step="any"
                                            min="0"
                                            dense
                                            outlined
                                            hide-bottom-space
                                            :error="!!form.errors['units.' + index + '.cost_price']"
                                            @update:model-value="changeCost(row, index, $event)"
                                        />
                                    </td>
                                    <td class="units__margin">
                                        <q-input
                                            v-model.number="row.margin_percent"
                                            type="number"
                                            step="any"
                                            dense
                                            outlined
                                            suffix="%"
                                            hide-bottom-space
                                            :error="isMarginOutOfRange(row)"
                                            :aria-label="`Margin ${row.unit?.code ?? ''}`"
                                            @update:model-value="changeMargin(row, $event)"
                                        >
                                            <q-tooltip v-if="isMarginOutOfRange(row)">Margin harus kurang dari 100%</q-tooltip>
                                        </q-input>
                                    </td>
                                    <td class="units__price">
                                        <q-input
                                            v-model.number="row.selling_price"
                                            type="number"
                                            step="any"
                                            min="0"
                                            dense
                                            outlined
                                            :class="{ 'below-cost': isBelowCost(row) }"
                                            @update:model-value="changeSellingPrice(row, $event)"
                                        />
                                    </td>
                                    <td class="units__center">
                                        <q-checkbox v-model="row.is_sellable" dense @update:model-value="ensureDefaultIsSellable" />
                                    </td>
                                    <td class="units__center">
                                        <q-radio :model-value="defaultRowIndex" :val="index" dense :disable="!row.is_sellable" @update:model-value="makeDefault(index)" />
                                    </td>
                                    <td class="units__center">
                                        <q-btn
                                            v-if="index > 0 && !isRowLocked(row)"
                                            flat
                                            round
                                            dense
                                            icon="delete_outline"
                                            color="negative"
                                            :aria-label="`Hapus satuan ${row.unit?.code ?? ''}`"
                                            @click="removeUnit(index)"
                                        />
                                    </td>
                                </tr>
                                <tr v-if="rowError(index)">
                                    <td colspan="9" class="units__error">{{ rowError(index) }}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <p v-if="form.errors.units" class="units__error">{{ form.errors.units }}</p>

                <div class="units__footer">
                    <q-btn outline no-caps dense icon="add" label="Tambah satuan" color="primary" class="q-px-sm" @click="addUnit" />
                    <span class="tip">Harga di bawah modal meminta konfirmasi</span>
                </div>
            </section>
        </q-form>

        <UnitFormDialog v-model="unitDialog.isOpen" :create-url="page.props.store.units_url" @saved="selectCreatedUnit" />
        <ConversionCalculatorDialog
            v-if="form.units[calculator.rowIndex]?.unit && form.units[0].unit"
            v-model="calculator.isOpen"
            :unit-code="form.units[calculator.rowIndex].unit.code"
            :base-unit-code="form.units[0].unit.code"
            @apply="applyCalculatedConversion"
        />
    </StoreLayout>
</template>

<style scoped>
.product-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 1000px;
}

.card {
    padding: 20px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.card h2 {
    margin: 0 0 14px;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: normal;
    line-height: 1.4;
}

.grid {
    display: grid;
    gap: 14px;
    margin-bottom: 14px;
}

.grid--info {
    grid-template-columns: 1fr 3fr;
}

.grid--four {
    grid-template-columns: repeat(4, 1fr);
}

.grid--base {
    grid-template-columns: 1fr 1fr;
}

.toggle {
    display: flex;
    flex-direction: column;
    gap: 2px;
    color: var(--app-ink-soft);
    font-size: 12.5px;
}

.hint {
    margin: 0;
    align-self: center;
    color: var(--app-mute);
    font-size: 12.5px;
}

.note {
    margin: 0 0 14px;
    padding: 8px 12px;
    border-radius: 10px;
    background: #fdf1e0;
    color: #c2710c;
    font-size: 13px;
}

.table-wrap {
    overflow-x: auto;
}

.units {
    width: 100%;
    min-width: 940px;
    border-collapse: collapse;
}

.units th {
    padding: 8px 8px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
}

.units td {
    padding: 8px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.units__center {
    text-align: center !important;
}

.units__unit {
    min-width: 160px;
}

.units__narrow {
    width: 110px;
}

.units__wide {
    min-width: 170px;
}

.units__price {
    width: 150px;
}

.units__margin {
    width: 120px;
}

.tag {
    margin-left: 6px;
    padding: 1px 8px;
    border-radius: 99px;
    background: var(--app-brand-soft);
    color: var(--app-brand);
    font-size: 11px;
    font-weight: 700;
}

.units__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 12px;
}

.tip {
    color: var(--app-mute);
    font-size: 12px;
}

.units__error {
    padding: 4px 8px;
    color: var(--q-negative);
    font-size: 12px;
}

.below-cost :deep(.q-field__control) {
    background: #fdf1e0;
}

@media (max-width: 900px) {
    .grid--info,
    .grid--four,
    .grid--base {
        grid-template-columns: 1fr;
    }
}
</style>
