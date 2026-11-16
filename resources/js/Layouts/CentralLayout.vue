<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppShell from './AppShell.vue';

defineProps({
    title: { type: String, required: true },
});

const page = usePage();

const brand = { initial: 'P', name: 'Mitra Manager', sub: 'Pusat' };

const menuGroups = computed(() => [
    {
        label: null,
        items: [
            { label: 'Dashboard', icon: 'space_dashboard', href: page.props.app_urls.central_dashboard, isExact: true },
            { label: 'Master Toko', icon: 'storefront', href: page.props.app_urls.central_stores },
            { label: 'User & Akses', icon: 'group', href: page.props.app_urls.central_users },
            { label: 'Pengaturan', icon: 'settings', href: page.props.app_urls.central_settings },
        ],
    },
]);
</script>

<template>
    <AppShell :title="title" :menu-groups="menuGroups" :brand="brand" role-label="Super Admin">
        <template #actions>
            <slot name="actions" />
        </template>

        <slot />
    </AppShell>
</template>
