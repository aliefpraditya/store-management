import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { Dialog, Notify, Quasar } from 'quasar';
import '@quasar/extras/material-icons/material-icons.css';
import 'quasar/src/css/index.sass';
import '../css/app.css';

const pages = import.meta.glob('./Pages/**/*.vue');

createInertiaApp({
    title: (title) => (title ? `${title} - Mitra Manager` : 'Mitra Manager'),
    resolve: (name) => pages[`./Pages/${name}.vue`](),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(Quasar, {
                plugins: { Notify, Dialog },
                config: {
                    brand: {
                        primary: '#e02a2a',
                        secondary: '#2a2d35',
                        accent: '#111318',
                        dark: '#15171c',
                        positive: '#059669',
                        negative: '#be123c',
                        info: '#2563eb',
                        warning: '#c2710c',
                    },
                },
            })
            .mount(el);
    },
});
