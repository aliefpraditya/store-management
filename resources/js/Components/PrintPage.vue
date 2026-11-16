<script setup>
import { nextTick, onMounted } from 'vue';

defineProps({
    /** Width of the paper as shown on screen, as a CSS length (e.g. `80mm`). */
    paperWidth: { type: String, required: true },
});

const print = () => window.print();

const close = () => window.close();

/** The print was counted on the server before this window opened, so the dialog opens by itself. */
onMounted(() => nextTick(print));
</script>

<template>
    <div class="print-page">
        <div class="print-page__bar" :style="{ width: paperWidth }">
            <q-btn unelevated no-caps color="primary" icon="print" label="Cetak" @click="print" />
            <q-btn flat no-caps label="Tutup" @click="close" />
        </div>

        <div class="print-page__paper" :style="{ width: paperWidth }">
            <slot />
        </div>
    </div>
</template>

<style>
.print-page {
    min-height: 100vh;
    padding: 16px;
    background: #eceff1;
}

.print-page__bar {
    display: flex;
    gap: 8px;
    max-width: 100%;
    margin: 0 auto 12px;
}

.print-page__paper {
    box-sizing: border-box;
    max-width: 100%;
    margin: 0 auto;
    padding: 6mm;
    background: #fff;
    box-shadow: 0 1px 6px rgb(0 0 0 / 20%);
}

@media print {
    .print-page {
        min-height: 0;
        padding: 0;
        background: none;
    }

    .print-page__bar {
        display: none;
    }

    .print-page__paper {
        width: auto !important;
        padding: 0;
        box-shadow: none;
    }
}
</style>
