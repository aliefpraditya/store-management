<script setup>
import { router } from '@inertiajs/vue3';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

defineProps({
    sale: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <StoreLayout :title="sale.number">
        <template #actions>
            <q-btn flat no-caps dense icon="arrow_back" label="Riwayat Nota" class="q-mr-sm" @click="router.visit(urls.index)" />
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
                Nota di-void<template v-if="sale.voided_at"> pada {{ dateTimeFormat.format(new Date(sale.voided_at)) }}</template><template v-if="sale.void_reason">: {{ sale.void_reason }}</template>
            </p>

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
