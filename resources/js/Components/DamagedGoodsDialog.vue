<script setup>
import { computed, ref, watch } from 'vue';
import { marginFromPrice } from '../Composables/usePriceMargin';
import { formatQuantity, formatStock } from '../Composables/useQuantityFormat';
import { baseQuantityOf, sealedPacksOf, splitIntoParts } from '../Composables/useUnitParts';
import UnitPartsInput from './UnitPartsInput.vue';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /**
     * The item being decided: its product, units, the shortage found (positive, in the base unit) and what the server knows
     * about its DG product: the SKU it would take, a reason it cannot have one, and the price it has now. `countedPacks` is
     * how many whole sealed packs (rolls) the shortage holds, for a product that has such a unit, and `maxPacks` how many whole
     * packs the decision can divide in all (null for a product without a sealed pack unit).
     * @type {{productName: string, baseUnit: string, units: {code: string, conversion_factor: string, is_sealed_pack?: boolean}[], shortage: number, countedPacks?: number|null, maxPacks?: number|null, damagedGoodsSku: string, damagedGoodsProblem: string|null, existingName: string|null, existingSku: string|null, existingPrice: string|null, originalCost: string|null, originalPrice: string|null, isReturn?: boolean}|null}
     */
    item: { type: Object, default: null },
    /**
     * The decision typed so far, to start from; the `...Packs` are how many whole sealed packs each part holds ('' when not said).
     * @type {{dead: string, dg: string, full: string, price: string, deadPacks?: string, dgPacks?: string, fullPacks?: string}}
     */
    decision: { type: Object, default: () => ({ dead: '', dg: '', full: '', price: '' }) },
});

const emit = defineEmits(['update:modelValue', 'save']);

/**
 * Each place the units can go is typed in any of the product's units (1 ROL + 30 MTR). The decision is sent as the quantity in
 * the base unit and how many of it are whole sealed packs, so a roll sent to dead stock or DG stays a roll there.
 */
const deadParts = ref([]);
const dgParts = ref([]);
const fullParts = ref([]);
const price = ref('');

const isSealedPackProduct = computed(() => props.item?.units.some((unit) => unit.is_sealed_pack) ?? false);

/** @param {string|undefined} packs the saved number of whole packs, '' or undefined when it was not said */
const packsOrNull = (packs) => (packs === undefined || packs === '' || !isSealedPackProduct.value ? null : Number(packs));

const partsOf = (baseQuantity, packs = null) => splitIntoParts(baseQuantity, props.item.units, props.item.baseUnit, packs);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            deadParts.value = partsOf(props.decision.dead, packsOrNull(props.decision.deadPacks));
            dgParts.value = partsOf(props.decision.dg, packsOrNull(props.decision.dgPacks));
            fullParts.value = partsOf(props.decision.full, packsOrNull(props.decision.fullPacks));
            price.value = props.decision.price;
        }
    },
);

const baseOf = (parts) => baseQuantityOf(parts, props.item?.units ?? []);

const dead = computed(() => baseOf(deadParts.value));
const dg = computed(() => baseOf(dgParts.value));
const full = computed(() => baseOf(fullParts.value));

const deadPacks = computed(() => sealedPacksOf(deadParts.value, props.item?.units ?? []));
const dgPacks = computed(() => sealedPacksOf(dgParts.value, props.item?.units ?? []));
const fullPacks = computed(() => sealedPacksOf(fullParts.value, props.item?.units ?? []));

const typedPacks = computed(() => (isSealedPackProduct.value ? [deadPacks.value, dgPacks.value, fullPacks.value] : []));

const arePacksWhole = computed(() => typedPacks.value.every((packs) => Number.isInteger(packs)));

const isOverPacks = computed(() => isSealedPackProduct.value && typedPacks.value.reduce((sum, packs) => sum + packs, 0) > (props.item?.maxPacks ?? 0));

const packUnitCode = computed(() => props.item?.units.find((unit) => unit.is_sealed_pack)?.code ?? '');

/** A quantity in the base unit written in the product's units, e.g. 130 as "1 ROL + 30 MTR". */
const textOf = (baseQuantity, packs = null) => (props.item.units.length > 1 ? formatStock(baseQuantity, props.item.units, props.item.baseUnit, packs) : `${formatQuantity(baseQuantity)} ${props.item.baseUnit}`);

const number = (value) => (value === '' || value === null ? 0 : Number(value));

const decided = computed(() => Math.round((dead.value + dg.value + full.value) * 1000) / 1000);

const remaining = computed(() => Math.round(((props.item?.shortage ?? 0) - decided.value) * 1000) / 1000);

const hasDamagedGoods = computed(() => dg.value > 0);

const isDamagedGoodsBlocked = computed(() => hasDamagedGoods.value && props.item?.damagedGoodsProblem !== null);

const priceFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

