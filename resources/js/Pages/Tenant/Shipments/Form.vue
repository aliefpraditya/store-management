<script setup>
import { computed, nextTick, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import DateInput from '../../../Components/DateInput.vue';
import MoneyInput from '../../../Components/MoneyInput.vue';
import SearchableSelect from '../../../Components/SearchableSelect.vue';
import SuggestedTextInput from '../../../Components/SuggestedTextInput.vue';
import { newUuid } from '../../../Composables/useCart';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    /** The draft being changed, or null for a new shipment. */
    shipment: { type: Object, default: null },
    carriers: { type: Array, required: true },
    /** The notas that can be loaded: ordered, and in no other shipment (plus the ones this draft already holds). */
    available_purchases: { type: Array, required: true },
    store_url: { type: String, required: true },
    index_url: { type: String, required: true },
});

const $q = useQuasar();

const isEditing = computed(() => props.shipment !== null);

const form = useForm({
    client_uuid: newUuid(),
    carrier_id: props.shipment?.carrier_id ?? null,
    vessel_name: props.shipment?.vessel_name ?? '',
    departure_date: props.shipment?.departure_date ?? '',
    notes: props.shipment?.notes ?? '',
});

let nextRowKey = 0;

/** One row of the packing list for one nota line: how much of it is in which container, and what the carrier charged. */
const newRow = (row = {}) => ({ key: nextRowKey++, container: '', quantity: '', freight_cost: '', ...row });

const trimmedNumber = (value) => String(Number(Number(value).toFixed(3)));

/**
 * One entry per nota in the shipment: the nota and, for each of its lines, the rows of the packing list. A nota line can have
 * several rows (one per container) when its goods are split over containers.
 */
function entryOf(purchase, rowsByItem) {
    return { purchase, rowsByItem };
}

const entries = ref(
    props.shipment
        ? props.shipment.purchases.map((purchase) =>
              entryOf(
                  purchase,
                  Object.fromEntries(
                      purchase.items.map((item) => [
                          item.id,
                          props.shipment.allocations
                              .filter((allocation) => allocation.purchase_item_id === item.id)
                              .map((allocation) => newRow({ container: allocation.container_number, quantity: trimmedNumber(allocation.quantity), freight_cost: trimmedNumber(allocation.freight_cost) })),
                      ]),
                  ),
              ),
          )
        : [],
);

const allRows = computed(() => entries.value.flatMap((entry) => Object.values(entry.rowsByItem).flat()));

const normalizedContainer = (number) => number.trim().replace(/\s+/g, ' ').toUpperCase();

/** The container numbers typed so far, offered again on the other rows so one container is spelled one way. */
const containerOptions = computed(() => [...new Set(allRows.value.map((row) => row.container.trim()).filter(Boolean))]);

const lastContainer = computed(() => containerOptions.value.at(-1) ?? '');

const rowsOf = (entry, item) => entry.rowsByItem[item.id];

const allocatedOf = (entry, item) => rowsOf(entry, item).reduce((sum, row) => sum + (Number(row.quantity) || 0), 0);

const remainingOf = (entry, item) => Number(item.quantity) - allocatedOf(entry, item);

const isEverythingInContainers = computed(() =>
    entries.value.length > 0 && entries.value.every((entry) => entry.purchase.items.every((item) => Math.abs(remainingOf(entry, item)) < 0.0005)),
);

/**
 * The packing list as it is being typed, grouped by container like the carrier's paper: each container with its lines and what
 * the carrier charged for them (the sub total). Rows without a container are not in any container yet.
 */
const containerSummaries = computed(() => {
    const summaries = new Map();

    entries.value.forEach((entry) =>
        entry.purchase.items.forEach((item) =>
            rowsOf(entry, item)
                .filter((row) => row.container.trim() !== '')
                .forEach((row) => {
                    const number = normalizedContainer(row.container);
                    const summary = summaries.get(number) ?? { number, total: 0, lines: [] };

                    summary.total += Number(row.freight_cost) || 0;
                    summary.lines.push({
                        key: row.key,
                        purchase_number: entry.purchase.number,
                        supplier_name: entry.purchase.supplier_name,
                        product_name: item.name,
                        sku: item.sku,
                        unit_code: item.unit_code,
                        quantity: row.quantity,
                        freight_cost: row.freight_cost,
                    });
                    summaries.set(number, summary);
                }),
        ),
    );

    return [...summaries.values()];
});

