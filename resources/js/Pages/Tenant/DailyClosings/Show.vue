<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import DateInput from '../../../Components/DateInput.vue';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    date: { type: String, required: true },
    /** The saved closing of this day, or null when it was never closed. */
    closing: { type: Object, default: null },
    checklist: { type: Object, required: true },
    /** The saved figures of a closed day, otherwise the figures as they stand now. */
    figures: { type: Object, required: true },
    /** The receipts voided on this day, whatever day they were sold. */
    voids: { type: Array, required: true },
    history: { type: Array, required: true },
    can_reopen: { type: Boolean, required: true },
    urls: { type: Object, required: true },
});

const $q = useQuasar();

const notes = ref('');

const longDateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'full' });
const shortDateFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

/** @param {string} isoDay e.g. `2026-10-24`, read as that day in the browser's own time zone */
const asLocalDate = (isoDay) => new Date(`${isoDay}T00:00:00`);

const dateLabel = computed(() => longDateFormat.format(asLocalDate(props.date)));

const isClosed = computed(() => props.closing?.status === 'closed');

const isReopened = computed(() => props.closing?.status === 'reopened');

const today = new Date().toLocaleDateString('en-CA');

const statusChipClass = { closed: 'chip--ok', reopened: 'chip--warn' };

/** Rows of the summary card, the money ones first as the daily report prints them. */
const salesRows = computed(() => [
    { label: 'Jumlah nota', value: String(props.figures.sales_count) },
    { label: 'Tunai (setelah kembalian)', value: formatRupiah(props.figures.sales_cash) },
    { label: 'Transfer', value: formatRupiah(props.figures.sales_transfer) },
    { label: 'QRIS', value: formatRupiah(props.figures.sales_qris) },
    { label: 'EDC', value: formatRupiah(props.figures.sales_edc) },
    { label: 'COD (ditagih saat diantar, tidak masuk laci)', value: formatRupiah(props.figures.sales_cod) },
    { label: 'Total penjualan', value: formatRupiah(props.figures.total_sales), isTotal: true },
]);

const otherRows = computed(() => [
    { label: 'Retur disetujui', value: formatRupiah(props.figures.total_returns) },
    { label: `Void (${props.figures.voids_count} nota)`, value: formatRupiah(props.figures.total_voids) },
    { label: 'Pengembalian tunai (retur dan DP void)', value: formatRupiah(props.figures.refunds_cash) },
    { label: 'Pengeluaran dari laci', value: formatRupiah(props.figures.expenses_drawer) },
    { label: 'Pengeluaran sumber lain', value: formatRupiah(props.figures.expenses_other) },
]);

const cashRows = computed(() => [
    { label: `Shift (${props.figures.shifts_count})`, value: '' },
    { label: 'Tunai seharusnya', value: formatRupiah(props.figures.total_expected_cash) },
    { label: 'Tunai dihitung', value: formatRupiah(props.figures.total_counted_cash) },
    { label: 'Selisih', value: formatRupiah(props.figures.total_cash_difference), isTotal: true },
    { label: 'Setoran', value: formatRupiah(props.figures.total_deposit) },
]);

function goToDate(newDate) {
    if (newDate && newDate !== props.date) {
        router.visit(`${props.urls.index}/${newDate}`);
    }
}

