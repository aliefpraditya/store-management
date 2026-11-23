<script setup>
import '../../css/sales-receipt-tables.css';
import { formatDeduction, formatRupiah } from '../Composables/useMoneyFormat';
import { formatQuantity } from '../Composables/useQuantityFormat';

defineProps({
    /** @type {{number: string, time: string, customer_name: string|null, fulfillment: string, total: string, discount_total: string, change_amount: string, is_voided?: boolean, void_reason?: string|null, items: object[], payments: object[]}[]} */
    receipts: { type: Array, required: true },
    /** What came in per method, `{method, label, amount}`. */
    methods: { type: Array, required: true },
    /** The first line of the totals under the receipts, which says what the sales figure is of. */
    summaryTitle: { type: String, required: true },
    grossTotal: { type: String, required: true },
    /** Left out, with the net figure, when the list was narrowed to one payment method. */
    returnsTotal: { type: String, default: null },
    netTotal: { type: String, default: null },
    /** Receipts voided since, listed among the others and counted in the methods; says so under the totals. */
    voidedCount: { type: Number, default: 0 },
});
</script>

<template>
    <div>
        <table v-for="receipt in receipts" :key="receipt.number" class="receipt-table" :class="{ 'receipt-table--void': receipt.is_voided }">
            <thead>
                <tr>
                    <th colspan="3"><span v-if="receipt.is_voided" class="receipt-table__void-mark">VOID</span>{{ receipt.number }} · {{ receipt.time }}<template v-if="receipt.customer_name"> · {{ receipt.customer_name }}</template> · {{ receipt.fulfillment }}</th>
                    <th>{{ formatRupiah(receipt.total) }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, index) in receipt.items" :key="index">
                    <td>{{ item.name }}</td>
                    <td>{{ formatQuantity(item.quantity) }} {{ item.unit }}</td>
                    <td>{{ formatRupiah(item.unit_price) }}</td>
                    <td>{{ formatRupiah(item.subtotal) }}</td>
                </tr>
                <tr v-if="Number(receipt.discount_total) > 0"><td colspan="3">Diskon</td><td>{{ formatDeduction(receipt.discount_total) }}</td></tr>
                <tr class="receipt-table__paid">
                    <td colspan="4">
                        Dibayar:
                        <span v-for="(payment, index) in receipt.payments" :key="index">{{ index > 0 ? ' + ' : ' ' }}{{ payment.label }} {{ formatRupiah(payment.amount) }}<template v-if="payment.reference"> (ref. {{ payment.reference }})</template></span>
                        <template v-if="Number(receipt.change_amount) > 0"> · kembali {{ formatRupiah(receipt.change_amount) }}</template>
                        <template v-if="receipt.is_voided"> · di-void<template v-if="receipt.void_reason">: {{ receipt.void_reason }}</template></template>
                    </td>
                </tr>
            </tbody>
        </table>

        <table class="receipt-sum">
            <tbody>
                <tr><td>{{ summaryTitle }}</td><td>{{ formatRupiah(grossTotal) }}</td></tr>
                <tr v-for="method in methods" :key="method.method" class="receipt-sum__method"><td>{{ method.label }}</td><td>{{ formatRupiah(method.amount) }}</td></tr>
                <tr v-if="voidedCount > 0" class="receipt-sum__note"><td colspan="2">Metode bayar memuat uang dari {{ voidedCount }} nota void di atas (uang sempat masuk), kecuali COD, jadi bisa lebih besar dari penjualan.</td></tr>
                <tr v-if="returnsTotal !== null && Number(returnsTotal) > 0"><td>Retur</td><td>{{ formatDeduction(returnsTotal) }}</td></tr>
                <tr v-if="netTotal !== null" class="receipt-sum__total"><td>Bersih</td><td>{{ formatRupiah(netTotal) }}</td></tr>
            </tbody>
        </table>
    </div>
</template>
