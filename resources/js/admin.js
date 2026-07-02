import { createApp, h, nextTick } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { enhanceFormAccessibility } from '@/support/formAccessibility';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FlashBanner from '@/Components/ui/FlashBanner.vue';
import FormField from '@/Components/ui/FormField.vue';
import FormGrid from '@/Components/ui/FormGrid.vue';
import FormSection from '@/Components/ui/FormSection.vue';
import FormActions from '@/Components/ui/FormActions.vue';
import ChoiceGroup from '@/Components/ui/ChoiceGroup.vue';
import CheckboxField from '@/Components/ui/CheckboxField.vue';
import HubCard from '@/Components/ui/HubCard.vue';
import InputError from '@/Components/ui/InputError.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import PageShell from '@/Components/ui/PageShell.vue';

router.on('httpException', (event) => {
    const status = event.detail.response?.status;

    if (status === 419 || status === 401) {
        event.preventDefault();
        window.location.href = '/login?session=expired';
    }
});

function runAccessibilityPass() {
    nextTick(() => enhanceFormAccessibility(document.body));
}

router.on('navigate', () => runAccessibilityPass());
router.on('success', () => runAccessibilityPass());

createInertiaApp({
    title: (title) => `${title} — Sahodaya Admin`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/Admin/${name}.vue`,
            import.meta.glob('./Pages/Admin/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const pinia = createPinia();
        const app = createApp({ render: () => h(App, props) });

        app.component('FormField', FormField);
        app.component('FormGrid', FormGrid);
        app.component('FormSection', FormSection);
        app.component('FormActions', FormActions);
        app.component('ChoiceGroup', ChoiceGroup);
        app.component('CheckboxField', CheckboxField);
        app.component('InputError', InputError);
        app.component('EmptyState', EmptyState);
        app.component('PageHeader', PageHeader);
        app.component('PageShell', PageShell);
        app.component('FlashBanner', FlashBanner);
        app.component('HubCard', HubCard);

        app.use(plugin);
        app.use(pinia);
        app.mount(el);

        runAccessibilityPass();
    },
    progress: {
        color: '#041525',
    },
});
