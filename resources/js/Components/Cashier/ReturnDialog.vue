<script setup>
import { computed, ref, watch } from 'vue';
import { useQuasar } from 'quasar';
import { postJson } from '../../Composables/postJson';
import { printInNewWindow } from '../../Composables/printInNewWindow';
import { formatRupiah } from '../../Composables/useMoneyFormat';
import { formatQuantity } from '../../Composables/useQuantityFormat';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** The receipt the return is for. @type {{number: string, return_url: string, return_store_url: string}|null} */
    receipt: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const $q = useQuasar();

const isLoading = ref(false);
const isSaving = ref(false);
const printingId = ref(null);

/** @type {import('vue').Ref<{lines: object[], returns: object[], refund_methods: object[], dispositions: object[], is_approved_at_once: boolean}>} */
const data = ref({ lines: [], returns: [], refund_methods: [], dispositions: [], is_approved_at_once: false });

const quantities = ref({});
const dispositions = ref({});
const refundMethod = ref('cash');
const reason = ref('');

const hasAnythingLeft = computed(() => data.value.lines.some((line) => Number(line.remaining) > 0));

const hasAnythingToReturn = computed(() => data.value.lines.some((line) => Number(quantities.value[line.id]) > 0));

const refundTotal = computed(() => data.value.lines.reduce((sum, line) => sum + (Number(quantities.value[line.id]) || 0) * Number(line.unit_price), 0));

const canSave = computed(() => hasAnythingToReturn.value && !isSaving.value);

function fail(error) {
    $q.notify({ type: 'negative', message: error.message, position: 'top' });
}

/**
 * Loads the receipt's lines and returns. Nothing is returned until a quantity is typed, and goods go back on the
 * shelf unless the cashier says otherwise.
 */
async function load() {
    isLoading.value = true;

    try {
        const response = await fetch(props.receipt.return_url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error('Retur tidak bisa dimuat.');
        }

        data.value = (await response.json()).data;
        quantities.value = Object.fromEntries(data.value.lines.map((line) => [line.id, 0]));
        dispositions.value = Object.fromEntries(data.value.lines.map((line) => [line.id, 'resellable']));
        refundMethod.value = data.value.default_refund_method;
    } catch (error) {
        fail(error);
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        reason.value = '';
        data.value = { lines: [], returns: [], refund_methods: [], dispositions: [], is_approved_at_once: false };
        load();
    },
);

/**
 * Sends the return. A cashier's waits for an admin; an admin's is done at once, and the stock is back.
 */
async function save() {
    isSaving.value = true;

    try {
        const saleReturn = await postJson(props.receipt.return_store_url, {
            refund_method: refundMethod.value,
            reason: reason.value,
            items: data.value.lines.map((line) => ({ sale_item_id: line.id, quantity: Number(quantities.value[line.id]) || 0, disposition: dispositions.value[line.id] })),
        });

        $q.notify({
            type: 'positive',
            position: 'top',
            message: saleReturn.status === 'pending' ? `Retur ${saleReturn.number} diajukan, menunggu persetujuan Admin.` : `Retur ${saleReturn.number} selesai.`,
        });
        emit('saved');
        await load();
    } catch (error) {
        fail(error);
    } finally {
        isSaving.value = false;
    }
}

/** Opens the return slip in a new window, which prints itself. */
async function print(saleReturn) {
    printingId.value = saleReturn.id;

    try {
        await printInNewWindow(async () => saleReturn.sheet_url);
    } catch (error) {
        fail(error);
    } finally {
        printingId.value = null;
    }
}

