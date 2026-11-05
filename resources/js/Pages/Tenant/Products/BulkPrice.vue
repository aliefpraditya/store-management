<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import { postJson } from '../../../Composables/postJson';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    /** One page of the items to pick from, with the current modal and selling price of each unit. */
    products: { type: Object, required: true },
    /** Every item the current search and filters match (up to max_products), for "select all". */
    matching_ids: { type: Array, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    categories: { type: Array, required: true },
    brands: { type: Array, required: true },
    units: { type: Array, required: true },
    cost_methods: { type: Array, required: true },
    selling_methods: { type: Array, required: true },
    max_products: { type: Number, required: true },
    urls: { type: Object, required: true },
});

const page = usePage();
const $q = useQuasar();

const priceFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

const search = ref(props.filters.search);
const category = ref(props.filters.category);
const brand = ref(props.filters.brand);
const status = ref(props.filters.status);

const statusOptions = computed(() => [{ label: 'Semua status', value: '' }, ...props.statuses]);

/** What is ticked stays ticked while the list is searched, filtered or paged. */
const selectedIds = ref([]);
const selectedSet = computed(() => new Set(selectedIds.value));

const pageIds = computed(() => props.products.data.map((product) => product.id));
const isPageAllSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every((id) => selectedSet.value.has(id)));
const isPageSomeSelected = computed(() => pageIds.value.some((id) => selectedSet.value.has(id)));

/**
 * Adds items to the selection, unless that would pass the most one run can take.
 * @param {number[]} ids
 */
function select(ids) {
    const merged = new Set([...selectedIds.value, ...ids]);

    if (merged.size > props.max_products) {
        $q.notify({ type: 'warning', message: `Paling banyak ${props.max_products} barang sekali ubah. Persempit pilihan atau ubah per kategori.` });

        return;
    }

    selectedIds.value = [...merged];
}

/** @param {number[]} ids */
function deselect(ids) {
    const removed = new Set(ids);

    selectedIds.value = selectedIds.value.filter((id) => !removed.has(id));
}

function togglePage() {
    if (isPageAllSelected.value) {
        deselect(pageIds.value);
    } else {
        select(pageIds.value);
    }
}

function toggleProduct(productId) {
    if (selectedSet.value.has(productId)) {
        deselect([productId]);
    } else {
        select([productId]);
    }
}

function applyFilters(extraParams = {}) {
    router.get(
        page.url.split('?')[0],
        { search: search.value || undefined, category: category.value || undefined, brand: brand.value || undefined, status: status.value || undefined, ...extraParams },
        { preserveState: true, replace: true, preserveScroll: true, only: ['products', 'matching_ids', 'filters'] },
    );
}

let searchTimer;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});

watch([category, brand, status], () => applyFilters());

onBeforeUnmount(() => clearTimeout(searchTimer));

/** An empty string means 
ot chosen: a select cannot show its first option while its model is null. */
const unitCode = ref('');
const costMethod = ref('');
const costValue = ref('');
const sellingMethod = ref('');
const sellingValue = ref('');

const sellingMethodOption = computed(() => props.selling_methods.find((method) => method.value === sellingMethod.value) ?? null);

const costValueLabel = computed(() => (costMethod.value === 'percent' ? 'Persen (5 = naik 5%, -3 = turun 3%)' : 'Rupiah (negatif = turun)'));
const sellingValueLabel = computed(() => {
    if (sellingMethod.value === 'margin') {
        return 'Margin (dari harga jual)';
    }

    return sellingMethod.value === 'percent' ? 'Persen (5 = naik 5%, -3 = turun 3%)' : 'Rupiah (negatif = turun)';
});

const isFilled = (value) => value !== '' && value !== null && value !== undefined;

const isChangeComplete = computed(
    () => (costMethod.value !== '' || sellingMethod.value !== '')
        && (costMethod.value === '' || isFilled(costValue.value))
        && (sellingMethod.value === '' || !sellingMethodOption.value.takes_value || isFilled(sellingValue.value))
        && !(sellingMethod.value === 'keep_margin' && costMethod.value === ''),
);

