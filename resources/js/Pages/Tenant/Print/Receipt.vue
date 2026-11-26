<script setup>
import { Head } from '@inertiajs/vue3';
import PrintPage from '../../../Components/PrintPage.vue';
import ReceiptSheet from '../../../Components/ReceiptSheet.vue';
import { receiptEscPosOf } from '../../../Composables/receiptEscPos';

const props = defineProps({
    receipt: { type: Object, required: true },
});

/**
 * The receipt as text, for QZ Tray; null for a roll whose width has no known columns. The print that closes a sale (not a
 * copy, not a void) also opens the cash drawer, which a copy printed again must not.
 */
const escposText = receiptEscPosOf(props.receipt, { isDrawerOpened: !props.receipt.is_copy && props.receipt.status !== 'voided' });
</script>

<template>
    <Head :title="`Nota ${receipt.number}`" />

    <PrintPage :paper-width="`${receipt.paper_width}mm`" printer-kind="receipt" :escpos-text="escposText">
        <ReceiptSheet :receipt="receipt" />
    </PrintPage>
</template>
