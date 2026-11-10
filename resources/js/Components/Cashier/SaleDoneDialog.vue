<script setup>
import { computed, ref, watch } from 'vue';
import { useQuasar } from 'quasar';
import { postJson } from '../../Composables/postJson';
import { printSheet } from '../../Composables/printSheet';
import { formatQuantity } from '../../Composables/useQuantityFormat';
import { formatRupiah } from '../../Composables/useMoneyFormat';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** @type {{number: string, sold_at: string, customer_name: string|null, total: string, paid_total: string, change_amount: string, is_cod: boolean, cod_amount: string, delivery_notes: {id: number, number: string, print_url: string}[], items: object[], payments: object[]}|null} */
    sale: { type: Object, default: null },
    /** @type {{number: string, customer_name: string, customer_phone: string, expected_date: string|null, total: string, deposit: string, remaining: string, origin_sale_number: string|null, print_url: string, items: object[]}|null} the customer order made together with the receipt, or alone */
    order: { type: Object, default: null },
    /** Whether the receipt (or, with no receipt, the order slip) opens for printing as soon as this dialog shows. */
    isPrintedAutomatically: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'delivery', 'hidden']);

const $q = useQuasar();

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

const printingId = ref(null);
const isPrintingOrder = ref(false);
const isPrintingReceipt = ref(false);

/** Whether the receipt (or the order slip when there is no receipt) of what is shown was printed; the dialog only closes after that. */
const isDocumentPrinted = ref(false);

const isPrinting = computed(() => isPrintingReceipt.value || isPrintingOrder.value);

/** The document that was printed by itself last, so showing the dialog again does not print it twice. */
let lastAutoPrintedNumber = null;

watch(
    () => props.sale?.number ?? props.order?.number,
    () => (isDocumentPrinted.value = false),
);

watch(
    () => [props.modelValue, props.sale?.number ?? props.order?.number],
    ([isOpen, number]) => {
        if (!isOpen || !number || !props.isPrintedAutomatically || number === lastAutoPrintedNumber) {
            return;
        }

        lastAutoPrintedNumber = number;

        if (props.sale) {
            printReceipt();
        } else {
            printOrder();
        }
    },
    { immediate: true },
);

/** Counts the print on the server and prints the receipt. */
async function printReceipt() {
    isPrintingReceipt.value = true;

    try {
        await printSheet(async () => (await postJson(props.sale.print_url)).sheet_url);
        isDocumentPrinted.value = true;
    } catch (error) {
        $q.notify({ type: 'negative', message: error.message, position: 'top' });
    } finally {
        isPrintingReceipt.value = false;
    }
}

/** Counts the print on the server and prints the order slip. */
async function printOrder() {
    isPrintingOrder.value = true;

    try {
        await printSheet(async () => (await postJson(props.order.print_url)).sheet_url);
        isDocumentPrinted.value = true;
    } catch (error) {
        $q.notify({ type: 'negative', message: error.message, position: 'top' });
    } finally {
        isPrintingOrder.value = false;
    }
}

/** Printing is the way out; once the paper is out (also by the automatic print) the same button just moves on. */
const printButtonLabel = computed(() => {
    if (isDocumentPrinted.value) {
        return 'Transaksi Baru';
    }

    return props.sale ? (props.order ? 'Cetak Nota + Pesanan' : 'Cetak Nota') : 'Cetak Nota Pesanan';
});

/**
 * The one way out: prints the receipt (the order slip when there is no receipt) unless it already was, and only then closes.
 * A print that fails keeps the dialog open.
 */
async function printAndFinish() {
    if (!isDocumentPrinted.value) {
        await (props.sale ? printReceipt() : printOrder());
    }

    if (isDocumentPrinted.value) {
        emit('update:modelValue', false);
    }
}

/** The delivery note comes after the receipt: it is printed first when it was not yet. */
async function openDelivery() {
    if (!isDocumentPrinted.value) {
        await printReceipt();
    }

    if (isDocumentPrinted.value) {
        emit('delivery', props.sale);
    }
}

/** Counts the print on the server and prints the delivery note sheet. */
async function printNote(note) {
    printingId.value = note.id;

    try {
        await printSheet(async () => (await postJson(note.print_url)).sheet_url);
    } catch (error) {
        $q.notify({ type: 'negative', message: error.message, position: 'top' });
    } finally {
        printingId.value = null;
    }
}
</script>

