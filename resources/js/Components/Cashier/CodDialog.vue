<script setup>
import { ref, watch } from 'vue';
import { useQuasar } from 'quasar';
import { postJson } from '../../Composables/postJson';
import { formatRupiah } from '../../Composables/useMoneyFormat';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** Where the COD sales still waiting for their delivery are fetched. */
    listUrl: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue', 'changed']);

const $q = useQuasar();

const isLoading = ref(false);
const isWorking = ref(false);

/** @type {import('vue').Ref<object[]>} */
const sales = ref([]);

/** How many COD sales wait in all; more than the list holds when only the newest are shown. */
const totalWaiting = ref(0);

/** The sale being voided, or null while the void window is closed. */
const voidTarget = ref(null);
const voidReason = ref('');
const refundAccount = ref('');

const dateFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });

function fail(error) {
    $q.notify({ type: 'negative', message: error.message, position: 'top' });
}

async function load() {
    isLoading.value = true;

    try {
        const response = await fetch(props.listUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error('Daftar COD tidak bisa dimuat.');
        }

        const body = await response.json();

        sales.value = body.data;
        totalWaiting.value = body.meta.total;
    } catch (error) {
        fail(error);
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            sales.value = [];
            load();
        }
    },
);

/** The customer took the goods: the delivery note is finished as delivered, after the cashier confirms. */
function confirmDelivered(sale) {
    $q.dialog({
        title: 'Barang sudah diantar?',
        message: `Nota ${sale.number} ditandai sudah diantar dan diterima pelanggan. Setelah itu nota tidak bisa di-void.`,
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Sudah Diantar', color: 'primary', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => act(sale.delivered_url, {}, `Nota ${sale.number} ditandai sudah diantar.`));
}

function openVoid(sale) {
    voidTarget.value = sale;
    voidReason.value = '';
    refundAccount.value = '';
}

const canVoid = () => voidReason.value.trim() !== '' && (!voidTarget.value?.has_non_cash_deposit || refundAccount.value.trim() !== '') && !isWorking.value;

/** The void window stays open when the server refuses, so the reason that was typed is not lost. */
async function submitVoid() {
    const sale = voidTarget.value;

    const isDone = await act(sale.void_url, { reason: voidReason.value.trim(), refund_account: refundAccount.value.trim() || null }, `Nota ${sale.number} di-void. Barangnya kembali ke stok.`);

    if (isDone) {
        voidTarget.value = null;
    }
}

/**
 * Posts one action on a COD sale and reloads the list.
 *
 * @returns {Promise<boolean>} whether the server accepted it
 */
async function act(url, body, successMessage) {
    isWorking.value = true;

    try {
        await postJson(url, body);
        $q.notify({ type: 'positive', position: 'top', message: successMessage });
        emit('changed');
        await load();

        return true;
    } catch (error) {
        fail(error);

        return false;
    } finally {
        isWorking.value = false;
    }
}
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card class="cod">
            <q-card-section class="cod__head">
                <div class="cod__title">Nota COD yang belum diantar</div>
                <small>Dari semua hari. Tandai sudah diantar, atau void bila pelanggan tidak mau menerima atau membayar.</small>
            </q-card-section>

            <q-card-section class="cod__body">
                <q-inner-loading :showing="isLoading" />

                <p v-if="!isLoading && sales.length === 0" class="cod__empty">Tidak ada nota COD yang menunggu.</p>
                <p v-if="totalWaiting > sales.length" class="cod__more">Menampilkan {{ sales.length }} nota terbaru dari {{ totalWaiting }}. Yang lebih lama muncul setelah yang di atas selesai atau di-void.</p>

                <div v-for="sale in sales" :key="sale.id" class="sale">
                    <div class="sale__main">
                        <b>{{ sale.number }}</b>
                        <small>
                            {{ dateFormat.format(new Date(sale.sold_at)) }} · {{ sale.recipient_name || sale.customer_name || 'tanpa nama' }} · {{ sale.delivery_status_label }}
                        </small>
                        <small v-if="Number(sale.deposit_amount) > 0">DP sudah diterima {{ formatRupiah(sale.deposit_amount) }}</small>
                    </div>
                    <b class="sale__amount">{{ formatRupiah(sale.cod_amount) }}</b>
                    <div class="sale__actions">
                        <q-btn v-if="sale.can_mark_delivered" outline dense no-caps color="primary" label="Sudah diantar" :disable="isWorking" @click="confirmDelivered(sale)" />
                        <q-btn outline dense no-caps color="negative" label="Void" :disable="isWorking" @click="openVoid(sale)" />
                    </div>
                </div>
            </q-card-section>

            <q-card-actions align="right">
                <q-btn flat no-caps label="Tutup" v-close-popup />
            </q-card-actions>
        </q-card>

        <q-dialog :model-value="voidTarget !== null" persistent @update:model-value="voidTarget = null">
            <q-card v-if="voidTarget" class="void">
                <q-card-section>
                    <div class="cod__title">Void nota {{ voidTarget.number }}?</div>
                    <small class="void__hint">Barangnya kembali ke stok dan surat jalan yang masih berjalan dibatalkan.</small>
                </q-card-section>

                <q-card-section class="void__fields">
                    <q-input v-model="voidReason" outlined dense autofocus maxlength="255" label="Alasan void" />
                    <template v-if="Number(voidTarget.deposit_amount) > 0">
                        <p v-if="voidTarget.has_cash_deposit" class="void__note">DP tunai dikembalikan dari laci shift Anda dan mengurangi tunai seharusnya.</p>
                        <q-input v-if="voidTarget.has_non_cash_deposit" v-model="refundAccount" outlined dense maxlength="255" label="Rekening penerima pengembalian DP" placeholder="Bank, nomor rekening, atas nama" />
                    </template>
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat no-caps label="Kembali" :disable="isWorking" @click="voidTarget = null" />
                    <q-btn color="negative" unelevated no-caps label="Void Nota" :loading="isWorking" :disable="!canVoid()" @click="submitVoid" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </q-dialog>
</template>

<style scoped>
.cod {
    display: flex;
    flex-direction: column;
    width: 560px;
    max-width: 94vw;
    max-height: 90vh;
}

.cod__title {
    font-size: 17px;
    font-weight: 700;
}

.cod__head small {
    color: var(--app-mute);
}

.cod__body {
    position: relative;
    flex: 1;
    min-height: 120px;
    overflow-y: auto;
}

.cod__empty {
    margin: 0;
    padding: 24px 0;
    color: var(--app-mute);
    text-align: center;
}

.cod__more {
    margin: 0 0 8px;
    color: var(--app-mute);
    font-size: 12px;
}

.sale {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 6px 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--app-line);
}

.sale__main b,
.sale__main small {
    display: block;
}

.sale__main small {
    color: var(--app-mute);
    font-size: 12px;
}

.sale__amount {
    align-self: center;
}

.sale__actions {
    display: flex;
    grid-column: 1 / -1;
    gap: 6px;
}

.void {
    width: 440px;
    max-width: 94vw;
}

.void__hint {
    color: var(--app-mute);
}

.void__fields {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.void__note {
    margin: 0;
    color: var(--app-ink-soft);
    font-size: 13px;
}
</style>
