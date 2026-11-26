<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import { shipmentStatusChipClass } from '../../../Composables/useShipmentStatus';
import { visitUnlessNewTab } from '../../../Composables/useVisitLink';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    shipment: { type: Object, required: true },
});

const $q = useQuasar();

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });
const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

const isCancelled = computed(() => props.shipment.status === 'cancelled');

const readableSize = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

const newFiles = ref([]);
const isUploading = ref(false);

function uploadFiles() {
    if (newFiles.value.length === 0) {
        return;
    }

    router.post(
        props.shipment.urls.attachments,
        { attachments: newFiles.value },
        {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => (isUploading.value = true),
            onFinish: () => (isUploading.value = false),
            onSuccess: () => (newFiles.value = []),
            onError: (errors) => $q.notify({ type: 'negative', position: 'top', message: Object.values(errors)[0] ?? 'File gagal diunggah.' }),
        },
    );
}

/** @param {{name: string, delete_url: string}} attachment */
function deleteAttachment(attachment) {
    $q.dialog({
        title: 'Hapus file?',
        message: `${attachment.name} dihapus dari pengiriman ini.`,
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Hapus', color: 'negative', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => router.delete(attachment.delete_url, { preserveScroll: true }));
}

function cancelShipment() {
    $q.dialog({
        title: 'Batalkan pengiriman?',
        message: `${props.shipment.number} tetap ada di daftar sebagai dibatalkan, dan notanya bisa dimuat di pengiriman lain. Isi alasannya.`,
        prompt: { model: '', type: 'text', label: 'Alasan pembatalan', outlined: true, dense: true, isValid: (reason) => reason.trim() !== '' },
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Batalkan Pengiriman', color: 'negative', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk((reason) => router.post(props.shipment.urls.cancel, { reason }, { preserveScroll: true }));
}
</script>

<template>
    <StoreLayout :title="shipment.number">
        <template #actions>
            <q-btn flat no-caps label="Daftar pengiriman" class="q-mr-sm" :href="shipment.urls.index" @click="visitUnlessNewTab($event, shipment.urls.index)" />
            <q-btn v-if="shipment.is_editable" flat no-caps color="negative" label="Batalkan" class="q-mr-sm" @click="cancelShipment" />
            <q-btn v-if="shipment.is_editable" color="primary" unelevated no-caps icon="edit" label="Ubah / Isi Packing List" :href="shipment.urls.edit" @click="visitUnlessNewTab($event, shipment.urls.edit)" />
        </template>

        <p v-if="isCancelled" class="notice">
            Dibatalkan {{ shipment.cancelled_at ? dateTimeFormat.format(new Date(shipment.cancelled_at)) : '' }}: {{ shipment.cancel_reason }}
        </p>

        <section class="card">
            <div class="card__head">
                <h2>Pengiriman</h2>
                <span class="chip" :class="shipmentStatusChipClass[shipment.status]">{{ shipment.status_label }}</span>
            </div>

            <dl class="facts">
                <div><dt>Ekspedisi</dt><dd>{{ shipment.carrier_name }}</dd></div>
                <div><dt>Kapal</dt><dd>{{ shipment.vessel_name ?? '—' }}</dd></div>
                <div><dt>Tanggal berangkat</dt><dd>{{ shipment.departure_date ? dateFormat.format(new Date(shipment.departure_date)) : 'Belum berangkat' }}</dd></div>
                <div v-if="shipment.expected_arrival_date">
                    <dt>Estimasi sampai di toko</dt>
                    <dd>
                        {{ dateFormat.format(new Date(shipment.expected_arrival_date)) }}
                        <small v-if="!shipment.estimated_arrival_date">dari lama kirim ekspedisi</small>
                    </dd>
                </div>
                <div><dt>Dibuat oleh</dt><dd>{{ shipment.created_by_name }}</dd></div>
                <div><dt>Total biaya</dt><dd>{{ formatRupiah(shipment.total_freight_cost) }}</dd></div>
            </dl>
            <p v-if="shipment.notes" class="notes">{{ shipment.notes }}</p>
        </section>

        <section class="card">
            <h2>Nota di pengiriman ini</h2>
            <ul class="purchases">
                <li v-for="purchase in shipment.purchases" :key="purchase.id">
                    <a :href="purchase.url" @click="visitUnlessNewTab($event, purchase.url)">{{ purchase.number }}</a>
                    <span>{{ purchase.supplier_name }}<template v-if="purchase.supplier_document_number"> · nota {{ purchase.supplier_document_number }}</template></span>
                </li>
            </ul>
        </section>

        <section class="card">
            <h2>File packing list</h2>

            <p v-if="shipment.attachments.length === 0" class="empty">Belum ada file dari ekspedisi.</p>
            <ul v-else class="files">
                <li v-for="attachment in shipment.attachments" :key="attachment.id">
                    <q-icon name="description" color="grey-7" size="20px" />
                    <a :href="attachment.url" target="_blank" rel="noopener" class="files__name">{{ attachment.name }}</a>
                    <small>{{ readableSize(attachment.size) }}</small>
                    <q-btn v-if="!isCancelled" flat round dense icon="delete_outline" color="negative" :aria-label="`Hapus ${attachment.name}`" @click="deleteAttachment(attachment)" />
                </li>
            </ul>

            <div v-if="!isCancelled" class="upload">
                <q-file v-model="newFiles" outlined dense multiple append use-chips label="Tambah file packing list" accept=".pdf,.xls,.xlsx,.csv,.doc,.docx,.jpg,.jpeg,.png,.webp" class="upload__field">
                    <template #prepend><q-icon name="attach_file" /></template>
                </q-file>
                <q-btn color="primary" unelevated no-caps label="Unggah" :disable="newFiles.length === 0" :loading="isUploading" @click="uploadFiles" />
            </div>
        </section>

        <section class="card">
            <h2>Packing list</h2>
            <p v-if="shipment.containers.length === 0" class="empty">Packing list belum diisi. Pakai Ubah / Isi Packing List saat dokumen dari ekspedisi sudah ada.</p>

            <div v-for="container in shipment.containers" :key="container.id" class="container">
                <div class="container__head">
                    <b>Container {{ container.number }}</b>
                    <span>Sub total {{ formatRupiah(container.total_freight_cost) }}</span>
                </div>
                <div class="table-scroll">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nota</th>
                                <th>Toko</th>
                                <th>Barang</th>
                                <th class="table__right">Jumlah</th>
                                <th class="table__right">Biaya</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in container.lines" :key="line.purchase_item_id">
                                <td>{{ line.purchase_number }}</td>
                                <td>{{ line.supplier_name }}</td>
                                <td>
                                    <b>{{ line.product_name }}</b>
                                    <small>{{ line.sku }}</small>
                                </td>
                                <td class="table__right">{{ formatQuantity(line.quantity) }} {{ line.unit_code }}</td>
                                <td class="table__right">{{ formatRupiah(line.freight_cost) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </StoreLayout>
</template>

<style scoped>
.notice {
    margin: 0 0 14px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #fff1f2;
    color: #be123c;
    font-size: 13px;
}

.card {
    margin-bottom: 16px;
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

.card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.card__head h2 {
    margin: 0;
}

.facts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    margin: 12px 0 0;
}

.facts dt {
    color: var(--app-mute);
    font-size: 12px;
}

.facts dd {
    margin: 2px 0 0;
    font-weight: 600;
}

.facts dd small {
    display: block;
    color: var(--app-mute);
    font-weight: 400;
}

.notes {
    margin: 12px 0 0;
    color: var(--app-mute);
    white-space: pre-line;
}

.purchases {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.purchases li span {
    margin-left: 8px;
    color: var(--app-mute);
}

.container {
    margin-top: 12px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
}

.container__head {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--app-line);
    background: var(--app-bg, #f7f7f9);
}

.table-scroll {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    padding: 8px 14px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.table td {
    padding: 8px 14px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: top;
}

.table td small {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
}

.table tr:last-child td {
    border-bottom: 0;
}

.table .table__right {
    text-align: right;
}

.empty {
    margin: 0 0 12px;
    color: var(--app-mute);
}

.files {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin: 0 0 12px;
    padding: 0;
    list-style: none;
}

.files li {
    display: flex;
    align-items: center;
    gap: 8px;
}

.files__name {
    flex: 1;
    overflow: hidden;
    color: var(--q-primary);
    text-overflow: ellipsis;
    white-space: nowrap;
}

.files small {
    color: var(--app-mute);
}

.upload {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 8px;
}

.upload__field {
    flex: 1 1 260px;
}
</style>
