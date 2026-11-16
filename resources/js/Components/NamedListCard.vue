<script setup>
import { ref } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    hint: { type: String, required: true },
    /** Shown above the field that adds a name. */
    addLabel: { type: String, required: true },
    emptyText: { type: String, default: null },
    /** The form key of the list, so the error of a row (`{errorKey}.{index}.name`) is shown on it. */
    errorKey: { type: String, required: true },
    errors: { type: Object, default: () => ({}) },
});

/**
 * The names as the settings page holds them, not yet saved: a row with an id exists, a row without one was added here. A
 * name is never deleted, only switched off, so a row has no remove button once it exists.
 *
 * @type {import('vue').ModelRef<{id: number|null, name: string, is_active: boolean, is_system?: boolean}[]>}
 */
const rows = defineModel({ type: Array, required: true });

const newName = ref('');

/** @param {number} index @param {{name?: string, is_active?: boolean}} changes */
function setRow(index, changes) {
    rows.value = rows.value.map((row, rowIndex) => (rowIndex === index ? { ...row, ...changes } : row));
}

function add() {
    const name = newName.value.trim();

    if (name === '') {
        return;
    }

    rows.value = [...rows.value, { id: null, name, is_active: true, is_system: false }];
    newName.value = '';
}

const errorOf = (index) => props.errors[`${props.errorKey}.${index}.name`] ?? props.errors[`${props.errorKey}.${index}.is_active`];
</script>

<template>
    <section class="card">
        <h2>{{ title }}</h2>
        <p class="hint">{{ hint }}</p>

        <p v-if="rows.length === 0 && emptyText" class="hint">{{ emptyText }}</p>

        <div v-for="(row, index) in rows" :key="row.id ?? `new-${index}`" class="row" :class="{ 'row--off': !row.is_active }">
            <q-input
                :model-value="row.name"
                outlined
                dense
                maxlength="100"
                class="row__name"
                :aria-label="`Nama ${row.name}`"
                :error="!!errorOf(index)"
                :error-message="errorOf(index)"
                @update:model-value="(name) => setRow(index, { name })"
            />
            <small v-if="row.is_system" class="row__tag">bawaan</small>
            <small v-else-if="row.id === null" class="row__tag row__tag--new">baru</small>
            <q-toggle :model-value="row.is_active" :disable="row.is_system" :aria-label="`Aktifkan ${row.name}`" @update:model-value="(isActive) => setRow(index, { is_active: isActive })" />
        </div>

        <form class="add" @submit.prevent="add">
            <q-input v-model="newName" outlined dense :label="addLabel" maxlength="100" class="row__name" />
            <q-btn type="submit" outline color="primary" no-caps icon="add" label="Tambah ke daftar" :disable="newName.trim() === ''" />
        </form>
    </section>
</template>

<style scoped>
.card {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px 18px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.card h2 {
    margin: 0;
    font-size: 16px;
}

.hint {
    margin: 0;
    color: var(--app-mute);
    font-size: 12.5px;
}

.row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.row--off :deep(input) {
    color: var(--app-mute);
    text-decoration: line-through;
}

.row__name {
    flex: 1;
}

.row__tag {
    align-self: center;
    color: var(--app-mute);
}

.row__tag--new {
    color: var(--q-primary);
}

.add {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}
</style>
