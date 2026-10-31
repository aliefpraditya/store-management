import { router } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';

/**
 * Asks for confirmation, then sends a DELETE request.
 *
 * @returns {(url: string, options: {title: string, message: string}) => void}
 */
export function useConfirmDelete() {
    const $q = useQuasar();

    return (url, { title, message }) => {
        $q.dialog({
            title,
            message,
            cancel: { label: 'Batal', flat: true, noCaps: true },
            ok: { label: 'Hapus', color: 'negative', unelevated: true, noCaps: true },
            persistent: true,
        }).onOk(() => router.delete(url, { preserveScroll: true }));
    };
}
