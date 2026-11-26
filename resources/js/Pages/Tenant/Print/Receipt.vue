<script setup>
import { Head } from '@inertiajs/vue3';
import PrintPage from '../../../Components/PrintPage.vue';
import ReceiptSheet from '../../../Components/ReceiptSheet.vue';
import { receiptEscPosOf } from '../../../Composables/receiptEscPos';

const props = defineProps({
    receipt: { type: Object, required: true },
});

/** The receipt as text, for a store that prints it in text mode; null for a roll whose width has no known columns. */
const escposText = receiptEscPosOf(props.receipt);
</script>

<template>
    <Head :title="`Nota ${receipt.number}`" />

    <PrintPage :paper-width="`${receipt.paper_width}mm`" printer-kind="receipt" :escpos-text="escposText">
        <ReceiptSheet :receipt="receipt" />
    </PrintPage>
</template>
