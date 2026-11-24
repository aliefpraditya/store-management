<script setup>
import { computed, ref, watch } from 'vue';
import { Deferred, router, usePage } from '@inertiajs/vue3';
import DateInput from '../../../Components/DateInput.vue';
import SalesReportTabs from '../../../Components/SalesReportTabs.vue';
import { formatDeduction, formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

/**
 * `return_details`, `returns` and `voids` are deferred props, each in a group of its own: the page shows at once and each
 * part fills in when the server has it, so they are undefined until then and only read inside a `<Deferred>` that waits
 * for them.
 */
const props = defineProps({
    period: { type: Object, required: true },
    return_details: { type: Object, default: undefined },
    returns: { type: Object, default: undefined },
    voids: { type: Object, default: undefined },
    /** The page sizes the two lists offer. */
    per_page_options: { type: Array, required: true },
    /** Whether the period asked for was longer than the report allows and was cut back from its start. */
    is_period_cut_back: { type: Boolean, required: true },
    max_days: { type: Number, required: true },
});

const page = usePage();

const from = ref(props.period.from);
const to = ref(props.period.to);

const shortDayFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
const asLocalDate = (isoDay) => new Date(`${isoDay}T00:00:00`);

const hasReturnInspections = computed(() => (props.return_details?.outcomes ?? []).some((outcome) => outcome.products_count > 0));

/**
 * What the lists are showing, as the address holds it, so a list that is turned does not undo the other and the page
 * comes back as it was when it is reloaded. Only what has arrived is there.
 */
function tableQuery() {
    return {
        returns_page: props.returns?.current_page,
        returns_per_page: props.returns?.per_page,
        returns_sort: props.returns?.sort,
        returns_direction: props.returns?.direction,
        voids_page: props.voids?.current_page,
        voids_per_page: props.voids?.per_page,
        voids_sort: props.voids?.sort,
        voids_direction: props.voids?.direction,
    };
}

/**
 * Asks for the period typed. Skipped when it is the period on screen already, which is also what the server answers when it
 * swapped the dates or cut the period back and the fields were set to that answer. The lists keep their sizes and start
 * from their first page.
 */
function applyPeriod() {
    if (from.value && to.value && (from.value !== props.period.from || to.value !== props.period.to)) {
        router.get(page.url.split('?')[0], { from: from.value, to: to.value, ...tableQuery(), returns_page: undefined, voids_page: undefined }, { preserveState: true, replace: true, preserveScroll: true });
    }
}

watch([from, to], applyPeriod);

watch(
    () => [props.period.from, props.period.to],
    ([periodFrom, periodTo]) => {
        from.value = periodFrom;
        to.value = periodTo;
    },
);

/**
 * Turning a list (its page, size or order) asks the server for that list only, so the rest of the page is not worked out
 * again.
 * @param {'returns'|'voids'} list
 * @param {Record<string, number|string>} change What of the list's query changes.
 */
function reloadList(list, change) {
    router.get(page.url.split('?')[0], { from: props.period.from, to: props.period.to, ...tableQuery(), ...change }, { only: [list], preserveState: true, replace: true, preserveScroll: true });
}

const showReturnsPage = (returnsPage) => reloadList('returns', { returns_page: returnsPage });
const changeReturnsPerPage = (perPage) => reloadList('returns', { returns_page: 1, returns_per_page: perPage });
const showVoidsPage = (voidsPage) => reloadList('voids', { voids_page: voidsPage });
const changeVoidsPerPage = (perPage) => reloadList('voids', { voids_page: 1, voids_per_page: perPage });

/** `isText` columns run A to Z the first time they are chosen; the rest (dates, amounts) start with the newest or biggest. */
const returnColumns = [
    { key: 'date', label: 'Tanggal', isText: false, isFigure: false },
    { key: 'number', label: 'Retur', isText: true, isFigure: false },
    { key: 'receipt', label: 'Nota asal', isText: true, isFigure: false },
    { key: 'resolution', label: 'Penyelesaian', isText: true, isFigure: false },
    { key: 'items', label: 'Barang', isText: true, isFigure: false },
    { key: 'reason', label: 'Alasan', isText: true, isFigure: false },
    { key: 'amount', label: 'Nilai', isText: false, isFigure: true },
];

const voidColumns = [
    { key: 'number', label: 'Nota', isText: true, isFigure: false },
    { key: 'receipt_date', label: 'Tanggal nota', isText: false, isFigure: false },
    { key: 'void_date', label: 'Tanggal void', isText: false, isFigure: false },
    { key: 'reason', label: 'Alasan', isText: true, isFigure: false },
    { key: 'total', label: 'Total', isText: false, isFigure: true },
];

/**
 * Ordering by the column a list is already in turns its direction. The order is the server's, so it holds across pages,
 * and the list starts from its first page.
 * @param {'returns'|'voids'} list
 * @param {{ key: string, isText: boolean }} column
 */
function sortBy(list, column) {
    const current = props[list];
    const isSameColumn = current?.sort === column.key;
    const direction = isSameColumn ? (current.direction === 'desc' ? 'asc' : 'desc') : column.isText ? 'asc' : 'desc';

    reloadList(list, { [`${list}_page`]: 1, [`${list}_sort`]: column.key, [`${list}_direction`]: direction });
}

const sortIcon = (list, column) => (props[list]?.sort === column ? (props[list].direction === 'desc' ? 'arrow_downward' : 'arrow_upward') : 'unfold_more');
const sortState = (list, column) => (props[list]?.sort === column ? (props[list].direction === 'desc' ? 'descending' : 'ascending') : 'none');
</script>

<template>
    <StoreLayout title="Laporan Penjualan">
        <SalesReportTabs current="returns" />

        <div class="period">
            <DateInput v-model="from" label="Dari" outlined dense :error="!from" error-message="Wajib diisi" class="period__date" />
            <DateInput v-model="to" label="Sampai" outlined dense :error="!to" error-message="Wajib diisi" class="period__date" />
        </div>
        <p v-if="is_period_cut_back" class="notice">Periode terlalu panjang, jadi laporan hanya memuat {{ max_days }} hari terakhir sampai tanggal akhir. Pilih periode yang lebih pendek untuk melihat yang lebih awal.</p>

        <div class="columns">
            <section class="card">
                <h2>Nasib barang retur <small>(nilai modal)</small></h2>
                <Deferred data="return_details">
                    <template #fallback>
                        <q-skeleton height="150px" />
                    </template>

                    <dl class="rows">
                        <div><dt>Diretur pada periode<small> · {{ return_details.returned.products_count }} barang</small></dt><dd>{{ formatRupiah(return_details.returned.cost_total) }}</dd></div>
                    </dl>

                    <h3 class="subhead">Hasil pemeriksaan gudang retur</h3>
                    <p v-if="!hasReturnInspections" class="empty">Belum ada pemeriksaan retur yang diposting pada periode ini.</p>
                    <dl v-else class="rows">
                        <div v-for="outcome in return_details.outcomes" :key="outcome.outcome">
                            <dt>{{ outcome.label }}<small> · {{ outcome.products_count }} barang</small></dt>
                            <dd>{{ formatRupiah(outcome.cost_total) }}<small class="table__muted"> · {{ outcome.share_percent }}%</small></dd>
                        </div>
                    </dl>

                    <dl class="rows rows--spaced">
                        <div><dt>Menunggu diperiksa sekarang<small> · {{ return_details.waiting.products_count }} barang</small></dt><dd>{{ formatRupiah(return_details.waiting.cost_total) }}</dd></div>
                    </dl>
                    <p class="note">Hasil pemeriksaan adalah yang diposting pada periode ini, jadi bisa berasal dari retur sebelum periode. Yang menunggu adalah isi gudang retur saat ini, berapa pun periodenya.</p>
                </Deferred>
            </section>

            <section class="card">
                <h2>Barang paling sering diretur <small>(5 teratas)</small></h2>
                <Deferred data="return_details">
                    <template #fallback>
                        <q-skeleton height="150px" />
                    </template>

                    <p v-if="return_details.products.length === 0" class="empty">Tidak ada retur pada periode ini.</p>
                    <div v-else class="table-scroll">
                        <table class="table">
                            <thead>
                                <tr><th>Barang</th><th class="table__right">Retur</th><th class="table__right">Jumlah</th><th class="table__right">Dari terjual</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="product in return_details.products" :key="product.product_id">
                                    <td>{{ product.name }}</td>
                                    <td class="table__right">{{ product.returns_count }}×</td>
                                    <td class="table__right">{{ formatQuantity(product.quantity) }} {{ product.unit }}</td>
                                    <td class="table__right">{{ product.rate_percent === null ? '-' : `${product.rate_percent}%` }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Deferred>
            </section>
        </div>

        <section class="card">
            <div class="card__head">
                <h2>Retur disetujui</h2>
                <span v-if="returns" class="card__sum">{{ returns.total }} retur · {{ formatRupiah(returns.amount_total) }}</span>
            </div>
            <Deferred data="returns">
                <template #fallback>
                    <q-skeleton height="200px" />
                </template>

                <template #default="{ reloading }">
                    <p v-if="returns.total === 0" class="empty">Tidak ada retur disetujui pada periode ini.</p>
                    <div v-else :class="{ 'is-reloading': reloading }">
                        <div class="table-scroll">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th v-for="column in returnColumns" :key="column.key" :class="{ table__right: column.isFigure }" :aria-sort="sortState('returns', column.key)">
                                            <button type="button" class="table__sort" @click="sortBy('returns', column)">{{ column.label }}<q-icon :name="sortIcon('returns', column.key)" size="14px" /></button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="saleReturn in returns.data" :key="saleReturn.number">
                                        <td class="table__date">{{ shortDayFormat.format(asLocalDate(saleReturn.date)) }}</td>
                                        <td><b>{{ saleReturn.number }}</b></td>
                                        <td>{{ saleReturn.receipt_number }}</td>
                                        <td>{{ saleReturn.resolution }}<small v-if="saleReturn.refund_method" class="table__muted">{{ saleReturn.refund_method }}</small></td>
                                        <td>
                                            <span v-for="(item, index) in saleReturn.items" :key="index" class="table__line">{{ item.name }} <span class="table__muted">{{ formatQuantity(item.quantity) }} {{ item.unit }}</span></span>
                                        </td>
                                        <td class="table__reason">{{ saleReturn.reason ?? '-' }}</td>
                                        <td class="table__right"><b>{{ formatRupiah(saleReturn.credit_amount) }}</b></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="pager">
                            <span class="pager__range">{{ returns.from }}–{{ returns.to }} dari {{ returns.total }} retur</span>
                            <div class="pager__controls">
                                <q-select
                                    :model-value="returns.per_page"
                                    :options="per_page_options"
                                    outlined
                                    dense
                                    behavior="menu"
                                    :option-label="(option) => `${option} per halaman`"
                                    class="pager__per-page"
                                    aria-label="Jumlah retur per halaman"
                                    @update:model-value="changeReturnsPerPage"
                                />
                                <q-pagination
                                    v-if="returns.last_page > 1"
                                    :model-value="returns.current_page"
                                    :max="returns.last_page"
                                    :max-pages="7"
                                    direction-links
                                    boundary-links
                                    color="primary"
                                    @update:model-value="showReturnsPage"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </Deferred>
            <p class="note">Retur dihitung pada hari buku shift tempat retur dibuat, dan nilainya sudah mengurangi penjualan di laporan. Alasan diketik bebas oleh petugas, jadi bacalah sebagai catatan, bukan penggolongan.</p>
        </section>

        <section class="card">
            <div class="card__head">
                <h2>Nota void</h2>
                <span v-if="voids" class="card__sum">{{ voids.total }} nota · {{ formatRupiah(voids.amount_total) }}</span>
            </div>
            <Deferred data="voids">
                <template #fallback>
                    <q-skeleton height="200px" />
                </template>

                <template #default="{ reloading }">
                    <p v-if="voids.total === 0" class="empty">Tidak ada nota void pada periode ini.</p>
                    <div v-else :class="{ 'is-reloading': reloading }">
                        <div class="table-scroll">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th v-for="column in voidColumns" :key="column.key" :class="{ table__right: column.isFigure }" :aria-sort="sortState('voids', column.key)">
                                            <button type="button" class="table__sort" @click="sortBy('voids', column)">{{ column.label }}<q-icon :name="sortIcon('voids', column.key)" size="14px" /></button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="voided in voids.data" :key="voided.number">
                                        <td><b>{{ voided.number }}</b></td>
                                        <td>{{ shortDayFormat.format(asLocalDate(voided.business_date)) }}</td>
                                        <td>{{ shortDayFormat.format(asLocalDate(voided.void_date)) }}</td>
                                        <td class="table__reason">{{ voided.reason ?? '-' }}</td>
                                        <td class="table__right"><b>{{ formatRupiah(voided.total) }}</b></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="pager">
                            <span class="pager__range">{{ voids.from }}–{{ voids.to }} dari {{ voids.total }} nota</span>
                            <div class="pager__controls">
                                <q-select
                                    :model-value="voids.per_page"
                                    :options="per_page_options"
                                    outlined
                                    dense
                                    behavior="menu"
                                    :option-label="(option) => `${option} per halaman`"
                                    class="pager__per-page"
                                    aria-label="Jumlah nota void per halaman"
                                    @update:model-value="changeVoidsPerPage"
                                />
                                <q-pagination
                                    v-if="voids.last_page > 1"
                                    :model-value="voids.current_page"
                                    :max="voids.last_page"
                                    :max-pages="7"
                                    direction-links
                                    boundary-links
                                    color="primary"
                                    @update:model-value="showVoidsPage"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </Deferred>
            <p class="note">Nota void dihitung pada hari buku saat nota di-void, bukan saat nota dibuat, dan tidak termasuk di penjualan. Nota yang dijual satu hari lalu di-void hari lain muncul di sini pada hari void-nya.</p>
        </section>
    </StoreLayout>
</template>

<style scoped>
.period {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}

.period__date {
    width: 170px;
}

.notice {
    margin: 0 0 14px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #fff8e6;
    font-size: 13px;
}

.columns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 16px;
    align-items: start;
}

.card {
    margin-bottom: 16px;
    padding: 16px 18px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.card h2 {
    margin: 0 0 12px;
    font-size: 15px;
}

.card h2 small {
    color: var(--app-mute);
    font-weight: 400;
}

.card__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 16px;
    margin-bottom: 12px;
}

.card__head h2 {
    margin: 0;
}

.card__sum {
    color: var(--app-ink-soft);
    font-size: 13px;
    font-weight: 600;
}

.is-reloading {
    opacity: 0.5;
    transition: opacity 0.15s;
}

.table-scroll {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    padding: 8px 6px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.table td {
    padding: 8px 6px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: top;
}

.table td b,
.table td small {
    display: block;
}

.table .table__right {
    text-align: right;
    white-space: nowrap;
}

.table .table__muted,
.table__muted {
    color: var(--app-mute);
    font-size: 12px;
}

.table .table__line {
    display: block;
}

.table .table__date {
    white-space: nowrap;
}

.table__sort {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 0;
    border: 0;
    background: none;
    color: inherit;
    cursor: pointer;
    font: inherit;
    white-space: nowrap;
}

.table__sort:hover {
    color: var(--app-ink);
}

.table .table__reason {
    min-width: 160px;
}

.rows {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin: 0;
}

.rows div {
    display: flex;
    justify-content: space-between;
    color: var(--app-ink-soft);
}

.rows dd {
    margin: 0;
    color: var(--app-ink);
    font-weight: 600;
}

.rows small {
    color: var(--app-mute);
}

.rows--spaced {
    margin-top: 14px;
}

.subhead {
    margin: 14px 0 8px;
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
}

.pager {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 14px;
}

.pager__controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

.pager__per-page {
    min-width: 150px;
}

.pager__range {
    color: var(--app-mute);
    font-size: 12px;
}

.empty {
    margin: 0;
    color: var(--app-mute);
}

.note {
    margin: 12px 0 0;
    color: var(--app-mute);
    font-size: 12px;
}
</style>
