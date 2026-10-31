<script setup>
import { formatQuantity } from '../Composables/useQuantityFormat';

defineProps({
    /** @type {{number: string, business_date: string, status: string, is_copy: boolean, recipient_name: string, recipient_phone: string|null, recipient_address: string|null, driver_name: string|null, vehicle_number: string|null, notes: string|null, sale: {number: string}, store: {name: string, address: string|null, phone: string|null}, items: object[]}} */
    note: { type: Object, required: true },
});

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' });
</script>

<template>
    <div class="delivery-print">
        <header>
            <div>
                <b>{{ note.store.name }}</b>
                <span v-if="note.store.address">{{ note.store.address }}</span>
                <span v-if="note.store.phone">{{ note.store.phone }}</span>
            </div>
            <div class="delivery-print__title">
                <h1>SURAT JALAN</h1>
                <b>{{ note.number }}</b>
                <span v-if="note.status === 'cancelled'" class="delivery-print__mark">BATAL</span>
                <span v-else-if="note.is_copy" class="delivery-print__mark">SALINAN</span>
            </div>
        </header>

        <dl class="delivery-print__meta">
            <div><dt>Tanggal</dt><dd>{{ dateFormat.format(new Date(note.business_date)) }}</dd></div>
            <div><dt>Nota</dt><dd>{{ note.sale.number }}</dd></div>
            <div><dt>Penerima</dt><dd>{{ note.recipient_name }}<template v-if="note.recipient_phone"> · {{ note.recipient_phone }}</template></dd></div>
            <div v-if="note.recipient_address"><dt>Alamat</dt><dd>{{ note.recipient_address }}</dd></div>
            <div v-if="note.driver_name || note.vehicle_number"><dt>Pengirim</dt><dd>{{ [note.driver_name, note.vehicle_number].filter(Boolean).join(' · ') }}</dd></div>
        </dl>

        <table class="delivery-print__items">
            <thead>
                <tr><th>No</th><th>Barang</th><th class="delivery-print__right">Jumlah</th></tr>
            </thead>
            <tbody>
                <tr v-for="(item, index) in note.items" :key="item.id">
                    <td>{{ index + 1 }}</td>
                    <td>{{ item.name }}</td>
                    <td class="delivery-print__right">{{ formatQuantity(item.quantity) }} {{ item.unit }}</td>
                </tr>
            </tbody>
        </table>

        <p v-if="note.notes" class="delivery-print__notes">Catatan: {{ note.notes }}</p>

        <div class="delivery-print__signs">
            <div><span>Pengirim</span></div>
            <div><span>Penerima</span></div>
        </div>
    </div>
</template>

<style>
@page {
    size: A5 landscape;
    margin: 8mm;
}

.delivery-print {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    line-height: 1.4;
    color: #000;
}

.delivery-print header {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 2px solid #000;
}

.delivery-print header b,
.delivery-print header span {
    display: block;
}

.delivery-print__title {
    text-align: right;
}

.delivery-print__title h1 {
    margin: 0;
    font-size: 18px;
    letter-spacing: 0.1em;
}

.delivery-print__mark {
    margin-top: 2px;
    font-weight: 700;
    letter-spacing: 0.2em;
}

.delivery-print__meta {
    margin: 0 0 10px;
}

.delivery-print__meta div {
    display: flex;
    gap: 8px;
}

.delivery-print__meta dt {
    flex: 0 0 70px;
    font-weight: 700;
}

.delivery-print__meta dd {
    margin: 0;
}

.delivery-print__items {
    width: 100%;
    border-collapse: collapse;
}

.delivery-print__items th,
.delivery-print__items td {
    padding: 4px 6px;
    border: 1px solid #000;
    text-align: left;
}

.delivery-print__items .delivery-print__right {
    text-align: right;
}

.delivery-print__notes {
    margin: 8px 0 0;
}

.delivery-print__signs {
    display: flex;
    justify-content: space-around;
    margin-top: 24px;
    text-align: center;
}

.delivery-print__signs div {
    width: 40%;
}

.delivery-print__signs span {
    display: block;
    padding-top: 46px;
    border-bottom: 1px solid #000;
    padding-bottom: 2px;
}
</style>
