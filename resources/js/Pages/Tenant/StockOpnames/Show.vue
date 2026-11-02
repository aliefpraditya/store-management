<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import ProductSearchSelect from '../../../Components/ProductSearchSelect.vue';
import { formatQuantity, formatStock } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    opname: { type: Object, required: true },
    summary: { type: Object, required: true },
    items: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const page = usePage();
const $q = useQuasar();

const steps = [
    { status: 'draft', label: 'Draft' },
    { status: 'counting', label: 'Menghitung' },
    { status: 'review', label: 'Review' },
    { status: 'posted', label: 'Diposting' },
];

const currentStepIndex = computed(() => steps.findIndex((step) => step.status === props.opname.status));

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });
const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
const moneyFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

const scopeSummary = computed(() =>
    props.opname.scope === 'category'
        ? [props.opname.category && `Kategori ${props.opname.category}`, props.opname.brand && `Merek ${props.opname.brand}`].filter(Boolean).join(' · ')
        : props.opname.scope_label,
);

const search = ref(props.filters.search);
const filter = ref(props.filters.filter);

const filterOptions = [
    { label: 'Semua barang', value: '' },
    { label: 'Belum dihitung', value: 'uncounted' },
    { label: 'Ada selisih', value: 'variance' },
];

/**
 * What the admin is typing, per item id: one quantity per unit counted (5 DUS and 3 PAIL are two parts) and the reason.
 * @type {import('vue').Ref<Record<number, {parts: {unit_code: string, quantity: string}[], reason: string}>>}
 */
const typedCounts = ref({});

/** The typed counts as they were last loaded from the server, to tell which ones were edited. */
const loadedCounts = ref({});

/**
 * A saved count comes back as one base-unit quantity, so it is shown as a single part in the base unit.
 */
function typedCountOf(item) {
    return {
        parts: [{ unit_code: item.base_unit, quantity: item.counted_quantity === null ? '' : String(Number(item.counted_quantity)) }],
        reason: item.reason ?? '',
    };
}

watch(
    () => props.items.data,
    (items) => {
        typedCounts.value = Object.fromEntries(items.map((item) => [item.id, typedCountOf(item)]));
        loadedCounts.value = Object.fromEntries(items.map((item) => [item.id, JSON.stringify(typedCountOf(item))]));
    },
    { immediate: true },
);

const dirtyItems = computed(() => props.items.data.filter((item) => JSON.stringify(typedCounts.value[item.id]) !== loadedCounts.value[item.id]));

const isDirty = computed(() => dirtyItems.value.length > 0);

const uncountedCount = computed(() => props.summary.items_count - props.summary.counted_count);

/**
 * @returns {number|null} the typed count in the base unit, or null while nothing is typed
 */
function countedBase(item) {
    const filledParts = typedCounts.value[item.id].parts.filter((part) => part.quantity !== '' && part.quantity !== null);

    if (filledParts.length === 0) {
        return null;
    }

    const factors = Object.fromEntries(item.units.map((unit) => [unit.code, Number(unit.conversion_factor)]));
    const total = filledParts.reduce((sum, part) => sum + Number(part.quantity) * (factors[part.unit_code] ?? 0), 0);

    return Math.round(total * 1000) / 1000;
}

/**
 * While counting the variance follows what is typed; afterwards it is the one the server worked out. When the
 * opname counts arrivals, what is typed is the variance itself.
 * @returns {number|null}
 */
function varianceOf(item) {
    if (!props.opname.can_count) {
        return item.variance === null ? null : Number(item.variance);
    }

    const counted = countedBase(item);

    if (counted === null) {
        return null;
    }

    return props.opname.is_counting_arrivals ? counted : Math.round((counted - Number(item.system_quantity)) * 1000) / 1000;
}

/**
 * @returns {string|null} the stock once the typed arrival is added, or null while nothing is typed
 */
function stockAfter(item) {
    const variance = varianceOf(item);

    return variance === null ? null : formatStock(Number(item.system_quantity) + variance, item.units, item.base_unit);
}

