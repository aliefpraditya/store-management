<script setup>
defineProps({
    /** Whether the dialog is up. The mark it belongs to decides, from the pointer. */
    isOpen: { type: Boolean, required: true },
    title: { type: String, required: true },
    /** The lines under the title, `{label, value}`. */
    rows: { type: Array, required: true },
});

const emit = defineEmits(['enter', 'leave', 'close']);
</script>

<template>
    <q-menu no-parent-event no-focus no-refocus anchor="top middle" self="bottom middle" :offset="[0, 8]" :model-value="isOpen" @update:model-value="(isShown) => !isShown && emit('close')">
        <div class="hover-details" @mouseenter="emit('enter')" @mouseleave="emit('leave')">
            <strong>{{ title }}</strong>
            <dl class="hover-details__rows">
                <div v-for="row in rows" :key="row.label"><dt>{{ row.label }}</dt><dd>{{ row.value }}</dd></div>
            </dl>
            <slot />
        </div>
    </q-menu>
</template>

<style scoped>
.hover-details {
    min-width: 230px;
    padding: 12px 14px;
}

.hover-details__rows {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin: 8px 0 0;
}

.hover-details__rows div {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    font-size: 13px;
}

.hover-details__rows dt {
    color: var(--app-ink-soft);
}

.hover-details__rows dd {
    margin: 0;
    font-weight: 600;
    white-space: nowrap;
}
</style>
