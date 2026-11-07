<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { newUuid } from '../../../Composables/useCart';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    /** The receipt chosen on the way in, or null. @type {{id: number, number: string, customer_name: string|null, lines: object[]}|null} */
    sale: { type: Object, default: null },
    urls: { type: Object, required: true },
});

const SEARCH_DELAY_MS = 250;

/** One uuid per opened form: a second tap or a retry of the same save gives back the first note, not another. */
const form = useForm({
    client_uuid: newUuid(),
    sale_id: null,
    recipient_name: '',
    recipient_phone: '',
    recipient_address: '',
    driver_name: '',
    vehicle_number: '',
    notes: '',
    items: [],
});

const chosenSale = ref(null);
const saleOptions = ref([]);
let latestSearchId = 0;

/**
 * Picks the receipt: the recipient defaults to the receipt's customer, and every line starts at what is still left
 * to deliver.
 */
function chooseSale(sale) {
    chosenSale.value = sale;
    form.sale_id = sale?.id ?? null;
    form.recipient_name = sale?.customer_name ?? '';
    form.items = (sale?.lines ?? []).map((line) => ({ sale_item_id: line.id, quantity: Number(line.remaining) }));
}

if (props.sale) {
    chooseSale(props.sale);
}

/**
 * Looks completed receipts up as the user types; an answer that arrives after a newer search was started is dropped.
 */
function searchSales(typed, update, abort) {
    const searchId = ++latestSearchId;

    fetch(`${props.urls.sales}?q=${encodeURIComponent(typed.trim())}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((response) => (response.ok ? response.json() : { data: [] }))
        .catch(() => ({ data: [] }))
        .then(({ data }) => (searchId === latestSearchId ? update(() => (saleOptions.value = data)) : abort()));
}

const lines = computed(() => chosenSale.value?.lines ?? []);

const hasAnythingToSend = computed(() => form.items.some((item) => Number(item.quantity) > 0));

watch(
    () => form.items,
    () => form.clearErrors('items'),
    { deep: true },
);

function submit() {
    form.post(props.urls.store);
}
</script>

<template>
    <StoreLayout title="Surat Jalan Baru">
        <template #actions>
            <q-btn flat no-caps label="Batal" class="q-mr-sm" @click="router.visit(urls.index)" />
            <q-btn color="primary" unelevated no-caps label="Buat Surat Jalan" :loading="form.processing" :disable="!chosenSale || !hasAnythingToSend" @click="submit" />
        </template>

        <q-form class="note-form" @submit.prevent="submit">
            <section class="card">
                <h2>Nota</h2>
                <q-select
                    :model-value="chosenSale"
                    :options="saleOptions"
                    option-label="number"
                    option-value="id"
                    label="Cari nomor nota atau nama pelanggan"
                    outlined
                    use-input
                    hide-selected
                    fill-input
                    input-debounce="250"
                    :error="!!form.errors.sale_id"
                    :error-message="form.errors.sale_id"
                    @filter="searchSales"
                    @update:model-value="chooseSale"
                >
                    <template #prepend><q-icon name="receipt_long" /></template>
                    <template #option="{ itemProps, opt }">
                        <q-item v-bind="itemProps">
                            <q-item-section>
                                <q-item-label>{{ opt.number }}</q-item-label>
                                <q-item-label caption>{{ opt.customer_name ?? 'Tanpa nama pelanggan' }} · {{ opt.lines.length }} barang</q-item-label>
                            </q-item-section>
                        </q-item>
                    </template>
                    <template #no-option>
                        <q-item><q-item-section class="text-grey">Ketik untuk mencari nota yang masih punya barang untuk diantar</q-item-section></q-item>
                    </template>
                </q-select>
            </section>

            <template v-if="chosenSale">
                <section class="card">
                    <h2>Penerima</h2>
                    <div class="grid">
                        <q-input v-model="form.recipient_name" label="Nama penerima" outlined :error="!!form.errors.recipient_name" :error-message="form.errors.recipient_name" />
                        <q-input v-model="form.recipient_phone" label="Telepon" outlined :error="!!form.errors.recipient_phone" :error-message="form.errors.recipient_phone" />
                    </div>
                    <q-input v-model="form.recipient_address" type="textarea" autogrow label="Alamat" outlined :error="!!form.errors.recipient_address" :error-message="form.errors.recipient_address" />
                    <q-input v-model="form.notes" label="Catatan (opsional)" outlined class="note-form__notes" />
                </section>

                <section class="card">
                    <h2>Barang yang diantar</h2>
                    <p class="hint">Isi jumlah yang diantar kali ini. Biarkan 0 untuk barang yang tidak ikut.</p>
                    <p v-if="form.errors.items" class="error">{{ form.errors.items }}</p>

                    <table class="lines">
                        <thead>
                            <tr><th>Barang</th><th class="lines__right">Di nota</th><th class="lines__right">Sisa</th><th class="lines__qty">Diantar</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(line, index) in lines" :key="line.id">
                                <td>{{ line.name }}</td>
                                <td class="lines__right">{{ formatQuantity(line.quantity) }} {{ line.unit }}</td>
                                <td class="lines__right">{{ formatQuantity(line.remaining) }} {{ line.unit }}</td>
                                <td class="lines__qty">
                                    <q-input
                                        v-model.number="form.items[index].quantity"
                                        type="number"
                                        min="0"
                                        step="any"
                                        outlined
                                        dense
                                        hide-bottom-space
                                        :suffix="line.unit"
                                        :disable="Number(line.remaining) <= 0"
                                        :error="!!form.errors[`items.${index}.quantity`]"
                                        :aria-label="`Jumlah ${line.name}`"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </template>
        </q-form>
    </StoreLayout>
</template>

<style scoped>
.note-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 860px;
}

.card {
    padding: 20px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.card h2 {
    margin: 0 0 14px;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: normal;
    line-height: 1.4;
}

.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 14px;
}

.note-form__notes {
    margin-top: 14px;
}

.hint {
    margin: 0 0 10px;
    color: var(--app-mute);
    font-size: 12.5px;
}

.error {
    margin: 0 0 10px;
    color: #be123c;
    font-size: 13px;
}

.lines {
    width: 100%;
    border-collapse: collapse;
}

.lines th {
    padding: 8px 0;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
}

.lines td {
    padding: 8px 0;
    border-bottom: 1px solid var(--app-line);
}

.lines .lines__right {
    text-align: right;
    white-space: nowrap;
}

.lines .lines__qty {
    width: 170px;
    padding-left: 14px;
}

@media screen and (max-width: 640px) {
    .grid {
        grid-template-columns: 1fr;
    }
}
</style>
