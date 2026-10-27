<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import logo from '../../../images/logo-mitra-utama.jpg';
import AppFooter from '../../Components/AppFooter.vue';

defineProps({
    store: { type: Object, required: true },
});

const page = usePage();
</script>

<template>
    <Head title="Kasir" />

    <q-layout view="hHh lpR fFf">
        <q-page-container>
            <q-page class="placeholder">
                <img :src="logo" alt="" class="placeholder__logo" />
                <h1>{{ store.name }}</h1>
                <p>Layar kasir sedang disiapkan.</p>

                <div class="placeholder__actions">
                    <q-btn flat no-caps icon="logout" label="Keluar" @click="router.post(page.props.urls.logout)" />
                </div>

                <AppFooter class="placeholder__footer" />
            </q-page>
        </q-page-container>
    </q-layout>
</template>

<style scoped>
.placeholder {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    background: var(--app-surface);
    text-align: center;
}

.placeholder__logo {
    width: 120px;
    height: 120px;
    object-fit: contain;
}

.placeholder h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    letter-spacing: -0.02em;
}

.placeholder p {
    margin: 0 0 12px;
    color: var(--app-ink-soft);
}

.placeholder__footer {
    position: absolute;
    bottom: 12px;
}

.placeholder__actions {
    display: flex;
    gap: 8px;
}
</style>