const rowsWithoutContainer = computed(() => allRows.value.filter((row) => row.container.trim() === '').length);

const grandTotal = computed(() => containerSummaries.value.reduce((sum, container) => sum + container.total, 0));

const selectedIds = computed(() => entries.value.map((entry) => entry.purchase.id));

const pickablePurchases = computed(() => props.available_purchases.filter((purchase) => !selectedIds.value.includes(purchase.id)));

/**
 * A nota starts with every line in one container, the last one used, so only the container (when it differs) and the price of
 * each line are left to fill in. The container of the first line is focused so it can be typed over straight away.
 */
function addPurchase(purchase) {
    entries.value.push(
        entryOf(
            purchase,
            Object.fromEntries(purchase.items.map((item) => [item.id, [newRow({ container: lastContainer.value, quantity: trimmedNumber(item.quantity) })]])),
        ),
    );

    nextTick(() => {
        const input = document.querySelector(`input[data-line-container="${purchase.items[0]?.id}"]`);

        input?.focus();
        input?.select();
    });
}

function removePurchase(entry) {
    entries.value = entries.value.filter((candidate) => candidate !== entry);
}

/** `purchase` is the working view, per nota; `container` is the same data grouped like the carrier's packing list, to check it against the paper. */
const view = ref('purchase');

const scanText = ref('');

const matchesNeedle = (purchase, needle, isExact) =>
    [purchase.number, purchase.supplier_document_number ?? ''].some((text) => (isExact ? text.toLowerCase() === needle : text.toLowerCase().includes(needle)));

/**
 * Takes what was scanned or typed (the nota's number, or the supplier's nota number) and loads that nota. An exact match wins;
 * otherwise exactly one partial match is enough.
 */
function scanPurchase() {
    const needle = scanText.value.trim().toLowerCase();

    if (needle === '') {
        return;
    }

    const exactMatches = pickablePurchases.value.filter((purchase) => matchesNeedle(purchase, needle, true));
    const matches = exactMatches.length > 0 ? exactMatches : pickablePurchases.value.filter((purchase) => matchesNeedle(purchase, needle, false));

    if (matches.length === 1) {
        addPurchase(matches[0]);
        scanText.value = '';

        return;
    }

    $q.notify({
        type: matches.length === 0 ? 'negative' : 'warning',
        position: 'top',
        message: matches.length === 0 ? 'Nota tidak ditemukan, belum dibayar, atau sudah masuk pengiriman lain.' : `${matches.length} nota cocok. Ketik lebih lengkap atau pilih dari daftar di bawah.`,
    });
}

/** Another container for the rest of a line: starts with whatever is not in a container yet. */
function addRow(entry, item) {
    const remaining = remainingOf(entry, item);

    rowsOf(entry, item).push(newRow({ container: lastContainer.value, quantity: remaining > 0 ? trimmedNumber(remaining) : '' }));
}

function removeRow(entry, item, row) {
    entry.rowsByItem[item.id] = rowsOf(entry, item).filter((candidate) => candidate !== row);
}

/**
 * A line is simple while all of it sits in one container: only the container and the price are typed, and the quantity is the
 * nota's. The lines of one nota can go in different containers, so the container is always per line. Splitting a line over
 * several containers is the rare case and shows the quantity too.
 */
function isSimpleLine(entry, item) {
    const rows = rowsOf(entry, item);

    return rows.length === 1 && Math.abs(Number(rows[0].quantity) - Number(item.quantity)) < 0.0005;
}

/** Every distinct thing the server complained about, as one list: a packing list row is not the same as a screen row. */
const errorMessages = computed(() => [...new Set(Object.values(form.errors))]);

