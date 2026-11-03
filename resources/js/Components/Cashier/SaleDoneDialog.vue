<script setup>
import { ref } from 'vue';
import { useQuasar } from 'quasar';
import { postJson } from '../../Composables/postJson';
import { printInNewWindow } from '../../Composables/printInNewWindow';
import { formatQuantity } from '../../Composables/useQuantityFormat';
import { formatRupiah } from '../../Composables/useMoneyFormat';

defineProps({
    modelValue: { type: Boolean, required: true },
    /** @type {{number: string, sold_at: string, customer_name: string|null, total: string, paid_total: string, change_amount: string, is_cod: boolean, cod_amount: string, delivery_notes: {id: number, number: string, print_url: string}[], items: object[], payments: object[]}|null} */
    sale: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'delivery']);

const $q = useQuasar();

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

const printingId = ref(null);

/** Counts the print on the server and opens the delivery note sheet in a window of its own. */
async function printNote(note) {
    printingId.value = note.id;

    try {
        await printInNewWindow(async () => (await postJson(note.print_url)).sheet_url);
    } catch (error) {
        $q.notify({ type: 'negative', message: error.message, position: 'top' });
    } finally {
        printingId.value = null;
    }
}
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card v-if="sale" class="done">
            <q-card-section v-if="sale.is_cod" class="done__cod">
                <span>COD, ditagih saat barang diantar</span>
                <b>{{ formatRupiah(sale.cod_amount) }}</b>
            </q-card-section>
            <q-card-section v-else class="done__change">
                <span>Kembalian</span>
                <b>{{ formatRupiah(sale.change_amount) }}</b>
            </q-card-section>

            <q-card-section v-if="sale.is_cod" class="done__notes">
                <div v-for="note in sale.delivery_notes" :key="note.id" class="done__note">
                    <span>Surat jalan <b>{{ note.number }}</b> sudah dibuat. Cetak dan sertakan saat barang diantar.</span>
                    <q-btn color="primary" unelevated dense no-caps icon="print" label="Cetak Surat Jalan" :loading="printingId === note.id" @click="printNote(note)" />
                </div>
            </q-card-section>

            <q-card-section>
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
                <q-btn outline no-caps color="primary" icon="local_shipping" label="Surat Jalan" @click="emit('delivery', sale)" />
                <q-btn color="primary" unelevated no-caps label="Transaksi Baru" autofocus v-close-popup />
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
