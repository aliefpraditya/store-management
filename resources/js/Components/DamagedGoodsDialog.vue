<script setup>
import { computed, ref, watch } from 'vue';
import { marginFromPrice } from '../Composables/usePriceMargin';
import { formatQuantity } from '../Composables/useQuantityFormat';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /**
     * The item being decided: its product, base unit, the shortage found (positive) and what the server knows about its
     * DG product: the SKU it would take, a reason it cannot have one, and the price it has now.
     * @type {{productName: string, baseUnit: string, shortage: number, damagedGoodsSku: string, damagedGoodsProblem: string|null, existingName: string|null, existingSku: string|null, existingPrice: string|null, originalCost: string|null, originalPrice: string|null, isReturn?: boolean}|null}
     */
    item: { type: Object, default: null },
    /** The decision typed so far, to start from. @type {{dead: string, dg: string, full: string, price: string}} */
    decision: { type: Object, default: () => ({ dead: '', dg: '', full: '', price: '' }) },
});

const emit = defineEmits(['update:modelValue', 'save']);

const dead = ref('');
const dg = ref('');
const full = ref('');
const price = ref('');

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            dead.value = props.decision.dead;
            dg.value = props.decision.dg;
            full.value = props.decision.full;
            price.value = props.decision.price;
        }
    },
);

const number = (value) => (value === '' || value === null ? 0 : Number(value));

const decided = computed(() => Math.round((number(dead.value) + number(dg.value) + number(full.value)) * 1000) / 1000);

const remaining = computed(() => Math.round(((props.item?.shortage ?? 0) - decided.value) * 1000) / 1000);

const hasDamagedGoods = computed(() => number(dg.value) > 0);

const isDamagedGoodsBlocked = computed(() => hasDamagedGoods.value && props.item?.damagedGoodsProblem !== null);

const priceFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

/** What the typed DG price earns over the modal, as a share of that price, to price against. */
const typedMargin = computed(() => (props.item?.originalCost == null || price.value === '' ? null : marginFromPrice(props.item.originalCost, price.value)));

const hintOfPrice = computed(() => {
    const margin = typedMargin.value === null ? '' : `Margin ${priceFormat.format(typedMargin.value)}% dari modal. `;
    const other = props.item?.existingPrice ? `Harga barang DG sekarang ${priceFormat.format(props.item.existingPrice)}, diganti harga ini.` : 'Satuan lain mengikuti harga ini dikali konversinya.';

    return margin + other;
});

const canSave = computed(() => remaining.value === 0 && !isDamagedGoodsBlocked.value && (!hasDamagedGoods.value || (price.value !== '' && number(price.value) >= 0)));

/** Puts the whole shortage in one place, the quick answer when nothing is split. */
function putAllIn(target) {
    const all = String(props.item.shortage);

    dead.value = target === 'dead' ? all : '0';
    dg.value = target === 'dg' ? all : '0';
    full.value = target === 'full' ? all : '0';
}

