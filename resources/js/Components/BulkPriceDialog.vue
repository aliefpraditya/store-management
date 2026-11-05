<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { postJson } from '../Composables/postJson';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** The items ticked on the item list. @type {number[]} */
    productIds: { type: Array, required: true },
    /** Codes of the units a change can be limited to. @type {string[]} */
    units: { type: Array, required: true },
    /** @type {{value: string, label: string}[]} */
    costMethods: { type: Array, required: true },
    /** @type {{value: string, label: string, takes_value: boolean}[]} */
    sellingMethods: { type: Array, required: true },
    /** @type {{preview: string, apply: string}} */
    urls: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue', 'applied']);

/** How long typing has to pause before the preview is worked out again. */
const PREVIEW_DELAY_MS = 450;

const priceFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

/** An empty string means "not chosen": a select cannot show its first option while its model is null. */
const unitCode = ref('');
const costMethod = ref('');
const costValue = ref('');
const sellingMethod = ref('');
const sellingValue = ref('');

const preview = ref(null);
const previewedKey = ref(null);
const previewError = ref(null);
const isPreviewing = ref(false);
const isApplying = ref(false);

const sellingMethodOption = computed(() => props.sellingMethods.find((method) => method.value === sellingMethod.value) ?? null);

const valueLabel = (method, setLabel) => {
    if (method === 'percent') {
        return 'Persen (5 = naik 5%, -3 = turun 3%)';
    }

    return method === 'set' ? setLabel : 'Rupiah (negatif = turun)';
};

const costValueLabel = computed(() => valueLabel(costMethod.value, 'Harga beli baru (Rp)'));

const sellingValueLabel = computed(() => (sellingMethod.value === 'margin' ? 'Margin (dari harga jual)' : valueLabel(sellingMethod.value, 'Harga jual baru (Rp)')));

const isSetChosen = computed(() => costMethod.value === 'set' || sellingMethod.value === 'set');

const isFilled = (value) => value !== '' && value !== null && value !== undefined;

const isChangeComplete = computed(
    () => (costMethod.value !== '' || sellingMethod.value !== '')
        && (costMethod.value === '' || isFilled(costValue.value))
        && (sellingMethod.value === '' || !sellingMethodOption.value.takes_value || isFilled(sellingValue.value))
        && !(sellingMethod.value === 'keep_margin' && costMethod.value === ''),
);

const changeHint = computed(() => (sellingMethod.value === 'keep_margin' && costMethod.value === '' ? 'Mempertahankan margin hanya berarti bila harga beli ikut diubah.' : null));

/** Everything the server needs to work out the same change for the preview and the apply. */
const payload = computed(() => ({
    product_ids: props.productIds,
    unit_code: unitCode.value || null,
    cost_method: costMethod.value || null,
    cost_value: costMethod.value === '' ? null : costValue.value,
    selling_method: sellingMethod.value || null,
    selling_value: sellingMethod.value === '' || !sellingMethodOption.value.takes_value ? null : sellingValue.value,
}));

const payloadKey = computed(() => JSON.stringify(payload.value));

/** The preview on screen belongs to what is typed now, so it is what Terapkan would do. */
const isPreviewCurrent = computed(() => preview.value !== null && previewedKey.value === payloadKey.value);

const canApply = computed(() => isPreviewCurrent.value && preview.value.totals.units_changed > 0 && !isApplying.value);

let previewTimer;
let previewRequest = 0;

/**
 * Works the preview out for the change as typed. An answer that arrives after the change was edited again is dropped,
 * so a slow answer never shows over a newer one.
 */
async function loadPreview() {
    const requestNumber = ++previewRequest;
    const key = payloadKey.value;

    isPreviewing.value = true;
    previewError.value = null;

    try {
        const result = await postJson(props.urls.preview, payload.value);

        if (requestNumber === previewRequest) {
            preview.value = result;
            previewedKey.value = key;
        }
    } catch (error) {
        if (requestNumber === previewRequest) {
            preview.value = null;
            previewError.value = error.message;
        }
    } finally {
        if (requestNumber === previewRequest) {
            isPreviewing.value = false;
        }
    }
}

function schedulePreview() {
    clearTimeout(previewTimer);
    previewRequest++;

    if (!props.modelValue || !isChangeComplete.value || props.productIds.length === 0) {
        preview.value = null;
        previewError.value = null;
        isPreviewing.value = false;

        return;
    }

    isPreviewing.value = true;
    previewTimer = setTimeout(loadPreview, PREVIEW_DELAY_MS);
}

