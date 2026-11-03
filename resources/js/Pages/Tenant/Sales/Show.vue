<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    sale: { type: Object, required: true },
    /** Why a COD sale cannot be voided now, or null when it can (also null for a sale that is not COD). */
    void_blocker: { type: String, default: null },
    urls: { type: Object, required: true },
});

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

const isVoidOpen = ref(false);
const voidReason = ref('');
const refundAccount = ref('');
const isVoiding = ref(false);

const canVoid = computed(() => props.sale.status === 'completed' && props.sale.is_cod && props.void_blocker === null);

const hasNonCashDeposit = computed(() => props.sale.payments.some((payment) => ['Transfer', 'QRIS', 'EDC'].includes(payment.method)));

const hasCashDeposit = computed(() => props.sale.payments.some((payment) => payment.method === 'Tunai'));

const isVoidReady = computed(() => voidReason.value.trim() !== '' && (!hasNonCashDeposit.value || refundAccount.value.trim() !== ''));

function openVoid() {
    voidReason.value = '';
    refundAccount.value = '';
    isVoidOpen.value = true;
}

function submitVoid() {
    router.post(props.urls.void, { reason: voidReason.value.trim(), refund_account: refundAccount.value.trim() || null }, {
        preserveScroll: true,
        onStart: () => (isVoiding.value = true),
        onFinish: () => (isVoiding.value = false),
        onSuccess: () => (isVoidOpen.value = false),
    });
}
</script>

<template>
    <StoreLayout :title="sale.number">
        <template #back>
            <q-btn flat no-caps dense icon="arrow_back" label="Riwayat Nota" class="q-ml-sm" @click="router.visit(urls.index)" />
        </template>

        <template #actions>
            <q-btn v-if="canVoid" outline no-caps dense color="negative" icon="block" label="Void Nota COD" class="q-mr-sm" @click="openVoid" />
            <q-btn v-if="sale.status === 'completed'" outline no-caps dense color="primary" icon="undo" label="Buat Retur" class="q-mr-sm" @click="router.visit(urls.new_return)" />
            <q-btn v-if="sale.status === 'completed'" outline no-caps dense color="primary" icon="local_shipping" label="Buat Surat Jalan" @click="router.visit(urls.new_delivery_note)" />
        </template>

        <article class="receipt">
            <header class="receipt__head">
                <div>
                    <b>{{ sale.number }}</b>
                    <small>{{ dateTimeFormat.format(new Date(sale.sold_at)) }} · {{ sale.register.code }} · {{ sale.cashier_name }}</small>
                    <small v-if="sale.customer_name">Pelanggan: {{ sale.customer_name }}</small>
                </div>
                <span class="chip" :class="sale.status === 'completed' ? 'chip--ok' : 'chip--bad'">{{ sale.status_label }}</span>
            </header>

            <p v-if="sale.status === 'voided'" class="receipt__void">
                Nota di-void<template v-if="sale.voided_at"> pada {{ dateTimeFormat.format(new Date(sale.voided_at)) }}</template><template v-if="sale.voided_by_name"> oleh {{ sale.voided_by_name }}</template><template v-if="sale.void_reason">: {{ sale.void_reason }}</template>
                <template v-if="Number(sale.void_cash_refund) > 0"><br />DP tunai {{ formatRupiah(sale.void_cash_refund) }} dikembalikan dari laci.</template>
                <template v-if="sale.void_refund_account"><br />DP non tunai dikembalikan ke: {{ sale.void_refund_account }}</template>
            </p>

            <section v-if="sale.is_cod" class="receipt__cod">
                <b>COD: {{ formatRupiah(sale.cod_amount) }} ditagih saat diantar</b>
                <a v-for="note in sale.delivery_notes" :key="note.number" :href="note.show_url" @click.prevent="router.visit(note.show_url)">{{ note.number }} · {{ note.status_label }}</a>
                <small v-if="sale.status === 'completed' && void_blocker" class="receipt__cod-blocked">Tidak bisa di-void: {{ void_blocker }}</small>
            </section>

            <table class="receipt__items">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th class="receipt__right">Jumlah</th>
                        <th class="receipt__right">Harga</th>
                        <th class="receipt__right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in sale.items" :key="index">
                        <td>{{ item.name }}</td>
                        <td class="receipt__right">{{ formatQuantity(item.quantity) }} {{ item.unit }}</td>
                        <td class="receipt__right">{{ formatRupiah(item.unit_price) }}</td>
                        <td class="receipt__right">{{ formatRupiah(item.subtotal) }}</td>
                    </tr>
                </tbody>
            </table>

            <dl class="receipt__totals">
                <div v-if="Number(sale.discount_total) > 0"><dt>Diskon</dt><dd>{{ formatRupiah(sale.discount_total) }}</dd></div>
                <div class="receipt__total"><dt>Total</dt><dd>{{ formatRupiah(sale.total) }}</dd></div>
                <div v-for="(payment, index) in sale.payments" :key="index">
                    <dt>{{ payment.method }}<template v-if="payment.reference"> ({{ payment.reference }})</template></dt>
                    <dd>{{ formatRupiah(payment.amount) }}</dd>
                </div>
                <div><dt>Kembalian</dt><dd>{{ formatRupiah(sale.change_amount) }}</dd></div>
            </dl>
        </article>

        <q-dialog v-model="isVoidOpen" persistent>
            <q-card class="void-dialog">
                <q-card-section>
                    <div class="void-dialog__title">Void nota {{ sale.number }}?</div>
                    <small class="void-dialog__hint">Barangnya kembali ke stok dan surat jalan yang masih berjalan dibatalkan. Nota tetap tercatat sebagai void di hari ini.</small>
                </q-card-section>

                <q-card-section class="void-dialog__fields">
                    <q-input v-model="voidReason" outlined dense autofocus maxlength="255" label="Alasan void" />
                    <p v-if="hasCashDeposit" class="void-dialog__note">DP tunai dikembalikan dari laci shift Anda, jadi Anda perlu shift yang sedang berjalan.</p>
                    <q-input v-if="hasNonCashDeposit" v-model="refundAccount" outlined dense maxlength="255" label="Rekening penerima pengembalian DP" placeholder="Bank, nomor rekening, atas nama" />
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat no-caps label="Kembali" :disable="isVoiding" @click="isVoidOpen = false" />
                    <q-btn color="negative" unelevated no-caps label="Void Nota" :loading="isVoiding" :disable="!isVoidReady" @click="submitVoid" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </StoreLayout>
