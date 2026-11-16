<script setup>
import { computed, ref, watch } from 'vue';
import { formatRupiah } from '../Composables/useMoneyFormat';
import { formatQuantity } from '../Composables/useQuantityFormat';

const props = defineProps({
    /** Endpoint of the cashier's item lookup; answers `?q=` with `{ data: item[] }`. */
    productsUrl: { type: String, required: true },
    /** What the returned goods are worth: the replacement is paid with it first. */
    credit: { type: Number, required: true },
    /** The ways the customer can pay what is more, and the refund can go back. @type {{value: string, label: string}[]} */
    paymentMethods: { type: Array, required: true },
    /** The refund method to start with. */
    defaultMethod: { type: String, default: 'cash' },
});

/**
 * `change` carries what the form sends: the replacement lines, the customer's extra payment (only when the goods
 * cost more than the credit), the refund method for what is left over, and whether it is complete enough to save.
 */
const emit = defineEmits(['change']);

const SEARCH_DELAY_MS = 250;

/** @type {import('vue').Ref<{key: number, unitId: number, productId: number, name: string, units: object[], stock: string, baseUnit: string, quantity: number}[]>} */
const lines = ref([]);
const productOptions = ref([]);
const paymentMethod = ref(props.defaultMethod);
const typedAmount = ref(null);
const refundMethod = ref(props.defaultMethod);
const refundAccount = ref('');
let latestSearchId = 0;
let nextLineKey = 1;

/**
 * Looks items up as the user types; an answer that arrives after a newer search was started is dropped.
 */
