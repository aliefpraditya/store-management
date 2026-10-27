<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
});

const page = usePage();

const user = computed(() => page.props.auth.user);
const userInitial = computed(() => user.value.name.charAt(0).toUpperCase());

const isDrawerOpen = ref(false);

/**
 * Sidebar of the central (Super Admin) area. An item without `href` is planned but not built yet.
 * @type {{label: string, items: {label: string, icon: string, href?: string}[]}[]}
 */
const menuGroups = computed(() => [
    {
        label: null,
        items: [
            { label: 'Dashboard', icon: 'space_dashboard', href: page.props.urls.central_dashboard },
            { label: 'Master Toko', icon: 'storefront' },
            { label: 'User & Akses', icon: 'group' },
        ],
    },
    {
        label: 'Menyusul',
        items: [
            { label: 'Cek Stok Lintas Toko', icon: 'manage_search' },
            { label: 'Audit Trail', icon: 'policy' },
        ],
    },
]);

const isActive = (item) => item.href === page.url || (item.href && page.url.startsWith(new URL(item.href).pathname));

function signOut() {
    router.post(page.props.urls.logout);
}
</script>

<template>
    <Head :title="title" />

    <q-layout view="lHh LpR lFf">
        <q-header bordered class="central__header">
            <q-toolbar class="central__toolbar">
                <q-btn flat round dense icon="menu" class="lt-md" aria-label="Menu" @click="isDrawerOpen = !isDrawerOpen" />
                <q-toolbar-title class="central__title">{{ title }}</q-toolbar-title>
                <slot name="actions" />
            </q-toolbar>
        </q-header>

        <q-drawer v-model="isDrawerOpen" show-if-above :width="248" class="central__drawer">
            <div class="central__nav">
                <div class="central__brand">
                    <div class="central__logo">P</div>
                    <div>
                        <b>Mitra Manager</b>
                        <small>Pusat</small>
                    </div>
                </div>

                <template v-for="group in menuGroups" :key="group.label ?? 'main'">
                    <div v-if="group.label" class="central__group">{{ group.label }}</div>

                    <q-item
                        v-for="item in group.items"
                        :key="item.label"
                        :clickable="!!item.href"
                        :disable="!item.href"
                        :active="isActive(item)"
                        active-class="central__item--on"
                        class="central__item"
                        @click="item.href && router.visit(item.href)"
                    >
                        <q-item-section avatar class="central__item-icon">
                            <q-icon :name="item.icon" size="20px" />
                        </q-item-section>
                        <q-item-section>{{ item.label }}</q-item-section>
                        <q-item-section v-if="!item.href && !group.label" side>
                            <span class="central__soon">Segera</span>
                        </q-item-section>
                    </q-item>
                </template>

                <div class="central__me">
                    <div class="central__avatar">{{ userInitial }}</div>
                    <div class="central__me-text">
                        <b>{{ user.name }}</b>
                        <small>Super Admin</small>
                    </div>
                    <q-btn flat round dense icon="logout" color="grey-5" aria-label="Keluar" @click="signOut">
                        <q-tooltip>Keluar</q-tooltip>
                    </q-btn>
                </div>
            </div>
        </q-drawer>

        <q-page-container>
            <q-page class="central__page">
                <slot />
            </q-page>
        </q-page-container>
    </q-layout>
</template>

<style scoped>
.central__header {
    background: var(--app-surface);
    color: var(--app-ink);
}

.central__toolbar {
    min-height: 60px;
    padding: 0 22px;
}

.central__title {
    font-size: 17px;
    font-weight: 700;
    letter-spacing: -0.01em;
}

.central__drawer :deep(.q-drawer) {
    background: var(--app-dark);
}

.central__nav {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-height: 100%;
    padding: 16px 12px;
    color: #b4b8c2;
    background: var(--app-dark);
}

.central__brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 2px 8px 14px;
}

.central__brand b {
    display: block;
    line-height: 1.1;
    color: #fff;
}

.central__brand small {
    color: #8a8f9a;
    font-size: 11.5px;
}

.central__logo {
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: var(--app-brand);
    color: #fff;
    font-size: 12px;
    font-weight: 800;
}

.central__group {
    margin: 12px 10px 3px;
    color: #6b7280;
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.central__item {
    min-height: 38px;
    border-radius: 10px;
    color: #b4b8c2;
}

.central__item-icon {
    min-width: 0;
    padding-right: 10px;
    color: inherit;
}

.central__item.central__item--on {
    background: rgba(224, 42, 42, 0.18);
    box-shadow: inset 3px 0 0 var(--app-brand);
    color: #fff;
    font-weight: 600;
}

.central__item.central__item--on .central__item-icon {
    color: #ff6b6b;
}

.central__soon {
    padding: 0 7px;
    border-radius: 99px;
    background: var(--app-dark-line);
    color: #9ca3af;
    font-size: 9.5px;
    font-weight: 700;
}

.central__me {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-top: auto;
    padding: 10px 8px 0;
    border-top: 1px solid var(--app-dark-line);
}

.central__avatar {
    display: grid;
    flex: none;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--app-dark-line);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
}

.central__me-text {
    flex: 1;
    min-width: 0;
}

.central__me-text b {
    display: block;
    overflow: hidden;
    color: #fff;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.central__me-text small {
    color: var(--app-mute);
    font-size: 11.5px;
}

.central__page {
    padding: 22px;
}
</style>