const changeHint = computed(() => {
    if (sellingMethod.value === 'keep_margin' && costMethod.value === '') {
        return 'Mempertahankan margin hanya berarti bila harga beli ikut diubah.';
    }

    return null;
});

const canPreview = computed(() => selectedIds.value.length > 0 && isChangeComplete.value);

/** Everything the server needs to work out the same change for the preview and the apply. */
const payload = computed(() => ({
    product_ids: selectedIds.value,
    unit_code: unitCode.value || null,
    cost_method: costMethod.value || null,
    cost_value: costMethod.value === '' ? null : costValue.value,
    selling_method: sellingMethod.value || null,
    selling_value: sellingMethod.value === '' || !sellingMethodOption.value.takes_value ? null : sellingValue.value,
}));

const preview = ref(null);
const isPreviewOpen = ref(false);
const isPreviewing = ref(false);
const isApplying = ref(false);

async function showPreview() {
    isPreviewing.value = true;

    try {
        preview.value = await postJson(props.urls.preview, payload.value);
        isPreviewOpen.value = true;
    } catch (error) {
        $q.notify({ type: 'negative', message: error.message });
    } finally {
        isPreviewing.value = false;
    }
}

function apply() {
    router.post(props.urls.apply, payload.value, {
        preserveScroll: true,
        onStart: () => (isApplying.value = true),
        onFinish: () => (isApplying.value = false),
        onSuccess: () => {
            isPreviewOpen.value = false;
            selectedIds.value = [];
        },
    });
}

/** Units worth showing in the preview: the ones that change or cannot be changed. */
const affectedUnits = (product) => product.units.filter((unit) => unit.is_changed || unit.skip_reason !== null);

const isDifferent = (oldValue, newValue) => oldValue !== newValue;
</script>

