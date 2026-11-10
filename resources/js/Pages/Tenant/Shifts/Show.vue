<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import { printSheet } from '../../../Composables/printSheet';
import { formatDeduction, formatRupiah } from '../../../Composables/useMoneyFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    shift: { type: Object, required: true },
});

const $q = useQuasar();

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });
const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

const differenceChipClass = { none: 'chip--off', pending_approval: 'chip--warn', approved: 'chip--ok', rejected: 'chip--bad' };
const returnChipClass = { pending: 'chip--warn', completed: 'chip--ok', rejected: 'chip--bad' };

const note = ref('');
const errorMessage = ref('');
const isDeciding = ref(false);

/**
 * Asks the admin to confirm the decision, since it cannot be changed afterwards. The note is required, so an empty
 * one is stopped here before anything is asked.
 *
 * @param {string} url where the decision is sent
 * @param {boolean} isApproval
 */
function decide(url, isApproval) {
    if (note.value.trim() === '') {
        errorMessage.value = 'Isi catatan keputusan.';

        return;
    }

    const difference = formatRupiah(props.shift.cash_difference);

    $q.dialog({
        title: isApproval ? 'Setujui selisih kas?' : 'Selisih ditanggung kasir?',
        message: isApproval
            ? `Selisih ${difference} pada ${props.shift.label} disetujui dan shift ditutup. Keputusan ini tidak bisa diubah lagi.`
            : `Selisih ${difference} pada ${props.shift.label} ditanggung kasir dan shift ditutup. Buat berita acara di luar sistem dan minta kasir mengganti uangnya. Keputusan ini tidak bisa diubah lagi.`,
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: isApproval ? 'Setujui' : 'Ya, ditanggung kasir', color: isApproval ? 'primary' : 'negative', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => sendDecision(url));
}

/** The end-of-shift report with every figure, from a hidden frame; a shift still running has none. */
const printReport = () => printSheet(() => props.shift.urls.sheet);

function sendDecision(url) {
    router.post(url, { note: note.value }, {
        preserveScroll: true,
        onStart: () => (isDeciding.value = true),
        onFinish: () => (isDeciding.value = false),
        onSuccess: () => (note.value = ''),
        onError: (errors) => (errorMessage.value = Object.values(errors)[0]),
    });
}

</script>

<template>
    <StoreLayout :title="shift.label">
        <template #back>
            <q-btn flat no-caps dense icon="arrow_back" label="Rekap Shift" class="q-ml-sm" @click="router.visit(shift.urls.index)" />
        </template>

        <template #actions>
            <q-btn v-if="shift.status !== 'open'" outline no-caps icon="print" label="Cetak Laporan Shift" @click="printReport" />
        </template>

        <article class="shift">
            <header class="shift__head">
                <div>
                    <b>{{ shift.label }}</b>
                    <small>{{ shift.register_name }} · {{ shift.cashier_name }} · {{ dateFormat.format(new Date(shift.business_date)) }}</small>
                    <small>
                        Buka {{ dateTimeFormat.format(new Date(shift.opened_at)) }}
                        <template v-if="shift.closed_at"> · tutup {{ dateTimeFormat.format(new Date(shift.closed_at)) }}</template>
                    </small>
                </div>
                <span class="chip" :class="{ 'chip--ok': shift.status === 'open', 'chip--warn': shift.status === 'pending_review', 'chip--off': shift.status === 'closed' }">{{ shift.status_label }}</span>
            </header>

            <h2>Penjualan</h2>
            <dl class="rows">
                <div><dt>Jumlah nota</dt><dd>{{ shift.sale_count }}</dd></div>
                <div><dt>Total penjualan</dt><dd>{{ formatRupiah(shift.total_sales) }}</dd></div>
                <div v-for="payment in shift.payments" :key="payment.method">
                    <dt>{{ payment.method }}<small v-if="payment.method === 'Tunai'"> (setelah kembalian)</small></dt>
                    <dd>{{ formatRupiah(payment.amount) }}</dd>
                </div>
                <div v-if="Number(shift.void_deposits) > 0"><dt>Sudah termasuk DP nota void<small> (uang yang sempat masuk, nota kemudian di-void)</small></dt><dd>{{ formatRupiah(shift.void_deposits) }}</dd></div>
                <div v-if="Number(shift.returns_total) > 0"><dt>Retur disetujui</dt><dd>{{ formatRupiah(shift.returns_total) }}</dd></div>
                <template v-if="shift.cod.delivered.count + shift.cod.undelivered.count > 0">
                    <div><dt>COD sudah diantar ({{ shift.cod.delivered.count }} nota)<small> (tidak masuk laci)</small></dt><dd>{{ formatRupiah(shift.cod.delivered.amount) }}</dd></div>
                    <div><dt>COD belum diantar ({{ shift.cod.undelivered.count }} nota)<small> (tidak masuk laci)</small></dt><dd>{{ formatRupiah(shift.cod.undelivered.amount) }}</dd></div>
                </template>
            </dl>

            <h2>Kas</h2>
            <dl class="rows">
                <div><dt>Modal kas awal</dt><dd>{{ formatRupiah(shift.opening_cash) }}</dd></div>
                <div v-if="Number(shift.order_deposit_cash) !== 0"><dt>DP pesanan tunai<small> (setelah DP yang dikembalikan)</small></dt><dd>{{ formatRupiah(shift.order_deposit_cash) }}</dd></div>
                <div v-if="Number(shift.cash_refunds) > 0"><dt>Retur tunai</dt><dd class="rows__minus">{{ formatDeduction(shift.cash_refunds) }}</dd></div>
                <div v-if="Number(shift.void_cash_refunds) > 0"><dt>DP void dikembalikan tunai</dt><dd class="rows__minus">{{ formatDeduction(shift.void_cash_refunds) }}</dd></div>
                <div v-if="Number(shift.drawer_expenses) > 0"><dt>Pengeluaran dari laci</dt><dd class="rows__minus">{{ formatDeduction(shift.drawer_expenses) }}</dd></div>
                <div><dt>Tunai seharusnya di laci</dt><dd>{{ formatRupiah(shift.expected_cash) }}</dd></div>
                <template v-if="shift.status !== 'open'">
                    <div><dt>Tunai dihitung</dt><dd>{{ formatRupiah(shift.counted_cash) }}</dd></div>
                    <div class="rows__strong" :class="{ 'rows__minus': Number(shift.cash_difference) < 0 }">
                        <dt>Selisih</dt><dd>{{ formatRupiah(shift.cash_difference) }}</dd>
                    </div>
                    <div><dt>Setoran</dt><dd>{{ formatRupiah(shift.deposit_amount) }}</dd></div>
                    <div><dt>Ditinggal di laci</dt><dd>{{ formatRupiah(shift.drawer_left_cash) }}</dd></div>
                </template>
            </dl>
            <p v-if="shift.opening_cash_note" class="shift__note"><small>Alasan modal awal berbeda dari sisa laci</small>{{ shift.opening_cash_note }}</p>
            <p v-if="shift.closing_note" class="shift__note"><small>Catatan kasir</small>{{ shift.closing_note }}</p>

            <template v-if="shift.returns.length > 0">
                <h2>Retur di shift ini</h2>
                <ul class="returns">
                    <li v-for="saleReturn in shift.returns" :key="saleReturn.number" @click="router.visit(saleReturn.show_url)">
                        <b>{{ saleReturn.number }}</b>
                        <span class="chip" :class="returnChipClass[saleReturn.status]">{{ saleReturn.status_label }}</span>
                        <span class="returns__amount">{{ formatRupiah(saleReturn.refund_amount) }} <small>{{ saleReturn.refund_method_label }}</small></span>
                    </li>
                </ul>
            </template>

            <template v-if="shift.expenses.length > 0">
                <h2>Pengeluaran dari laci</h2>
                <ul class="returns returns--static">
                    <li v-for="expense in shift.expenses" :key="expense.number" :class="{ 'returns__voided': expense.status === 'voided' }">
                        <b>{{ expense.description }}</b>
                        <span class="chip" :class="expense.status === 'voided' ? 'chip--off' : 'chip--ok'">{{ expense.status_label }}</span>
                        <span class="returns__amount">{{ formatRupiah(expense.amount) }} <small>{{ expense.category_name }}</small></span>
                    </li>
                </ul>
            </template>

            <template v-if="shift.status !== 'open' && shift.difference_status !== 'none'">
                <h2>
                    Keputusan selisih
                    <span class="chip" :class="differenceChipClass[shift.difference_status]">{{ shift.difference_status_label }}</span>
                </h2>

                <p v-if="shift.is_own_shift && !shift.can_decide && shift.difference_status === 'pending_approval'" class="decide__own">Selisih ini dari shift Anda sendiri, jadi diputuskan Admin lain atau Super Admin.</p>
                <div v-else-if="shift.can_decide" class="decide">
                    <p>Selisih {{ formatRupiah(shift.cash_difference) }}. Setujui bila selisihnya wajar, atau minta kasir menanggungnya (berita acara dibuat di luar sistem dan uangnya diganti kasir). Shift baru benar-benar tertutup setelah Anda memutuskan.</p>
                    <q-input v-model="note" type="textarea" autogrow outlined label="Catatan keputusan (mis. nomor berita acara)" :error="errorMessage !== ''" :error-message="errorMessage" @update:model-value="errorMessage = ''" />
                    <div class="decide__actions">
                        <q-btn outline no-caps color="negative" label="Ditanggung kasir" :loading="isDeciding" @click="decide(shift.urls.reject, false)" />
                        <q-btn unelevated no-caps color="primary" label="Setujui" :loading="isDeciding" @click="decide(shift.urls.approve, true)" />
                    </div>
                </div>

                <div v-else class="decided">
                    <p>
                        {{ shift.difference_status_label }}
                        <template v-if="shift.decided_by_name">oleh {{ shift.decided_by_name }}</template>
                        <template v-if="shift.decided_at"> · {{ dateTimeFormat.format(new Date(shift.decided_at)) }}</template>
                    </p>
                    <p v-if="shift.approval_note" class="shift__note"><small>Catatan</small>{{ shift.approval_note }}</p>
                    <p v-if="shift.difference_status === 'rejected'" class="decided__outside">Berita acara dan penggantian uang oleh kasir dicatat di luar sistem.</p>
                </div>
            </template>
        </article>
    </StoreLayout>
</template>

<style scoped>
.shift {
    max-width: 720px;
    padding: 20px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.shift__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
}

.shift__head b,
.shift__head small {
    display: block;
}

.shift__head b {
    font-size: 17px;
}

.shift__head small {
    color: var(--app-mute);
    font-size: 12.5px;
}

.shift h2 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 20px 0 8px;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--app-line);
    font-size: 14px;
    font-weight: 700;
    letter-spacing: normal;
    line-height: 1.4;
}