function save() {
    emit('save', { dead: String(number(dead.value)), dg: String(number(dg.value)), full: String(number(full.value)), price: hasDamagedGoods.value ? String(price.value) : '' });
    emit('update:modelValue', false);
}
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card v-if="item" class="damaged">
            <q-card-section>
                <div class="damaged__title">{{ item.isReturn ? 'Keputusan barang retur' : 'Keputusan barang rusak' }}</div>
                <small v-if="item.isReturn" class="damaged__hint">{{ item.productName }} dihitung {{ formatQuantity(item.shortage) }} {{ item.baseUnit }} di gudang retur. Tentukan ke mana semuanya pergi sampai pas.</small>
                <small v-else class="damaged__hint">{{ item.productName }} kurang {{ formatQuantity(item.shortage) }} {{ item.baseUnit }}. Bagi jumlah itu sampai pas.</small>
            </q-card-section>

            <q-card-section class="damaged__fields">
                <q-input v-model="dead" type="number" min="0" step="any" outlined dense :suffix="item.baseUnit" label="Masuk dead stock" hint="Tidak dijual di kasir." />
                <q-input v-model="dg" type="number" min="0" step="any" outlined dense :suffix="item.baseUnit" label="Dijual sebagai barang DG" :hint="item.damagedGoodsProblem ? `Tidak bisa: ${item.damagedGoodsProblem}` : `Jadi barang baru ${item.damagedGoodsSku}.`" :error="isDamagedGoodsBlocked" />
                <template v-if="hasDamagedGoods && !item.damagedGoodsProblem">
                    <div v-if="item.existingSku" class="damaged__existing">
                        Barang DG sudah ada: <b>{{ item.existingName }}</b> ({{ item.existingSku }}).
                        <template v-if="item.existingPrice !== null">Harga jualnya sekarang <b>Rp {{ priceFormat.format(item.existingPrice) }}</b> per {{ item.baseUnit }}.</template>
                        Stok rusak ini ditambahkan ke sana.
                    </div>
                    <div v-if="item.originalPrice !== null" class="damaged__reference">
                        <div><span>Harga jual asli</span><b>Rp {{ priceFormat.format(item.originalPrice) }}</b></div>
                        <div><span>Modal</span><b>Rp {{ priceFormat.format(item.originalCost) }}</b></div>
                        <small>per {{ item.baseUnit }}</small>
                    </div>
                    <q-input v-model="price" type="number" min="0" step="any" outlined dense prefix="Rp" :label="`Harga jual DG per ${item.baseUnit}`" :hint="hintOfPrice" />
                </template>
                <q-input v-model="full" type="number" min="0" step="any" outlined dense :suffix="item.baseUnit" :label="item.isReturn ? 'Kembali ke stok jual' : 'Layak jual harga penuh'" :hint="item.isReturn ? 'Masih bagus, dikembalikan ke gudang asal barang.' : 'Ternyata tidak rusak, tetap di stok jual.'" />

                <div class="damaged__quick">
                    <q-btn flat dense no-caps size="sm" color="primary" label="Semua dead stock" @click="putAllIn('dead')" />
                    <q-btn flat dense no-caps size="sm" color="primary" :label="item.isReturn ? 'Semua kembali ke stok jual' : 'Semua layak jual penuh'" @click="putAllIn('full')" />
                </div>

                <div class="damaged__remaining" :class="{ 'damaged__remaining--ok': remaining === 0 }">
                    <template v-if="remaining === 0">Pas: semua {{ formatQuantity(item.shortage) }} {{ item.baseUnit }} sudah dibagi.</template>
                    <template v-else-if="remaining > 0">Belum dibagi: {{ formatQuantity(remaining) }} {{ item.baseUnit }}</template>
                    <template v-else>Kelebihan: {{ formatQuantity(-remaining) }} {{ item.baseUnit }}</template>
                </div>
            </q-card-section>

            <q-card-actions align="right">
                <q-btn flat no-caps label="Batal" @click="emit('update:modelValue', false)" />
                <q-btn color="primary" unelevated no-caps label="Pakai keputusan" :disable="!canSave" @click="save" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.damaged {
    width: 460px;
    max-width: 94vw;
}

.damaged__title {
    font-size: 17px;
    font-weight: 700;
}

.damaged__hint {
    color: var(--app-mute);
}

.damaged__fields {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.damaged :deep(input[type='number']) {
    appearance: textfield;
    -moz-appearance: textfield;
}

.damaged :deep(input[type='number']::-webkit-outer-spin-button),
.damaged :deep(input[type='number']::-webkit-inner-spin-button) {
    margin: 0;
    appearance: none;
    -webkit-appearance: none;
}

.damaged__existing {
    padding: 8px 12px;
    border-radius: 10px;
    background: #fdf1e0;
    color: #c2710c;
    font-size: 13px;
}

.damaged__reference {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 4px 18px;
    padding: 8px 12px;
    border-radius: 10px;
    background: #f6f6f7;
}

.damaged__reference span {
    margin-right: 6px;
    color: var(--app-mute);
    font-size: 12.5px;
}

.damaged__reference small {
    color: var(--app-mute);
}

.damaged__quick {
    display: flex;
    gap: 8px;
}

.damaged__remaining {
    padding: 8px 12px;
    border-radius: 10px;
    background: #fdf1e0;
    color: #c2710c;
    font-weight: 600;
}

.damaged__remaining--ok {
    background: #e7f8f1;
    color: #059669;
}
</style>
