import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';

router.on('httpException', (event) => {
    const status = event.detail.response?.status;

    if (status === 419 || status === 401) {
        event.preventDefault();
        window.location.href = '/login?session=expired';
    }
});

createInertiaApp({
    title: (title) => `${title} — Sahodaya Admin`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/Admin/${name}.vue`,
            import.meta.glob('./Pages/Admin/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const pinia = createPinia();
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .mount(el);
    },
    progress: {
        color: '#4f46e5',
    },
});