/** What the typed DG price earns over the modal, as a share of that price, to price against. */
const typedMargin = computed(() => (props.item?.originalCost == null || price.value === '' ? null : marginFromPrice(props.item.originalCost, price.value)));

const hintOfPrice = computed(() => {
    const margin = typedMargin.value === null ? '' : `Margin ${priceFormat.format(typedMargin.value)}% dari modal. `;
    const other = props.item?.existingPrice ? `Harga barang DG sekarang ${priceFormat.format(props.item.existingPrice)}, diganti harga ini.` : 'Satuan lain mengikuti harga ini dikali konversinya.';

    return margin + other;
});

const canSave = computed(
    () => remaining.value === 0 && arePacksWhole.value && !isOverPacks.value && !isDamagedGoodsBlocked.value && (!hasDamagedGoods.value || (price.value !== '' && number(price.value) >= 0)),
);

/** Puts the whole shortage in one place, the quick answer when nothing is split; the whole packs there are go along with it. */
function putAllIn(target) {
    const packFactor = Number(props.item.units.find((unit) => unit.is_sealed_pack)?.conversion_factor ?? 0);
    const allPacks = isSealedPackProduct.value ? Math.min(props.item.maxPacks ?? 0, Math.floor(props.item.shortage / packFactor + 1e-9)) : null;
    const all = partsOf(String(props.item.shortage), allPacks);
    const none = partsOf('0', isSealedPackProduct.value ? 0 : null);

    deadParts.value = target === 'dead' ? all : none;
    dgParts.value = target === 'dg' ? all : none;
    fullParts.value = target === 'full' ? all : none;
}

const packsText = (packs) => (isSealedPackProduct.value ? String(packs) : '');

function save() {
    emit('save', {
        dead: String(dead.value),
        dg: String(dg.value),
        full: String(full.value),
        price: hasDamagedGoods.value ? String(price.value) : '',
        deadPacks: packsText(deadPacks.value),
        dgPacks: packsText(dgPacks.value),
        fullPacks: packsText(fullPacks.value),
    });
    emit('update:modelValue', false);
}
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card v-if="item" class="damaged">
            <q-card-section>
                <div class="damaged__title">{{ item.isReturn ? 'Keputusan barang retur' : 'Keputusan barang rusak' }}</div>
                <small v-if="item.isReturn" class="damaged__hint">{{ item.productName }} dihitung {{ textOf(item.shortage, item.countedPacks ?? null) }} di gudang retur. Tentukan ke mana semuanya pergi sampai pas.</small>
                <small v-else class="damaged__hint">{{ item.productName }} kurang {{ textOf(item.shortage) }}. Bagi jumlah itu sampai pas.</small>
            </q-card-section>

            <q-card-section class="damaged__fields">
                <UnitPartsInput v-model="deadParts" :units="item.units" label="Masuk dead stock" hint="Tidak dijual di kasir." />
                <UnitPartsInput v-model="dgParts" :units="item.units" label="Dijual sebagai barang DG" :hint="item.damagedGoodsProblem ? `Tidak bisa: ${item.damagedGoodsProblem}` : `Jadi barang baru ${item.damagedGoodsSku}.`" :is-error="isDamagedGoodsBlocked" />
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
                <UnitPartsInput
                    v-model="fullParts"
                    :units="item.units"
                    :label="item.isReturn ? 'Kembali ke stok jual' : 'Layak jual harga penuh'"
                    :hint="item.isReturn ? 'Masih bagus, dikembalikan ke gudang asal barang.' : 'Ternyata tidak rusak, tetap di stok jual.'"
                />

                <div class="damaged__quick">
                    <q-btn flat dense no-caps size="sm" color="primary" label="Semua dead stock" @click="putAllIn('dead')" />
                    <q-btn flat dense no-caps size="sm" color="primary" :label="item.isReturn ? 'Semua kembali ke stok jual' : 'Semua layak jual penuh'" @click="putAllIn('full')" />
                </div>

                <div class="damaged__remaining" :class="{ 'damaged__remaining--ok': remaining === 0 }">
                    <template v-if="remaining === 0">Pas: semua {{ textOf(item.shortage, item.countedPacks ?? null) }} sudah dibagi.</template>
                    <template v-else-if="remaining > 0">Belum dibagi: {{ textOf(remaining) }}</template>
                    <template v-else>Kelebihan: {{ textOf(-remaining) }}</template>
                </div>
                <div v-if="isSealedPackProduct && (!arePacksWhole || isOverPacks)" class="damaged__remaining">
                    <template v-if="!arePacksWhole">{{ packUnitCode }} harus bilangan bulat; sisa potongan ditulis dalam satuan yang lebih kecil.</template>
                    <template v-else>{{ packUnitCode }} utuh yang dibagi melebihi yang ada ({{ item.maxPacks ?? 0 }} {{ packUnitCode }}). Potongan yang sudah terpakai tidak bisa jadi {{ packUnitCode }} lagi.</template>
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
