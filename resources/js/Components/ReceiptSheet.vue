<script setup>
import { computed } from 'vue';
import { formatFigure as figure } from '../Composables/useMoneyFormat';
import { formatQuantity } from '../Composables/useQuantityFormat';
import { thermalLayoutOf } from '../Composables/thermalLayout';
import Code39Barcode from './Code39Barcode.vue';
import ThermalPageSize from './ThermalPageSize.vue';

const props = defineProps({
    /** `order` is the customer order made in the same checkout, printed on the same paper (null when there is none). The server has named the payments as they are printed. The receipt shows plain figures ("1.280.000"); only a unit price carries "Rp.". @type {{order: object|null, number: string, sold_at: string, cashier_name: string, customer_name: string|null, status: string, is_copy: boolean, store: {name: string, address: string|null, phone: string|null}, footer: string, paper_width: number, items: object[], payments: object[], total: string, change_amount: string}} */
    receipt: { type: Object, required: true },
});

const dateFormat = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
const timeFormat = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });

/** Width of one character of the 12px monospace font, in millimetres (7.2 px at 96 px per inch). */
const CHARACTER_WIDTH_MM = 7.2 / (96 / 25.4);

const layout = computed(() => thermalLayoutOf(props.receipt.paper_width));

/**
 * The space around the text and the shift of the letterhead are written into the elements, not into the stylesheet: the
 * renderer of QZ Tray does not know CSS variables, `calc()` or flex columns, and ignores `text-align`, so the sheet is built
 * from tables with `align` attributes, which every renderer knows.
 */
const sheetStyle = computed(() => `padding: 4mm ${layout.value.right}mm 4mm ${layout.value.left}mm;`);

/** A centred cell is moved left by padding on its right, which takes twice the distance away from the centre. */
const letterheadStyle = computed(() => `padding-right: ${layout.value.letterheadShift * 2}mm;`);

/** As many dashes as fit the text, one character short, ending in a plus: a line longer than the cell would widen the table past the paper. */
const rule = computed(() => {
    const textWidthMm = props.receipt.paper_width - layout.value.left - layout.value.right;
    const characters = Math.floor(textWidthMm / CHARACTER_WIDTH_MM) - 1;

    return `${'- '.repeat(characters).slice(0, characters - 1)}+`;
});
</script>

