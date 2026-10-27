<script setup>
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import logo from '../../../images/logo-mitra-utama.jpg';

const page = usePage();

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const isPasswordVisible = ref(false);

function submit() {
    form.post(page.props.urls.login, {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Masuk" />

    <q-layout view="hHh lpR fFf">
        <q-page-container>
            <q-page class="login">
                <section class="login__art">
                    <h1>Mitra Manager</h1>
                    <p>Kasir, stok, dan kas dalam satu tempat.</p>
                </section>

                <section class="login__form">
                    <q-form class="login__box" @submit.prevent="submit">
                        <img :src="logo" alt="TB. Mitra Utama" class="login__logo" />
                        <h2>Masuk</h2>
                        <p class="login__hint">Gunakan akun Anda.</p>

                        <q-input
                            v-model="form.username"
                            label="Username"
                            autocomplete="username"
                            autofocus
                            outlined
                            :error="!!form.errors.username"
                            :error-message="form.errors.username"
                        />

                        <q-input
                            v-model="form.password"
                            :type="isPasswordVisible ? 'text' : 'password'"
                            label="Password"
                            autocomplete="current-password"
                            outlined
                            :error="!!form.errors.password"
                            :error-message="form.errors.password"
                        >
                            <template #append>
                                <q-icon
                                    :name="isPasswordVisible ? 'visibility_off' : 'visibility'"
                                    class="cursor-pointer"
                                    @click="isPasswordVisible = !isPasswordVisible"
                                />
                            </template>
                        </q-input>

                        <q-checkbox v-model="form.remember" label="Ingat saya" dense class="login__remember" />

                        <q-btn
                            type="submit"
                            label="Masuk"
                            color="primary"
                            unelevated
                            no-caps
                            size="lg"
                            class="full-width login__submit"
                            :loading="form.processing"
                        />
                    </q-form>
                </section>
            </q-page>
        </q-page-container>
    </q-layout>
</template>

<style scoped>
.login {
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    min-height: 100vh;
}

.login__art {
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 56px;
    color: #fff;
    background: linear-gradient(145deg, #0f1115, #23262e 60%, #3a1618);
    border-bottom: 4px solid var(--app-brand);
}

.login__art h1 {
    margin: 0 0 6px;
    font-size: 40px;
    font-weight: 700;
    letter-spacing: -0.02em;
    line-height: 1.1;
}

.login__art p {
    margin: 0;
    font-size: 16px;
    opacity: 0.85;
}

.login__form {
    display: grid;
    place-items: center;
    padding: 32px 24px;
    background: var(--app-surface);
}

.login__box {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 100%;
    max-width: 340px;
}

.login__logo {
    width: 120px;
    height: 120px;
    margin: -10px 0 4px -14px;
    object-fit: contain;
}

.login__box h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    letter-spacing: -0.02em;
}

.login__hint {
    margin: 0 0 16px;
    color: var(--app-ink-soft);
}

.login__remember {
    margin: 2px 0 10px;
    color: var(--app-ink-soft);
}

.login__submit {
    border-radius: 10px;
}

@media (max-width: 900px) {
    .login {
        grid-template-columns: 1fr;
    }

    .login__art {
        display: none;
    }
}
</style>
