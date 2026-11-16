<script setup>
import { ref, watch } from 'vue';
import { useQuasar } from 'quasar';

const props = defineProps({
    /** The chosen record: an existing one ({ id, name }) or a new one typed here ({ id: null, name }). */
    modelValue: { type: Object, default: null },
    options: { type: Array, required: true },
    label: { type: String, required: true },
    /** What a new record is called in the confirmation, e.g. "kategori". */
    recordName: { type: String, default: 'data' },
    hint: { type: String, default: undefined },
    error: { type: String, default: undefined },
    disable: { type: Boolean, default: false },
    clearable: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const $q = useQuasar();

const visibleOptions = ref(props.options);

watch(
    () => props.options,
    (options) => {
        visibleOptions.value = options;
    },
);

function filterOptions(typed, update) {
    update(() => {
        const needle = typed.trim().toLowerCase();

        visibleOptions.value = props.options.filter((option) => option.name.toLowerCase().includes(needle));
    });
}

/**
 * Typing a name that already exists picks that record instead of making a duplicate. A new name is created for good
 * when the item is saved and there is no screen to fix or merge it, so it is confirmed first: a typo would stay.
 */
function createFromTyped(typed, done) {
    const name = typed.trim();

    if (name === '') {
        done();

        return;
    }

    const existing = props.options.find((option) => option.name.toLowerCase() === name.toLowerCase());

    if (existing) {
        done(existing, 'add-unique');

        return;
    }

    $q.dialog({
        title: `Buat ${props.recordName} baru?`,
        message: `"${name}" belum ada dan akan dibuat saat barang disimpan. Periksa ejaannya, karena nama ini tidak bisa diubah dari layar lain.`,
        ok: { label: 'Buat baru', color: 'primary', unelevated: true, noCaps: true },
        cancel: { label: 'Batal', flat: true, noCaps: true },
        persistent: true,
    })
        .onOk(() => done({ id: null, name }, 'add-unique'))
        .onCancel(() => done());
}
</script>

<template>
    <q-select
        :model-value="modelValue"
        :options="visibleOptions"
        :label="label"
        :hint="hint"
        :error="!!error"
        :error-message="error"
        :disable="disable"
        :clearable="clearable"
        option-label="name"
        option-value="name"
        outlined
        use-input
        hide-selected
        fill-input
        input-debounce="0"
        @filter="filterOptions"
        @new-value="createFromTyped"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <template #no-option>
            <q-item>
                <q-item-section class="text-grey">Ketik nama lalu Enter untuk menambah baru</q-item-section>
            </q-item>
        </template>
    </q-select>
</template>
