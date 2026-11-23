<script setup>
import '../../css/sales-receipt-tables.css';
import { formatRupiah } from '../Composables/useMoneyFormat';
import { formatQuantity } from '../Composables/useQuantityFormat';

defineProps({
    /** @type {{number: string, receipt_number: string, date: string, resolution: string, reason: string|null, refund_method: string|null, credit_amount: string, items: {name: string, quantity: string, unit: string, refund_subtotal: string}[]}[]} */
    returns: { type: Array, required: true },
});

const shortDayFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
const asLocalDate = (isoDay) => new Date(`${isoDay}T00:00:00`);
</script>

<template>
    <div>
        <table v-for="saleReturn in returns" :key="saleReturn.number" class="receipt-table">
            <thead>
                <tr>
                    <th colspan="3">{{ saleReturn.number }} · {{ shortDayFormat.format(asLocalDate(saleReturn.date)) }} · dari nota {{ saleReturn.receipt_number }} · {{ saleReturn.resolution }}<template v-if="saleReturn.refund_method"> ({{ saleReturn.refund_method }})</template></th>
                    <th>{{ formatRupiah(saleReturn.credit_amount) }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, index) in saleReturn.items" :key="index">
                    <td colspan="2">{{ item.name }}</td>
                    <td>{{ formatQuantity(item.quantity) }} {{ item.unit }}</td>
                    <td>{{ formatRupiah(item.refund_subtotal) }}</td>
                </tr>
                <tr v-if="saleReturn.reason" class="receipt-table__paid"><td colspan="4">Alasan: {{ saleReturn.reason }}</td></tr>
            </tbody>
        </table>
    </div>
</template>