.rows {
    margin: 0;
}

.rows div {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 4px 0;
}

.rows dt small {
    color: var(--app-mute);
}

.rows dd {
    margin: 0;
}

.rows .rows__strong {
    font-weight: 700;
}

.rows .rows__minus {
    color: #be123c;
}

.shift__note {
    margin: 10px 0 0;
    padding: 8px 12px;
    border-radius: 6px;
    background: #f5f6f8;
}

.shift__note small {
    display: block;
    color: var(--app-mute);
}

.returns {
    margin: 0;
    padding: 0;
    list-style: none;
}

.returns li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid var(--app-line);
    cursor: pointer;
}

.returns li:hover {
    background: #fafafb;
}

.returns--static li {
    cursor: default;
}

.returns--static li:hover {
    background: none;
}

.returns__voided {
    color: var(--app-mute);
    text-decoration: line-through;
}

.returns__amount {
    margin-left: auto;
}

.returns__amount small {
    color: var(--app-mute);
}

.decide p,
.decided p {
    margin: 0 0 10px;
}

.decided__outside {
    margin: 8px 0 0;
    color: var(--app-mute);
    font-size: 13px;
}

.decide__own {
    margin: 0;
    color: var(--app-mute);
    font-size: 13px;
}

.decide__actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 10px;
}
</style>