function searchItems(typed, update, abort) {
    const term = typed.trim();

    if (term === '') {
        abort();

        return;
    }

    const searchId = ++latestSearchId;

    fetch(`${props.productsUrl}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((response) => (response.ok ? response.json() : { data: [] }))
        .catch(() => ({ data: [] }))
        .then(({ data }) => (searchId === latestSearchId ? update(() => (productOptions.value = data)) : abort()));
}

/** Adds the item in its first unit; the same unit twice adds up. */
function addItem(item) {
    if (!item) {
        return;
    }

    const unit = item.units[0];
    const existing = lines.value.find((line) => line.productId === item.id && line.unitId === unit.id);

    if (existing) {
        existing.quantity += 1;

        return;
    }

    lines.value.push({ key: nextLineKey++, productId: item.id, unitId: unit.id, name: item.name, units: item.units, stock: item.stock, baseUnit: item.base_unit, quantity: 1 });
}

const unitOf = (line) => line.units.find((unit) => unit.id === line.unitId);

const subtotalOf = (line) => Math.round((Number(line.quantity) || 0) * Number(unitOf(line).price) * 100) / 100;

const total = computed(() => lines.value.reduce((sum, line) => sum + subtotalOf(line), 0));

/** Positive: the customer owes it. Negative: that much of the credit is left to pay back. */
const difference = computed(() => Math.round((total.value - props.credit) * 100) / 100);

const amountPaid = computed(() => typedAmount.value ?? difference.value);

const isPaymentShort = computed(() => difference.value > 0 && Number(amountPaid.value) < difference.value);

/** What is left of the credit is paid back; by transfer, QRIS or EDC it needs the account it goes to. */
const isRefundAccountMissing = computed(() => difference.value < 0 && refundMethod.value !== 'cash' && refundAccount.value.trim() === '');

const isReady = computed(() => lines.value.length > 0 && lines.value.every((line) => Number(line.quantity) > 0) && total.value > 0 && !isPaymentShort.value && !isRefundAccountMissing.value);

const methodOptions = computed(() => props.paymentMethods);

watch(total, () => (typedAmount.value = null));

watch(
    [lines, paymentMethod, typedAmount, refundMethod, refundAccount, () => props.credit],
    () => emit('change', {
        items: lines.value.map((line) => ({ product_unit_id: line.unitId, quantity: Number(line.quantity) || 0 })),
        payments: difference.value > 0 ? [{ method: paymentMethod.value, amount: Number(amountPaid.value) || 0 }] : [],
        refund_method: refundMethod.value,
        refund_account: refundAccount.value,
        is_ready: isReady.value,
    }),
    { deep: true, immediate: true },
);
</script>

<template>
    <div class="exchange">
        <q-select
            :model-value="null"
            :options="productOptions"
            option-label="name"
            option-value="id"
            label="Cari barang pengganti"
            outlined
            dense
            use-input
            hide-selected
            fill-input
            behavior="menu"
            :input-debounce="SEARCH_DELAY_MS"
            @filter="searchItems"
            @update:model-value="addItem"
        >
            <template #prepend><q-icon name="search" /></template>
            <template #option="{ itemProps, opt }">
                <q-item v-bind="itemProps">
                    <q-item-section>
                        <q-item-label>{{ opt.name }}</q-item-label>
                        <q-item-label caption>{{ opt.sku }} · stok {{ formatQuantity(opt.stock) }} {{ opt.base_unit }}</q-item-label>
                    </q-item-section>
                    <q-item-section side>{{ formatRupiah(opt.units[0].price) }}</q-item-section>
                </q-item>
            </template>
            <template #no-option>
                <q-item><q-item-section class="text-grey">Ketik untuk mencari barang</q-item-section></q-item>
            </template>
        </q-select>

        <p v-if="lines.length === 0" class="exchange__hint">Belum ada barang pengganti.</p>

        <div v-for="(line, index) in lines" :key="line.key" class="exchange__line">
            <span class="exchange__what">{{ line.name }}<small>stok {{ formatQuantity(line.stock) }} {{ line.baseUnit }}</small></span>
            <q-select
                v-model="line.unitId"
                :options="line.units"
                option-value="id"
                option-label="code"
                emit-value
                map-options
                behavior="menu"
                outlined
                dense
                hide-bottom-space
                class="exchange__unit"
                :aria-label="`Satuan ${line.name}`"
            />
            <q-input v-model.number="line.quantity" type="number" min="0" step="any" outlined dense hide-bottom-space class="exchange__qty" :aria-label="`Jumlah ${line.name}`" />
            <b class="exchange__subtotal">{{ formatRupiah(subtotalOf(line)) }}</b>
            <q-btn flat round dense icon="close" aria-label="Hapus" @click="lines.splice(index, 1)" />
        </div>

        <div v-if="lines.length > 0" class="exchange__sum">
            <div><span>Nilai barang pengganti</span><b>{{ formatRupiah(total) }}</b></div>
            <div><span>Nilai barang yang diretur</span><b>{{ formatRupiah(credit) }}</b></div>

            <template v-if="difference > 0">
                <div class="exchange__owe"><span>Pelanggan menambah</span><b>{{ formatRupiah(difference) }}</b></div>
                <div class="exchange__pay">
                    <q-select v-model="paymentMethod" :options="methodOptions" option-value="value" option-label="label" emit-value map-options behavior="menu" outlined dense label="Dibayar lewat" />
                    <q-input
                        :model-value="amountPaid"
                        type="number"
                        min="0"
                        step="any"
                        outlined
                        dense
                        label="Jumlah dibayar"
                        :error="isPaymentShort"
                        error-message="Pembayaran kurang dari tambahan."
                        @update:model-value="typedAmount = $event === '' ? 0 : Number($event)"
                    />
                </div>
            </template>

            <template v-else-if="difference < 0">
                <div class="exchange__owe"><span>Dikembalikan ke pelanggan</span><b>{{ formatRupiah(-difference) }}</b></div>
                <q-select v-model="refundMethod" :options="methodOptions" option-value="value" option-label="label" emit-value map-options behavior="menu" outlined dense label="Dikembalikan lewat" />
                <q-input v-if="refundMethod !== 'cash'" v-model="refundAccount" outlined dense label="Rekening penerima" placeholder="Bank, nomor rekening, atas nama" />
            </template>

            <p v-else class="exchange__hint">Nilainya pas, tidak ada tambahan atau kembalian.</p>
        </div>
    </div>
</template>

<style scoped>
.exchange {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.exchange__hint {
    margin: 0;
    color: var(--app-mute);
    font-size: 12.5px;
}

.exchange__line {
    display: flex;
    align-items: center;
    gap: 8px;
}

.exchange__what {
    flex: 1;
    min-width: 0;
}

.exchange__what small {
    display: block;
    color: var(--app-mute);
}

.exchange__unit {
    width: 100px;
}

.exchange__qty {
    width: 90px;
}

.exchange__subtotal {
    min-width: 96px;
    text-align: right;
}

.exchange__sum {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 10px 12px;
    border-radius: 6px;
    background: #f5f6f8;
}

.exchange__sum > div:not(.exchange__pay) {
    display: flex;
    justify-content: space-between;
}

.exchange__owe {
    font-weight: 700;
}

.exchange__pay {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
</style>
