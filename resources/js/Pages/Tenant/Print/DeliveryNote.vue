<script setup>
import { Head } from '@inertiajs/vue3';
import DeliveryNoteSheet from '../../../Components/DeliveryNoteSheet.vue';
import PrintPage from '../../../Components/PrintPage.vue';
import { deliveryNoteEscPOf } from '../../../Composables/deliveryNoteEscP';

const props = defineProps({
    note: { type: Object, required: true },
});

/** The note as text for the form printer, which QZ Tray sends instead of the page. */
const escposText = deliveryNoteEscPOf(props.note);
</script>

<template>
    <Head :title="`Surat Jalan ${note.number}`" />

    <PrintPage paper-width="9.5in" printer-kind="delivery_note" :escpos-text="escposText">
        <DeliveryNoteSheet :note="note" />
    </PrintPage>
</template>