/** Asks first: closing locks the day's transactions, and only the Super Admin can open it again. */
function confirmClose() {
    $q.dialog({
        title: 'Tutup hari?',
        message: `${dateLabel.value} akan ditutup. Nota, retur, pengeluaran, dan shift pada tanggal itu terkunci, dan hanya Super Admin yang bisa membukanya kembali.`,
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Tutup Hari', color: 'negative', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => router.post(props.urls.close, { notes: notes.value }, { preserveScroll: true, onSuccess: () => (notes.value = '') }));
}

/** Asks why, then opens the day again; the reason stays on the record. */
function confirmReopen() {
    $q.dialog({
        title: 'Buka kembali hari ini?',
        message: 'Transaksi tanggal ini bisa diubah lagi, dan hari harus ditutup ulang setelah koreksi selesai. Isi alasannya.',
        prompt: { model: '', type: 'text', label: 'Alasan', outlined: true, dense: true, attrs: { maxlength: 255 }, isValid: (reason) => reason.trim() !== '' },
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Buka Kembali', color: 'negative', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk((reason) => router.post(props.urls.reopen, { reason }, { preserveScroll: true }));
}

/** The report opens in a window of its own and prints itself there. */
const printReport = () => window.open(props.closing.sheet_url, '_blank');
</script>

<template>
    <StoreLayout title="Tutup Hari">
        <header class="day">
            <div>
                <h2>{{ dateLabel }}</h2>
                <span v-if="closing" class="chip" :class="statusChipClass[closing.status]">{{ closing.status_label }}</span>
            </div>
            <DateInput :model-value="date" label="Ganti tanggal" outlined dense :max="today" class="day__picker" @update:model-value="goToDate" />
        </header>

        <div class="columns">
            <div class="column">
                <section class="card">
                    <h3>Sebelum menutup</h3>

                    <div class="check">
                        <q-icon :name="checklist.open_shifts.length === 0 ? 'check_circle' : 'error'" :color="checklist.open_shifts.length === 0 ? 'positive' : 'warning'" size="22px" />
                        <div>
                            <b>{{ checklist.open_shifts.length === 0 ? 'Semua shift sudah ditutup' : `${checklist.open_shifts.length} shift masih terbuka` }}</b>
                            <a v-for="shift in checklist.open_shifts" :key="shift.label" :href="shift.show_url" class="check__link" @click.prevent="router.visit(shift.show_url)">{{ shift.label }} · {{ shift.cashier_name }}</a>
                        </div>
                    </div>

                    <div class="check">
                        <q-icon :name="checklist.pending_differences.length === 0 ? 'check_circle' : 'error'" :color="checklist.pending_differences.length === 0 ? 'positive' : 'warning'" size="22px" />
                        <div>
                            <b>{{ checklist.pending_differences.length === 0 ? 'Semua selisih kas sudah diputuskan' : 'Selisih kas belum diputuskan' }}</b>
                            <a v-for="shift in checklist.pending_differences" :key="shift.label" :href="shift.show_url" class="check__link" @click.prevent="router.visit(shift.show_url)">{{ shift.label }} · {{ formatRupiah(shift.amount) }} · Tinjau</a>
                        </div>
                    </div>

                    <div class="check">
                        <q-icon :name="checklist.pending_return_count === 0 ? 'check_circle' : 'error'" :color="checklist.pending_return_count === 0 ? 'positive' : 'warning'" size="22px" />
                        <div>
                            <b>{{ checklist.pending_return_count === 0 ? 'Tidak ada retur menunggu' : `${checklist.pending_return_count} retur menunggu persetujuan` }}</b>
                            <a v-if="checklist.pending_return_count > 0" :href="urls.returns" class="check__link" @click.prevent="router.visit(urls.returns)">Tinjau retur</a>
                        </div>
                    </div>

                    <div v-if="checklist.unfinished_delivery_note_count > 0" class="check">
                        <q-icon name="info" color="info" size="22px" />
                        <div>
                            <b>{{ checklist.unfinished_delivery_note_count }} surat jalan belum selesai</b>
                            <small>Hanya pengingat, tidak menghalangi tutup hari.</small>
                            <a :href="urls.delivery_notes" class="check__link" @click.prevent="router.visit(urls.delivery_notes)">Lihat surat jalan</a>
                        </div>
                    </div>
                </section>

                <section v-if="isClosed" class="card closed">
                    <p>
                        Ditutup oleh <b>{{ closing.closed_by_name }}</b> · {{ dateTimeFormat.format(new Date(closing.closed_at)) }}
                    </p>
                    <p v-if="closing.notes" class="closed__notes"><small>Catatan penutupan</small>{{ closing.notes }}</p>
                    <div class="closed__actions">
                        <q-btn unelevated no-caps color="primary" icon="print" label="Cetak Laporan Harian" @click="printReport" />
                        <q-btn v-if="can_reopen" outline no-caps color="negative" icon="lock_open" label="Buka Kembali" @click="confirmReopen" />
                    </div>
                    <small v-if="!can_reopen" class="closed__hint">Hanya Super Admin yang bisa membuka hari yang sudah ditutup.</small>
                </section>

                <section v-else class="card">
                    <p v-if="isReopened" class="reopened">
                        Dibuka kembali oleh <b>{{ closing.reopened_by_name }}</b> · {{ dateTimeFormat.format(new Date(closing.reopened_at)) }}<br />
                        Alasan: {{ closing.reopen_reason }}. Tutup lagi setelah koreksi selesai.
                    </p>
                    <q-input v-model="notes" type="textarea" autogrow outlined maxlength="500" label="Catatan penutupan (opsional)" />
                    <q-btn class="close-button" unelevated no-caps color="negative" icon="lock" label="Tutup Hari & Kunci Transaksi" :disable="!checklist.can_close" @click="confirmClose" />
                    <small v-if="!checklist.can_close" class="closed__hint">Selesaikan yang bertanda kuning dulu.</small>
                </section>
            </div>

            <div class="column">
                <section class="card">
                    <h3>Ringkasan <small v-if="!isClosed">(sementara)</small></h3>
                    <dl class="rows">
                        <div v-for="row in salesRows" :key="row.label" :class="{ rows__total: row.isTotal }"><dt>{{ row.label }}</dt><dd>{{ row.value }}</dd></div>
                    </dl>
                    <dl class="rows rows--gap">
                        <div v-for="row in otherRows" :key="row.label"><dt>{{ row.label }}</dt><dd>{{ row.value }}</dd></div>
                    </dl>
                    <dl class="rows rows--gap">
                        <div v-for="row in cashRows" :key="row.label" :class="{ rows__total: row.isTotal, rows__minus: row.isTotal && Number(figures.total_cash_difference) < 0 }"><dt>{{ row.label }}</dt><dd>{{ row.value }}</dd></div>
                    </dl>
                    <small v-if="!isClosed" class="closed__hint">Angka ini disimpan sebagai snapshot saat hari ditutup.</small>
                </section>

                <section v-if="voids.length > 0" class="card">
                    <h3>Void pada hari ini</h3>
                    <ul class="voids">
                        <li v-for="sale in voids" :key="sale.number">
                            <a :href="sale.show_url" @click.prevent="router.visit(sale.show_url)">
                                <b>{{ sale.number }}</b>
                                <small>dijual {{ shortDateFormat.format(asLocalDate(sale.sold_on)) }}<template v-if="sale.customer_name"> · {{ sale.customer_name }}</template> · {{ sale.voided_by_name }}</small>
                                <small v-if="sale.reason">{{ sale.reason }}</small>
                            </a>
                            <b>{{ formatRupiah(sale.total) }}</b>
                        </li>
                    </ul>
                </section>

                <section v-if="history.length > 0" class="card">
                    <h3>Riwayat</h3>
                    <div class="history">
                        <a v-for="closedDay in history" :key="closedDay.business_date" :href="closedDay.show_url" class="chip" :class="statusChipClass[closedDay.status]" @click.prevent="router.visit(closedDay.show_url)">
                            {{ shortDateFormat.format(asLocalDate(closedDay.business_date)) }} · {{ closedDay.status_label }}
                        </a>
                    </div>
                </section>
            </div>
        </div>
    </StoreLayout>
</template>

<style scoped>
.day {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
}

.day h2 {
    display: inline;
    margin: 0 10px 0 0;
    font-size: 20px;
}

.day__picker {
    width: 190px;
}

.columns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 16px;
    align-items: start;
}

.column {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.card {
    padding: 16px 18px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.card h3 {
    margin: 0 0 12px;
    font-size: 15px;
}

.card h3 small {
    color: var(--app-mute);
    font-weight: 400;
}

.check {
    display: flex;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid var(--app-line);
}

.check:last-child {
    border-bottom: 0;
}

.check b,
.check small,
.check__link {
    display: block;
}

.check small {
    color: var(--app-mute);
    font-size: 12px;
}

.check__link {
    color: var(--q-primary);
    font-size: 12.5px;
    cursor: pointer;
}

.close-button {
    width: 100%;
    margin-top: 12px;
}

.closed p {
    margin: 0 0 8px;
}

.closed__notes small {
    display: block;
    color: var(--app-mute);
}

.closed__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.closed__hint {
    display: block;
    margin-top: 8px;
    color: var(--app-mute);
}

.reopened {
    margin: 0 0 12px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #fff8e6;
    font-size: 13px;
}

.rows {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin: 0;
}

.rows--gap {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid var(--app-line);
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

.rows .rows__total {
    color: var(--app-ink);
    font-weight: 700;
}

.rows .rows__minus dd {
    color: #be123c;
}

.voids {
    margin: 0;
    padding: 0;
    list-style: none;
}

.voids li {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid var(--app-line);
}

.voids a {
    color: inherit;
    text-decoration: none;
    cursor: pointer;
}

.voids b,
.voids small {
    display: block;
}

.voids small {
    color: var(--app-mute);
    font-size: 12px;
}

.history {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.history .chip {
    text-decoration: none;
    cursor: pointer;
}
</style>
