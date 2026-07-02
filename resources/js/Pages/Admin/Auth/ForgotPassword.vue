<template>
    <div class="login-page">
        <div class="login-bg"></div>
        <div class="login-wrap">
            <div class="login-page-inner max-w-md mx-auto">
                <a href="/portal/login" class="login-back">← Back to portal login</a>
                <div class="login-form-panel rounded-2xl shadow-xl">
                    <div class="login-form-inner">
                        <h2 class="login-form-title mb-2">Reset password</h2>
                        <p class="login-form-sub mb-6">Enter your email and we will send a reset link.</p>

                        <p v-if="status" class="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">{{ status }}</p>

                        <form @submit.prevent="submit" class="login-form">
                            <div>
                                <label class="login-label" for="email">Email</label>
                                <input id="email" v-model="form.email" type="email" required autocomplete="email" class="login-input" />
                                <p v-if="form.errors.email" class="login-error">{{ form.errors.email }}</p>
                            </div>
                            <button type="submit" :disabled="form.processing" class="login-btn">
                                {{ form.processing ? 'Sending…' : 'Send reset link' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({ email: '' });

function submit() {
    form.post('/portal/forgot-password');
}
</script>

<style scoped>
.login-page { min-height: 100dvh; background: #041525; position: relative; font-family: 'Inter', system-ui, sans-serif; padding: 2rem 1rem; }
.login-bg { position: fixed; inset: 0; background: linear-gradient(165deg, #041525 0%, #0f3d7a 100%); }
.login-wrap { position: relative; z-index: 1; }
.login-back { display: inline-block; color: rgba(255,255,255,.6); font-size: .875rem; margin-bottom: 1rem; text-decoration: none; }
.login-form-panel { background: #fff; padding: 2rem; }
.login-form-title { font-size: 1.375rem; font-weight: 800; color: #041525; }
.login-form-sub { font-size: .875rem; color: #64748b; }
.login-form { display: flex; flex-direction: column; gap: 1rem; }
.login-label { display: block; font-size: .75rem; font-weight: 600; color: #334155; margin-bottom: .4rem; }
.login-input { width: 100%; border: 1.5px solid #e2e8f0; border-radius: .75rem; padding: .7rem .9rem; font-size: .875rem; }
.login-error { font-size: .75rem; color: #dc2626; margin-top: .35rem; }
.login-btn { width: 100%; padding: .85rem; border: none; border-radius: .75rem; background: #1e5aa8; color: #fff; font-weight: 700; cursor: pointer; }
.login-btn:disabled { opacity: .6; }
</style>