<template>
    <StoreLayout title="Ubah Harga Massal">
        <template #back>
            <q-btn flat no-caps dense icon="arrow_back" label="Daftar Barang" class="q-ml-sm" @click="router.visit(urls.products)" />
        </template>

        <section class="card change">
            <h2>Perubahan harga</h2>
            <p class="hint">Pilih barang di bawah, atur perubahannya, lalu lihat pratinjau sebelum diterapkan. Harga dihitung per satuan dan dibulatkan ke sen saja.</p>

            <div class="change__grid">
                <q-select
                    v-model="unitCode"
                    :options="[{ label: 'Semua satuan', value: '' }, ...units.map((code) => ({ label: `Hanya satuan ${code}`, value: code }))]"
                    emit-value
                    map-options
                    outlined
                    dense
                    label="Satuan yang diubah"
                />
                <div class="change__pair">
                    <q-select v-model="costMethod" :options="[{ label: 'Harga beli tidak diubah', value: '' }, ...cost_methods]" emit-value map-options outlined dense label="Harga beli (modal)" />
                    <q-input v-if="costMethod" v-model="costValue" type="number" step="any" outlined dense :label="costValueLabel" :prefix="costMethod === 'nominal' ? 'Rp' : undefined" :suffix="costMethod === 'percent' ? '%' : undefined" />
                </div>
                <div class="change__pair">
                    <q-select v-model="sellingMethod" :options="[{ label: 'Harga jual tidak diubah', value: '' }, ...selling_methods]" emit-value map-options outlined dense label="Harga jual" />
                    <q-input
                        v-if="sellingMethodOption?.takes_value"
                        v-model="sellingValue"
                        type="number"
                        step="any"
                        outlined
                        dense
                        :label="sellingValueLabel"
                        :prefix="sellingMethod === 'nominal' ? 'Rp' : undefined"
                        :suffix="sellingMethod === 'percent' || sellingMethod === 'margin' ? '%' : undefined"
                    />
                </div>
            </div>
            <p v-if="changeHint" class="hint hint--bad">{{ changeHint }}</p>
            <p v-else-if="sellingMethod === 'margin'" class="hint">Harga jual = modal ÷ (1 − margin). Bila modal ikut diubah, dipakai modal yang baru.</p>
            <p v-else-if="sellingMethod === 'keep_margin'" class="hint">Margin tiap satuan sekarang dijaga: harga jual naik atau turun mengikuti modal yang baru.</p>
        </section>

        <div class="filters">
            <q-input v-model="search" outlined dense clearable placeholder="Cari nama, SKU, atau barcode" class="filters__search">
                <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select v-model="category" :options="[{ id: null, name: 'Semua kategori' }, ...categories]" option-value="id" option-label="name" emit-value map-options outlined dense class="filters__select" />
            <q-select v-model="brand" :options="[{ id: null, name: 'Semua merek' }, ...brands]" option-value="id" option-label="name" emit-value map-options outlined dense class="filters__select" />
            <q-select v-model="status" :options="statusOptions" option-value="value" option-label="label" emit-value map-options outlined dense class="filters__select" />
        </div>

        <div class="selection">
            <span>
                <b>{{ selectedIds.length }}</b> barang dipilih
                <small v-if="products.total > matching_ids.length" class="hint">(Pilih semua dibatasi {{ max_products }} barang)</small>
            </span>
            <span class="selection__actions">
                <q-btn flat dense no-caps color="primary" :label="`Pilih semua ${matching_ids.length} hasil`" :disable="matching_ids.length === 0" @click="select(matching_ids)" />
                <q-btn flat dense no-caps color="grey-7" label="Kosongkan pilihan" :disable="selectedIds.length === 0" @click="selectedIds = []" />
            </span>
        </div>

        <div class="items">
            <table class="items__table">
                <thead>
                    <tr>
                        <th class="items__check">
                            <q-checkbox :model-value="isPageAllSelected ? true : isPageSomeSelected ? null : false" dense aria-label="Pilih semua barang di halaman ini" @update:model-value="togglePage" />
                        </th>
                        <th>Barang</th>
                        <th>Satuan, modal, dan harga jual sekarang</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="products.data.length === 0">
                        <td colspan="3" class="items__empty">Belum ada barang yang cocok.</td>
                    </tr>
                    <tr v-for="product in products.data" :key="product.id" :class="{ 'items__row--on': selectedSet.has(product.id) }" @click="toggleProduct(product.id)">
                        <td class="items__check">
                            <q-checkbox :model-value="selectedSet.has(product.id)" dense :aria-label="`Pilih ${product.name}`" @click.stop @update:model-value="toggleProduct(product.id)" />
                        </td>
                        <td>
                            <b>{{ product.name }}</b>
                            <small>{{ [product.sku, product.category, product.brand].filter(Boolean).join(' · ') }}</small>
                            <span v-if="!product.is_active" class="chip chip--off">Nonaktif</span>
                        </td>
                        <td>
                            <span v-for="unit in product.units" :key="unit.code" class="unit-line">
                                <b>{{ unit.code }}</b> modal {{ priceFormat.format(unit.cost_price) }}<template v-if="unit.is_sellable"> · jual {{ priceFormat.format(unit.selling_price) }}</template>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="products.total > 0" class="pager">
            <span class="hint">{{ products.from }}–{{ products.to }} dari {{ products.total }} barang</span>
            <q-pagination
                v-if="products.last_page > 1"
                :model-value="products.current_page"
                :max="products.last_page"
                :max-pages="7"
                direction-links
                boundary-links
                color="primary"
                @update:model-value="applyFilters({ page: $event })"
            />
        </div>

        <div class="bar">
            <span>{{ selectedIds.length }} barang dipilih</span>
            <q-btn color="primary" unelevated no-caps icon="visibility" label="Lihat pratinjau" :loading="isPreviewing" :disable="!canPreview" @click="showPreview" />
        </div>

        <q-dialog v-model="isPreviewOpen" persistent>
            <q-card v-if="preview" class="preview">
                <q-card-section>
                    <div class="preview__title">Pratinjau perubahan</div>
                    <p class="preview__summary">
                        <b>{{ preview.totals.products_changed }}</b> barang berubah ({{ preview.totals.units_changed }} satuan) dari {{ preview.totals.products_selected }} yang dipilih.
                        <template v-if="preview.totals.units_skipped > 0"><span class="preview__skipped">{{ preview.totals.units_skipped }} satuan dilewati.</span></template>
                    </p>
                </q-card-section>

                <q-card-section class="preview__list">
                    <p v-if="preview.products.length === 0" class="hint">Tidak ada harga yang berubah dengan pengaturan ini.</p>
                    <table v-else class="preview__table">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Satuan</th>
                                <th class="preview__right">Modal</th>
                                <th class="preview__right">Harga jual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="product in preview.products" :key="product.id">
                                <tr v-for="(unit, unitIndex) in affectedUnits(product)" :key="unit.unit_id">
                                    <td>
                                        <template v-if="unitIndex === 0"><b>{{ product.name }}</b><small>{{ product.sku }}</small></template>
                                    </td>
                                    <td>{{ unit.code }}</td>
                                    <template v-if="unit.skip_reason">
                                        <td colspan="2" class="preview__skipped">Dilewati: {{ unit.skip_reason }}</td>
                                    </template>
                                    <template v-else>
                                        <td class="preview__right">
                                            <template v-if="isDifferent(unit.old_cost, unit.new_cost)">{{ priceFormat.format(unit.old_cost) }} → <b>{{ priceFormat.format(unit.new_cost) }}</b></template>
                                            <template v-else>{{ priceFormat.format(unit.old_cost) }}</template>
                                        </td>
                                        <td class="preview__right">
                                            <template v-if="isDifferent(unit.old_price, unit.new_price)">{{ priceFormat.format(unit.old_price) }} → <b>{{ priceFormat.format(unit.new_price) }}</b></template>
                                            <template v-else>{{ priceFormat.format(unit.old_price) }}</template>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p v-if="preview.is_truncated" class="hint">Hanya sebagian barang yang ditampilkan. Semuanya tetap ikut berubah.</p>
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat no-caps label="Kembali" :disable="isApplying" @click="isPreviewOpen = false" />
                    <q-btn color="primary" unelevated no-caps :label="`Terapkan ke ${preview.totals.products_changed} barang`" :loading="isApplying" :disable="preview.totals.units_changed === 0" @click="apply" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </StoreLayout>
