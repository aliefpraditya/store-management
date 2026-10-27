<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import AppFooter from '../Components/AppFooter.vue';

const props = defineProps({
    title: { type: String, required: true },
    /**
     * Sidebar groups. An item without `href` is planned but not built yet.
     * @type {{label: string|null, items: {label: string, icon: string, href?: string, isExact?: boolean}[]}[]}
     */
    menuGroups: { type: Array, required: true },
    brand: { type: Object, required: true },
    roleLabel: { type: String, required: true },
});

const page = usePage();
const $q = useQuasar();

const user = computed(() => page.props.auth.user);
const userInitial = computed(() => user.value.name.charAt(0).toUpperCase());

const isDrawerOpen = ref(false);

const isActive = (item) => {
    if (!item.href) {
        return false;
    }

    const path = new URL(item.href).pathname;

    return item.isExact ? page.url === path : page.url.startsWith(path);
};

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

function signOut() {
    router.post(page.props.urls.logout);
}
</script>

<template>
    <Head :title="props.title" />

    <q-layout view="lHh LpR lFf">
        <q-header bordered class="shell__header">
            <q-toolbar class="shell__toolbar">
                <q-btn flat round dense icon="menu" class="lt-md" aria-label="Menu" @click="isDrawerOpen = !isDrawerOpen" />
                <q-toolbar-title class="shell__title">{{ props.title }}</q-toolbar-title>
                <slot name="actions" />
            </q-toolbar>
        </q-header>

        <q-drawer v-model="isDrawerOpen" show-if-above :width="248" class="shell__drawer">
            <div class="shell__nav">
                <div class="shell__brand">
                    <div class="shell__logo">{{ brand.initial }}</div>
                    <div>
                        <b>{{ brand.name }}</b>
                        <small>{{ brand.sub }}</small>
                    </div>
                </div>

                <template v-for="group in menuGroups" :key="group.label ?? 'main'">
                    <div v-if="group.label" class="shell__group">{{ group.label }}</div>

                    <q-item
                        v-for="item in group.items"
                        :key="item.label"
                        :clickable="!!item.href"
                        :disable="!item.href"
                        :active="isActive(item)"
                        active-class="shell__item--on"
                        class="shell__item"
                        @click="item.href && router.visit(item.href)"
                    >
                        <q-item-section avatar class="shell__item-icon">
                            <q-icon :name="item.icon" size="20px" />
                        </q-item-section>
                        <q-item-section>{{ item.label }}</q-item-section>
                        <q-item-section v-if="!item.href && !group.label" side>
                            <span class="shell__soon">Segera</span>
                        </q-item-section>
                    </q-item>
                </template>

                <div class="shell__me">
                    <div class="shell__avatar">{{ userInitial }}</div>
                    <div class="shell__me-text">
                        <b>{{ user.name }}</b>
                        <small>{{ roleLabel }}</small>
                    </div>
                    <q-btn flat round dense icon="logout" color="grey-5" aria-label="Keluar" @click="signOut">
                        <q-tooltip>Keluar</q-tooltip>
                    </q-btn>
                </div>
            </div>
        </q-drawer>

        <q-page-container>
            <q-page class="shell__page">
                <div class="shell__content">
                    <slot />
                </div>
                <AppFooter />
            </q-page>
        </q-page-container>
    </q-layout>
</template>

<style scoped>
.shell__header {
    background: var(--app-surface);
    color: var(--app-ink);
}

.shell__toolbar {
    min-height: 60px;
    padding: 0 22px;
}

.shell__title {
    font-size: 17px;
    font-weight: 700;
    letter-spacing: -0.01em;
}

.shell__drawer :deep(.q-drawer) {
    background: var(--app-dark);
}

.shell__nav {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-height: 100%;
    padding: 16px 12px;
    color: #b4b8c2;
    background: var(--app-dark);
}

.shell__brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 2px 8px 14px;
}

.shell__brand b {
    display: block;
    line-height: 1.1;
    color: #fff;
}

.shell__brand small {
    color: #8a8f9a;
    font-size: 11.5px;
}

.shell__logo {
    display: grid;
    flex: none;
    place-items: center;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: var(--app-brand);
    color: #fff;
    font-size: 12px;
    font-weight: 800;
}

.shell__group {
    margin: 12px 10px 3px;
    color: #6b7280;
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.shell__item {
    min-height: 38px;
    border-radius: 10px;
    color: #b4b8c2;
}

.shell__item-icon {
    min-width: 0;
    padding-right: 10px;
    color: inherit;
}

.shell__item.shell__item--on {
    background: rgba(224, 42, 42, 0.18);
    box-shadow: inset 3px 0 0 var(--app-brand);
    color: #fff;
    font-weight: 600;
}

.shell__item.shell__item--on .shell__item-icon {
    color: #ff6b6b;
}

.shell__soon {
    padding: 0 7px;
    border-radius: 99px;
    background: var(--app-dark-line);
    color: #9ca3af;
    font-size: 9.5px;
    font-weight: 700;
}

.shell__me {
    position: sticky;
    bottom: 0;
    display: flex;
    align-items: center;
    gap: 9px;
    margin: auto -12px -16px;
    padding: 12px 20px;
    border-top: 1px solid var(--app-dark-line);
    background: var(--app-dark);
}

.shell__avatar {
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

.shell__me-text {
    flex: 1;
    min-width: 0;
}

.shell__me-text b {
    display: block;
    overflow: hidden;
    color: #fff;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.shell__me-text small {
    color: var(--app-mute);
    font-size: 11.5px;
}

.shell__page {
    display: flex;
    flex-direction: column;
    padding: 22px;
}

.shell__content {
    flex: 1;
}
</style>
