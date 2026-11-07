<script setup>
import { computed } from 'vue';
import { formatQuantity } from '../Composables/useQuantityFormat';

const props = defineProps({
    /** @type {{number: string, created_at: string, status: string, is_copy: boolean, recipient_name: string, recipient_phone: string|null, recipient_address: string|null, driver_name: string|null, vehicle_number: string|null, notes: string|null, sale: {number: string}, store: {name: string, address: string|null, phone: string|null}, items: object[]}} */
    note: { type: Object, required: true },
});

const SIGNERS = ['Pembuat', 'Penyiap', 'Pengantar', 'Penerima'];

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

/** The letterhead reads "TOKO ..."; a store whose name already starts that way is not given the word twice. */
const storeTitle = computed(() => (/^toko\b/i.test(props.note.store.name) ? props.note.store.name : `TOKO ${props.note.store.name}`));

const carrier = computed(() => [props.note.driver_name, props.note.vehicle_number].filter(Boolean).join(' · '));
</script>

<template>
    <div class="delivery-print">
        <header>
            <div class="delivery-print__store">
                <b>{{ storeTitle }}</b>
                <span v-if="note.store.phone">Tlp: {{ note.store.phone }}</span>
                <span class="delivery-print__number">No DO: <b>{{ note.number }}</b></span>
                <span>Tanggal : {{ dateTimeFormat.format(new Date(note.created_at)) }}</span>
                <span>Nama Penerima: <b>{{ note.recipient_name }}</b></span>
                <span v-if="note.recipient_phone">No tlp. : {{ note.recipient_phone }}</span>
            </div>
            <div class="delivery-print__address">
                <span v-if="note.status === 'cancelled'" class="delivery-print__mark">BATAL</span>
                <span v-else-if="note.is_copy" class="delivery-print__mark">SALINAN</span>
                <b>Alamat Pengantaran:</b>
                <span>{{ note.recipient_address }}</span>
                <span class="delivery-print__reference">No Nota : {{ [note.sale.number, ...(note.other_sale_numbers ?? [])].join(', ') }}</span>
                <span v-if="carrier">Pengantar : {{ carrier }}</span>
            </div>
        </header>

        <table class="delivery-print__items">
            <thead>
                <tr>
                    <th>Nama Barang</th>
                    <th class="delivery-print__check">Cek</th>
                    <th class="delivery-print__quantity">Jumlah</th>
                    <th class="delivery-print__unit">Satuan</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in note.items" :key="item.id">
                    <td>{{ item.name }}</td>
                    <td class="delivery-print__check"></td>
                    <td class="delivery-print__quantity">{{ formatQuantity(item.quantity) }}</td>
                    <td class="delivery-print__unit">{{ item.unit }}</td>
                </tr>
            </tbody>
        </table>

        <p v-if="note.notes" class="delivery-print__notes">Catatan: {{ note.notes }}</p>

        <div class="delivery-print__signs">
            <div v-for="signer in SIGNERS" :key="signer">
                <b>{{ signer }}</b>
                <span>Paraf</span>
            </div>
        </div>
    </div>
</template>

<style>
/*
 * Printed on continuous-form paper by a dot matrix printer: 9.5 x 5.5 inch, one form per page. Dot matrix prints only
 * black dots, so the sheet uses a monospace font, pure black, 1px lines and no shading. The paper margin is zero so
 * the browser has no room for its own header and footer (date, address, page number); the space is padding instead.
 */
@page {
    size: 9.5in 5.5in;
    margin: 0;
}

.delivery-print {
    box-sizing: border-box;
    padding: 6mm 10mm;
    font-family: 'Courier New', Courier, monospace;
    font-size: 11pt;
    font-weight: 700;
    line-height: 1.15;
    color: #000;
}

.delivery-print header {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 6px;
}

.delivery-print header span,
.delivery-print header b {
    display: block;
}

.delivery-print__store > b {
    font-size: 13pt;
}

.delivery-print__number {
    margin-top: 4px;
}

.delivery-print__address {
    max-width: 45%;
    text-align: left;
}

.delivery-print__address span:not(.delivery-print__mark) {
    white-space: pre-line;
}

.delivery-print__address .delivery-print__reference {
    margin-top: 4px;
}

.delivery-print__mark {
    margin-bottom: 2px;
    letter-spacing: 0.2em;
}

.delivery-print__items {
    width: 100%;
    border-collapse: collapse;
}

.delivery-print__items thead {
    display: table-header-group;
}

.delivery-print__items tr {
    break-inside: avoid;
}

.delivery-print__items th {
    padding: 2px 4px;
    border-top: 1px solid #000;
    border-bottom: 1px solid #000;
    text-align: left;
}

.delivery-print__items td {
    padding: 2px 4px;
    border-bottom: 1px solid #000;
}

.delivery-print__items .delivery-print__check {
    width: 14mm;
    text-align: center;
}

.delivery-print__items .delivery-print__quantity {
    width: 22mm;
    text-align: center;
}

.delivery-print__items .delivery-print__unit {
    width: 28mm;
}

.delivery-print__notes {
    margin: 6px 0 0;
}

.delivery-print__signs {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-top: 8px;
    padding-top: 4px;
    border-top: 1px solid #000;
    break-inside: avoid;
}

.delivery-print__signs div {
    flex: 1;
    text-align: center;
}

.delivery-print__signs b,
.delivery-print__signs span {
    display: block;
}

.delivery-print__signs span {
    margin-top: 22px;
}
</style>