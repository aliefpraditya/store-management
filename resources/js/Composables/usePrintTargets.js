import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Where the store prints its receipt and its delivery note: the chosen printer (its name and page) and whether a print goes
 * straight to it through QZ Tray. A page asks for the target of a print by its kind. Without QZ Tray the name only reminds
 * whoever presses a print button which printer to pick in the print dialog.
 *
 * @typedef {{name: string, width_mm: number, height_mm: number|null}} PrintTarget
 * @typedef {'receipt'|'delivery_note'} PrintKind
 */
export function usePrintTargets() {
    const page = usePage();

    const isQzTrayEnabled = computed(() => page.props.print_targets?.is_qz_tray_enabled ?? false);

    /** @param {PrintKind} kind @returns {PrintTarget|null} */
    const targetOf = (kind) => page.props.print_targets?.[kind] ?? null;

    /**
     * What to tell whoever presses the print button of a kind; null while the store has chosen no printer for it.
     *
     * @param {PrintKind} kind
     * @returns {string|null}
     */
    const printHintOf = (kind) => {
        const target = targetOf(kind);

        if (target === null) {
            return null;
        }

        return isQzTrayEnabled.value ? `Dicetak langsung ke printer ${target.name}` : `Pilih printer ${target.name} di dialog cetak`;
    };

    return { isQzTrayEnabled, targetOf, printHintOf };
}
