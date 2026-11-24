<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Deferred, Link, router, usePage } from '@inertiajs/vue3';
import DateInput from '../../../Components/DateInput.vue';
import SalesReportTabs from '../../../Components/SalesReportTabs.vue';
import { useDetailReportOutput } from '../../../Composables/useDetailReportOutput';
import { formatDeduction, formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

/**
 * `summary` and `receipts` are deferred props, each in a group of its own: the page shows at once and each part fills in
 * when the server has it, so they are undefined until then and only read inside a `<Deferred>` that waits for them.
 */
const props = defineProps({
    period: { type: Object, required: true },
    /** What the table is narrowed to, as the address holds it. */
    filters: { type: Object, required: true },
    summary: { type: Object, default: undefined },
    receipts: { type: Object, default: undefined },
    /** The methods a sale can be paid by, `{value, label}`. */
    payment_method_options: { type: Array, required: true },
    /** The page sizes the table offers. */
    per_page_options: { type: Array, required: true },
    /** Whether the period asked for was longer than the report allows and was cut back from its start. */
    is_period_cut_back: { type: Boolean, required: true },
    max_days: { type: Number, required: true },
    /** Whether the period is longer than the printed detailed sheet can cover; the spreadsheet has no such limit. */
    is_sheet_too_long: { type: Boolean, required: true },
    max_sheet_days: { type: Number, required: true },
    /** The addresses of the detailed sheet and of the spreadsheet, for the period on screen. */
    urls: { type: Object, required: true },
});

const page = usePage();

const from = ref(props.period.from);
const to = ref(props.period.to);
const search = ref(props.filters.search);
const method = ref(props.filters.method);

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

const methodChoices = computed(() => [{ label: 'Semua metode bayar', value: '' }, ...props.payment_method_options]);

const permissions = computed(() => page.props.auth.permissions);

/** The receipt page belongs to the sales history, so the link is only for those who may see it. */
const canOpenReceipt = computed(() => permissions.value.includes('sales.view'));

const { isDownloadingExcel, printDetailReport, downloadDetailExcel } = useDetailReportOutput(() => props.urls, () => method.value);

/** The receipts opened to show their goods and payments. */
const openIds = ref([]);

/** @param {number} id */
function toggleReceipt(id) {
    openIds.value = openIds.value.includes(id) ? openIds.value.filter((openId) => openId !== id) : [...openIds.value, id];
}

/**
 * What the screen is showing, as the address holds it, so the page comes back as it was when it is reloaded. The
 * period is the one on screen, not a date half typed.
 * @param {Record<string, string|number|undefined>} change What of the query changes.
 */
function currentQuery(change = {}) {
    return {
        from: props.period.from,
        to: props.period.to,
        search: search.value || undefined,
        method: method.value || undefined,
        page: props.receipts?.current_page,
        per_page: props.receipts?.per_page,
        ...change,
    };
}

/**
 * Asks the server for the named parts only, so the rest of the page is not worked out again.
 * @param {string[]} only
 * @param {Record<string, string|number|undefined>} change
 */
function reloadParts(only, change) {
    router.get(page.url.split('?')[0], currentQuery(change), { only, preserveState: true, replace: true, preserveScroll: true });
}

/**
 * Asks for the period typed. Skipped when it is the period on screen already, which is also what the server answers when
 * it swapped the dates or cut the period back and the fields were set to that answer. Both the cards and the table change
 * with the period, and the table starts from its first page.
 */
function applyPeriod() {
    if (from.value && to.value && (from.value !== props.period.from || to.value !== props.period.to)) {
        router.get(page.url.split('?')[0], currentQuery({ from: from.value, to: to.value, page: undefined }), { preserveState: true, replace: true, preserveScroll: true });
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

/** Narrowing the table starts it from its first page; the cards follow, and `filters` is asked for so the address and the fields agree. */
const applyFilters = () => reloadParts(['receipts', 'summary', 'filters'], { page: undefined });

let searchTimer;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});

watch(method, applyFilters);

/** A search typed just before leaving the page must not pull the admin back to it. */
onBeforeUnmount(() => clearTimeout(searchTimer));

const showPage = (receiptsPage) => reloadParts(['receipts'], { page: receiptsPage });
const changePerPage = (perPage) => reloadParts(['receipts'], { page: 1, per_page: perPage });

/** @param {{label: string}[]} payments */
const methodsOf = (payments) => [...new Set(payments.map((payment) => payment.label))].join(', ');

const dayFormat = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const asLocalDate = (isoDay) => new Date(`${isoDay}T00:00:00`);

/**
 * The receipts of the page in runs of one business day, in the order the server gave them (newest day first, which the
 * server sorts by so a day is never split into two runs). Each run gets a heading with the day's figures.
 */
const dayGroups = computed(() => {
    const groups = [];

    for (const receipt of props.receipts?.data ?? []) {
        const lastGroup = groups[groups.length - 1];

        if (lastGroup?.date === receipt.business_date) {
            lastGroup.receipts.push(receipt);
        } else {
            groups.push({ date: receipt.business_date, receipts: [receipt] });
        }
    }

    return groups;
});
</script>

<template>
    <StoreLayout title="Laporan Penjualan">
        <template #actions>
            <q-btn outline no-caps icon="picture_as_pdf" label="Cetak / PDF rinci" :disable="is_sheet_too_long" @click="printDetailReport" />
            <q-btn outline no-caps icon="table_view" label="Excel rinci" class="q-ml-sm" :loading="isDownloadingExcel" @click="downloadDetailExcel" />
        </template>

        <SalesReportTabs current="receipts" />

        <div class="period">
            <DateInput v-model="from" label="Dari" outlined dense :error="!from" error-message="Wajib diisi" class="period__date" />
            <DateInput v-model="to" label="Sampai" outlined dense :error="!to" error-message="Wajib diisi" class="period__date" />
        </div>
        <p v-if="is_period_cut_back" class="notice">Periode terlalu panjang, jadi laporan hanya memuat {{ max_days }} hari terakhir sampai tanggal akhir. Pilih periode yang lebih pendek untuk melihat yang lebih awal.</p>
        <p v-if="is_sheet_too_long" class="notice">Periode lebih dari {{ max_sheet_days }} hari terlalu panjang untuk dicetak, jadi tombol Cetak / PDF rinci dimatikan. Unduh Excel untuk periode ini, atau pilih periode paling lama {{ max_sheet_days }} hari.</p>

        <Deferred data="summary">
            <template #fallback>
                <div class="kpis">
                    <q-skeleton v-for="placeholder in 2" :key="placeholder" height="86px" />
                </div>
            </template>

            <div class="kpis">
                <div class="kpi">
                    <small>Nota</small>
                    <b>{{ summary.receipt_count }}</b>
                    <em>nota yang berlaku, void tidak dihitung</em>
                </div>
                <div class="kpi">
                    <small>Penjualan</small>
                    <b>{{ formatRupiah(summary.total) }}</b>
                    <em>jumlah total nota di atas, sebelum retur</em>
                </div>
            </div>
        </Deferred>

        <section class="card">
            <h2>Daftar nota</h2>

            <div class="filters">
                <q-input v-model="search" outlined dense clearable placeholder="Cari nomor nota atau pelanggan" class="filters__search">
                    <template #prepend><q-icon name="search" /></template>
                </q-input>
                <q-select v-model="method" :options="methodChoices" emit-value map-options outlined dense class="filters__select" aria-label="Metode bayar" />
            </div>

            <Deferred data="receipts">
                <template #fallback>
                    <q-skeleton height="300px" />
                </template>

                <template #default="{ reloading }">
                    <div :class="{ 'is-reloading': reloading }">
                        <div class="table-scroll">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th class="table__toggle"></th>
                                        <th>Nota</th>
                                        <th>Pelanggan</th>
                                        <th>Pengambilan</th>
                                        <th>Dibayar dengan</th>
                                        <th class="table__right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="receipts.data.length === 0">
                                        <td colspan="6" class="table__empty">Tidak ada nota yang cocok pada periode ini.</td>
                                    </tr>
                                    <template v-for="(group, groupIndex) in dayGroups" :key="group.date">
                                        <tr class="table__day">
                                            <td colspan="6">
                                                <strong>{{ dayFormat.format(asLocalDate(group.date)) }}</strong>
                                                <span v-if="groupIndex === 0 && receipts.continues_previous_day" class="table__continued">(lanjutan)</span>
                                                <span v-if="summary?.days?.[group.date]" class="table__day-figures">
                                                    {{ summary.days[group.date].receipt_count }} nota · Penjualan {{ formatRupiah(summary.days[group.date].gross_total) }}
                                                    <template v-if="Number(summary.days[group.date].returns_total) > 0"> · Retur {{ formatDeduction(summary.days[group.date].returns_total) }}</template>
                                                    <template v-if="summary.days[group.date].net_total !== null"> · Bersih <strong>{{ formatRupiah(summary.days[group.date].net_total) }}</strong></template>
                                                    <template v-if="summary.days[group.date].voids_count > 0"> · Void {{ summary.days[group.date].voids_count }} nota ({{ formatRupiah(summary.days[group.date].voids_total) }})</template>
                                                </span>
                                            </td>
                                        </tr>
                                        <template v-for="receipt in group.receipts" :key="receipt.id">
                                            <tr class="table__row" :class="{ 'table__row--void': receipt.status === 'voided' }" @click="toggleReceipt(receipt.id)">
                                                <td class="table__toggle">
                                                    <q-btn flat round dense size="sm" :icon="openIds.includes(receipt.id) ? 'expand_less' : 'expand_more'" :aria-expanded="openIds.includes(receipt.id)" :aria-label="`Rincian nota ${receipt.number}`" @click.stop="toggleReceipt(receipt.id)" />
                                                </td>
                                                <td>
                                                    <b>{{ receipt.number }}</b>
                                                    <small class="table__muted">{{ dateTimeFormat.format(new Date(receipt.sold_at)) }}</small>
                                                </td>
                                                <td>{{ receipt.customer_name ?? '-' }}</td>
                                                <td>{{ receipt.fulfillment_label }}</td>
                                                <td>{{ methodsOf(receipt.payments) }}</td>
                                                <td class="table__right">
                                                    <b>{{ formatRupiah(receipt.total) }}</b>
                                                    <small v-if="receipt.status === 'voided'" class="table__void-mark">{{ receipt.status_label }}</small>
                                                </td>
                                            </tr>
                                            <tr v-if="openIds.includes(receipt.id)" class="table__detail">
                                                <td></td>
                                                <td colspan="5">
                                                    <table class="lines">
                                                        <tbody>
                                                            <tr v-for="(item, index) in receipt.items" :key="index">
                                                                <td>{{ item.name }}</td>
                                                                <td class="table__right">{{ formatQuantity(item.quantity) }} {{ item.unit }}</td>
                                                                <td class="table__right">{{ formatRupiah(item.unit_price) }}</td>
                                                                <td class="table__right">{{ formatRupiah(item.subtotal) }}</td>
                                                            </tr>
                                                            <tr v-if="Number(receipt.discount_total) > 0">
                                                                <td colspan="3">Diskon</td>
                                                                <td class="table__right">{{ formatDeduction(receipt.discount_total) }}</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                    <p class="paid">
                                                        Dibayar:
                                                        <span v-for="(payment, index) in receipt.payments" :key="index">{{ index > 0 ? ' + ' : ' ' }}{{ payment.label }} {{ formatRupiah(payment.amount) }}<template v-if="payment.reference"> (ref. {{ payment.reference }})</template></span>
                                                        <template v-if="Number(receipt.change_amount) > 0"> · kembali {{ formatRupiah(receipt.change_amount) }}</template>
                                                        <template v-if="receipt.status === 'voided'"> · di-void<template v-if="receipt.void_reason">: {{ receipt.void_reason }}</template></template>
                                                    </p>
                                                    <Link v-if="canOpenReceipt" :href="receipt.show_url" class="paid__link">Buka nota</Link>
                                                </td>
                                            </tr>
                                        </template>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="receipts.total > 0" class="pager">
                            <span class="pager__range">{{ receipts.from }}–{{ receipts.to }} dari {{ receipts.total }} nota</span>
                            <div class="pager__controls">
                                <q-select
                                    :model-value="receipts.per_page"
                                    :options="per_page_options"
                                    outlined
                                    dense
                                    behavior="menu"
                                    :option-label="(option) => `${option} per halaman`"
                                    class="pager__per-page"
                                    aria-label="Jumlah nota per halaman"
                                    @update:model-value="changePerPage"
                                />
                                <q-pagination
                                    v-if="receipts.last_page > 1"
                                    :model-value="receipts.current_page"
                                    :max="receipts.last_page"
                                    :max-pages="7"
                                    direction-links
                                    boundary-links
                                    color="primary"
                                    @update:model-value="showPage"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </Deferred>
            <p class="note">Nota dikelompokkan per hari buku (shift yang lewat tengah malam tetap di hari bukanya). Angka di judul hari adalah angka seluruh hari, walau nota hari itu terbagi di beberapa halaman. Saat pencarian atau metode bayar dipilih, judul hanya memuat jumlah dan total nota yang cocok, karena retur dan void tidak terikat metode bayar. Nota void tampil dan diberi tanda, tetapi tidak dihitung. Tombol Cetak dan Excel di kanan atas memuat semua nota periode ini (pilihan metode bayar ikut, pencarian tidak), lengkap dengan retur dan void.</p>
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

.kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
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

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}