const statusChipClass = { pending: 'chip--warn', completed: 'chip--ok', rejected: 'chip--bad' };
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card v-if="receipt" class="return">
            <q-card-section class="return__head">
                <div class="return__title">Retur</div>
                <small>Nota {{ receipt.number }}</small>
            </q-card-section>

            <q-card-section class="return__body">
                <q-inner-loading :showing="isLoading" />

                <template v-if="data.returns.length > 0">
                    <div class="return__label">Sudah diajukan</div>
                    <div v-for="saleReturn in data.returns" :key="saleReturn.id" class="return__card">
                        <div class="return__row">
                            <b>{{ saleReturn.number }}</b>
                            <span class="chip" :class="statusChipClass[saleReturn.status]">{{ saleReturn.status_label }}</span>
                            <q-btn outline no-caps dense color="primary" icon="print" label="Cetak" :loading="printingId === saleReturn.id" class="return__print" @click="print(saleReturn)" />
                        </div>
                        <ul class="return__items">
                            <li v-for="item in saleReturn.items" :key="item.id">
                                <span>{{ item.name }}</span>
                                <span>{{ formatQuantity(item.quantity) }} {{ item.unit }} <small>{{ item.disposition_label }}</small></span>
                            </li>
                        </ul>
                        <small class="return__sum">{{ formatRupiah(saleReturn.refund_amount) }} lewat {{ saleReturn.refund_method_label }}</small>
                    </div>
                </template>

                <template v-if="hasAnythingLeft">
                    <div class="return__label">Retur baru</div>
                    <div v-for="line in data.lines" :key="line.id" class="return__line">
                        <span class="return__what">{{ line.name }}<small>sisa {{ formatQuantity(line.remaining) }} {{ line.unit }} · {{ formatRupiah(line.unit_price) }}</small></span>
                        <q-input
                            v-model.number="quantities[line.id]"
                            type="number"
                            min="0"
                            step="any"
                            outlined
                            dense
                            hide-bottom-space
                            :suffix="line.unit"
                            :disable="Number(line.remaining) <= 0"
                            :aria-label="`Jumlah retur ${line.name}`"
                            class="return__qty"
                        />
                        <q-select
                            v-model="dispositions[line.id]"
                            :options="data.dispositions"
                            option-value="value"
                            option-label="label"
                            emit-value
                            map-options
                            outlined
                            dense
                            hide-bottom-space
                            :disable="Number(line.remaining) <= 0"
                            :aria-label="`Tujuan ${line.name}`"
                            class="return__where"
                        />
                    </div>

                    <q-select v-model="refundMethod" :options="data.refund_methods" option-value="value" option-label="label" emit-value map-options outlined dense label="Uang dikembalikan lewat" />
                    <q-input v-model="reason" outlined dense label="Alasan (opsional)" />

                    <div class="return__total"><span>Total dikembalikan</span><b>{{ formatRupiah(refundTotal) }}</b></div>
                    <p class="return__hint">
                        <template v-if="data.is_approved_at_once">Retur Anda langsung selesai: stok kembali dan retur tunai keluar dari laci shift ini.</template>
                        <template v-else>Retur menunggu persetujuan Admin. Stok dan kas baru berubah setelah disetujui, dan shift tidak bisa ditutup sebelum itu.</template>
                    </p>
                </template>
                <p v-else-if="!isLoading" class="return__done">Semua barang di nota ini sudah diretur.</p>
            </q-card-section>

            <q-card-actions align="right">
                <q-btn flat no-caps label="Tutup" v-close-popup />
                <q-btn v-if="hasAnythingLeft" color="primary" unelevated no-caps :label="data.is_approved_at_once ? 'Simpan Retur' : 'Ajukan Retur'" :disable="!canSave" :loading="isSaving" @click="save" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.return {
    display: flex;
    flex-direction: column;
    width: 620px;
    max-width: 94vw;
    max-height: 90vh;
}

.return__title {
    font-size: 17px;
    font-weight: 700;
}

.return__head small,
.return__sum {
    color: var(--app-mute);
}

.return__body {
    position: relative;
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 10px;
    min-height: 120px;
    overflow-y: auto;
}

.return__label {
    margin-top: 4px;
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.return__card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 8px 10px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
}

.return__row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.return__print {
    margin-left: auto;
}

.return__items {
    margin: 0;
    padding: 0;
    list-style: none;
    font-size: 13px;
}

.return__items li {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.return__items small {
    margin-left: 4px;
    color: var(--app-mute);
}

.return__line {
    display: flex;
    align-items: center;
    gap: 10px;
}

.return__what {
    flex: 1;
}

.return__what small {
    display: block;
    color: var(--app-mute);
}

.return__qty {
    width: 130px;
}

.return__where {
    width: 130px;
}

.return__total {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
}

.return__total b {
    font-size: 20px;
}

.return__hint,
.return__done {
    margin: 0;
    color: var(--app-mute);
    font-size: 12.5px;
}
</style>
