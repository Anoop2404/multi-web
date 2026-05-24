import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';

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