function varianceClass(variance) {
    if (variance === 0 || (props.opname.is_counting_arrivals && variance > 0)) {
        return 'chip--ok';
    }

    return variance > 0 ? 'chip--warn' : 'chip--bad';
}

function formatVariance(variance, baseUnit) {
    return `${variance > 0 ? '+' : variance < 0 ? '−' : ''}${formatQuantity(Math.abs(variance))} ${baseUnit}`;
}

function formatMoney(value) {
    const amount = Number(value);

    return `${amount > 0 ? '+' : amount < 0 ? '−' : ''}Rp ${moneyFormat.format(Math.abs(amount))}`;
}

function addPart(item) {
    const row = typedCounts.value[item.id];
    const unusedUnit = item.units.find((unit) => !row.parts.some((part) => part.unit_code === unit.code));

    row.parts.push({ unit_code: (unusedUnit ?? item.units[0]).code, quantity: '' });
}

function removePart(item, partIndex) {
    typedCounts.value[item.id].parts.splice(partIndex, 1);
}

/**
 * @param {() => void} [afterSaved] runs once the counts are stored
 */
function saveCounts(afterSaved) {
    const counts = dirtyItems.value.map((item) => ({
        item_id: item.id,
        parts: typedCounts.value[item.id].parts.map((part) => ({ unit_code: part.unit_code, quantity: part.quantity === '' ? null : part.quantity })),
        reason: typedCounts.value[item.id].reason || null,
    }));

    router.put(
        props.opname.urls.counts,
        { counts },
        {
            preserveScroll: true,
            onSuccess: () => afterSaved?.(),
            onError: (errors) => $q.notify({ type: 'negative', position: 'top', message: Object.values(errors)[0] ?? 'Hitungan gagal disimpan.' }),
        },
    );
}

/**
 * Runs the next step only after unsaved counts are stored, so moving on never throws typed counts away.
 * @param {() => void} runNext
 */
function afterSavingCounts(runNext) {
    if (isDirty.value) {
        saveCounts(runNext);
    } else {
        runNext();
    }
}

function postAction(url, payload = {}) {
    router.post(url, payload, { preserveScroll: true });
}