</template>

<style scoped>
.receipt {
    max-width: 640px;
    padding: 20px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.receipt__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.receipt__head b,
.receipt__head small {
    display: block;
}

.receipt__head b {
    font-size: 17px;
}

.receipt__head small {
    color: var(--app-mute);
    font-size: 12.5px;
}

.receipt__void {
    margin: 0 0 14px;
    padding: 8px 12px;
    border-radius: 10px;
    background: #fde8ee;
    color: #be123c;
    font-size: 13px;
}

.receipt__cod {
    margin: 0 0 14px;
    padding: 10px 12px;
    border-radius: 10px;
    background: #fff4e0;
    font-size: 13px;
}

.receipt__cod b,
.receipt__cod a,
.receipt__cod small {
    display: block;
}

.receipt__cod a {
    color: var(--q-primary);
    cursor: pointer;
}

.receipt__cod-blocked {
    margin-top: 4px;
    color: #be123c;
}

.void-dialog {
    width: 440px;
    max-width: 94vw;
}

.void-dialog__title {
    font-size: 17px;
    font-weight: 700;
}

.void-dialog__hint {
    color: var(--app-mute);
}

.void-dialog__fields {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.void-dialog__note {
    margin: 0;
    color: var(--app-ink-soft);
    font-size: 13px;
}

.receipt__items {
    width: 100%;
    border-collapse: collapse;
}

.receipt__items th {
    padding: 8px 0;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
}

.receipt__items td {
    padding: 9px 0;
    border-bottom: 1px solid var(--app-line);
}

.receipt__items .receipt__right {
    text-align: right;
    white-space: nowrap;
}

.receipt__totals {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin: 14px 0 0;
}

.receipt__totals div {
    display: flex;
    justify-content: space-between;
    color: var(--app-ink-soft);
}

.receipt__totals dd {
    margin: 0;
}

.receipt__totals .receipt__total {
    color: var(--app-ink);
    font-size: 17px;
    font-weight: 700;
}
</style>
