<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    /** The receipt chosen on the way in, or null. @type {{id: number, number: string, customer_name: string|null, default_refund_method: string, lines: object[]}|null} */
    sale: { type: Object, default: null },
    refund_methods: { type: Array, required: true },
    dispositions: { type: Array, required: true },
    urls: { type: Object, required: true },
});

const form = useForm({
    sale_id: null,
    refund_method: 'cash',
    reason: '',
    items: [],
});

const chosenSale = ref(null);
const saleOptions = ref([]);
let latestSearchId = 0;

/**
 * Picks the receipt: the money goes back the way the customer paid, and no line is returned until a quantity is typed.
 */
function chooseSale(sale) {
    chosenSale.value = sale;
    form.sale_id = sale?.id ?? null;
    form.refund_method = sale?.default_refund_method ?? 'cash';
    form.items = (sale?.lines ?? []).map((line) => ({ sale_item_id: line.id, quantity: 0, disposition: 'resellable' }));
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

const hasAnythingToReturn = computed(() => form.items.some((item) => Number(item.quantity) > 0));

/** What the customer gets back: the receipt's price for each returned quantity. */
const refundTotal = computed(() => form.items.reduce((sum, item, index) => sum + (Number(item.quantity) || 0) * Number(lines.value[index].unit_price), 0));

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
    <StoreLayout title="Retur Baru">
        <template #actions>
            <q-btn flat no-caps label="Batal" class="q-mr-sm" @click="router.visit(urls.index)" />
            <q-btn color="primary" unelevated no-caps label="Simpan Retur" :loading="form.processing" :disable="!chosenSale || !hasAnythingToReturn" @click="submit" />
        </template>

        <q-form class="return-form" @submit.prevent="submit">
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
                        <q-item><q-item-section class="text-grey">Ketik untuk mencari nota yang masih punya barang untuk diretur</q-item-section></q-item>
                    </template>
                </q-select>
            </section>

            <template v-if="chosenSale">
                <section class="card">
                    <h2>Barang yang diretur</h2>
                    <p class="hint">Isi jumlah yang dikembalikan pelanggan dan pilih ke mana barangnya. Biarkan 0 untuk barang yang tidak diretur.</p>
                    <p v-if="form.errors.items" class="error">{{ form.errors.items }}</p>

                    <table class="lines">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th class="lines__right">Di nota</th>
                                <th class="lines__right">Sisa</th>
                                <th class="lines__qty">Diretur</th>
                                <th class="lines__where">Barang masuk ke</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(line, index) in lines" :key="line.id">
                                <td>{{ line.name }}<small>{{ formatRupiah(line.unit_price) }} / {{ line.unit }}</small></td>
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
                                        :aria-label="`Jumlah retur ${line.name}`"
                                    />
                                </td>
                                <td class="lines__where">
                                    <q-select
                                        v-model="form.items[index].disposition"
                                        :options="dispositions"
                                        option-value="value"
                                        option-label="label"
                                        emit-value
                                        map-options
                                        outlined
                                        dense
                                        hide-bottom-space
                                        :disable="Number(line.remaining) <= 0"
                                        :aria-label="`Tujuan ${line.name}`"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="card">
                    <h2>Uang kembali</h2>
                    <div class="refund">
                        <q-select
                            v-model="form.refund_method"
                            :options="refund_methods"
                            option-value="value"
                            option-label="label"
                            emit-value
                            map-options
                            label="Dikembalikan lewat"
                            outlined
                            :error="!!form.errors.refund_method"
                            :error-message="form.errors.refund_method"
                        />
                        <div class="refund__total"><span>Total dikembalikan</span><b>{{ formatRupiah(refundTotal) }}</b></div>
                    </div>
                    <q-input v-model="form.reason" label="Alasan retur (opsional)" outlined :error="!!form.errors.reason" :error-message="form.errors.reason" class="return-form__reason" />
                    <p class="hint">Retur tunai keluar dari laci kas shift Anda yang sedang berjalan. Harga mengikuti nota asli.</p>
                </section>
            </template>
        </q-form>
    </StoreLayout>
</template>

<style scoped>
.return-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 920px;
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

.lines small {
    display: block;
    color: var(--app-mute);
}

.lines .lines__right {
    text-align: right;
    white-space: nowrap;
}

.lines .lines__qty {
    width: 150px;
    padding-left: 14px;
}

.lines .lines__where {
    width: 150px;
    padding-left: 14px;
}

.refund {
    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: center;
    gap: 14px;
}

.refund__total {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    color: var(--app-mute);
}

.refund__total b {
    color: var(--app-ink);
    font-size: 22px;
}

.return-form__reason {
    margin: 14px 0 10px;
}

@media screen and (max-width: 640px) {
    .refund {
        grid-template-columns: 1fr;
    }
}
</style>
