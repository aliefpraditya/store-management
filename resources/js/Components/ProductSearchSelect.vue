<script setup>
import { ref } from 'vue';
import { useQuasar } from 'quasar';

const props = defineProps({
    /** The chosen product ({ id, sku, name, brand, base_unit, units }) or null. */
    modelValue: { type: Object, default: null },
    /** Endpoint that answers `?q=` with `{ data: product[] }`. */
    searchUrl: { type: String, required: true },
    label: { type: String, default: 'Cari barang' },
    clearable: { type: Boolean, default: false },
    dense: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const $q = useQuasar();

const options = ref([]);
const select = ref(null);
let latestSearchId = 0;

defineExpose({ focus: () => select.value?.focus() });
let isFailureShown = false;

/**
 * One notice for a failing lookup, however many searches fail in a row while the user keeps typing, so a lost
 * session or a server error is not mistaken for "no such product".
 */
function notifyLookupFailed() {
    if (isFailureShown) {
        return;
    }

    isFailureShown = true;
    $q.notify({ type: 'negative', position: 'top', message: 'Pencarian barang gagal. Periksa koneksi atau masuk ulang, lalu coba lagi.', onDismiss: () => (isFailureShown = false) });
}

/**
 * Looks products up on the server as the user types; an answer that arrives after a newer search was
 * started is dropped so a slow response cannot replace fresher results.
 */
function searchProducts(typed, update, abort) {
    const term = typed.trim();

    if (term === '') {
        abort();

        return;
    }

    const searchId = ++latestSearchId;

    fetch(`${props.searchUrl}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Lookup failed');
            }

            return response.json();
        })
        .catch(() => {
            notifyLookupFailed();

            return { data: [] };
        })
        .then(({ data }) => {
            if (searchId === latestSearchId) {
                update(() => {
                    options.value = data;
                });
            } else {
                abort();
            }
        });
}
</script>

<template>
    <q-select
        ref="select"
        :model-value="modelValue"
        :options="options"
        :label="label"
        :clearable="clearable"
        :dense="dense"
        option-label="name"
        option-value="id"
        outlined
        use-input
        hide-selected
        fill-input
        input-debounce="250"
        @filter="searchProducts"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <template #prepend><q-icon name="search" /></template>
        <template #option="{ itemProps, opt }">
            <q-item v-bind="itemProps">
                <q-item-section>
                    <q-item-label>{{ opt.name }}</q-item-label>
                    <q-item-label caption>{{ [opt.brand, opt.sku, opt.base_unit].filter(Boolean).join(' · ') }}</q-item-label>
                </q-item-section>
            </q-item>
        </template>
        <template #no-option>
            <q-item><q-item-section class="text-grey">Ketik untuk mencari barang</q-item-section></q-item>
        </template>
    </q-select>
</template>
