<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import { postJson } from '../../../Composables/postJson';
import { printInNewWindow } from '../../../Composables/printInNewWindow';
import { deliveryNoteStatusChipClass } from '../../../Composables/useDeliveryNoteStatus';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    note: { type: Object, required: true },
    /** @type {{id: number, name: string}[]} the drivers that can take the goods out */
    drivers: { type: Array, default: () => [] },
    settings_url: { type: String, default: null },
});

const $q = useQuasar();

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });

const isCompleteOpen = ref(false);
const isCompleting = ref(false);

/** How much of each item came back to the store; the rest counts as delivered. */
const returnedByItem = ref({});

const isPrinting = ref(false);

const isFinished = computed(() => ['delivered', 'partially_delivered', 'returned'].includes(props.note.status));

function openComplete() {
    returnedByItem.value = Object.fromEntries(props.note.items.map((item) => [item.id, 0]));
    isCompleteOpen.value = true;
}

const isCompletionValid = computed(() => props.note.items.every((item) => {
    const returned = Number(returnedByItem.value[item.id]);

    return Number.isFinite(returned) && returned >= 0 && returned <= Number(item.quantity);
}));

function complete() {
    const results = props.note.items.map((item) => {
        const returned = Number(returnedByItem.value[item.id]);

        return { item_id: item.id, quantity_delivered: Math.round((Number(item.quantity) - returned) * 1000) / 1000, quantity_returned: returned };
    });

    router.post(props.note.urls.complete, { results }, {
        preserveScroll: true,
        onStart: () => (isCompleting.value = true),
        onFinish: () => (isCompleting.value = false),
        onSuccess: () => (isCompleteOpen.value = false),
        onError: (errors) => $q.notify({ type: 'negative', message: Object.values(errors)[0], position: 'top' }),
    });
}

const isShipOpen = ref(false);
const isShipping = ref(false);
const driverId = ref(null);

/** Who takes the goods out is chosen now, from the drivers kept in the store settings; leaving it empty is allowed. */
function openShip() {
    driverId.value = null;
    isShipOpen.value = true;
}

function ship() {
    router.post(props.note.urls.ship, { driver_id: driverId.value }, {
        preserveScroll: true,
        onStart: () => (isShipping.value = true),
        onFinish: () => (isShipping.value = false),
        onSuccess: () => (isShipOpen.value = false),
        onError: (errors) => $q.notify({ type: 'negative', message: Object.values(errors)[0], position: 'top' }),
    });
}

