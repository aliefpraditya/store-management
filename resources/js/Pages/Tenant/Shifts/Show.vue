<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
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

    $q.dialog({
        title: isApproval ? 'Setujui selisih kas?' : 'Tolak selisih kas?',
        message: `Selisih ${formatRupiah(props.shift.cash_difference)} pada ${props.shift.label} akan ${isApproval ? 'disetujui' : 'ditolak'}. Keputusan ini tidak bisa diubah lagi.`,
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: isApproval ? 'Setujui' : 'Tolak', color: isApproval ? 'primary' : 'negative', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => sendDecision(url));
}

function sendDecision(url) {
    router.post(url, { note: note.value }, {
        preserveScroll: true,
        onStart: () => (isDeciding.value = true),
        onFinish: () => (isDeciding.value = false),
        onSuccess: () => (note.value = ''),
        onError: (errors) => (errorMessage.value = Object.values(errors)[0]),
    });
}

/** Marks a refused difference as dealt with, after the admin confirms. */
function markFollowedUp() {
    $q.dialog({
        title: 'Tandai sudah ditindaklanjuti?',
        message: 'Selisih ini tidak lagi muncul di daftar Perlu tindakan. Pastikan tindak lanjutnya memang sudah dilakukan.',
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Sudah ditindaklanjuti', color: 'primary', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => router.post(props.shift.urls.follow_up, {}, { preserveScroll: true }));
}
</script>

<template>
    <StoreLayout :title="shift.label">
        <template #back>
            <q-btn flat no-caps dense icon="arrow_back" label="Rekap Shift" class="q-ml-sm" @click="router.visit(shift.urls.index)" />
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
                <span class="chip" :class="shift.status === 'open' ? 'chip--ok' : 'chip--off'">{{ shift.status_label }}</span>
            </header>

            <h2>Penjualan</h2>
            <dl class="rows">
                <div><dt>Jumlah nota</dt><dd>{{ shift.sale_count }}</dd></div>
                <div><dt>Total penjualan</dt><dd>{{ formatRupiah(shift.total_sales) }}</dd></div>
                <div v-for="payment in shift.payments" :key="payment.method">
                    <dt>{{ payment.method }}<small v-if="payment.method === 'Tunai'"> (sudah dikurangi kembalian dan retur tunai)</small></dt>
                    <dd>{{ formatRupiah(payment.amount) }}</dd>
                </div>
                <div v-if="Number(shift.returns_total) > 0"><dt>Retur disetujui</dt><dd>{{ formatRupiah(shift.returns_total) }}</dd></div>
            </dl>

            <h2>Kas</h2>
            <dl class="rows">
                <div><dt>Modal kas awal</dt><dd>{{ formatRupiah(shift.opening_cash) }}</dd></div>
                <div><dt>Tunai seharusnya di laci</dt><dd>{{ formatRupiah(shift.expected_cash) }}</dd></div>
                <template v-if="shift.status === 'closed'">
                    <div><dt>Tunai dihitung</dt><dd>{{ formatRupiah(shift.counted_cash) }}</dd></div>
                    <div class="rows__strong" :class="{ 'rows__minus': Number(shift.cash_difference) < 0 }">
                        <dt>Selisih</dt><dd>{{ formatRupiah(shift.cash_difference) }}</dd>
                    </div>
                    <div><dt>Setoran</dt><dd>{{ formatRupiah(shift.deposit_amount) }}</dd></div>
                </template>
            </dl>
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

            <template v-if="shift.status === 'closed' && shift.difference_status !== 'none'">
                <h2>
                    Keputusan selisih
                    <span class="chip" :class="differenceChipClass[shift.difference_status]">{{ shift.difference_status_label }}</span>
                </h2>

                <div v-if="shift.can_decide" class="decide">
                    <p>Selisih {{ formatRupiah(shift.cash_difference) }}. Isi catatan, lalu setujui atau tolak. Keputusan ini tidak menghalangi shift berikutnya.</p>
                    <q-input v-model="note" type="textarea" autogrow outlined label="Catatan keputusan" :error="errorMessage !== ''" :error-message="errorMessage" @update:model-value="errorMessage = ''" />
                    <div class="decide__actions">
                        <q-btn outline no-caps color="negative" label="Tolak" :loading="isDeciding" @click="decide(shift.urls.reject, false)" />
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

                    <div v-if="shift.difference_status === 'rejected'" class="follow" :class="{ 'follow--open': shift.needs_follow_up }">
                        <template v-if="shift.needs_follow_up">
                            <span>Selisih ini ditolak dan <b>belum ditindaklanjuti</b>. Tetap ada di daftar Perlu tindakan sampai Anda menandainya.</span>
                            <q-btn unelevated no-caps color="primary" label="Tandai sudah ditindaklanjuti" @click="markFollowedUp" />
                        </template>
                        <span v-else>
                            Sudah ditindaklanjuti<template v-if="shift.followed_up_by_name"> oleh {{ shift.followed_up_by_name }}</template>
                            <template v-if="shift.followed_up_at"> · {{ dateTimeFormat.format(new Date(shift.followed_up_at)) }}</template>
                        </span>
                    </div>
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

.follow {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 6px;
    background: #f5f6f8;
}

.follow--open {
    border: 1px solid #fcd9a8;
    background: #fff7ec;
    color: #9a5b00;
}

.decide__actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 10px;
}
</style>
