import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

/**
 * Shared login form for Sahodaya / school / superadmin pages.
 * Surfaces validation and auth errors reliably (page props + form errors).
 */
export function useAuthLoginForm(initialEmail = '') {
    const page = usePage();
    const form = useForm({
        email: initialEmail || page.props.old?.email || '',
        password: '',
    });

    const authError = computed(() => {
        const fromForm = form.errors.email || form.errors.password;
        if (fromForm) return fromForm;

        const shared = page.props.errors ?? {};
        return shared.email || shared.password || '';
    });

    const fieldErrors = computed(() => ({
        email: form.errors.email || page.props.errors?.email || '',
        password: form.errors.password || page.props.errors?.password || '',
    }));

    function submit() {
        form.post('/login', {
            preserveScroll: true,
            onFinish: () => form.reset('password'),
        });
    }

    return { form, authError, fieldErrors, submit };
}