<template>
    <q-dialog :model-value="modelValue" persistent @update:model-value="emit('update:modelValue', $event)" @hide="emit('hidden')">
        <q-card v-if="sale || order" class="done">
            <q-card-section v-if="sale?.is_cod" class="done__cod">
                <span>COD, ditagih saat barang diantar</span>
                <b>{{ formatRupiah(sale.cod_amount) }}</b>
            </q-card-section>
            <q-card-section v-else-if="sale" class="done__change">
                <span>Kembalian</span>
                <b>{{ formatRupiah(sale.change_amount) }}</b>
            </q-card-section>

            <q-card-section v-if="sale?.is_cod && sale.delivery_notes.length === 0" class="done__notes">
                <div class="done__note">
                    <span>Barang nota ini disimpan di toko dan diantar bersama pesanan. Surat jalan dan penagihan COD diselesaikan sekali saat barang pesanan datang.</span>
                </div>
            </q-card-section>

            <q-card-section v-if="sale?.is_cod" class="done__notes">
                <div v-for="note in sale.delivery_notes" :key="note.id" class="done__note">
                    <span>Surat jalan <b>{{ note.number }}</b> sudah dibuat. Cetak dan sertakan saat barang diantar.</span>
                    <q-btn color="primary" unelevated dense no-caps icon="print" label="Cetak Surat Jalan" :loading="printingId === note.id" @click="printNote(note)" />
                </div>
            </q-card-section>

            <q-card-section v-if="order" class="done__order">
                <b>Nota pesanan {{ order.number }}</b>
                <span>{{ order.customer_name }} · {{ order.customer_phone }}</span>
                <ul>
                    <li v-for="(item, index) in order.items" :key="index">{{ formatQuantity(item.quantity) }} {{ item.unit }} {{ item.name }}</li>
                </ul>
                <span>Total {{ formatRupiah(order.total) }} · uang muka {{ formatRupiah(order.deposit) }} · sisa {{ formatRupiah(order.remaining) }}</span>
                <small v-if="sale">Pesanan ini ikut tercetak di nota bayar, jadi pelanggan memegang satu lembar.</small>
            </q-card-section>

            <q-card-section v-if="sale">
                <div class="done__number">{{ sale.number }}</div>
                <small>{{ dateTimeFormat.format(new Date(sale.sold_at)) }}<template v-if="sale.customer_name"> · {{ sale.customer_name }}</template></small>

                <ul class="done__items">
                    <li v-for="(item, index) in sale.items" :key="index">
                        <span>{{ item.name }}<small>{{ formatQuantity(item.quantity) }} {{ item.unit }} × {{ formatRupiah(item.unit_price) }}</small></span>
                        <b>{{ formatRupiah(item.subtotal) }}</b>
                    </li>
                </ul>

                <dl class="done__totals">
                    <div class="done__total"><dt>Total</dt><dd>{{ formatRupiah(sale.total) }}</dd></div>
                    <div v-for="(payment, index) in sale.payments" :key="index">
                        <dt>{{ payment.method }}<template v-if="payment.reference"> ({{ payment.reference }})</template></dt>
                        <dd>{{ formatRupiah(payment.amount) }}</dd>
                    </div>
                </dl>
            </q-card-section>

            <q-card-actions align="right">
                <q-btn v-if="sale" outline no-caps color="primary" icon="local_shipping" label="Surat Jalan" :disable="isPrinting" @click="openDelivery" />
                <q-btn color="primary" unelevated no-caps icon="print" :label="printButtonLabel" :loading="isPrinting" autofocus @click="printAndFinish" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.done {
    width: 420px;
    max-width: 94vw;
}

.done__change {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    background: #e7f8f1;
    color: #059669;
}

.done__change b {
    font-size: 28px;
}

.done__cod {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    background: #fff4e0;
    color: #b45309;
}

.done__cod b {
    font-size: 24px;
}

.done__notes {
    padding-bottom: 0;
}

.done__note {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px 12px;
    border-radius: 10px;
    background: #f1f5ff;
    font-size: 13px;
}

.done__order {
    display: flex;
    flex-direction: column;
    gap: 6px;
    background: #fff4e0;
    font-size: 13px;
}

.done__order ul {
    margin: 0;
    padding-left: 18px;
}

.done__number {
    font-weight: 700;
}

.done small {
    color: var(--app-mute);
}

.done__items {
    margin: 12px 0;
    padding: 0;
    list-style: none;
}

.done__items li {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 6px 0;
    border-bottom: 1px solid var(--app-line);
}

.done__items small {
    display: block;
}

.done__totals {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin: 0;
}

.done__totals div {
    display: flex;
    justify-content: space-between;
    color: var(--app-ink-soft);
}

.done__totals dd {
    margin: 0;
}

.done__totals .done__total {
    color: var(--app-ink);
    font-weight: 700;
}
</style>
