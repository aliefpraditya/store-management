<script setup>
import { computed, ref, watch } from 'vue';
import { useQuasar } from 'quasar';
import { postJson } from '../../Composables/postJson';
import { printInNewWindow } from '../../Composables/printInNewWindow';
import { newUuid } from '../../Composables/useCart';
import { deliveryNoteStatusChipClass } from '../../Composables/useDeliveryNoteStatus';
import { formatQuantity } from '../../Composables/useQuantityFormat';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** The receipt the note is for. @type {{number: string, customer_name: string|null, delivery_url: string, delivery_note_store_url: string}|null} */
    receipt: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue']);

const $q = useQuasar();

const isLoading = ref(false);
const isSaving = ref(false);
const printingId = ref(null);

/** What the server says about the receipt: its lines with what is left to deliver, and the notes already made. @type {import('vue').Ref<{lines: object[], notes: object[]}>} */
const receiptDeliveryContext = ref({ lines: [], notes: [] });

const form = ref({ recipient_name: '', recipient_phone: '', recipient_address: '', driver_name: '', vehicle_number: '', notes: '' });
const quantities = ref({});

const hasAnythingLeft = computed(() => receiptDeliveryContext.value.lines.some((line) => Number(line.remaining) > 0));

const hasAnythingToSend = computed(() => receiptDeliveryContext.value.lines.some((line) => Number(quantities.value[line.id]) > 0));

/**
 * Per receipt line: what the receipt has, what is on delivery notes that still stand, and what has arrived.
 * Cancelled notes do not count; "arrived" only fills in once a delivery has ended.
 */
const lineProgress = computed(() => receiptDeliveryContext.value.lines.map((line) => {
    const noteItems = receiptDeliveryContext.value.notes
        .filter((note) => note.status !== 'cancelled')
        .flatMap((note) => note.items)
        .filter((item) => item.sale_item_id === line.id);
    const sum = (field) => noteItems.reduce((total, item) => total + Number(item[field] ?? 0), 0);

    return { ...line, onNotes: sum('quantity'), arrived: sum('quantity_delivered'), returned: sum('quantity_returned') };
}));

/** What a note's item is doing now, in words. */
function itemProgress(note, item) {
    const progressByStatus = { draft: 'belum dikirim', shipped: 'dalam perjalanan', cancelled: 'dibatalkan' };

    if (progressByStatus[note.status]) {
        return progressByStatus[note.status];
    }

    const returned = Number(item.quantity_returned) > 0 ? `, kembali ${formatQuantity(item.quantity_returned)}` : '';

    return `diterima ${formatQuantity(item.quantity_delivered)}${returned}`;
}

const canSave = computed(() => form.value.recipient_name.trim() !== '' && hasAnythingToSend.value && !isSaving.value);

function fail(error) {
    $q.notify({ type: 'negative', message: error.message, position: 'top' });
}

/**
 * Loads the receipt's lines and notes; every line starts at what is still left to deliver.
 */
async function load() {
    isLoading.value = true;

    try {
        const response = await fetch(props.receipt.delivery_url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error('Surat jalan tidak bisa dimuat.');
        }

        receiptDeliveryContext.value = (await response.json()).data;
        quantities.value = Object.fromEntries(receiptDeliveryContext.value.lines.map((line) => [line.id, Number(line.remaining)]));
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

        form.value = { recipient_name: props.receipt.customer_name ?? '', recipient_phone: '', recipient_address: '', driver_name: '', vehicle_number: '', notes: '' };
        receiptDeliveryContext.value = { lines: [], notes: [] };
        noteUuid = newUuid();
        load();
    },
);

/**
 * Sent with every save of one note. A retry of the same attempt (a lost answer, a second tap) carries the same uuid,
 * so the server gives back the first note instead of making another; it is renewed once a note is saved.
 */
let noteUuid = newUuid();

async function saveNote() {
    const note = await postJson(props.receipt.delivery_note_store_url, {
        client_uuid: noteUuid,
        ...form.value,
        items: receiptDeliveryContext.value.lines.map((line) => ({ sale_item_id: line.id, quantity: Number(quantities.value[line.id]) || 0 })),
    });

    noteUuid = newUuid();
    $q.notify({ type: 'positive', message: `Surat jalan ${note.number} dibuat.`, position: 'top' });
    await load();

    return note;
}

/**
 * Makes the note; with `shouldPrintAfter` it is printed straight away. The print window opens first, from the click:
 * browsers block a pop-up opened after the note has been saved.
 */
async function create(shouldPrintAfter) {
    isSaving.value = true;

    try {
        if (shouldPrintAfter) {
            await printInNewWindow(async () => countPrint(await saveNote()));
        } else {
            await saveNote();
        }
    } catch (error) {
        fail(error);
    } finally {
        isSaving.value = false;
    }
}

/**
 * Counts the print on the server and answers the address of the sheet for that print; it says SALINAN from the second print on.
 *
 * @returns {Promise<string>}
 */
async function countPrint(note) {
    const { print_count: printCount, sheet_url: sheetUrl } = await postJson(note.print_url);
    const listedNote = receiptDeliveryContext.value.notes.find((listed) => listed.id === note.id);

    if (listedNote) {
        listedNote.print_count = printCount;
    }

    return sheetUrl;
}