function cancel() {
    $q.dialog({
        title: 'Batalkan surat jalan?',
        message: 'Barangnya bisa dimasukkan lagi ke surat jalan lain.',
        cancel: { label: 'Tidak', flat: true, noCaps: true },
        ok: { label: 'Batalkan', color: 'negative', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => router.post(props.note.urls.cancel, {}, { preserveScroll: true }));
}

/**
 * Counts the print on the server, then prints the sheet in a new window. The sheet says SALINAN from the second print on.
 */
async function print() {
    isPrinting.value = true;

    try {
        await printInNewWindow(async () => (await postJson(props.note.urls.print)).sheet_url);
        router.reload({ only: ['note'] });
    } catch (error) {
        $q.notify({ type: 'negative', message: error.message, position: 'top' });
    } finally {
        isPrinting.value = false;
    }
}
</script>

<template>
    <StoreLayout :title="note.number">
        <template #back>
            <q-btn flat no-caps dense icon="arrow_back" label="Surat Jalan" class="q-ml-sm" @click="router.visit(note.urls.index)" />
        </template>

        <template #actions>
            <q-btn v-if="note.can_cancel" flat no-caps dense color="negative" label="Batalkan" class="q-mr-sm" @click="cancel" />
            <q-btn outline no-caps dense color="primary" icon="print" :label="note.print_count > 0 ? 'Cetak ulang' : 'Cetak'" :loading="isPrinting" class="q-mr-sm" @click="print" />
            <q-btn v-if="note.can_ship" color="primary" unelevated no-caps dense label="Tandai Dikirim" @click="openShip" />
            <q-btn v-if="note.can_complete" color="positive" unelevated no-caps dense label="Selesaikan Pengiriman" @click="openComplete" />
            <q-btn v-if="isFinished && note.items.some((item) => Number(item.quantity_returned) > 0)" outline no-caps dense color="primary" icon="undo" label="Buat Retur" class="q-ml-sm" @click="router.visit(note.sale.new_return_url)" />
        </template>

        <article class="note">
            <header class="note__head">
                <div>
                    <b>{{ note.number }}</b>
                    <small>{{ dateFormat.format(new Date(note.business_date)) }} · dibuat {{ note.created_by_name }}</small>
                    <small>Nota <a :href="note.sale.show_url" @click.prevent="router.visit(note.sale.show_url)">{{ note.sale.number }}</a><template v-if="note.other_sale_numbers.length > 0">, juga memuat barang dari nota {{ note.other_sale_numbers.join(', ') }}</template></small>
                </div>
                <span class="chip" :class="deliveryNoteStatusChipClass[note.status]">{{ note.status_label }}</span>
            </header>

            <dl class="note__facts">
                <div><dt>Penerima</dt><dd>{{ note.recipient_name }}<template v-if="note.recipient_phone"> · {{ note.recipient_phone }}</template></dd></div>
                <div v-if="note.recipient_address"><dt>Alamat</dt><dd>{{ note.recipient_address }}</dd></div>
                <div v-if="note.driver_name || note.vehicle_number"><dt>Pengirim</dt><dd>{{ [note.driver_name, note.vehicle_number].filter(Boolean).join(' · ') }}</dd></div>
                <div v-if="note.shipped_at"><dt>Dikirim</dt><dd>{{ dateTimeFormat.format(new Date(note.shipped_at)) }}</dd></div>
                <div v-if="note.completed_at"><dt>Selesai</dt><dd>{{ dateTimeFormat.format(new Date(note.completed_at)) }}</dd></div>
                <div v-if="note.notes"><dt>Catatan</dt><dd>{{ note.notes }}</dd></div>
            </dl>

            <table class="note__items">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th class="note__right">Diantar</th>
                        <th v-if="isFinished" class="note__right">Terkirim</th>
                        <th v-if="isFinished" class="note__right">Kembali</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in note.items" :key="item.id">
                        <td>{{ item.name }}</td>
                        <td class="note__right">{{ formatQuantity(item.quantity) }} {{ item.unit }}</td>
                        <td v-if="isFinished" class="note__right">{{ formatQuantity(item.quantity_delivered) }} {{ item.unit }}</td>
                        <td v-if="isFinished" class="note__right">{{ formatQuantity(item.quantity_returned) }} {{ item.unit }}</td>
                    </tr>
                </tbody>
            </table>
        </article>

        <q-dialog v-model="isShipOpen" persistent>
            <q-card class="complete">
                <q-card-section>
                    <div class="complete__title">Tandai dikirim</div>
                    <p class="complete__hint">Pilih sopir yang mengantar.</p>

                    <q-select
                        v-model="driverId"
                        :options="drivers"
                        option-value="id"
                        option-label="name"
                        emit-value
                        map-options
                        clearable
                        outlined
                        dense
                        autofocus
                        label="Sopir"
                        behavior="menu"
                    />
                    <p v-if="drivers.length === 0" class="complete__hint">
                        Belum ada sopir. Tambahkan di <a :href="settings_url" @click.prevent="router.visit(settings_url)">Pengaturan Toko</a>, atau lanjutkan tanpa sopir.
                    </p>
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat no-caps label="Batal" :disable="isShipping" @click="isShipOpen = false" />
                    <q-btn color="primary" unelevated no-caps label="Tandai Dikirim" :loading="isShipping" @click="ship" />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <q-dialog v-model="isCompleteOpen" persistent>
            <q-card class="complete">
                <q-card-section>
                    <div class="complete__title">Selesaikan pengiriman</div>
                    <p class="complete__hint">Isi barang yang kembali ke toko. Sisanya dianggap sudah diterima.</p>

                    <div v-for="item in note.items" :key="item.id" class="complete__row">
                        <span>{{ item.name }}<small>diantar {{ formatQuantity(item.quantity) }} {{ item.unit }}</small></span>
                        <q-input v-model.number="returnedByItem[item.id]" type="number" min="0" step="any" outlined dense hide-bottom-space :suffix="item.unit" label="Kembali" :aria-label="`Kembali ${item.name}`" />
                    </div>
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat no-caps label="Batal" :disable="isCompleting" @click="isCompleteOpen = false" />
                    <q-btn color="positive" unelevated no-caps label="Selesaikan" :loading="isCompleting" :disable="!isCompletionValid" @click="complete" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </StoreLayout>
</template>

<style scoped>
.note {
    max-width: 720px;
    padding: 20px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.note__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.note__head b,
.note__head small {
    display: block;
}

.note__head b {
    font-size: 17px;
}

.note__head small {
    color: var(--app-mute);
    font-size: 12.5px;
}

.note__facts {
    margin: 0 0 16px;
}

.note__facts div {
    display: flex;
    gap: 12px;
    padding: 3px 0;
}

.note__facts dt {
    flex: 0 0 90px;
    color: var(--app-mute);
}

.note__facts dd {
    margin: 0;
}

.note__items {
    width: 100%;
    border-collapse: collapse;
}

.note__items th {
    padding: 8px 0;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
}

.note__items td {
    padding: 9px 0;
    border-bottom: 1px solid var(--app-line);
}

.note__items .note__right {
    text-align: right;
    white-space: nowrap;
}

.complete {
    width: 480px;
    max-width: 94vw;
}

.complete__title {
    font-size: 17px;
    font-weight: 700;
}

.complete__hint {
    margin: 4px 0 14px;
    color: var(--app-mute);
    font-size: 13px;
}

.complete__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 6px 0;
}

.complete__row small {
    display: block;
    color: var(--app-mute);
}

.complete__row :deep(.q-field) {
    width: 150px;
}
</style>
