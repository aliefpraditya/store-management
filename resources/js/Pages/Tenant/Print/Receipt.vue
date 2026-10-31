<script setup>
import { Head } from '@inertiajs/vue3';
import PrintPage from '../../../Components/PrintPage.vue';
import ReceiptSheet from '../../../Components/ReceiptSheet.vue';

defineProps({
    receipt: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`Nota ${receipt.number}`" />

    <PrintPage paper-width="80mm">
        <ReceiptSheet :receipt="receipt" />
    </PrintPage>
</template>