watch([payloadKey, () => props.modelValue], schedulePreview);

onBeforeUnmount(() => clearTimeout(previewTimer));

function apply() {
    router.post(props.urls.apply, payload.value, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (isApplying.value = true),
        onFinish: () => (isApplying.value = false),
        onSuccess: () => {
            emit('applied');
            emit('update:modelValue', false);
        },
    });
}

/** Units worth showing in the preview: the ones that change or cannot be changed. */
const affectedUnits = (product) => product.units.filter((unit) => unit.is_changed || unit.skip_reason !== null);

const isDifferent = (oldValue, newValue) => oldValue !== newValue;
</script>

<template>
    <q-dialog :model-value="modelValue" persistent @update:model-value="emit('update:modelValue', $event)">
        <q-card class="bulk">
            <q-card-section>
                <div class="bulk__title">Ubah harga {{ productIds.length }} barang</div>
                <small class="bulk__hint">Atur perubahannya, pratinjau muncul di sebelahnya. Harga dihitung per satuan dan dibulatkan ke sen saja.</small>
            </q-card-section>

            <q-card-section class="bulk__body">
                <div class="bulk__fields">
                    <q-select
                        v-model="unitCode"
                        :options="[{ label: 'Semua satuan', value: '' }, ...units.map((code) => ({ label: `Hanya satuan ${code}`, value: code }))]"
                        emit-value
                        map-options
                        outlined
                        dense
                        label="Satuan yang diubah"
                    />

                    <div class="bulk__pair">
                        <q-select v-model="costMethod" :options="[{ label: 'Harga beli tidak diubah', value: '' }, ...costMethods]" emit-value map-options outlined dense label="Harga beli (modal)" />
                        <q-input
                            v-if="costMethod"
                            v-model="costValue"
                            type="number"
                            step="any"
                            outlined
                            dense
                            :label="costValueLabel"
                            :prefix="costMethod === 'nominal' || costMethod === 'set' ? 'Rp' : undefined"
                            :suffix="costMethod === 'percent' ? '%' : undefined"
                        />
                    </div>

                    <div class="bulk__pair">
                        <q-select v-model="sellingMethod" :options="[{ label: 'Harga jual tidak diubah', value: '' }, ...sellingMethods]" emit-value map-options outlined dense label="Harga jual" />
                        <q-input
                            v-if="sellingMethodOption?.takes_value"
                            v-model="sellingValue"
                            type="number"
                            step="any"
                            outlined
                            dense
                            :label="sellingValueLabel"
                            :prefix="sellingMethod === 'nominal' || sellingMethod === 'set' ? 'Rp' : undefined"
                            :suffix="sellingMethod === 'percent' || sellingMethod === 'margin' ? '%' : undefined"
                        />
                    </div>

                    <p v-if="changeHint" class="bulk__note bulk__note--bad">{{ changeHint }}</p>
                    <p v-else-if="isSetChosen" class="bulk__note">Setarakan membuat tiap satuan yang diubah memakai harga yang sama. Batasi ke satu satuan lewat <i>Satuan yang diubah</i> bila harganya beda per satuan.</p>
                    <p v-else-if="sellingMethod === 'margin'" class="bulk__note">Harga jual = modal ÷ (1 − margin). Bila modal ikut diubah, dipakai modal yang baru.</p>
                    <p v-else-if="sellingMethod === 'keep_margin'" class="bulk__note">Margin tiap satuan sekarang dijaga: harga jual naik atau turun mengikuti modal yang baru.</p>
                </div>

                <div class="bulk__preview" aria-live="polite">
                    <p v-if="previewError" class="bulk__note bulk__note--bad">{{ previewError }}</p>
                    <p v-else-if="!isChangeComplete" class="bulk__note">Isi perubahannya di sebelah kiri, lalu pratinjau muncul di sini.</p>
                    <template v-else-if="preview">
                        <p class="bulk__summary" :class="{ 'bulk__summary--stale': isPreviewing }">
                            <b>{{ preview.totals.products_changed }}</b> barang berubah ({{ preview.totals.units_changed }} satuan) dari {{ preview.totals.products_selected }} yang dipilih.
                            <span v-if="preview.totals.units_skipped > 0" class="bulk__skipped">{{ preview.totals.units_skipped }} satuan dilewati.</span>
                            <span v-if="isPreviewing" class="bulk__calculating">Menghitung ulang…</span>
                        </p>

                        <div class="bulk__list" :class="{ 'bulk__list--stale': isPreviewing }">
                            <p v-if="preview.products.length === 0" class="bulk__note">Tidak ada harga yang berubah dengan pengaturan ini.</p>
                            <table v-else class="bulk__table">
                                <thead>
                                    <tr>
                                        <th>Barang</th>
                                        <th>Satuan</th>
                                        <th class="bulk__right">Modal</th>
                                        <th class="bulk__right">Harga jual</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="product in preview.products" :key="product.id">
                                        <tr v-for="(unit, unitIndex) in affectedUnits(product)" :key="unit.unit_id">
                                            <td>
                                                <template v-if="unitIndex === 0"><b>{{ product.name }}</b><small>{{ product.sku }}</small></template>
                                            </td>
                                            <td>{{ unit.code }}</td>
                                            <td v-if="unit.skip_reason" colspan="2" class="bulk__skipped">Dilewati: {{ unit.skip_reason }}</td>
                                            <template v-else>
                                                <td class="bulk__right">
                                                    <template v-if="isDifferent(unit.old_cost, unit.new_cost)">{{ priceFormat.format(unit.old_cost) }} → <b>{{ priceFormat.format(unit.new_cost) }}</b></template>
                                                    <template v-else>{{ priceFormat.format(unit.old_cost) }}</template>
                                                </td>
                                                <td class="bulk__right">
                                                    <template v-if="isDifferent(unit.old_price, unit.new_price)">{{ priceFormat.format(unit.old_price) }} → <b>{{ priceFormat.format(unit.new_price) }}</b></template>
                                                    <template v-else>{{ priceFormat.format(unit.old_price) }}</template>
                                                </td>
                                            </template>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <p v-if="preview.is_truncated" class="bulk__note">Hanya sebagian barang yang ditampilkan. Semuanya tetap ikut berubah.</p>
                        </div>
                    </template>
                    <p v-else class="bulk__note"><q-spinner size="16px" class="q-mr-sm" />Menghitung pratinjau…</p>
                </div>
            </q-card-section>

            <q-card-actions align="right">
                <q-btn flat no-caps label="Batal" :disable="isApplying" @click="emit('update:modelValue', false)" />
                <q-btn
                    color="primary"
                    unelevated
                    no-caps
                    :label="preview ? `Terapkan ke ${preview.totals.products_changed} barang` : 'Terapkan'"
                    :loading="isApplying"
                    :disable="!canApply"
                    @click="apply"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.bulk {
    width: 1040px;
    max-width: 96vw;
}

