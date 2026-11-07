<script setup>
import { computed } from 'vue';
import { formatQuantity } from '../../Composables/useQuantityFormat';
import { formatRupiah } from '../../Composables/useMoneyFormat';
import { roundMoney } from '../../Composables/useCart';

const props = defineProps({
    /** The part of the cart that is short: `{line, quantity, subtotal}` entries from the cart's split. */
    orderLines: { type: Array, required: true },
    orderTotal: { type: Number, required: true },
    /** What was paid beyond the receipt and went to the order as its deposit; worked out by the payment screen. */
    depositAmount: { type: Number, required: true },
});

/** @type {import('vue').Ref<{phone: string, address: string, isPayingNow: boolean}>} */
const form = defineModel({ type: Object, required: true });

const remaining = computed(() => roundMoney(props.orderTotal - props.depositAmount));
</script>

<template>
    <section class="order">
        <header class="order__head">
            <div>
                <b>Pesanan (PO)</b>
                <small>Barang belum ada, dibelikan dulu. Pelanggan membawa nota ini.</small>
            </div>
            <b>{{ formatRupiah(orderTotal) }}</b>
        </header>

        <ul class="order__lines">
            <li v-for="orderLine in orderLines" :key="orderLine.line.key">
                <span>{{ orderLine.line.product.name }} <small>{{ formatQuantity(orderLine.quantity) }} {{ orderLine.line.product.units.find((unit) => unit.id === orderLine.line.unitId).code }}</small></span>
                <b>{{ formatRupiah(orderLine.subtotal) }}</b>
            </li>
        </ul>

        <q-input v-model="form.phone" outlined dense maxlength="255" label="Telepon pelanggan (wajib)" hint="Untuk dihubungi saat barang sudah datang" />
        <q-input v-model="form.address" outlined dense type="textarea" autogrow maxlength="1000" label="Alamat pengantaran (opsional)" hint="Diisi bila barang nanti diantar, jadi tidak perlu ditanya lagi" />


        <q-checkbox v-model="form.isPayingNow" dense label="Pelanggan bayar pesanan sekarang (uang muka)" />

        <p v-if="!form.isPayingNow" class="order__hint">Belum dibayar. Seluruh {{ formatRupiah(orderTotal) }} dibayar saat barang diambil, dengan harga yang sama seperti di nota ini. Kelebihan bayar di nota penjualan jadi kembalian.</p>
        <p v-else-if="depositAmount <= 0" class="order__hint">Isi jumlah yang diterima lebih besar dari nota penjualan: kelebihannya jadi uang muka pesanan.</p>
        <p v-else-if="remaining === 0" class="order__hint">Uang muka {{ formatRupiah(depositAmount) }}: pesanan lunas, tidak ada yang dibayar lagi saat barang diambil.</p>
        <p v-else class="order__hint">Uang muka {{ formatRupiah(depositAmount) }}, sisa {{ formatRupiah(remaining) }} dibayar saat barang diambil.</p>
    </section>
</template>

<style scoped>
.order {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 12px;
    border: 1px solid var(--app-line);
    border-radius: 10px;
    background: #fff9ee;
}

.order__head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
}

.order__head small {
    display: block;
    color: var(--app-mute);
}

.order__lines {
    margin: 0;
    padding: 0;
    list-style: none;
}

.order__lines li {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 4px 0;
    border-bottom: 1px dashed var(--app-line);
}

.order__lines small {
    color: var(--app-mute);
}

.order__hint {
    margin: 0;
    color: var(--app-mute);
    font-size: 12px;
}
</style>