</template>

<style scoped>
.card {
    padding: 16px 18px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.card h2 {
    margin: 0 0 4px;
    font-size: 16px;
}

.change {
    margin-bottom: 14px;
}

.change__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 12px;
    margin-top: 12px;
}

.change__pair {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.hint {
    margin: 0;
    color: var(--app-mute);
    font-size: 12.5px;
}

.hint--bad {
    color: #be123c;
}

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 10px;
}

.filters__search {
    flex: 2 1 260px;
}

.filters__select {
    flex: 1 1 170px;
}

.selection {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
}

.items {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.items__table {
    width: 100%;
    border-collapse: collapse;
}

.items__table th {
    padding: 10px 14px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.items__table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.items__table tbody tr {
    cursor: pointer;
}

.items__table tr:last-child td {
    border-bottom: 0;
}

.items__row--on {
    background: var(--app-brand-soft);
}

.items__check {
    width: 44px;
}

.items__table td b,
.items__table td small {
    display: block;
}

.items__table td small {
    color: var(--app-mute);
    font-size: 12px;
}

.unit-line {
    display: block;
    font-size: 13px;
}

.unit-line b {
    display: inline;
}

.items__empty {
    padding: 28px 16px !important;
    color: var(--app-mute);
    text-align: center;
}

.pager {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 12px;
}

.bar {
    position: sticky;
    bottom: 0;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 14px;
    padding: 12px 16px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.preview {
    width: 680px;
    max-width: 96vw;
}

.preview__title {
    font-size: 17px;
    font-weight: 700;
}

.preview__summary {
    margin: 6px 0 0;
}

.preview__skipped {
    color: #be123c;
}

.preview__list {
    max-height: 52vh;
    overflow-y: auto;
}

.preview__table {
    width: 100%;
    border-collapse: collapse;
}

.preview__table th,
.preview__table td {
    padding: 6px 8px;
    border-bottom: 1px solid var(--app-line);
    text-align: left;
    vertical-align: top;
}

.preview__table th {
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
}

.preview__table small {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
}

.preview__table .preview__right {
    text-align: right;
    white-space: nowrap;
}
</style>