.filters__search {
    flex: 2 1 260px;
}

.filters__select {
    flex: 1 1 200px;
    min-width: 200px;
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
    vertical-align: middle;
}

.table td b,
.table td small {
    display: block;
}

.table .table__toggle {
    width: 36px;
    padding-right: 0;
}

.table .table__right {
    text-align: right;
    white-space: nowrap;
}

.table .table__muted {
    color: var(--app-mute);
    font-size: 12px;
}

.table .table__empty {
    padding: 28px 6px;
    color: var(--app-mute);
    text-align: center;
}

.table .table__day td {
    padding: 10px 6px;
    background: var(--app-bg);
    border-bottom: 1px solid var(--app-line);
    font-size: 13px;
}

.table__continued {
    margin-left: 4px;
    color: var(--app-mute);
}

.table__day-figures {
    margin-left: 10px;
    color: var(--app-ink-soft);
}

.table__row {
    cursor: pointer;
}

.table__row:hover {
    background: var(--app-bg);
}

.table__row--void td {
    color: var(--app-mute);
}

.table .table__void-mark {
    color: var(--q-negative);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
}

.table__detail > td {
    background: var(--app-bg);
}

.lines {
    width: 100%;
    border-collapse: collapse;
}

.lines td {
    padding: 3px 6px;
    border-bottom: 1px solid var(--app-line);
    font-size: 13px;
}

.paid {
    margin: 8px 0 0;
    font-size: 13px;
}

.paid__link {
    display: inline-block;
    margin-top: 6px;
    font-size: 13px;
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

.note {
    margin: 12px 0 0;
    color: var(--app-mute);
    font-size: 12px;
}
</style>
