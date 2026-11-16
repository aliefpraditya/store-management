<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import { postJson } from '../../Composables/postJson';
import { printSheet } from '../../Composables/printSheet';
import { formatRupiah } from '../../Composables/useMoneyFormat';
import { formatQuantity } from '../../Composables/useQuantityFormat';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** Endpoint of the receipts of the running shift (`?q=` searches). */
    receiptsUrl: { type: String, required: true },
    /** The full receipt history page, for older receipts. */
    historyUrl: { type: String, required: true },
    /** Only admins may leave for the history pages; a cashier works with the receipts of the shift alone. */
    canOpenHistory: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'delivery', 'return']);

const $q = useQuasar();

const SEARCH_DELAY_MS = 250;

const query = ref('');
const receipts = ref([]);
const isLoading = ref(false);
/** Why the list could not be loaded (a closed shift, a server error, no connection); empty when it loaded. */
const loadError = ref('');
const expandedId = ref(null);
const printingId = ref(null);
let latestLoadId = 0;
let searchTimer;

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { timeStyle: 'short' });

async function load() {
    const loadId = ++latestLoadId;
    isLoading.value = true;

    try {
        const response = await fetch(`${props.receiptsUrl}?q=${encodeURIComponent(query.value.trim())}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const data = response.ok ? (await response.json()).data : [];

        if (loadId === latestLoadId) {
            receipts.value = data;
        }
    } finally {
        if (loadId === latestLoadId) {
            isLoading.value = false;
        }
    }
}

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            query.value = '';
            expandedId.value = null;
            load();
        }
    },
);

watch(query, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(load, SEARCH_DELAY_MS);
});

function toggle(receipt) {
    expandedId.value = expandedId.value === receipt.id ? null : receipt.id;
}

/**
 * Counts the print on the server, then prints the receipt in a new window, so the till stays where it is. The
 * receipt says SALINAN from the second print on.
 */
async function print(receipt) {
    printingId.value = receipt.id;

    try {
        await printSheet(async () => {
            const { print_count: printCount, sheet_url: sheetUrl } = await postJson(receipt.print_url);
            receipt.print_count = printCount;

            return sheetUrl;
        });
    } catch (error) {
        $q.notify({ type: 'negative', message: error.message, position: 'top' });
    } finally {
        printingId.value = null;
    }
}
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card class="history">
            <q-card-section class="history__head">
                <div class="history__title">Nota shift ini</div>
                <q-input v-model="query" outlined dense clearable autofocus placeholder="Cari nomor nota atau nama pelanggan">
                    <template #prepend><q-icon name="search" /></template>
                </q-input>
            </q-card-section>

            <q-card-section class="history__list">
                <q-inner-loading :showing="isLoading" />
                <p v-if="loadError" class="history__empty history__empty--error">{{ loadError }}</p>
                <p v-else-if="!isLoading && receipts.length === 0" class="history__empty">Belum ada nota di shift ini.</p>

                <div v-for="receipt in receipts" :key="receipt.id" class="receipt" :class="{ 'receipt--open': expandedId === receipt.id }">
                    <button type="button" class="receipt__row" @click="toggle(receipt)">
                        <span class="receipt__who">
                            <b>{{ receipt.number }}</b>
                            <small>{{ dateTimeFormat.format(new Date(receipt.sold_at)) }}<template v-if="receipt.customer_name"> · {{ receipt.customer_name }}</template></small>
                        </span>
                        <span v-if="receipt.status === 'voided'" class="chip chip--bad">Void</span>
                        <b>{{ formatRupiah(receipt.total) }}</b>
                        <q-icon :name="expandedId === receipt.id ? 'expand_less' : 'expand_more'" />
                    </button>

                    <div v-if="expandedId === receipt.id" class="receipt__detail">
                        <ul>
                            <li v-for="(item, index) in receipt.items" :key="index">
                                <span>{{ item.name }}<small>{{ formatQuantity(item.quantity) }} {{ item.unit }} × {{ formatRupiah(item.unit_price) }}</small></span>
                                <b>{{ formatRupiah(item.subtotal) }}</b>
                            </li>
                        </ul>
                        <dl>
                            <div v-for="(payment, index) in receipt.payments" :key="index"><dt>{{ payment.method }}</dt><dd>{{ formatRupiah(payment.amount) }}</dd></div>
                            <div><dt>Kembalian</dt><dd>{{ formatRupiah(receipt.change_amount) }}</dd></div>
                        </dl>

                        <div class="receipt__actions">
                            <q-btn outline no-caps dense color="primary" icon="print" :label="receipt.print_count > 0 ? 'Cetak ulang' : 'Cetak'" :loading="printingId === receipt.id" @click="print(receipt)" />
                            <q-btn v-if="receipt.status === 'completed'" outline no-caps dense color="primary" icon="local_shipping" label="Surat jalan" @click="emit('delivery', receipt)" />
                            <q-btn v-if="receipt.status === 'completed'" outline no-caps dense color="primary" icon="undo" label="Retur" @click="emit('return', receipt)" />
                            <q-btn outline no-caps dense color="grey-7" icon="block" label="Void" disable><q-tooltip>Nota COD di-void lewat tombol COD di bar atas. Nota lain dikoreksi lewat Retur.</q-tooltip></q-btn>
                            <q-btn v-if="canOpenHistory && receipt.show_url" flat no-caps dense icon="open_in_new" label="Lihat detail" class="receipt__more" @click="router.visit(receipt.show_url)" />
                        </div>
                    </div>
                </div>
            </q-card-section>

            <q-card-actions>
                <q-btn v-if="canOpenHistory" flat no-caps dense icon="history" label="Nota lama" @click="router.visit(historyUrl)" />
                <q-space />
                <q-btn flat no-caps label="Tutup" v-close-popup />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.history {
    display: flex;
    flex-direction: column;
    width: 640px;
    max-width: 94vw;
    max-height: 86vh;
}

.history__title {
    margin-bottom: 10px;
    font-size: 17px;
    font-weight: 700;
}

.history__list {
    position: relative;
    flex: 1;
    min-height: 120px;
    overflow-y: auto;
}

.history__empty {
    margin: 0;
    padding: 24px 0;
    color: var(--app-mute);
    text-align: center;
}

.history__empty--error {
    color: var(--q-negative);
}

.receipt {
    margin-bottom: 8px;
    border: 1px solid var(--app-line);
    border-radius: 12px;
}

.receipt--open {
    border-color: var(--app-brand);
}

.receipt__row {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 10px 14px;
    border: 0;
    background: transparent;
    font: inherit;
    text-align: left;
    cursor: pointer;
}

.receipt__who {
    flex: 1;
}

.receipt__who b,
.receipt__who small {
    display: block;
}

.receipt__who small {
    color: var(--app-mute);
    font-size: 12px;
}

.receipt__detail {
    padding: 0 14px 12px;
    border-top: 1px solid var(--app-line);
}

.receipt__detail ul {
    margin: 8px 0;
    padding: 0;
    list-style: none;
}

.receipt__detail li {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 4px 0;
}

.receipt__detail li small {
    display: block;
    color: var(--app-mute);
}

.receipt__detail dl {
    margin: 0 0 10px;
    color: var(--app-ink-soft);
}

.receipt__detail dl div {
    display: flex;
    justify-content: space-between;
}

.receipt__detail dd {
    margin: 0;
}

.receipt__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.receipt__more {
    margin-left: auto;
}
</style>
