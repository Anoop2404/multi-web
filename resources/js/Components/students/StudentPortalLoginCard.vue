<template>
    <section class="portal-card">
        <div class="portal-card__head">
            <div>
                <h3 class="portal-card__title">Portal access</h3>
                <p class="portal-card__sub">Student ID + password — no Gmail required</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-if="student.has_portal_login"
                    type="button"
                    class="btn-secondary text-sm"
                    @click="openCredentials"
                >
                    View credentials
                </button>
                <button
                    v-else-if="student.reg_no"
                    type="button"
                    class="btn-primary text-sm"
                    :disabled="provisionForm.processing"
                    @click="createLogin"
                >
                    Create login
                </button>
            </div>
        </div>

        <div class="portal-card__body">
            <div class="portal-cred-grid">
                <div class="portal-cred-item">
                    <span class="portal-cred-label">Student ID</span>
                    <span class="portal-cred-value font-mono">{{ portalUsername || '—' }}</span>
                </div>
                <div class="portal-cred-item">
                    <span class="portal-cred-label">Password</span>
                    <span class="portal-cred-value font-mono">
                        {{ storedPassword || (student.has_portal_login ? '—' : 'Not set') }}
                    </span>
                </div>
                <div class="portal-cred-item">
                    <span class="portal-cred-label">Status</span>
                    <span class="portal-cred-value">
                        <span class="portal-status" :class="student.has_portal_login ? 'portal-status--active' : 'portal-status--inactive'">
                            {{ student.has_portal_login ? 'Active' : 'Not created' }}
                        </span>
                    </span>
                </div>
            </div>
            <p v-if="!student.reg_no" class="text-xs text-amber-700 mt-3">
                Assign a Student ID before creating a portal login.
            </p>
            <p class="text-xs text-slate-500 mt-3">
                Sign in at
                <a :href="portalLoginUrl" target="_blank" rel="noopener" class="link-brand">{{ portalLoginUrl }}</a>
            </p>
        </div>
    </section>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="closeModal"></div>
        <div class="relative modal-shell max-w-md w-full p-6 space-y-4">
            <h3 class="font-bold text-lg">Portal credentials</h3>
            <p class="text-sm text-slate-600">{{ student.name }}</p>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3 text-sm">
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">Student ID</span>
                    <span class="font-mono font-semibold text-slate-900">{{ portalUsername || '—' }}</span>
                </div>
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">Password</span>
                    <span class="font-mono font-semibold text-emerald-800">{{ visiblePassword || '—' }}</span>
                </div>
            </div>
            <div class="flex justify-end gap-2 flex-wrap">
                <button type="button" class="text-sm text-slate-500" @click="closeModal">Close</button>
                <button
                    v-if="student.has_portal_login"
                    type="button"
                    class="btn-secondary text-sm"
                    :disabled="resetForm.processing"
                    @click="resetPassword"
                >
                    Reset password
                </button>
                <a :href="portalLoginUrl" target="_blank" rel="noopener" class="btn-primary text-sm">Open portal ↗</a>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    student: { type: Object, required: true },
    provisionUrl: { type: String, required: true },
    resetUrl: { type: String, required: true },
    portalLoginUrl: { type: String, default: '/portal/login' },
});

const page = usePage();
const showModal = ref(false);
const displayPassword = ref(null);

const provisionForm = useForm({});
const resetForm = useForm({});

const portalUsername = computed(() => props.student.portal_username || props.student.reg_no || null);
const storedPassword = computed(() => props.student.portal_password ?? null);
const visiblePassword = computed(() => displayPassword.value ?? storedPassword.value);

function applyFlashCredentials() {
    const creds = page.props.flash?.newCredentials;
    if (! creds) return;

    const matchesStudent =
        creds.student_name === props.student.name
        || creds.username === portalUsername.value;

    if (matchesStudent && creds.password) {
        displayPassword.value = creds.password;
        showModal.value = true;
    }
}

watch(() => page.props.flash?.newCredentials, applyFlashCredentials, { immediate: true });

function openCredentials() {
    displayPassword.value = storedPassword.value;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
}

function onPortalActionSuccess() {
    applyFlashCredentials();
    router.reload({ only: ['student'], preserveScroll: true });
}

function createLogin() {
    if (! props.student.reg_no) return;
    provisionForm.post(props.provisionUrl, { preserveScroll: true, onSuccess: onPortalActionSuccess });
}

function resetPassword() {
    if (! confirm(`Reset portal password for ${props.student.name}?`)) return;
    resetForm.post(props.resetUrl, { preserveScroll: true, onSuccess: onPortalActionSuccess });
}
</script>

<style scoped>
.portal-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    overflow: hidden;
}
.portal-card__head {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
}
.portal-card__title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.portal-card__sub {
    font-size: 0.75rem;
    color: #64748b;
    margin: 0.25rem 0 0;
}
.portal-card__body { padding: 1.25rem 1.5rem; }
.portal-cred-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
    gap: 1rem;
}
.portal-cred-item { display: flex; flex-direction: column; gap: 0.35rem; }
.portal-cred-label {
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #94a3b8;
}
.portal-cred-value { font-size: 0.875rem; font-weight: 600; color: #0f172a; }
.portal-status {
    display: inline-flex;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
}
.portal-status--active { background: #ecfdf5; color: #047857; }
.portal-status--inactive { background: #f1f5f9; color: #64748b; }
</style>
