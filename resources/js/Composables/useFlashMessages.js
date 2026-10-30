import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';

/**
 * Shows the `success` and `error` messages the server flashes after a redirect as toasts. Call it once in a layout.
 */
export function useFlashMessages() {
    const page = usePage();
    const $q = useQuasar();

    watch(
        () => page.props.flash.success,
        (message) => message && $q.notify({ type: 'positive', message, position: 'top' }),
        { immediate: true },
    );

    watch(
        () => page.props.flash.error,
        (message) =>
            message &&
            $q.notify({
                type: 'negative',
                message,
                position: 'top',
                multiLine: true,
                timeout: 10000,
                actions: [{ icon: 'close', color: 'white', round: true, dense: true }],
            }),
        { immediate: true },
    );
}