/**
 * Sends the shipment. A new one is a POST, a draft being changed a PUT. Rows left completely empty are dropped; a row with
 * something missing is sent so the server says what.
 */
function submit() {
    const allocations = entries.value.flatMap((entry) =>
        entry.purchase.items.flatMap((item) =>
            rowsOf(entry, item)
                .filter((row) => row.container.trim() !== '' || row.quantity !== '' || row.freight_cost !== '')
                .map((row) => ({ purchase_item_id: item.id, container_number: row.container, quantity: row.quantity, freight_cost: row.freight_cost })),
        ),
    );

    form.transform((submittedForm) => ({
        ...submittedForm,
        vessel_name: submittedForm.vessel_name || null,
        departure_date: submittedForm.departure_date || null,
        purchase_ids: entries.value.map((entry) => entry.purchase.id),
        allocations,
        ...(isEditing.value ? { client_uuid: undefined } : {}),
    }));

    if (isEditing.value) {
        form.put(props.shipment.urls.update);
    } else {
        form.post(props.store_url);
    }
}
</script>

<template>
    <StoreLayout :title="isEditing ? `Ubah ${shipment.number}` : 'Pengiriman Baru'">
        <template #actions>
            <q-btn flat no-caps label="Batal" class="q-mr-sm" @click="router.visit(isEditing ? shipment.urls.index : index_url)" />
            <q-btn color="primary" unelevated no-caps :label="form.departure_date ? 'Simpan & Berangkatkan' : 'Simpan Draft'" :loading="form.processing" @click="submit" />
        </template>

        <div class="shipment-form">
            <div v-if="errorMessages.length > 0" class="notice">
                <b>Belum bisa disimpan:</b>
                <ul>
                    <li v-for="message in errorMessages" :key="message">{{ message }}</li>
                </ul>
            </div>

            <section class="card">
                <h2>Pengiriman</h2>

                <div class="grid">
                    <SearchableSelect v-model="form.carrier_id" :options="carriers" label="Ekspedisi" :error="!!form.errors.carrier_id" :error-message="form.errors.carrier_id" />
                    <q-input v-model="form.vessel_name" outlined label="Kapal (opsional)" maxlength="100" :error="!!form.errors.vessel_name" :error-message="form.errors.vessel_name" />
                    <DateInput
                        v-model="form.departure_date"
                        label="Tanggal berangkat"
                        outlined
                        clearable
                        hint="Isi saat barangnya sudah berangkat. Menyimpan dengan tanggal ini mengubah semua nota di sini jadi Dikirim."
                        :error="!!form.errors.departure_date"
                        :error-message="form.errors.departure_date"
                    />
                </div>

                <q-input v-model="form.notes" outlined label="Catatan (opsional)" maxlength="1000" :error="!!form.errors.notes" :error-message="form.errors.notes" />
            </section>

            <section class="card">
                <h2>Nota dan packing list</h2>

                <q-input v-model="scanText" outlined dense autofocus clearable label="Scan atau ketik nomor nota, lalu Enter" @keydown.enter.prevent="scanPurchase">
                    <template #prepend><q-icon name="qr_code_scanner" /></template>
                </q-input>
                <div v-if="pickablePurchases.length > 0" class="pick">
                    <span>Nota siap kirim:</span>
                    <q-chip v-for="purchase in pickablePurchases" :key="purchase.id" clickable dense color="grey-3" @click="addPurchase(purchase)">
                        {{ purchase.number }} · {{ purchase.supplier_name }}
                    </q-chip>
                </div>
                <p v-else-if="entries.length === 0" class="hint">Belum ada nota yang siap dikirim. Nota harus sudah dipesan (Konfirmasi & Bayar) dan belum masuk pengiriman lain.</p>

                <q-btn-toggle
                    v-if="entries.length > 0"
                    v-model="view"
                    no-caps
                    unelevated
                    toggle-color="primary"
                    color="grey-3"
                    text-color="grey-8"
                    class="view-toggle"
                    :options="[
                        { label: 'Per nota', value: 'purchase' },
                        { label: 'Per container', value: 'container' },
                    ]"
                />

                <div v-for="entry in entries" v-show="view === 'purchase'" :key="entry.purchase.id" class="entry">
                    <div class="entry__head">
                        <div>
                            <a :href="entry.purchase.url" target="_blank" rel="noopener" class="entry__number">{{ entry.purchase.number }}</a>
                            <small>{{ entry.purchase.supplier_name }}<template v-if="entry.purchase.supplier_document_number"> · nota {{ entry.purchase.supplier_document_number }}</template></small>
                        </div>
                        <q-btn flat dense no-caps color="negative" icon="close" label="Keluarkan" @click="removePurchase(entry)" />
                    </div>

                    <div v-for="item in entry.purchase.items" :key="item.id" class="item">
                        <div class="item__info">
                            <b>{{ item.name }}</b>
                            <small>{{ item.sku }} · dipesan {{ formatQuantity(item.quantity) }} {{ item.unit_code }}</small>
                            <small v-if="Math.abs(remainingOf(entry, item)) < 0.0005" class="item__ok">Semua sudah masuk container</small>
                            <small v-else class="item__warn">
                                {{ remainingOf(entry, item) > 0 ? `Sisa ${formatQuantity(remainingOf(entry, item))} ${item.unit_code} belum masuk container` : `Lebih ${formatQuantity(-remainingOf(entry, item))} ${item.unit_code} dari nota` }}
                            </small>
                        </div>
                        <div v-if="isSimpleLine(entry, item)" class="simple-line">
                            <SuggestedTextInput v-model="rowsOf(entry, item)[0].container" :options="containerOptions" label="No. container" dense :data-line-container="item.id" />
                            <MoneyInput v-model="rowsOf(entry, item)[0].freight_cost" label="Biaya" prefix="Rp" outlined dense />
                            <q-btn flat dense no-caps color="primary" icon="call_split" label="Pisah ke container lain" @click="addRow(entry, item)" />
                        </div>
                        <div v-else class="item__rows">
                            <div v-for="row in rowsOf(entry, item)" :key="row.key" class="packing-row">
                                <SuggestedTextInput v-model="row.container" :options="containerOptions" label="No. container" dense />
                                <MoneyInput v-model="row.quantity" allow-decimal label="Jumlah" outlined dense />
                                <MoneyInput v-model="row.freight_cost" label="Biaya" prefix="Rp" outlined dense />
                                <q-btn flat round dense icon="close" color="grey-7" aria-label="Hapus baris" @click="removeRow(entry, item, row)" />
                            </div>
                            <q-btn flat dense no-caps color="primary" icon="add" :label="rowsOf(entry, item).length === 0 ? 'Masukkan ke container' : 'Container lain'" class="self-start" @click="addRow(entry, item)" />
                        </div>
                    </div>
                </div>

                <template v-if="view === 'container'">
                    <p v-if="containerSummaries.length === 0" class="hint">Belum ada barang yang diberi nomor container.</p>

                    <div v-for="container in containerSummaries" :key="container.number" class="summary__container">
                        <div class="summary__container-head">
                            <b>Container {{ container.number }}</b>
                            <span>Sub total {{ formatRupiah(container.total) }}</span>
                        </div>
                        <div class="summary__scroll">
                            <table class="summary__table">
                                <thead>
                                    <tr>
                                        <th>Nota</th>
                                        <th>Toko</th>
                                        <th>Barang</th>
                                        <th class="summary__right">Jumlah</th>
                                        <th class="summary__right">Biaya</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in container.lines" :key="line.key">
                                        <td>{{ line.purchase_number }}</td>
                                        <td>{{ line.supplier_name }}</td>
                                        <td>
                                            <b>{{ line.product_name }}</b>
                                            <small>{{ line.sku }}</small>
                                        </td>
                                        <td class="summary__right">{{ formatQuantity(line.quantity) }} {{ line.unit_code }}</td>
                                        <td class="summary__right">{{ line.freight_cost === '' ? '—' : formatRupiah(line.freight_cost) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </section>

            <section v-if="entries.length > 0" class="card">
                <h2>Ringkasan</h2>
                <p class="summary__state" :class="isEverythingInContainers ? 'summary__state--ok' : 'summary__state--warn'">
                    {{ isEverythingInContainers ? 'Semua barang nota sudah masuk container.' : 'Masih ada barang yang belum masuk container atau jumlahnya belum sama dengan nota.' }}
                </p>
                <p v-if="rowsWithoutContainer > 0" class="summary__state summary__state--warn">{{ rowsWithoutContainer }} baris belum punya nomor container.</p>

                <div v-for="container in containerSummaries" :key="container.number" class="summary__row">
                    <span>Container {{ container.number }} <small>· {{ container.lines.length }} baris</small></span>
                    <span>{{ formatRupiah(container.total) }}</span>
                </div>
                <div class="summary__row summary__row--total">
                    <span>Total biaya</span>
                    <b>{{ formatRupiah(grandTotal) }}</b>
                </div>
            </section>
        </div>
    </StoreLayout>
</template>

<style scoped>
.shipment-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.card {
    padding: 16px 18px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.card h2 {
    margin: 0 0 12px;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: normal;
    line-height: 1.4;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    margin-bottom: 12px;
}

.hint {
    margin: 10px 0 0;
    color: var(--app-mute);
    font-size: 12.5px;
}

.notice {
    padding: 10px 14px;
    border-radius: 8px;
    background: #fff1f2;
    color: #be123c;
    font-size: 13px;
}

.notice ul {
    margin: 4px 0 0;
    padding-left: 18px;
}

.pick {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
    margin-top: 8px;
    color: var(--app-mute);
    font-size: 12.5px;
}

.entry {
    margin-top: 16px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
}

.entry__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--app-line);
    background: var(--app-bg, #f7f7f9);
}

.entry__head small,
.item__info small {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
}

.entry__number {
    color: var(--app-brand);
    font-weight: 700;
    text-decoration: none;
}

.item {
    display: grid;
    grid-template-columns: minmax(180px, 2fr) 5fr;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid var(--app-line);
}

.item:last-child {
    border-bottom: 0;
}

.item .item__ok {
    color: #15803d;
}

.item .item__warn {
    color: #b45309;
}

.item__rows {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.simple-line {
    display: grid;
    grid-template-columns: minmax(160px, 2fr) minmax(130px, 1.2fr) auto;
    align-items: start;
    gap: 8px;
}

/* Not `.row`: Quasar puts that class on the root of every field, and a scoped rule reaches the root of child components. */
.packing-row {
    display: grid;
    grid-template-columns: minmax(160px, 2fr) minmax(100px, 1fr) minmax(130px, 1.2fr) 36px;
    align-items: start;
    gap: 8px;
}

.summary__state {
    margin: 0 0 10px;
    font-size: 13px;
}

.summary__state--ok {
    color: #15803d;
}

.summary__state--warn {
    color: #b45309;
}

.summary__container {
    margin-bottom: 12px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
}

.summary__container-head {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 14px;
    border-bottom: 1px solid var(--app-line);
    background: var(--app-bg, #f7f7f9);
}

.summary__scroll {
    overflow-x: auto;
}

.summary__table {
    width: 100%;
    border-collapse: collapse;
}

.summary__table th {
    padding: 6px 14px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.summary__table td {
    padding: 6px 14px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: top;
}

.summary__table td small {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
}

.summary__table tr:last-child td {
    border-bottom: 0;
}

.summary__table .summary__right {
    text-align: right;
}

.view-toggle {
    margin: 12px 0;
}

.summary__row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
}

.summary__row small {
    color: var(--app-mute);
}

.summary__row--total {
    font-size: 16px;
}

@media (max-width: 900px) {
    .item {
        grid-template-columns: 1fr;
    }

    .packing-row,
    .simple-line {
        grid-template-columns: 1fr 1fr;
    }
}
</style>
