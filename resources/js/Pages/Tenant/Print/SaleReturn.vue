<script setup>
import { Head } from '@inertiajs/vue3';
import PrintPage from '../../../Components/PrintPage.vue';
import SaleReturnSheet from '../../../Components/SaleReturnSheet.vue';

defineProps({
    saleReturn: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`Retur ${saleReturn.number}`" />

    <PrintPage paper-width="80mm">
        <SaleReturnSheet :sale-return="saleReturn" />
    </PrintPage>
</template>
