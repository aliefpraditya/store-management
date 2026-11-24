<script setup>
import { computed, ref, watch } from 'vue';
import { Deferred, router, usePage } from '@inertiajs/vue3';
import ColumnBars from '../../../Components/ColumnBars.vue';
import DateInput from '../../../Components/DateInput.vue';
import HourHeatmap from '../../../Components/HourHeatmap.vue';
import SalesReportTabs from '../../../Components/SalesReportTabs.vue';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

/**
 * `busy` is a deferred prop: the page shows at once and the charts fill in when the server has them, so it is undefined
 * until then and only read inside a `<Deferred>` that waits for it.
 */
const props = defineProps({
    period: { type: Object, required: true },
    busy: { type: Object, default: undefined },
    /** Whether the period asked for was longer than the report allows and was cut back from its start. */
    is_period_cut_back: { type: Boolean, required: true },
    max_days: { type: Number, required: true },
});

const page = usePage();

const from = ref(props.period.from);
const to = ref(props.period.to);

/** What the charts measure: what the receipts came to, which is where the page starts, or how many there are. */
const metric = ref('sales');

const metricOptions = [
    { label: 'Penjualan', value: 'sales' },
    { label: 'Jumlah nota', value: 'receipts' },
];

const WEEKDAYS = [
    { number: 1, full: 'Senin', short: 'Sen' },
    { number: 2, full: 'Selasa', short: 'Sel' },
    { number: 3, full: 'Rabu', short: 'Rab' },
    { number: 4, full: 'Kamis', short: 'Kam' },
    { number: 5, full: 'Jumat', short: 'Jum' },
    { number: 6, full: 'Sabtu', short: 'Sab' },
    { number: 7, full: 'Minggu', short: 'Min' },
];

const weekdayRows = WEEKDAYS.map((weekday) => ({ key: weekday.number, label: weekday.full }));

const pad = (hour) => String(hour).padStart(2, '0');
const hourSpan = (hour) => `${pad(hour)}.00–${pad(hour)}.59`;
const formatNumber = (value) => Number(value).toLocaleString('id-ID', { maximumFractionDigits: 1 });

const perDayKey = computed(() => (metric.value === 'sales' ? 'sales_per_day' : 'receipts_per_day'));

/** Writes an average per trading day as the chosen measure. */
const formatAverage = (value) => (metric.value === 'sales' ? formatRupiah(value) : `${formatNumber(value)} nota`);

const valueOf = (figures) => Number(figures[perDayKey.value]);

/**
 * What the dialog on hover says of a bucket: both averages whichever the chart shows, and the totals and days behind them.
 * @param {string} title
 * @param {{receipts: number, sales: string, days: number, receipts_per_day: string, sales_per_day: string}} figures
 * @param {string} weekdayName The weekday the figures are of, or empty when they are of every day, as the day rows name them.
 */
const detailsOf = (title, figures, weekdayName) => {
    const days = weekdayName === '' ? 'Hari' : `Hari ${weekdayName}`;

    return {
        title,
        rows: [
            { label: 'Nota per hari', value: `${formatNumber(figures.receipts_per_day)} nota` },
            { label: 'Penjualan per hari', value: formatRupiah(figures.sales_per_day) },
            { label: 'Total nota', value: `${figures.receipts} nota` },
            { label: 'Total penjualan', value: formatRupiah(figures.sales) },
            { label: `${days} ada nota`, value: `${figures.days} hari` },
            { label: `${days} tanpa nota`, value: `${figures.days_without_receipts} hari` },
        ],
    };
};

/** The hours the chart covers: from the first hour that had a receipt to the last, the quiet ones between included. */
const hourRange = computed(() => {
    const busyHours = (props.busy?.hours ?? []).filter((hour) => hour.receipts > 0).map((hour) => hour.hour);

    return busyHours.length === 0 ? [] : Array.from({ length: Math.max(...busyHours) - Math.min(...busyHours) + 1 }, (unused, index) => Math.min(...busyHours) + index);
});

const hourItems = computed(() =>
    hourRange.value.map((hour) => {
        const figures = props.busy.hours[hour];

        return { key: hour, label: pad(hour), value: valueOf(figures), details: detailsOf(hourSpan(hour), figures, '') };
    }),
);

const weekdayItems = computed(() =>
    WEEKDAYS.map((weekday) => {
        const figures = props.busy.weekdays[weekday.number - 1];

        return { key: weekday.number, label: weekday.short, value: valueOf(figures), details: detailsOf(weekday.full, figures, weekday.full) };
    }),
);

const heatmapCells = computed(() =>
    (props.busy?.cells ?? []).map((cell) => ({
        row: cell.weekday,
        hour: cell.hour,
        value: valueOf(cell),
        details: detailsOf(`${WEEKDAYS[cell.weekday - 1].full} ${hourSpan(cell.hour)}`, cell, WEEKDAYS[cell.weekday - 1].full),
    })),
);

/** The one with the biggest value, the first of them when they tie; `null` when there is nothing. */
const peakOf = (entries, valueFor) => entries.reduce((peak, entry) => (valueFor(entry) > (peak === null ? 0 : valueFor(peak)) ? entry : peak), null);

const busiestHour = computed(() => peakOf(props.busy?.hours ?? [], valueOf));
const busiestWeekday = computed(() => peakOf(props.busy?.weekdays ?? [], valueOf));
const busiestSlot = computed(() => peakOf(props.busy?.cells ?? [], valueOf));

