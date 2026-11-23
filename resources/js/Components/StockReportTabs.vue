<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    /** Which of the stock reports the page is: `positions`, `restock`, `idle` or `movements`. */
    current: { type: String, required: true },
});

const store = computed(() => usePage().props.store);

const tabs = computed(() => [
    { key: 'positions', label: 'Posisi stok', href: store.value.stock_report_url },
    { key: 'restock', label: 'Perlu restok', href: store.value.restock_url },
    { key: 'idle', label: 'Tidak terjual', href: store.value.idle_stock_url },
    { key: 'movements', label: 'Pergerakan', href: store.value.stock_movements_url },
]);
</script>

<template>
    <nav class="tabs" aria-label="Laporan stok">
        <Link v-for="tab in tabs" :key="tab.key" :href="tab.href" class="tabs__tab" :class="{ 'tabs__tab--on': tab.key === current }" :aria-current="tab.key === current ? 'page' : undefined">{{ tab.label }}</Link>
    </nav>
</template>

<style scoped>
.tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 16px;
}

.tabs__tab {
    padding: 6px 14px;
    border: 1px solid var(--app-line);
    border-radius: 999px;
    background: var(--app-surface);
    color: inherit;
    font-size: 13px;
    text-decoration: none;
}

.tabs__tab:hover,
.tabs__tab--on {
    border-color: var(--q-primary);
}

.tabs__tab--on {
    background: var(--app-brand-soft);
    font-weight: 600;
}
</style>
