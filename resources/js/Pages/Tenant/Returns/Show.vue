<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import { postJson } from '../../../Composables/postJson';
import { printSheet } from '../../../Composables/printSheet';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import { returnStatusChipClass } from '../../../Composables/useReturnStatus';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    saleReturn: { type: Object, required: true },
});

const $q = useQuasar();

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

const isPrinting = ref(false);

function approve() {
    $q.dialog({
        title: 'Setujui retur?',
        message: 'Barang masuk ke gudang tujuannya, dan uang dikembalikan atau barang pengganti dijual. Ini tidak bisa dibatalkan. Kalau persetujuan gagal, retur tetap menunggu: Anda bisa menolaknya lalu meminta kasir mengajukan ulang.',
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Setujui', color: 'primary', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => router.post(props.saleReturn.urls.approve, {}, { preserveScroll: true }));
}

function reject() {
    $q.dialog({
        title: 'Tolak retur?',
        message: 'Stok dan kas tidak berubah. Barangnya bisa diajukan retur lagi.',
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Tolak', color: 'negative', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => router.post(props.saleReturn.urls.reject, {}, { preserveScroll: true }));
}

const isPrintingReplacement = ref(false);

/** Counts a print of the receipt made for the replacement goods, then opens it in a new window. */
async function printReplacement() {
    isPrintingReplacement.value = true;

    try {
        await printSheet(async () => (await postJson(props.saleReturn.exchange.print_url)).sheet_url);
    } catch (error) {
        $q.notify({ type: 'negative', message: error.message, position: 'top' });
    } finally {
        isPrintingReplacement.value = false;
    }
}

/** Opens the return slip in a new window, which prints itself. */
async function print() {
    isPrinting.value = true;

    try {
        await printSheet(async () => props.saleReturn.urls.sheet);
    } catch (error) {
        $q.notify({ type: 'negative', message: error.message, position: 'top' });
    } finally {
        isPrinting.value = false;
    }
}
</script>

<template>
    <StoreLayout :title="saleReturn.number">
        <template #back>
            <q-btn flat no-caps dense icon="arrow_back" label="Retur" class="q-ml-sm" @click="router.visit(saleReturn.urls.index)" />
        </template>

        <template #actions>
            <q-btn outline no-caps dense color="primary" icon="print" label="Cetak Bukti Retur" :loading="isPrinting" class="q-mr-sm" @click="print" />
            <q-btn v-if="saleReturn.exchange?.print_url" outline no-caps dense color="primary" icon="receipt_long" label="Cetak Nota Pengganti" :loading="isPrintingReplacement" class="q-mr-sm" @click="printReplacement" />
            <template v-if="saleReturn.is_pending">
                <q-btn flat no-caps dense color="negative" label="Tolak" class="q-mr-sm" @click="reject" />
                <q-btn color="primary" unelevated no-caps dense label="Setujui" @click="approve" />
            </template>
        </template>

        <article class="ret">
            <header class="ret__head">
                <div>
                    <b>{{ saleReturn.number }}</b>
                    <small>{{ dateTimeFormat.format(new Date(saleReturn.returned_at)) }} · kasir {{ saleReturn.cashier_name }}</small>
                    <small>Nota <a :href="saleReturn.urls.sale" @click.prevent="router.visit(saleReturn.urls.sale)">{{ saleReturn.sale.number }}</a><template v-if="saleReturn.sale.customer_name"> · {{ saleReturn.sale.customer_name }}</template></small>
                </div>
                <span class="chip" :class="returnStatusChipClass[saleReturn.status]">{{ saleReturn.status_label }}</span>
            </header>

            <dl class="ret__facts">
                <div><dt>Penyelesaian</dt><dd>{{ saleReturn.resolution_label }}</dd></div>
                <div v-if="!saleReturn.exchange"><dt>Dikembalikan</dt><dd>{{ formatRupiah(saleReturn.refund_amount) }} lewat {{ saleReturn.refund_method_label }}</dd></div>
                <div v-else><dt>Nilai retur</dt><dd>{{ formatRupiah(saleReturn.credit_amount) }}</dd></div>
                <div v-if="saleReturn.refund_account"><dt>Rekening penerima</dt><dd>{{ saleReturn.refund_account }}</dd></div>
                <div v-if="saleReturn.reason"><dt>Alasan</dt><dd>{{ saleReturn.reason }}</dd></div>
            </dl>

            <table class="ret__items">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th class="ret__right">Jumlah</th>
                        <th class="ret__right">Harga</th>
                        <th class="ret__right">Subtotal</th>
                        <th>Masuk ke</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in saleReturn.items" :key="item.id">
                        <td>{{ item.name }}</td>
                        <td class="ret__right">{{ formatQuantity(item.quantity) }} {{ item.unit }}</td>
                        <td class="ret__right">{{ formatRupiah(item.unit_price) }}</td>
                        <td class="ret__right">{{ formatRupiah(item.refund_subtotal) }}</td>
                        <td><span class="chip" :class="item.disposition === 'dead_stock' ? 'chip--bad' : 'chip--ok'">{{ item.disposition_label }}</span></td>
                    </tr>
                </tbody>
            </table>

            <template v-if="saleReturn.exchange">
                <h2>Barang pengganti</h2>
                <table class="ret__items">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th class="ret__right">Jumlah</th>
                            <th class="ret__right">Harga</th>
                            <th class="ret__right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in saleReturn.exchange.items" :key="index">
                            <td>{{ item.name }}</td>
                            <td class="ret__right">{{ formatQuantity(item.quantity) }} {{ item.unit }}</td>
                            <td class="ret__right">{{ formatRupiah(item.unit_price) }}</td>
                            <td class="ret__right">{{ formatRupiah(item.subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>

                <dl class="ret__facts ret__settle">
                    <div><dt>Nilai pengganti</dt><dd>{{ formatRupiah(saleReturn.exchange.total) }}</dd></div>
                    <div><dt>Nilai retur</dt><dd>{{ formatRupiah(saleReturn.credit_amount) }}</dd></div>
                    <div v-for="payment in saleReturn.exchange.payments" :key="payment.method"><dt>Pelanggan menambah</dt><dd>{{ formatRupiah(payment.amount) }} lewat {{ payment.method }}</dd></div>
                    <div v-if="Number(saleReturn.refund_amount) > 0"><dt>Dikembalikan</dt><dd>{{ formatRupiah(saleReturn.refund_amount) }} lewat {{ saleReturn.refund_method_label }}</dd></div>
                    <div v-if="saleReturn.exchange.sale_number"><dt>Nota pengganti</dt><dd>{{ saleReturn.exchange.sale_number }}</dd></div>
                    <div v-else><dt>Nota pengganti</dt><dd class="ret__muted">dibuat saat disetujui</dd></div>
                </dl>
            </template>
        </article>
    </StoreLayout>
</template>

<style scoped>
.ret {
    max-width: 820px;
    padding: 20px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.ret__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.ret__head b,
.ret__head small {
    display: block;
}

.ret__head b {
    font-size: 17px;
}

.ret__head small {
    color: var(--app-mute);
    font-size: 12.5px;
}

.ret__facts {
    margin: 0 0 16px;
}

.ret__facts div {
    display: flex;
    gap: 12px;
    padding: 3px 0;
}

.ret__facts dt {
    flex: 0 0 100px;
    color: var(--app-mute);
}

.ret__facts dd {
    margin: 0;
}

.ret h2 {
    margin: 22px 0 8px;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: normal;
    line-height: 1.4;
}

.ret__settle {
    margin: 12px 0 0;
}

.ret__muted {
    color: var(--app-mute);
}

.ret__items {
    width: 100%;
    border-collapse: collapse;
}

.ret__items th {
    padding: 8px 0;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
}

.ret__items td {
    padding: 9px 0;
    border-bottom: 1px solid var(--app-line);
}

.ret__items .ret__right {
    text-align: right;
    white-space: nowrap;
}

.ret__items th:last-child,
.ret__items td:last-child {
    padding-left: 16px;
}
</style>
