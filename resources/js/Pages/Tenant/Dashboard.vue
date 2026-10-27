<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import logo from '../../../images/logo-mitra-utama.jpg';

defineProps({
    store: { type: Object, required: true },
});

const page = usePage();

const isSuperAdmin = page.props.auth.user.is_super_admin;
</script>

<template>
    <Head title="Dashboard Toko" />

    <q-layout view="hHh lpR fFf">
        <q-page-container>
            <q-page class="placeholder">
                <img :src="logo" alt="" class="placeholder__logo" />
                <h1>{{ store.name }}</h1>
                <p>Dashboard toko sedang disiapkan.</p>

                <div class="placeholder__actions">
                    <q-btn v-if="isSuperAdmin" flat no-caps icon="arrow_back" label="Kembali ke Pusat" @click="router.visit(page.props.urls.central_dashboard)" />
                    <q-btn flat no-caps icon="logout" label="Keluar" @click="router.post(page.props.urls.logout)" />
                </div>
            </q-page>
        </q-page-container>
    </q-layout>
</template>

<style scoped>
.placeholder {
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

.placeholder__actions {
    display: flex;
    gap: 8px;
}
</style>