.bulk :deep(input[type='number']) {
    appearance: textfield;
    -moz-appearance: textfield;
}

.bulk :deep(input[type='number']::-webkit-outer-spin-button),
.bulk :deep(input[type='number']::-webkit-inner-spin-button) {
    margin: 0;
    appearance: none;
    -webkit-appearance: none;
}

.bulk__title {
    font-size: 17px;
    font-weight: 700;
}

.bulk__hint {
    color: var(--app-mute);
}

.bulk__body {
    display: grid;
    grid-template-columns: minmax(280px, 340px) 1fr;
    gap: 20px;
    padding-top: 0;
}

.bulk__fields {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.bulk__pair {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.bulk__preview {
    min-width: 0;
    padding-left: 20px;
    border-left: 1px solid var(--app-line);
}

.bulk__note {
    margin: 0;
    color: var(--app-mute);
    font-size: 12.5px;
}

.bulk__note--bad {
    color: #be123c;
}

.bulk__summary {
    margin: 0 0 8px;
}

.bulk__summary--stale,
.bulk__list--stale {
    opacity: 0.55;
}

.bulk__calculating {
    margin-left: 8px;
    color: var(--app-mute);
    font-size: 12.5px;
}

.bulk__skipped {
    color: #be123c;
}

.bulk__list {
    max-height: 52vh;
    overflow-y: auto;
}

.bulk__table {
    width: 100%;
    border-collapse: collapse;
}

.bulk__table th,
.bulk__table td {
    padding: 6px 8px;
    border-bottom: 1px solid var(--app-line);
    text-align: left;
    vertical-align: top;
}

.bulk__table th {
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
}

.bulk__table small {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
}

.bulk__table .bulk__right {
    text-align: right;
    white-space: nowrap;
}

@media (max-width: 820px) {
    .bulk__body {
        grid-template-columns: 1fr;
    }

    .bulk__preview {
        padding-left: 0;
        border-left: 0;
    }
}
</style>