function confirmDialog({ title, message, okLabel, okColor = 'primary' }, onOk) {
    $q.dialog({
        title,
        message,
        cancel: { label: 'Batal', flat: true, noCaps: true },
        ok: { label: okLabel, color: okColor, unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(onOk);
}

function finishCounting() {
    afterSavingCounts(() => {
        if (uncountedCount.value === 0) {
            postAction(props.opname.urls.finish);

            return;
        }

        confirmDialog(
            {
                title: 'Selesai menghitung?',
                message: `${uncountedCount.value} barang belum dihitung. Stoknya tidak berubah saat diposting.`,
                okLabel: 'Selesai hitung',
            },
            () => postAction(props.opname.urls.finish),
        );
    });
}

function postOpname() {
    confirmDialog(
        {
            title: 'Posting opname?',
            message: props.opname.is_counting_arrivals
                ? `Stok sekitar ${props.summary.variance_count} barang akan bertambah, dengan nilai sekitar ${formatMoney(props.summary.variance_value)}. Angka ini dari tampilan terakhir; penjualan yang masuk sebelum posting ikut menggeser hasilnya. Setelah diposting, opname tidak bisa diubah.`
                : `Stok sekitar ${props.summary.variance_count} barang akan diperbarui, dengan nilai selisih sekitar ${formatMoney(props.summary.variance_value)}. Angka ini dari tampilan terakhir; penjualan yang masuk sebelum posting ikut menggeser selisihnya. Setelah diposting, opname tidak bisa diubah.`,
            okLabel: 'Posting',
            okColor: 'positive',
        },
        () => postAction(props.opname.urls.post),
    );
}

/** A draft is erased for good; a started opname is cancelled and hidden from the list but kept. */
function cancelOpname() {
    confirmDialog(
        props.opname.is_erased_on_cancel
            ? { title: 'Hapus draft opname?', message: 'Draft ini dihapus dan tidak disimpan.', okLabel: 'Hapus draft', okColor: 'negative' }
            : { title: 'Batalkan opname?', message: 'Stok tidak berubah. Opname disembunyikan dari daftar dan hanya bisa dilihat lewat filter status Dibatalkan.', okLabel: 'Batalkan opname', okColor: 'negative' },
        () => postAction(props.opname.urls.cancel),
    );
}

const isAddDialogOpen = ref(false);
const productToAdd = ref(null);

function addProduct(product) {
    if (product === null) {
        return;
    }

    afterSavingCounts(() =>
        router.post(
            props.opname.urls.add_item,
            { product_id: product.id },
            {
                preserveScroll: true,
                onFinish: () => {
                    productToAdd.value = null;
                    isAddDialogOpen.value = false;
                },
            },
        ),
    );
}

function removeProduct(item) {
    afterSavingCounts(() => router.delete(item.remove_url, { preserveScroll: true }));
}

function applyFilters(extra = {}) {
    afterSavingCounts(() =>
        router.get(page.url.split('?')[0], { search: search.value || undefined, filter: filter.value || undefined, ...extra }, { preserveState: true, replace: true, preserveScroll: true }),
    );
}

let searchTimer;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});

watch(filter, () => applyFilters());

/** A search typed just before leaving the page must not pull the admin back to it. */
onBeforeUnmount(() => clearTimeout(searchTimer));
</script>

<template>
    <StoreLayout :title="opname.number">
        <template #actions>
            <q-btn v-if="opname.can_cancel" flat no-caps color="negative" :label="opname.is_erased_on_cancel ? 'Hapus Draft' : 'Batalkan'" class="q-mr-sm" @click="cancelOpname" />
            <q-btn v-if="opname.can_start" color="primary" unelevated no-caps label="Mulai Hitung" @click="postAction(opname.urls.start)" />
            <q-btn v-if="opname.can_count" outline no-caps color="primary" label="Simpan" :disable="!isDirty" class="q-mr-sm" @click="saveCounts()" />
            <q-btn v-if="opname.can_count" color="primary" unelevated no-caps label="Selesai Hitung" @click="finishCounting" />
            <q-btn v-if="opname.can_reopen" flat no-caps label="Hitung Ulang" class="q-mr-sm" @click="postAction(opname.urls.reopen)" />
            <q-btn v-if="opname.can_post" color="positive" unelevated no-caps label="Posting" @click="postOpname" />
        </template>

        <div v-if="opname.status === 'cancelled'" class="steps"><span class="chip chip--bad">Dibatalkan</span></div>
        <ol v-else class="steps">
            <li v-for="(step, index) in steps" :key="step.status" :class="{ 'steps__item--done': index < currentStepIndex, 'steps__item--current': index === currentStepIndex }">
                <q-icon v-if="index < currentStepIndex" name="check_circle" size="18px" />
                <span v-else class="steps__dot" />
                {{ step.label }}
            </li>
        </ol>

        <section class="meta">
            <div><small>Gudang</small><b>{{ opname.warehouse }}</b></div>
            <div><small>Tanggal</small><b>{{ dateFormat.format(new Date(opname.opname_date)) }}</b></div>
            <div><small>Cakupan</small><b>{{ scopeSummary }}</b></div>
            <div><small>Alasan</small><b>{{ opname.reason_label ?? '—' }}</b></div>
            <div v-if="opname.snapshot_at"><small>Snapshot stok</small><b>{{ dateTimeFormat.format(new Date(opname.snapshot_at)) }}</b></div>
            <div v-if="opname.posted_at"><small>Diposting</small><b>{{ dateTimeFormat.format(new Date(opname.posted_at)) }}</b></div>
        </section>

        <section v-if="summary.items_count > 0" class="kpis">
            <div class="kpi"><small>{{ opname.is_counting_arrivals ? 'Diisi' : 'Dihitung' }}</small><b>{{ summary.counted_count }} / {{ summary.items_count }}</b></div>
            <div class="kpi"><small>{{ opname.is_counting_arrivals ? 'Barang bertambah' : 'Barang ada selisih' }}</small><b>{{ summary.variance_count }}</b></div>
            <div class="kpi">
                <small>{{ opname.is_counting_arrivals ? 'Nilai penambahan (estimasi)' : 'Nilai selisih (estimasi)' }}</small>
                <b :class="{ 'kpi--bad': Number(summary.variance_value) < 0 }">{{ formatMoney(summary.variance_value) }}</b>
            </div>
        </section>

        <div class="filters">
            <q-btn v-if="opname.can_manage_products" color="primary" outline no-caps icon="add" label="Tambah Barang" @click="isAddDialogOpen = true" />
            <q-input v-model="search" outlined dense clearable placeholder="Cari di daftar ini (nama atau SKU)" class="filters__search">
                <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select v-model="filter" :options="filterOptions" option-value="value" option-label="label" emit-value map-options outlined dense class="filters__select" />
            <q-btn
                v-if="opname.can_count && !opname.is_counting_arrivals && uncountedCount > 0"
                flat
                no-caps
                color="grey-8"
                label="Tandai yang belum dihitung = 0"
                @click="afterSavingCounts(() => postAction(opname.urls.zero_uncounted))"
            />
        </div>

        <div class="lines">
            <table class="lines__table">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th class="lines__right">Stok sistem</th>
                        <th>{{ opname.is_counting_arrivals ? 'Jumlah datang' : 'Hitung fisik' }}</th>
                        <th class="lines__right">{{ opname.is_counting_arrivals ? 'Bertambah' : 'Selisih' }}</th>
                        <th v-if="opname.is_counting_arrivals" class="lines__right">Stok setelah</th>
                        <th>Alasan</th>
                        <th v-if="opname.can_manage_products"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="items.data.length === 0">
                        <td colspan="7" class="lines__empty">
                            <template v-if="opname.status === 'draft' && opname.scope !== 'selected'">Barang masuk daftar hitung saat opname dimulai.</template>
                            <template v-else-if="opname.can_manage_products && summary.items_count === 0">Tambahkan barang yang akan dihitung.</template>
                            <template v-else>Tidak ada barang yang cocok.</template>
                        </td>
                    </tr>
                    <tr v-for="item in items.data" :key="item.id">
                        <td>
                            <b>{{ item.product.name }}</b>
                            <small>{{ item.product.sku }}</small>
                        </td>
                        <td class="lines__right">{{ formatStock(item.system_quantity, item.units, item.base_unit) }}</td>
                        <td>
                            <div v-if="opname.can_count" class="parts">
                                <div v-for="(part, partIndex) in typedCounts[item.id].parts" :key="partIndex" class="parts__row">
                                    <q-input
                                        v-model="part.quantity"
                                        type="number"
                                        min="0"
                                        step="any"
                                        outlined
                                        dense
                                        hide-bottom-space
                                        :placeholder="opname.is_counting_arrivals ? 'jumlah datang' : 'isi hitungan'"
                                        class="parts__quantity"
                                        :aria-label="`Jumlah ${item.product.name}`"
                                    />
                                    <q-select
                                        v-model="part.unit_code"
                                        :options="item.units.map((unit) => unit.code)"
                                        outlined
                                        dense
                                        hide-bottom-space
                                        class="parts__unit"
                                        :aria-label="`Satuan ${item.product.name}`"
                                    />
                                    <q-btn
                                        v-if="partIndex > 0"
                                        flat
                                        round
                                        dense
                                        size="sm"
                                        icon="close"
                                        color="grey-7"
                                        aria-label="Hapus satuan"
                                        @click="removePart(item, partIndex)"
                                    />
                                    <q-btn
                                        v-else-if="item.units.length > 1"
                                        flat
                                        round
                                        dense
                                        size="sm"
                                        icon="add"
                                        color="grey-7"
                                        aria-label="Tambah satuan lain"
                                        @click="addPart(item)"
                                    />
                                </div>
                            </div>
                            <template v-else>
                                <span v-if="item.counted_quantity !== null">{{ formatStock(item.counted_quantity, item.units, item.base_unit) }}</span>
                                <span v-else class="lines__muted">Belum dihitung</span>
                            </template>
                        </td>
                        <td class="lines__right">
                            <template v-if="varianceOf(item) !== null">
                                <span class="chip" :class="varianceClass(varianceOf(item))">{{ formatVariance(varianceOf(item), item.base_unit) }}</span>
                                <small v-if="!opname.can_count && Number(item.variance_value) !== 0" class="lines__muted">{{ formatMoney(item.variance_value) }}</small>
                            </template>
                            <span v-else class="lines__muted">—</span>
                        </td>
                        <td v-if="opname.is_counting_arrivals" class="lines__right">
                            <b v-if="stockAfter(item) !== null">{{ stockAfter(item) }}</b>
                            <span v-else class="lines__muted">—</span>
                        </td>
                        <td>
                            <q-input
                                v-if="opname.can_count"
                                v-model="typedCounts[item.id].reason"
                                outlined
                                dense
                                hide-bottom-space
                                maxlength="255"
                                placeholder="mis. tumpah"
                                class="lines__reason"
                                :aria-label="`Alasan ${item.product.name}`"
                            />
                            <span v-else>{{ item.reason ?? '' }}</span>
                        </td>
                        <td v-if="opname.can_manage_products" class="lines__right">
                            <q-btn flat round dense icon="delete_outline" color="negative" :aria-label="`Hapus ${item.product.name}`" @click="removeProduct(item)" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <q-dialog v-model="isAddDialogOpen" @hide="productToAdd = null">
            <q-card class="add-dialog">
                <q-card-section>
                    <div class="add-dialog__title">Tambah barang yang dihitung</div>
                    <ProductSearchSelect v-model="productToAdd" :search-url="opname.urls.product_search" label="Cari nama, SKU, atau barcode" autofocus @update:model-value="addProduct" />
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat no-caps label="Tutup" v-close-popup />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <div v-if="items.total > 0" class="pager">
            <span class="lines__muted">{{ items.from }}–{{ items.to }} dari {{ items.total }} barang</span>
            <q-pagination
                v-if="items.last_page > 1"
                :model-value="items.current_page"
                :max="items.last_page"
                :max-pages="7"
                direction-links
                boundary-links
                color="primary"
                @update:model-value="applyFilters({ page: $event })"
            />
        </div>
    </StoreLayout>
</template>

<style scoped>
.steps {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 22px;
    margin: 0 0 14px;
    padding: 0;
    list-style: none;
}

.steps li {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--app-mute);
    font-size: 13px;
    font-weight: 600;
}

