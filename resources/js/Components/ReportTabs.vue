<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    /** The tabs, each a page of its own: `{key, label, href, icon}`. */
    tabs: { type: Array, required: true },
    /** The `key` of the tab the page is. */
    current: { type: String, required: true },
    /** What the tabs are of, for a screen reader. */
    label: { type: String, required: true },
});
</script>

<template>
    <nav class="report-tabs" :aria-label="label">
        <Link v-for="tab in tabs" :key="tab.key" :href="tab.href" class="report-tabs__tab" :class="{ 'report-tabs__tab--on': tab.key === current }" :aria-current="tab.key === current ? 'page' : undefined">
            <q-icon :name="tab.icon" size="18px" />
            {{ tab.label }}
        </Link>
    </nav>
</template>

<style scoped>
.report-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--app-line);
}

/** A raised, bordered button that answers the pointer, so it reads as something to press; the page the reader is on is filled in the brand colour. */
.report-tabs__tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px;
    border: 1px solid var(--app-line);
    border-radius: 10px;
    background: var(--app-surface);
    box-shadow: 0 1px 2px rgb(17 19 28 / 8%);
    color: var(--app-ink);
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: background-color 0.15s, border-color 0.15s, color 0.15s;
}

.report-tabs__tab:hover {
    border-color: var(--q-primary);
    background: var(--app-brand-soft);
    color: var(--q-primary);
}

.report-tabs__tab:focus-visible {
    outline: 2px solid var(--q-primary);
    outline-offset: 2px;
}

.report-tabs__tab--on,
.report-tabs__tab--on:hover {
    border-color: var(--q-primary);
    background: var(--q-primary);
    box-shadow: none;
    color: #fff;
    cursor: default;
    font-weight: 600;
}
</style>
