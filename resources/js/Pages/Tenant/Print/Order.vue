<script setup>
import { Head } from '@inertiajs/vue3';
import OrderSheet from '../../../Components/OrderSheet.vue';
import PrintPage from '../../../Components/PrintPage.vue';

defineProps({
    order: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`Pesanan ${order.number}`" />

    <PrintPage :paper-width="`${order.paper_width}mm`">
        <OrderSheet :order="order" />
    </PrintPage>
</template>