.steps__dot {
    width: 10px;
    height: 10px;
    margin: 0 4px;
    border: 2px solid currentColor;
    border-radius: 50%;
}

.steps .steps__item--done {
    color: #059669;
}

.steps .steps__item--current {
    color: var(--app-brand);
}

.steps .steps__item--current .steps__dot {
    background: currentColor;
}

.meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 32px;
    margin-bottom: 14px;
    padding: 14px 18px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.meta small,
.kpi small {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
}

.kpis {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 14px;
}

.kpi {
    padding: 14px 18px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.kpi b {
    font-size: 20px;
}

.kpi .kpi--bad {
    color: #be123c;
}

.add-dialog {
    width: 460px;
    max-width: 92vw;
}

.add-dialog__title {
    margin-bottom: 14px;
    font-size: 15px;
    font-weight: 700;
}

.filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}

.filters__search {
    flex: 2 1 260px;
}

.filters__select {
    flex: 1 1 170px;
}

.lines {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.lines__table {
    width: 100%;
    border-collapse: collapse;
}

.lines__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.lines__table td {
    padding: 10px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.lines__table tr:last-child td {
    border-bottom: 0;
}

.lines__table td b,
.lines__table td small {
    display: block;
}

.lines__table td small,
.lines__muted {
    color: var(--app-mute);
    font-size: 12px;
}

.lines__table .lines__right {
    text-align: right;
    white-space: nowrap;
}

.lines__empty {
    padding: 28px 16px !important;
    color: var(--app-mute);
    text-align: center;
}

.parts {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.parts__row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.parts__quantity {
    width: 130px;
}

.parts__unit {
    width: 104px;
}

.lines__reason {
    min-width: 160px;
}

.pager {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 14px;
}

@media screen and (max-width: 640px) {
    .kpis {
        grid-template-columns: 1fr;
    }
}
</style>
