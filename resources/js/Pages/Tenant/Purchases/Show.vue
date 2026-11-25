<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import { purchaseStatusChipClass } from '../../../Composables/usePurchaseStatus';
import { visitUnlessNewTab } from '../../../Composables/useVisitLink';
import StoreLayout from '../../../Layouts/StoreLayout.vue';


const props = defineProps({
    purchase: { type: Object, required: true },
});

const $q = useQuasar();

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });
const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

const readableSize = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

const newFiles = ref([]);
const isUploading = ref(false);

const isCancelled = computed(() => props.purchase.status === 'cancelled');

/** The steps of a nota that goes the whole way, for the strip under the title; a cancelled one shows its own notice instead. */
const steps = [
    { status: 'draft', label: 'Draft' },
    { status: 'ordered', label: 'Dipesan' },
    { status: 'partially_received', label: 'Diterima sebagian' },
    { status: 'received', label: 'Diterima' },
];

const currentStepIndex = computed(() => steps.findIndex((step) => step.status === props.purchase.status));

function confirmPurchase() {
    $q.dialog({
        title: 'Konfirmasi nota ini?',
        message: 'Setelah dikonfirmasi, barang dan harga di nota ini terkunci dan tidak bisa diubah. Stok belum bertambah sampai barangnya diterima.',
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Konfirmasi', color: 'primary', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => router.post(props.purchase.urls.confirm, {}, { preserveScroll: true }));
}

function cancelPurchase() {
    $q.dialog({
        title: 'Batalkan nota?',
        message: `${props.purchase.number} tetap ada di daftar sebagai dibatalkan. Isi alasannya.`,
        prompt: { model: '', type: 'text', label: 'Alasan pembatalan', outlined: true, dense: true, isValid: (reason) => reason.trim() !== '' },
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Batalkan Nota', color: 'negative', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk((reason) => router.post(props.purchase.urls.cancel, { reason }, { preserveScroll: true }));
}

function uploadFiles() {
    if (newFiles.value.length === 0) {
        return;
    }

    router.post(
        props.purchase.urls.attachments,
        { attachments: newFiles.value },
        {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => (isUploading.value = true),
            onFinish: () => (isUploading.value = false),
            onSuccess: () => (newFiles.value = []),
            onError: (errors) => $q.notify({ type: 'negative', position: 'top', message: Object.values(errors)[0] ?? 'Lampiran gagal diunggah.' }),
        },
    );
}

/** @param {{name: string, delete_url: string}} attachment */
function deleteAttachment(attachment) {
    $q.dialog({
        title: 'Hapus lampiran?',
        message: `${attachment.name} dihapus dari nota ini.`,
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Hapus', color: 'negative', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => router.delete(attachment.delete_url, { preserveScroll: true }));
}
</script>

<template>
    <StoreLayout :title="purchase.number">
        <template #actions>
            <q-btn flat no-caps label="Daftar nota" class="q-mr-sm" :href="purchase.urls.index" @click="visitUnlessNewTab($event, purchase.urls.index)" />
            <q-btn v-if="purchase.is_editable" flat no-caps icon="edit" label="Ubah" class="q-mr-sm" :href="purchase.urls.edit" @click="visitUnlessNewTab($event, purchase.urls.edit)" />
            <q-btn v-if="purchase.is_cancellable" flat no-caps color="negative" label="Batalkan" class="q-mr-sm" @click="cancelPurchase" />
            <q-btn v-if="purchase.is_editable" color="primary" unelevated no-caps icon="check" label="Konfirmasi" @click="confirmPurchase" />
        </template>

        <p v-if="isCancelled" class="notice notice--bad">
            Dibatalkan {{ purchase.cancelled_at ? dateTimeFormat.format(new Date(purchase.cancelled_at)) : '' }}: {{ purchase.cancel_reason }}
        </p>
        <ol v-else-if="currentStepIndex >= 0" class="steps">
            <li v-for="(step, index) in steps" :key="step.status" :class="{ 'steps__item--done': index < currentStepIndex, 'steps__item--now': index === currentStepIndex }">{{ step.label }}</li>
        </ol>

        <section class="card">
            <div class="card__head">
                <h2>Nota</h2>
                <span class="chip" :class="purchaseStatusChipClass[purchase.status]">{{ purchase.status_label }}</span>
            </div>

            <dl class="facts">
                <div><dt>Supplier</dt><dd>{{ purchase.supplier_name }}</dd></div>
                <div><dt>Nomor nota supplier</dt><dd>{{ purchase.supplier_document_number ?? 'â€”' }}</dd></div>
                <div><dt>Tanggal nota</dt><dd>{{ dateFormat.format(new Date(purchase.purchase_date)) }}</dd></div>
                <div><dt>Perkiraan tiba</dt><dd>{{ purchase.expected_date ? dateFormat.format(new Date(purchase.expected_date)) : 'â€”' }}</dd></div>
                <div><dt>Gudang tujuan</dt><dd>{{ purchase.warehouse_name }}</dd></div>
                <div><dt>Dibuat oleh</dt><dd>{{ purchase.created_by_name }}</dd></div>
                <div v-if="purchase.confirmed_by_name"><dt>Dikonfirmasi oleh</dt><dd>{{ purchase.confirmed_by_name }}</dd></div>
            </dl>
            <p v-if="purchase.notes" class="notes">{{ purchase.notes }}</p>
        </section>

        <section class="card">
            <h2>Barang</h2>
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th class="table__right">Jumlah</th>
                            <th class="table__right">Harga satuan</th>
                            <th class="table__right">Subtotal</th>
                            <th class="table__right">Sudah diterima</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in purchase.items" :key="item.id">
                            <td>
                                <b>{{ item.product.name }}</b>
                                <small>{{ [item.product.sku, item.notes].filter(Boolean).join(' Â· ') }}</small>
                            </td>
                            <td class="table__right">
                                {{ formatQuantity(item.quantity) }} {{ item.unit_code }}
                                <small v-if="item.unit_code !== item.product.base_unit">= {{ formatQuantity(item.base_quantity) }} {{ item.product.base_unit }}</small>
                            </td>
                            <td class="table__right">{{ formatRupiah(item.unit_cost) }}</td>
                            <td class="table__right">{{ formatRupiah(item.subtotal) }}</td>
                            <td class="table__right">{{ formatQuantity(item.received_base_quantity) }} {{ item.product.base_unit }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="table__right">Total</td>
                            <td class="table__right"><b>{{ formatRupiah(purchase.total_amount) }}</b></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="card">
            <h2>Foto atau PDF nota</h2>

            <p v-if="purchase.attachments.length === 0" class="empty">Belum ada file nota.</p>
            <ul v-else class="files">
                <li v-for="attachment in purchase.attachments" :key="attachment.id">
                    <q-icon :name="attachment.is_image ? 'image' : 'picture_as_pdf'" color="grey-7" size="20px" />
                    <a :href="attachment.url" target="_blank" rel="noopener" class="files__name">{{ attachment.name }}</a>
                    <small>{{ readableSize(attachment.size) }}</small>
                    <q-btn v-if="!isCancelled" flat round dense icon="delete_outline" color="negative" :aria-label="`Hapus ${attachment.name}`" @click="deleteAttachment(attachment)" />
                </li>
            </ul>

            <div v-if="!isCancelled" class="upload">
                <q-file v-model="newFiles" outlined dense multiple append use-chips label="Tambah file nota" accept=".jpg,.jpeg,.png,.webp,.pdf" class="upload__field">
                    <template #prepend><q-icon name="attach_file" /></template>
                </q-file>
                <q-btn color="primary" unelevated no-caps label="Unggah" :disable="newFiles.length === 0" :loading="isUploading" @click="uploadFiles" />
            </div>
        </section>
    </StoreLayout>
</template>

<style scoped>
.notice {
    margin: 0 0 14px;
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 13px;
}

.notice--bad {
    background: #fff1f2;
    color: #be123c;
}

.steps {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0 0 14px;
    padding: 0;
    list-style: none;
}

.steps li {
    padding: 4px 12px;
    border: 1px solid var(--app-line);
    border-radius: 999px;
    color: var(--app-mute);
    font-size: 12.5px;
}

.steps__item--done {
    color: var(--app-ink-soft);
}

.steps li.steps__item--now {
    border-color: var(--q-primary);
    background: var(--q-primary);
    color: #fff;
    font-weight: 600;
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
}

.card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 12px;
}

.card__head h2 {
    margin: 0;
}

.facts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px 20px;
    margin: 0;
}

.facts dt {
    color: var(--app-mute);
    font-size: 12px;
}

.facts dd {
    margin: 2px 0 0;
    font-weight: 600;
}

.notes {
    margin: 12px 0 0;
    color: var(--app-ink-soft);
}

.table-scroll {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    padding: 8px 6px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.table td {
    padding: 8px 6px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.table td small {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
}

.table .table__right {
    text-align: right;
    white-space: nowrap;
}

.table tfoot td {
    border-bottom: 0;
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

.empty {
    margin: 0 0 12px;
    color: var(--app-mute);
}
</style>