<template>
    <div class="receipt-print" :style="sheetStyle">
        <ThermalPageSize :width="receipt.paper_width" />
        <table class="receipt-print__table" width="100%" cellspacing="0" cellpadding="0" border="0">
            <tbody>
                <tr>
                    <td colspan="2" align="center" class="receipt-print__store" :style="letterheadStyle"><b>{{ receipt.store.name }}</b></td>
                </tr>
                <tr v-if="receipt.store.address">
                    <td colspan="2" align="center" :style="letterheadStyle">{{ receipt.store.address }}</td>
                </tr>
                <tr v-if="receipt.store.phone">
                    <td colspan="2" align="center" :style="letterheadStyle">No Tlp. {{ receipt.store.phone }}</td>
                </tr>

                <tr v-if="receipt.status === 'voided'">
                    <td colspan="2" align="center" class="receipt-print__mark">VOID</td>
                </tr>
                <tr v-else-if="receipt.is_copy">
                    <td colspan="2" align="center" class="receipt-print__mark">SALINAN</td>
                </tr>

                <tr>
                    <td colspan="2" align="left" class="receipt-print__meta-first">No : {{ receipt.number }}</td>
                </tr>
                <tr>
                    <td colspan="2" align="left">Tanggal : {{ dateFormat.format(new Date(receipt.sold_at)) }} {{ timeFormat.format(new Date(receipt.sold_at)) }}</td>
                </tr>
                <tr>
                    <td colspan="2" align="left" :class="{ 'receipt-print__meta-last': !receipt.customer_name && !receipt.order?.customer_phone }">Kasir : {{ receipt.cashier_name }}</td>
                </tr>
                <tr v-if="receipt.customer_name">
                    <td colspan="2" align="left" :class="{ 'receipt-print__meta-last': !receipt.order?.customer_phone }">Pelanggan : {{ receipt.customer_name }}</td>
                </tr>
                <tr v-if="receipt.order?.customer_phone">
                    <td colspan="2" align="left" class="receipt-print__meta-last">Telepon : {{ receipt.order.customer_phone }}</td>
                </tr>

                <template v-for="(item, index) in receipt.items" :key="`item-${index}`">
                    <tr>
                        <td colspan="2" align="left">{{ item.name }}</td>
                    </tr>
                    <tr>
                        <td align="left" class="receipt-print__item-end">{{ formatQuantity(item.quantity) }} {{ item.unit }} x Rp. {{ figure(item.unit_price) }}</td>
                        <td align="right" class="receipt-print__item-end">{{ figure(item.subtotal) }}</td>
                    </tr>
                </template>

                <tr>
                    <td colspan="2" align="left" class="receipt-print__rule">{{ rule }}</td>
                </tr>

                <tr>
                    <td colspan="2" align="right">Total : {{ figure(receipt.total) }}</td>
                </tr>
                <template v-if="!receipt.order">
                    <tr v-for="(payment, index) in receipt.payments" :key="`payment-${index}`">
                        <td colspan="2" align="right">{{ payment.method }} : {{ figure(payment.amount) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" align="right" class="receipt-print__totals-last">Kembalian : {{ figure(receipt.change_amount) }}</td>
                    </tr>
                </template>
                <tr v-else>
                    <td colspan="2" class="receipt-print__totals-last"></td>
                </tr>

                <template v-if="receipt.order">
                    <tr>
                        <td colspan="2" align="left" class="receipt-print__rule">{{ rule }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" align="left"><b>PESANAN {{ receipt.order.number }}</b></td>
                    </tr>
                    <tr>
                        <td colspan="2" align="left" class="receipt-print__barcode"><Code39Barcode :value="receipt.order.number" :height="30" /></td>
                    </tr>
                    <tr>
                        <td colspan="2" align="left">{{ receipt.order.status === 'completed' ? 'Sudah diambil' : 'Barang menyusul' }}</td>
                    </tr>
                    <template v-for="(item, index) in receipt.order.items" :key="`order-item-${index}`">
                        <tr>
                            <td colspan="2" align="left">{{ item.name }}</td>
                        </tr>
                        <tr>
                            <td align="left" class="receipt-print__item-end">{{ formatQuantity(item.quantity) }} {{ item.unit }} x Rp. {{ figure(item.unit_price) }}</td>
                            <td align="right" class="receipt-print__item-end">{{ figure(item.subtotal) }}</td>
                        </tr>
                    </template>
                    <tr>
                        <td colspan="2" align="right">Total pesanan : {{ figure(receipt.order.total) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" align="right">Uang muka : {{ figure(receipt.order.deposit) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" align="right"><b>Sisa dibayar saat diambil : {{ figure(receipt.order.remaining) }}</b></td>
                    </tr>
                    <tr>
                        <td colspan="2" align="left" class="receipt-print__order-end">Bawa nota ini saat mengambil barang. Harga sesuai nota ini.</td>
                    </tr>

                    <tr>
                        <td colspan="2" align="left" class="receipt-print__rule">{{ rule }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" align="right">Uang muka pesanan : {{ figure(receipt.order.deposit) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" align="right">Total dibayar : {{ figure(Number(receipt.total) + Number(receipt.order.deposit)) }}</td>
                    </tr>
                    <tr v-for="(payment, index) in receipt.payments" :key="`order-payment-${index}`">
                        <td colspan="2" align="right">{{ payment.method }} : {{ figure(payment.amount) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" align="right" class="receipt-print__totals-last">Kembalian : {{ figure(receipt.change_amount) }}</td>
                    </tr>
                </template>

                <tr>
                    <td colspan="2" align="center" class="receipt-print__footer">{{ receipt.footer }}</td>
                </tr>
                <tr>
                    <td colspan="2" align="center">{{ receipt.number }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style>
/*
 * The sheet is a table of `align` cells with only plain CSS (fonts, spacing), because the renderer of QZ Tray ignores
 * `text-align` and does not know flex columns, CSS variables or `calc()`. The paper size is written by ThermalPageSize, from
 * the store's setting; the space around the text is padding because that component's paper margin is zero.
 */
.receipt-print {
    box-sizing: border-box;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.35;
    color: #000;
}

.receipt-print__table td {
    padding: 0;
    vertical-align: top;
}

.receipt-print__store {
    font-size: 16px;
}

.receipt-print .receipt-print__footer {
    padding: 8px 0 6px;
    white-space: pre-line;
}

.receipt-print__mark {
    padding: 6px 0;
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 0.2em;
}

.receipt-print__table .receipt-print__meta-first {
    padding-top: 10px;
}

.receipt-print__table .receipt-print__meta-last {
    padding-bottom: 12px;
}

.receipt-print__table .receipt-print__item-end {
    padding-bottom: 4px;
}

.receipt-print__table .receipt-print__rule {
    padding: 4px 0;
    white-space: nowrap;
}

.receipt-print__table .receipt-print__totals-last,
.receipt-print__table .receipt-print__order-end {
    padding-bottom: 8px;
}

.receipt-print__table .receipt-print__barcode {
    padding: 4px 0 6px;
}
</style>