async function print(note) {
    printingId.value = note.id;

    try {
        await printInNewWindow(() => countPrint(note));
    } catch (error) {
        fail(error);
    } finally {
        printingId.value = null;
    }
}

</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card v-if="receipt" class="delivery">
            <q-card-section class="delivery__head">
                <div class="delivery__title">Surat jalan</div>
                <small>Nota {{ receipt.number }}</small>
            </q-card-section>

            <q-card-section class="delivery__body">
                <q-inner-loading :showing="isLoading" />

                <template v-if="receiptDeliveryContext.notes.length > 0">
                    <div class="delivery__label">Ringkasan pengiriman</div>
                    <div v-for="line in lineProgress" :key="line.id" class="delivery__progress">
                        <b>{{ line.name }}</b>
                        <span>
                            Nota {{ formatQuantity(line.quantity) }} {{ line.unit }}
                            · di surat jalan {{ formatQuantity(line.onNotes) }}
                            · diterima {{ formatQuantity(line.arrived) }}
                            <template v-if="line.returned > 0"> · kembali {{ formatQuantity(line.returned) }}</template>
                            · <em :class="{ 'delivery__progress--left': Number(line.remaining) > 0 }">sisa {{ formatQuantity(line.remaining) }}</em>
                        </span>
                    </div>

                    <div class="delivery__label">Sudah dibuat</div>
                    <div v-for="note in receiptDeliveryContext.notes" :key="note.id" class="delivery__note-card">
                        <div class="delivery__note">
                            <b>{{ note.number }}</b>
                            <span class="chip" :class="deliveryNoteStatusChipClass[note.status]">{{ note.status_label }}</span>
                            <q-btn outline no-caps dense color="primary" icon="print" :label="note.print_count > 0 ? 'Cetak ulang' : 'Cetak'" :loading="printingId === note.id" class="delivery__print" @click="print(note)" />
                        </div>
                        <ul class="delivery__items">
                            <li v-for="item in note.items" :key="item.sale_item_id">
                                <span>{{ item.name }}</span>
                                <span>{{ formatQuantity(item.quantity) }} {{ item.unit }} <small>{{ itemProgress(note, item) }}</small></span>
                            </li>
                        </ul>
                    </div>
                </template>

                <template v-if="hasAnythingLeft">
                    <div class="delivery__label">Surat jalan baru</div>
                    <q-input v-model="form.recipient_name" outlined dense label="Nama penerima" />
                    <q-input v-model="form.recipient_phone" outlined dense label="Telepon" />
                    <q-input v-model="form.recipient_address" outlined dense type="textarea" autogrow label="Alamat" />

                    <div class="delivery__label">Barang yang diantar</div>
                    <div v-for="line in receiptDeliveryContext.lines" :key="line.id" class="delivery__line">
                        <span>{{ line.name }}<small>sisa {{ formatQuantity(line.remaining) }} {{ line.unit }}<template v-if="line.sale_number !== receipt.number"> · dari nota {{ line.sale_number }}</template></small></span>
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
                            :aria-label="`Jumlah ${line.name}`"
                        />
                    </div>
                </template>
                <p v-else-if="!isLoading" class="delivery__done">Semua barang di nota ini sudah masuk surat jalan.</p>
            </q-card-section>

            <q-card-actions align="right">
                <q-btn flat no-caps label="Tutup" v-close-popup />
                <template v-if="hasAnythingLeft">
                    <q-btn outline no-caps color="primary" label="Buat" :disable="!canSave" :loading="isSaving" @click="create(false)" />
                    <q-btn color="primary" unelevated no-caps icon="print" label="Buat & Cetak" :disable="!canSave" :loading="isSaving" @click="create(true)" />
                </template>
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.delivery {
    display: flex;
    flex-direction: column;
    width: 560px;
    max-width: 94vw;
    max-height: 90vh;
}

.delivery__title {
    font-size: 17px;
    font-weight: 700;
}

.delivery__head small {
    color: var(--app-mute);
}

.delivery__body {
    position: relative;
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 10px;
    min-height: 120px;
    overflow-y: auto;
}

.delivery__label {
    margin-top: 4px;
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.delivery__note {
    display: flex;
    align-items: center;
    gap: 10px;
}

.delivery__print {
    margin-left: auto;
}

.delivery__progress {
    display: flex;
    flex-direction: column;
    font-size: 13px;
}

.delivery__progress span {
    color: var(--app-mute);
}

.delivery__progress em {
    font-style: normal;
}

.delivery__progress .delivery__progress--left {
    color: var(--q-negative);
    font-weight: 600;
}

.delivery__note-card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 8px 10px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
}

.delivery__items {
    margin: 0;
    padding: 0;
    list-style: none;
    font-size: 13px;
}

.delivery__items li {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.delivery__items small {
    margin-left: 4px;
    color: var(--app-mute);
}

.delivery__line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.delivery__line small {
    display: block;
    color: var(--app-mute);
}

.delivery__line :deep(.q-field) {
    width: 150px;
}

.delivery__done {
    margin: 0;
    color: var(--app-mute);
}
</style>
