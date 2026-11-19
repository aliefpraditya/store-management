<script setup>
import { Head } from '@inertiajs/vue3';
import DeliveryNoteSheet from '../../../Components/DeliveryNoteSheet.vue';
import PrintPage from '../../../Components/PrintPage.vue';

defineProps({
    note: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`Surat Jalan ${note.number}`" />

    <PrintPage paper-width="9.5in" printer-kind="delivery_note">
        <DeliveryNoteSheet :note="note" />
    </PrintPage>
</template>