/**
 * Asks for the period typed. Skipped when it is the period on screen already, which is also what the server answers when it
 * swapped the dates or cut the period back and the fields were set to that answer.
 */
function applyPeriod() {
    if (from.value && to.value && (from.value !== props.period.from || to.value !== props.period.to)) {
        router.get(page.url.split('?')[0], { from: from.value, to: to.value }, { preserveState: true, replace: true, preserveScroll: true });
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
</script>

<template>
    <StoreLayout title="Laporan Penjualan">
        <SalesReportTabs current="busy" />

        <div class="period">
            <DateInput v-model="from" label="Dari" outlined dense :error="!from" error-message="Wajib diisi" class="period__date" />
            <DateInput v-model="to" label="Sampai" outlined dense :error="!to" error-message="Wajib diisi" class="period__date" />
            <q-btn-toggle v-model="metric" :options="metricOptions" no-caps unelevated dense toggle-color="primary" color="grey-3" text-color="grey-8" class="period__metric" />
        </div>
        <p v-if="is_period_cut_back" class="notice">Periode terlalu panjang, jadi laporan hanya memuat {{ max_days }} hari terakhir sampai tanggal akhir. Pilih periode yang lebih pendek untuk melihat yang lebih awal.</p>

        <Deferred data="busy">
            <template #fallback>
                <div class="kpis">
                    <q-skeleton v-for="placeholder in 4" :key="placeholder" height="86px" />
                </div>
                <q-skeleton height="260px" class="skeleton-card" />
            </template>

            <p v-if="busy.receipts === 0" class="card empty">Tidak ada nota pada periode ini.</p>
            <template v-else>
                <div class="kpis">
                    <div class="kpi">
                        <small>Jam tersibuk</small>
                        <b>{{ busiestHour ? hourSpan(busiestHour.hour) : '-' }}</b>
                        <em v-if="busiestHour">rata-rata {{ formatAverage(valueOf(busiestHour)) }} per hari yang ada nota</em>
                    </div>
                    <div class="kpi">
                        <small>Hari tersibuk</small>
                        <b>{{ busiestWeekday ? WEEKDAYS[busiestWeekday.weekday - 1].full : '-' }}</b>
                        <em v-if="busiestWeekday">rata-rata {{ formatAverage(valueOf(busiestWeekday)) }} per hari {{ WEEKDAYS[busiestWeekday.weekday - 1].full }} yang ada nota</em>
                    </div>
                    <div class="kpi">
                        <small>Waktu tersibuk</small>
                        <b>{{ busiestSlot ? `${WEEKDAYS[busiestSlot.weekday - 1].full} ${hourSpan(busiestSlot.hour)}` : '-' }}</b>
                        <em v-if="busiestSlot">rata-rata {{ formatAverage(valueOf(busiestSlot)) }} pada jam itu</em>
                    </div>
                    <div class="kpi">
                        <small>Hari ada nota</small>
                        <b>{{ busy.days }}</b>
                        <em>{{ busy.receipts }} nota, {{ formatRupiah(busy.sales) }}</em>
                        <em>{{ busy.days_without_receipts }} hari tanpa nota (libur atau tidak ada yang laku)</em>
                    </div>
                </div>

                <section class="card">
                    <h2>Jam dan hari <small>(rata-rata per hari yang ada nota)</small></h2>
                    <HourHeatmap :rows="weekdayRows" :hours="hourRange" :cells="heatmapCells" :format-value="formatAverage" label="Keramaian per jam dan hari" />
                    <p class="note">Makin gelap makin ramai. Arahkan kursor ke kotak untuk melihat rinciannya.</p>
                </section>

                <div class="columns">
                    <section class="card">
                        <h2>Per jam <small>(rata-rata per hari yang ada nota)</small></h2>
                        <ColumnBars :items="hourItems" :format-value="formatAverage" label="Keramaian per jam" />
                    </section>

                    <section class="card">
                        <h2>Per hari <small>(rata-rata per hari yang ada nota)</small></h2>
                        <ColumnBars :items="weekdayItems" :format-value="formatAverage" value-labels="all" label="Keramaian per hari dalam seminggu" />
                    </section>
                </div>

                <p class="note note--page">
                    Jam adalah saat nota dibuat; hari adalah hari buku nota, jadi nota yang dibuat lewat tengah malam dalam shift yang dibuka kemarin masuk ke hari kemarin.
                    Angkanya rata-rata per hari yang ada nota, bukan jumlah seluruh periode, supaya hari yang kebetulan lebih sering muncul tidak mengubah perbandingannya.
                    Hari tanpa nota (libur atau tidak ada yang laku, keduanya tidak bisa dibedakan dari data) tidak ikut dirata-rata; hari ini tidak dihitung selagi belum ada nota.
                    Nota void tidak dihitung, dan penjualan adalah total nota sebelum retur.
                </p>
            </template>
        </Deferred>
    </StoreLayout>
</template>

<style scoped>
.period {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}

.period__date {
    width: 170px;
}

.period__metric {
    margin-left: auto;
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
    font-size: 20px;
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

.card h2 small {
    color: var(--app-mute);
    font-weight: 400;
}

.card.empty {
    color: var(--app-mute);
}

.skeleton-card {
    margin-bottom: 16px;
}

.columns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
    gap: 16px;
    align-items: start;
}

.note {
    margin: 10px 0 0;
    color: var(--app-mute);
    font-size: 12px;
}

.note--page {
    margin-top: 0;
}
</style>
