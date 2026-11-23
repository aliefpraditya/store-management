<script setup>
import { computed, ref, watch } from 'vue';
import { Deferred, router, usePage } from '@inertiajs/vue3';
import { Notify } from 'quasar';
import DateInput from '../../../Components/DateInput.vue';
import { printSheet } from '../../../Composables/printSheet';
import { formatDeduction, formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

/**
 * `summary`, `comparison`, `payments`, `products` and `groups` are deferred props: the page shows at once and each
 * part fills in when the server has it, so they are undefined until then and only read inside a `<Deferred>` that
 * waits for them.
 */
const props = defineProps({
    period: { type: Object, required: true },
    summary: { type: Object, default: undefined },
    comparison: { type: Object, default: undefined },
    payments: { type: Object, default: undefined },
    products: { type: Object, default: undefined },
    groups: { type: Object, default: undefined },
    return_details: { type: Object, default: undefined },
    /** Whether the period asked for was longer than the report allows and was cut back from its start. */
    is_period_cut_back: { type: Boolean, required: true },
    max_days: { type: Number, required: true },
    /** The methods a sale can be paid by, `{value, label}`, for narrowing the detailed report. */
    payment_method_options: { type: Array, required: true },
    /** The page sizes the products table offers. */
    per_page_options: { type: Array, required: true },
    /** The page sizes the table per category or brand offers. */
    group_per_page_options: { type: Array, required: true },
    urls: { type: Object, required: true },
});

const groupingOptions = [
    { label: 'Kategori', value: 'category' },
    { label: 'Merek', value: 'brand' },
];

const page = usePage();

const from = ref(props.period.from);
const to = ref(props.period.to);

const dayFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
const asLocalDate = (isoDay) => new Date(`${isoDay}T00:00:00`);

/** A day number is written under a bar for at most this many bars, so a long period does not pile them up. */
const MAX_BAR_LABELS = 31;

/**
 * The bars of the daily chart: each day's net sales and gross profit against the biggest of either, so the two stand
 * on one scale and the tallest bar fills the chart. A day that lost money has no bar below the line, only the least.
 */
const bars = computed(() => {
    const days = props.summary?.days ?? [];
    const largest = Math.max(...days.flatMap((day) => [Number(day.net_total), Number(day.gross_profit)]), 1);
    const heightOf = (amount) => Math.max((Math.max(Number(amount), 0) / largest) * 100, 2);

    const labelEvery = Math.ceil(days.length / MAX_BAR_LABELS);

    return days.map((day, index) => ({ ...day, salesHeight: heightOf(day.net_total), profitHeight: heightOf(day.gross_profit), isLabelled: index % labelEvery === 0 }));
});

/**
 * How a figure moved against the period before, from the percent the server worked out: a rise or a fall with its
 * size, or nothing to say when there was nothing before to move from.
 * @param {string|null|undefined} percent
 * @returns {{text: string, tone: 'up'|'down'|'flat'|'none'}}
 */
function describeChange(percent) {
    if (percent === null || percent === undefined) {
        return { text: 'tidak ada pembanding', tone: 'none' };
    }

    const change = Number(percent);

    if (change === 0) {
        return { text: 'sama', tone: 'flat' };
    }

    return { text: `${change > 0 ? '▲' : '▼'} ${Math.abs(change).toFixed(1)}%`, tone: change > 0 ? 'up' : 'down' };
}

const hasReturnInspections = computed(() => (props.return_details?.outcomes ?? []).some((outcome) => outcome.products_count > 0));

const comparisonRange = computed(() => (props.comparison ? `${dayFormat.format(asLocalDate(props.comparison.from))} – ${dayFormat.format(asLocalDate(props.comparison.to))}` : ''));

/**
 * What the tables are showing, as the address holds it, so a table that is turned does not undo the others and the
 * page comes back as it was when it is reloaded. Only what has arrived is there.
 */
function tableQuery() {
    return {
        products_page: props.products?.current_page,
        products_per_page: props.products?.per_page,
        groups_by: props.groups?.grouping,
        groups_page: props.groups?.current_page,
        groups_per_page: props.groups?.per_page,
        groups_sort: props.groups?.sort,
        groups_direction: props.groups?.direction,
    };
}

/**
 * Asks for the period typed. Skipped when it is the period on screen already, which is also what the server answers when it swapped the dates or cut the period back and the fields were set to that answer. The tables keep their sizes, grouping and order, and start from their first page.
 */
function applyPeriod() {
    if (from.value && to.value && (from.value !== props.period.from || to.value !== props.period.to)) {
        router.get(page.url.split('?')[0], { from: from.value, to: to.value, ...tableQuery(), products_page: undefined, groups_page: undefined }, { preserveState: true, replace: true, preserveScroll: true });
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
 * Turning a table (its page, size, grouping or order) asks the server for that table only, so the rest of the report is not worked out again.
 * @param {'products'|'groups'} table
 * @param {Record<string, string|number>} change What of the table's query changes.
 */
function reloadTable(table, change) {
    router.get(page.url.split('?')[0], { from: props.period.from, to: props.period.to, ...tableQuery(), ...change }, { only: [table], preserveState: true, replace: true, preserveScroll: true });
}

const showProductsPage = (productsPage) => reloadTable('products', { products_page: productsPage });
const changeProductsPerPage = (perPage) => reloadTable('products', { products_page: 1, products_per_page: perPage });

const showGroupsPage = (groupsPage) => reloadTable('groups', { groups_page: groupsPage });
const changeGroupsPerPage = (perPage) => reloadTable('groups', { groups_page: 1, groups_per_page: perPage });
const changeGrouping = (grouping) => reloadTable('groups', { groups_page: 1, groups_by: grouping });

/** Ordering by the column the table is already in turns its direction; a new column starts with names A to Z and figures biggest first. */
function sortGroupsBy(column) {
    const isSameColumn = props.groups?.sort === column;
    const direction = isSameColumn ? (props.groups.direction === 'desc' ? 'asc' : 'desc') : column === 'name' ? 'asc' : 'desc';

    reloadTable('groups', { groups_page: 1, groups_sort: column, groups_direction: direction });
}

const sortIcon = (column) => (props.groups?.sort === column ? (props.groups.direction === 'desc' ? 'arrow_downward' : 'arrow_upward') : 'unfold_more');
const sortState = (column) => (props.groups?.sort === column ? (props.groups.direction === 'desc' ? 'descending' : 'ascending') : 'none');

/** The report prints from a hidden frame, for the period on screen. */
const printReport = () => printSheet(() => props.urls.sheet);

/** The payment method the detailed report is narrowed to; empty for every receipt. */
const detailMethod = ref('');

const detailMethodChoices = computed(() => [{ label: 'Semua metode bayar', value: '' }, ...props.payment_method_options.map((option) => ({ label: option.label, value: option.value }))]);

/** The address of a detailed report for the period on screen, narrowed to the method chosen. */
function detailUrl(baseUrl) {
    const url = new URL(baseUrl, window.location.origin);

    if (detailMethod.value !== '') {
        url.searchParams.set('method', detailMethod.value);
    }

    return url.toString();
}

const printDetailReport = () => printSheet(() => detailUrl(props.urls.detail_sheet));

/**
 * The file name the server gave: the UTF-8 one when the name has characters outside ASCII (the other is then a degraded
 * copy), else the plain one.
 * @param {string} contentDisposition
 * @returns {string}
 */
function fileNameOf(contentDisposition) {
    const encodedName = /filename\*=UTF-8''([^;]+)/i.exec(contentDisposition)?.[1];

    return encodedName ? decodeURIComponent(encodedName) : (/filename="?([^";]+)"?/.exec(contentDisposition)?.[1] ?? 'Laporan Penjualan.xlsx');
}

/** Whether the Excel file is being made, for the button to show it is busy. */
const isDownloadingExcel = ref(false);

/**
 * Fetches the Excel file and saves it. The server works the whole report out before it sends the first byte, which can take
 * a while for a long period, and a plain link would show nothing meanwhile; fetching it here lets the page say it is working.
 * The size is not known ahead, so the indicator does not count up.
 */
async function downloadDetailExcel() {
    if (isDownloadingExcel.value) {
        return;
    }

    isDownloadingExcel.value = true;
    const dismissNotice = Notify.create({ spinner: true, position: 'top', timeout: 0, message: 'Menyiapkan file Excel, mohon tunggu...' });

    try {
        const response = await fetch(detailUrl(props.urls.detail_export), { credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const fileName = fileNameOf(response.headers.get('Content-Disposition') ?? '');
        const fileUrl = URL.createObjectURL(await response.blob());
        const link = Object.assign(document.createElement('a'), { href: fileUrl, download: fileName });

        link.click();
        URL.revokeObjectURL(fileUrl);
        Notify.create({ type: 'positive', position: 'top', message: 'File Excel selesai diunduh.' });
    } catch (error) {
        Notify.create({ type: 'negative', position: 'top', message: `File Excel gagal dibuat (${error.message}). Coba lagi.` });
    } finally {
        dismissNotice();
        isDownloadingExcel.value = false;
    }
}
</script>

<template>
    <StoreLayout title="Laporan Penjualan">
        <template #actions>
            <q-btn outline no-caps icon="print" label="Cetak" @click="printReport" />
            <q-btn-dropdown outline no-caps icon="receipt_long" label="Laporan rinci (per nota)" class="q-ml-sm" :loading="isDownloadingExcel">
                <div class="detail-menu">
                    <q-select v-model="detailMethod" :options="detailMethodChoices" emit-value map-options outlined dense label="Metode bayar" />
                </div>
                <q-list>
                    <q-item v-close-popup clickable @click="printDetailReport">
                        <q-item-section avatar><q-icon name="picture_as_pdf" /></q-item-section>
                        <q-item-section>Cetak / simpan PDF</q-item-section>
                    </q-item>
                    <q-item v-close-popup clickable @click="downloadDetailExcel">
                        <q-item-section avatar><q-icon name="table_view" /></q-item-section>
                        <q-item-section>Unduh Excel</q-item-section>
                    </q-item>
                </q-list>
            </q-btn-dropdown>
        </template>

        <div class="period">
            <DateInput v-model="from" label="Dari" outlined dense :error="!from" error-message="Wajib diisi" class="period__date" />
            <DateInput v-model="to" label="Sampai" outlined dense :error="!to" error-message="Wajib diisi" class="period__date" />
        </div>
        <p v-if="is_period_cut_back" class="notice">Periode terlalu panjang, jadi laporan hanya memuat {{ max_days }} hari terakhir sampai tanggal akhir. Pilih periode yang lebih pendek untuk melihat yang lebih awal.</p>

        <section class="card">
            <div class="card__head">
                <h2>Penjualan dan laba per hari</h2>
                <div class="legend">
                    <span><i class="legend__swatch legend__swatch--sales"></i>Penjualan bersih</span>
                    <span><i class="legend__swatch legend__swatch--profit"></i>Laba kotor</span>
                </div>
            </div>
            <Deferred data="summary">
                <template #fallback>
                    <q-skeleton height="150px" />
                </template>

                <div class="bars">
                    <div v-for="day in bars" :key="day.date" class="bars__day" :title="`${dayFormat.format(asLocalDate(day.date))}: penjualan ${formatRupiah(day.net_total)}, laba ${formatRupiah(day.gross_profit)} (${day.receipt_count} nota)`">
                        <div class="bars__track">
                            <i class="bars__sales" :style="{ height: `${day.salesHeight}%` }"></i>
                            <i class="bars__profit" :style="{ height: `${day.profitHeight}%` }"></i>
                        </div>
                        <span>{{ day.isLabelled ? asLocalDate(day.date).getDate() : '' }}</span>
                    </div>
                </div>
            </Deferred>
        </section>

        <Deferred data="summary">
            <template #fallback>
                <div class="kpis">
                    <q-skeleton v-for="placeholder in 3" :key="placeholder" height="86px" />
                </div>
            </template>

            <div class="kpis">
                <div class="kpi">
                    <small>Penjualan bersih</small>
                    <b>{{ formatRupiah(summary.totals.net_total) }}</b>
                    <Deferred data="comparison">
                        <template #fallback><q-skeleton type="text" width="60%" /></template>
                        <em class="kpi__change" :class="`kpi__change--${describeChange(comparison.change_percent.net_total).tone}`">{{ describeChange(comparison.change_percent.net_total).text }} dibanding {{ comparisonRange }}</em>
                    </Deferred>
                    <em>{{ formatRupiah(summary.totals.gross_total) }} − retur {{ formatRupiah(summary.totals.returns_total) }}</em>
                </div>
                <div class="kpi">
                    <small>Nota</small>
                    <b>{{ summary.totals.receipt_count }}</b>
                    <Deferred data="comparison">
                        <template #fallback><q-skeleton type="text" width="60%" /></template>
                        <em class="kpi__change" :class="`kpi__change--${describeChange(comparison.change_percent.receipt_count).tone}`">{{ describeChange(comparison.change_percent.receipt_count).text }} dibanding {{ comparisonRange }}</em>
                    </Deferred>
                    <em>nota biasa {{ formatRupiah(summary.receipts.median_total) }} <abbr title="Nilai tengah: separuh nota lebih kecil dan separuhnya lebih besar, jadi satu nota besar tidak menariknya">(median)</abbr> · rata-rata {{ formatRupiah(summary.receipts.average_total) }}</em>
                    <em>{{ Number(summary.receipts.median_lines) }} jenis barang per nota (median)</em>
                    <em v-if="summary.totals.voids_count > 0">tidak termasuk {{ summary.totals.voids_count }} nota void ({{ formatRupiah(summary.totals.voids_total) }}) yang di-void pada periode ini</em>
                    <em v-else>tidak termasuk nota void</em>
                </div>
                <div class="kpi">
                    <small>Laba kotor (estimasi)</small>
                    <b>{{ formatRupiah(summary.totals.gross_profit) }}</b>
                    <Deferred data="comparison">
                        <template #fallback><q-skeleton type="text" width="60%" /></template>
                        <em class="kpi__change" :class="`kpi__change--${describeChange(comparison.change_percent.gross_profit).tone}`">{{ describeChange(comparison.change_percent.gross_profit).text }} dibanding {{ comparisonRange }}</em>
                    </Deferred>
                    <em>margin {{ summary.totals.margin_percent }}% · modal = harga beli terakhir</em>
                    <em v-if="Number(summary.totals.dead_stock_loss) > 0">sudah dikurangi rugi dead stock {{ formatRupiah(summary.totals.dead_stock_loss) }}</em>
                    <em v-if="summary.products_without_cost > 0" class="kpi__warn">{{ summary.products_without_cost }} barang belum punya harga beli, jadi labanya sama dengan penjualannya</em>
                </div>
            </div>
        </Deferred>

        <section class="card">
            <h2>Laba kotor per barang <small>(setelah retur)</small></h2>
            <Deferred data="products">
                <template #fallback>
                    <q-skeleton height="200px" />
                </template>

                <template #default="{ reloading }">
                    <p v-if="products.total === 0" class="empty">Belum ada penjualan pada periode ini.</p>
                    <div v-else :class="{ 'is-reloading': reloading }">
                        <table class="table">
                            <thead>
                                <tr><th>Barang</th><th class="table__right">Terjual</th><th class="table__right">Penjualan</th><th class="table__right">Modal</th><th class="table__right">Laba</th><th class="table__right">Margin</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="product in products.data" :key="product.product_id">
                                    <td>{{ product.name }}</td>
                                    <td class="table__right">{{ formatQuantity(product.quantity) }} {{ product.unit }}</td>
                                    <td class="table__right">{{ formatRupiah(product.revenue) }}</td>
                                    <td class="table__right">{{ formatRupiah(product.cost) }}</td>
                                    <td class="table__right"><b>{{ formatRupiah(product.profit) }}</b></td>
                                    <td class="table__right">{{ product.margin_percent }}%</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="pager">
                            <span class="pager__range">{{ products.from }}–{{ products.to }} dari {{ products.total }} barang</span>
                            <div class="pager__controls">
                                <q-select
                                    :model-value="products.per_page"
                                    :options="per_page_options"
                                    outlined
                                    dense
                                    behavior="menu"
                                    :option-label="(option) => `${option} per halaman`"
                                    class="pager__per-page"
                                    aria-label="Jumlah barang per halaman"
                                    @update:model-value="changeProductsPerPage"
                                />
                                <q-pagination
                                    v-if="products.last_page > 1"
                                    :model-value="products.current_page"
                                    :max="products.last_page"
                                    :max-pages="5"
                                    direction-links
                                    boundary-links
                                    color="primary"
                                    @update:model-value="showProductsPage"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </Deferred>
        </section>

        <div class="columns">
            <section class="card">
                <h2>Per metode bayar <small>(penjualan, sebelum retur)</small></h2>
                <Deferred :data="['payments', 'summary']">
                    <template #fallback>
                        <q-skeleton height="180px" />
                    </template>

                    <dl class="rows">
                        <div v-for="method in payments.methods" :key="method.method"><dt>{{ method.label }}</dt><dd>{{ formatRupiah(method.amount) }}</dd></div>
                        <div class="rows__sum"><dt>Penjualan</dt><dd>{{ formatRupiah(summary.totals.gross_total) }}</dd></div>
                        <div><dt>Retur disetujui</dt><dd>{{ formatDeduction(summary.totals.returns_total) }}</dd></div>
                        <div class="rows__sum"><dt>Penjualan bersih</dt><dd>{{ formatRupiah(summary.totals.net_total) }}</dd></div>
                    </dl>
                    <p class="note">COD ditagih saat diantar, jadi belum ada uang yang diterima.</p> 

                </Deferred>
            </section>

            <section class="card">
                <div class="card__head">
                    <h2>Penjualan per {{ groups?.grouping === 'brand' ? 'merek' : 'kategori' }} <small>(setelah retur)</small></h2>
                    <q-btn-toggle
                        :model-value="groups?.grouping ?? 'category'"
                        :options="groupingOptions"
                        no-caps
                        unelevated
                        dense
                        toggle-color="primary"
                        color="grey-3"
                        text-color="grey-8"
                        @update:model-value="changeGrouping"
                    />
                </div>
                <Deferred data="groups">
                    <template #fallback>
                        <q-skeleton height="200px" />
                    </template>

                    <template #default="{ reloading }">
                        <p v-if="groups.total === 0" class="empty">Belum ada penjualan pada periode ini.</p>
                        <div v-else :class="{ 'is-reloading': reloading }">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th :aria-sort="sortState('name')">
                                            <button type="button" class="table__sort" @click="sortGroupsBy('name')">{{ groups.grouping === 'brand' ? 'Merek' : 'Kategori' }}<q-icon :name="sortIcon('name')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('revenue')">
                                            <button type="button" class="table__sort" @click="sortGroupsBy('revenue')">Penjualan<q-icon :name="sortIcon('revenue')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('profit')">
                                            <button type="button" class="table__sort" @click="sortGroupsBy('profit')">Laba<q-icon :name="sortIcon('profit')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('margin')">
                                            <button type="button" class="table__sort" @click="sortGroupsBy('margin')">Margin<q-icon :name="sortIcon('margin')" size="14px" /></button>
                                        </th>
                                        <th class="table__right">Porsi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="group in groups.data" :key="group.id ?? 'none'">
                                        <td>{{ group.name }}<small class="table__muted"> · {{ group.product_count }} barang</small></td>
                                        <td class="table__right">{{ formatRupiah(group.revenue) }}</td>
                                        <td class="table__right"><b>{{ formatRupiah(group.profit) }}</b></td>
                                        <td class="table__right">{{ group.margin_percent }}%</td>
                                        <td class="table__right">{{ group.share_percent }}%</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="pager">
                                <span class="pager__range">{{ groups.from }}–{{ groups.to }} dari {{ groups.total }} {{ groups.grouping === 'brand' ? 'merek' : 'kategori' }}</span>
                                <div class="pager__controls">
                                    <q-select
                                        :model-value="groups.per_page"
                                        :options="group_per_page_options"
                                        outlined
                                        dense
                                        behavior="menu"
                                        :option-label="(option) => `${option} per halaman`"
                                        class="pager__per-page"
                                        aria-label="Jumlah baris per halaman"
                                        @update:model-value="changeGroupsPerPage"
                                    />
                                    <q-pagination
                                        v-if="groups.last_page > 1"
                                        :model-value="groups.current_page"
                                        :max="groups.last_page"
                                        :max-pages="5"
                                        direction-links
                                        boundary-links
                                        color="primary"
                                        @update:model-value="showGroupsPage"
                                    />
                                </div>
                            </div>
                        </div>
                    </template>
                </Deferred>
            </section>
        </div>

        <div class="columns columns--even">
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
                    <table v-else class="table">
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
                </Deferred>
            </section>
        </div>

        <section class="card">
            <h2>Per hari</h2>
            <Deferred data="summary">
                <template #fallback>
                    <q-skeleton height="200px" />
                </template>

                <table class="table">
                    <thead><tr><th>Tanggal</th><th class="table__right">Nota</th><th class="table__right">Penjualan</th><th class="table__right">Retur</th><th class="table__right">Bersih</th><th v-if="summary.totals.voids_count > 0" class="table__right">Void</th></tr></thead>
                    <tbody>
                        <tr v-for="day in summary.days" :key="day.date">
                            <td>{{ dayFormat.format(asLocalDate(day.date)) }}</td>
                            <td class="table__right">{{ day.receipt_count }}</td>
                            <td class="table__right">{{ formatRupiah(day.gross_total) }}</td>
                            <td class="table__right table__minus">{{ Number(day.returns_total) > 0 ? formatDeduction(day.returns_total) : '-' }}</td>
                            <td class="table__right"><b>{{ formatRupiah(day.net_total) }}</b></td>
                            <td v-if="summary.totals.voids_count > 0" class="table__right">{{ day.voids_count > 0 ? `${day.voids_count} · ${formatRupiah(day.voids_total)}` : '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </Deferred>
        </section>
    </StoreLayout>
</template>

<style scoped>
.detail-menu {
    min-width: 220px;
    padding: 12px 12px 4px;
}

.period {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}

.period__date {
    width: 170px;
}

.kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.kpi {
    padding: 14px 16px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.kpi small,
.kpi b,
.kpi em {
    display: block;
}

.kpi small {
    color: var(--app-mute);
    font-size: 12px;
}

.kpi b {
    font-size: 22px;
}

.kpi em {
    color: var(--app-mute);
    font-size: 12px;
    font-style: normal;
}

.kpi .kpi__change {
    font-weight: 600;
}

.kpi .kpi__change--up {
    color: var(--q-positive);
}

.kpi .kpi__change--down {
    color: var(--q-negative);
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

.legend {
    display: flex;
    gap: 14px;
    color: var(--app-mute);
    font-size: 12px;
}

.legend__swatch {
    display: inline-block;
    width: 10px;
    height: 10px;
    margin-right: 6px;
    border-radius: 2px;
}

.legend__swatch--sales,
.bars__sales {
    background: var(--q-primary);
}

.legend__swatch--profit,
.bars__profit {
    background: var(--q-positive);
}

.columns {
    display: grid;
    grid-template-columns: minmax(280px, 1fr) minmax(0, 2fr);
    gap: 16px;
    align-items: start;
}

.columns.columns--even {
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}

@media (max-width: 960px) {
    .columns {
        grid-template-columns: 1fr;
    }
}

.pager {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 14px;
}

.is-reloading {
    opacity: 0.5;
    transition: opacity 0.15s;
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

.bars {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    height: 150px;
}

.bars__day {
    display: flex;
    flex: 1;
    flex-direction: column;
    align-items: center;
    min-width: 0;
    height: 100%;
    color: var(--app-mute);
    font-size: 11px;
}

/** Holds only the bar, so every bar stands on the same baseline whether or not its day is labelled. */
.bars__track {
    display: flex;
    flex: 1;
    align-items: flex-end;
    justify-content: center;
    width: 100%;
    min-height: 0;
}

.bars__day span {
    flex: none;
    height: 16px;
    line-height: 16px;
    white-space: nowrap;
}

.bars__track {
    gap: 2px;
}

.bars__day i {
    display: block;
    flex: 1;
    min-width: 0;
    max-width: 18px;
    border-radius: 4px 4px 0 0;
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
}

.table__sort:hover {
    color: var(--app-ink);
}

.table__muted {
    color: var(--app-mute);
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
}

.table .table__right {
    text-align: right;
    white-space: nowrap;
}

.table .table__minus {
    color: #be123c;
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

.rows .rows__sum {
    padding-top: 6px;
    border-top: 1px solid var(--app-line);
    font-weight: 700;
}

.empty {
    margin: 0;
    color: var(--app-mute);
}

.notice {
    margin: 0 0 14px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #fff8e6;
    font-size: 13px;
}

.note {
    margin: 8px 0 0;
    color: var(--app-mute);
    font-size: 12px;
}

.kpi .kpi__warn {
    margin-top: 4px;
    color: #b45309;
}
</style>
