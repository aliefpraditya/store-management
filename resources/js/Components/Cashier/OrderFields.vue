<script setup>
import { computed } from 'vue';
import { formatQuantity } from '../../Composables/useQuantityFormat';
import { formatRupiah } from '../../Composables/useMoneyFormat';
import { roundMoney } from '../../Composables/useCart';

const props = defineProps({
    /** The part of the cart that is short: `{line, quantity, subtotal}` entries from the cart's split. */
    orderLines: { type: Array, required: true },
    orderTotal: { type: Number, required: true },
});

/** @type {import('vue').Ref<{phone: string, sourceNote: string, expectedDate: string, depositAmount: string|number}>} */
const form = defineModel({ type: Object, required: true });

const remaining = computed(() => roundMoney(props.orderTotal - (Number(form.value.depositAmount) || 0)));

const isDepositTooMuch = computed(() => remaining.value < 0);
</script>

<template>
    <section class="order">
        <header class="order__head">
            <div>
                <b>Pesanan (PO)</b>
                <small>Barang belum ada, dibelikan dulu. Pelanggan membawa nota pesanan ini.</small>
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

        <div class="order__grid">
            <q-input v-model="form.expectedDate" outlined dense type="date" label="Perkiraan datang" />
            <q-input v-model="form.sourceNote" outlined dense maxlength="255" label="Dibeli dari" placeholder="Toko lain / supplier" />
        </div>

        <div class="order__deposit">
            <b>Uang muka (DP), opsional</b>
            <div class="order__deposit-row">
                <q-input v-model="form.depositAmount" type="number" min="0" step="any" outlined dense prefix="Rp" placeholder="0" aria-label="Uang muka" />
                <q-btn outline no-caps color="primary" label="Bayar lunas" @click="form.depositAmount = orderTotal" />
            </div>
        </div>

        <p v-if="isDepositTooMuch" class="order__warning">Uang muka tidak boleh lebih besar dari total pesanan.</p>
        <p v-else-if="remaining === 0" class="order__hint">Pesanan lunas, tidak ada yang dibayar lagi saat barang diambil.</p>
        <p v-else class="order__hint">Sisa {{ formatRupiah(remaining) }} dibayar saat barang diambil, dengan harga yang sama seperti di nota ini.</p>
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

.order__grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.order__deposit {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.order__deposit-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
}

.order__hint,
.order__warning {
    margin: 0;
    font-size: 12px;
}

.order__hint {
    color: var(--app-mute);
}

.order__warning {
    color: #b91c1c;
}
</style>
