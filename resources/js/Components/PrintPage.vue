<script setup>
import { nextTick, onMounted, ref } from 'vue';
import { printSheetElement } from '../Composables/qzTray';
import { SHEET_QZ_FALLBACK_MESSAGE } from '../Composables/printSheet';
import { usePrintTargets } from '../Composables/usePrintTargets';

const props = defineProps({
    /** Width of the paper as shown on screen, as a CSS length (e.g. `80mm`). */
    paperWidth: { type: String, required: true },
    /** Which of the store's printers prints this sheet (`receipt` or `delivery_note`); null for a sheet that always goes through the print dialog. */
    printerKind: { type: String, default: null },
});

const { isQzTrayEnabled, targetOf } = usePrintTargets();

const paper = ref(null);

/**
 * Prints the sheet. When the store prints through QZ Tray and has chosen a printer for this kind of sheet, it goes straight
 * to that printer; the page it is in then reports the print as done, as the print dialog does when it closes. If QZ Tray
 * cannot be used, the page that asked is told and the print dialog opens instead, so a print is never lost.
 */
async function print() {
    const target = props.printerKind !== null && isQzTrayEnabled.value ? targetOf(props.printerKind) : null;

    if (target === null) {
        window.print();

        return;
    }

    try {
        await printSheetElement(target, paper.value);
        window.dispatchEvent(new Event('afterprint'));
    } catch (error) {
        window.parent.postMessage({ type: SHEET_QZ_FALLBACK_MESSAGE, message: error?.message ?? String(error) }, window.location.origin);
        window.print();
    }
}

const close = () => window.close();

/** The print was counted on the server before this window opened, so the print starts by itself. */
onMounted(() => nextTick(print));
</script>

<template>
    <div class="print-page">
        <div class="print-page__bar" :style="{ width: paperWidth }">
            <q-btn unelevated no-caps color="primary" icon="print" label="Cetak" @click="print" />
            <q-btn flat no-caps label="Tutup" @click="close" />
        </div>

        <div ref="paper" class="print-page__paper" :style="{ width: paperWidth }">
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
