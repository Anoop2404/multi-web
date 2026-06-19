<template>
    <div class="sp-login">
        <div class="sp-login-bg"></div>
        <div class="sp-login-grid"></div>
        <div class="sp-login-glow sp-login-glow-1"></div>
        <div class="sp-login-glow sp-login-glow-2"></div>

        <div class="sp-login-wrap">
            <div class="sp-login-card">
                <aside class="sp-login-brand">
                    <div class="sp-login-brand-inner">
                        <div class="sp-login-logo">
                            <svg viewBox="0 0 40 40" fill="none" aria-hidden="true">
                                <rect width="40" height="40" rx="10" fill="url(#sp-logo-grad)"/>
                                <path d="M12 28V12h6.2c3.4 0 5.6 1.8 5.6 4.6 0 2-1.1 3.4-2.8 4l3.4 7.4H18.8l-2.9-6.4H15.2V28H12zm3.2-9.2h2.8c1.3 0 2.1-.6 2.1-1.6s-.8-1.6-2.1-1.6h-2.8v3.2z" fill="white"/>
                                <defs>
                                    <linearGradient id="sp-logo-grad" x1="0" y1="0" x2="40" y2="40">
                                        <stop stop-color="#6366f1"/>
                                        <stop offset="1" stop-color="#8b5cf6"/>
                                    </linearGradient>
                                </defs>
                            </svg>
                        </div>

                        <p class="sp-login-eyebrow">Platform Console</p>
                        <h1 class="sp-login-title">{{ appName }}</h1>
                        <p class="sp-login-lead">
                            Manage Sahodaya clusters, schools, domains, and platform-wide settings from one place.
                        </p>

                        <div class="sp-login-features">
                            <div class="sp-login-feature">
                                <span class="sp-login-feature-icon">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </span>
                                <div>
                                    <p class="sp-login-feature-title">Tenants</p>
                                    <p class="sp-login-feature-desc">Clusters, schools &amp; domains</p>
                                </div>
                            </div>
                            <div class="sp-login-feature">
                                <span class="sp-login-feature-icon">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                </span>
                                <div>
                                    <p class="sp-login-feature-title">Domains</p>
                                    <p class="sp-login-feature-desc">Custom URLs per Sahodaya</p>
                                </div>
                            </div>
                            <div class="sp-login-feature">
                                <span class="sp-login-feature-icon">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                </span>
                                <div>
                                    <p class="sp-login-feature-title">Master data</p>
                                    <p class="sp-login-feature-desc">Global registration rules</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="sp-login-footnote">
                        Sahodaya cluster admins — sign in on your own domain.
                    </p>
                </aside>

                <section class="sp-login-form-side">
                    <div class="sp-login-form-wrap">
                        <div class="sp-login-form-head">
                            <p class="sp-login-form-eyebrow">Superadmin</p>
                            <h2 class="sp-login-form-title">Welcome back</h2>
                            <p class="sp-login-form-sub">Sign in with your platform credentials.</p>
                        </div>

                        <form class="sp-login-form" @submit.prevent="submit">
                            <div class="sp-login-field">
                                <label class="sp-login-label" for="email">Email address</label>
                                <div class="sp-login-input-wrap">
                                    <svg class="sp-login-input-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        required
                                        autocomplete="email"
                                        class="sp-login-input"
                                        :class="{ 'sp-login-input--error': form.errors.email }"
                                        placeholder="admin@platform.com"
                                    />
                                </div>
                                <p v-if="form.errors.email" class="sp-login-error">{{ form.errors.email }}</p>
                            </div>

                            <div class="sp-login-field">
                                <label class="sp-login-label" for="password">Password</label>
                                <div class="sp-login-input-wrap">
                                    <svg class="sp-login-input-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        type="password"
                                        required
                                        autocomplete="current-password"
                                        class="sp-login-input"
                                        placeholder="Enter your password"
                                    />
                                </div>
                            </div>

                            <button type="submit" class="sp-login-btn" :disabled="form.processing">
                                <span>{{ form.processing ? 'Signing in…' : 'Sign in' }}</span>
                                <svg v-if="!form.processing" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

defineProps({
    appName: { type: String, default: 'Sahodaya Platform' },
});

const form = useForm({ email: '', password: '' });

function submit() {
    form.post('/login');
}
</script>

<style scoped>
.sp-login {
    min-height: 100dvh;
    position: relative;
    font-family: 'Inter', system-ui, sans-serif;
    overflow: hidden;
}

.sp-login-bg {
    position: fixed;
    inset: 0;
    background:
        radial-gradient(ellipse 70% 55% at 15% 10%, rgba(99, 102, 241, 0.22) 0%, transparent 55%),
        radial-gradient(ellipse 60% 50% at 85% 90%, rgba(139, 92, 246, 0.18) 0%, transparent 50%),
        radial-gradient(ellipse 40% 35% at 50% 50%, rgba(56, 189, 248, 0.06) 0%, transparent 60%),
        linear-gradient(160deg, #09090f 0%, #111827 45%, #0f172a 100%);
}

.sp-login-grid {
    position: fixed;
    inset: 0;
    opacity: 0.035;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.9) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.9) 1px, transparent 1px);
    background-size: 48px 48px;
}

.sp-login-glow {
    position: fixed;
    border-radius: 50%;
    filter: blur(90px);
    pointer-events: none;
}

.sp-login-glow-1 {
    width: 420px;
    height: 420px;
    top: -120px;
    right: -80px;
    background: rgba(99, 102, 241, 0.15);
}

.sp-login-glow-2 {
    width: 320px;
    height: 320px;
    bottom: -60px;
    left: -60px;
    background: rgba(139, 92, 246, 0.12);
}

.sp-login-wrap {
    position: relative;
    z-index: 1;
    min-height: 100dvh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
}

.sp-login-card {
    width: 100%;
    max-width: 58rem;
    display: grid;
    border-radius: 1.25rem;
    overflow: hidden;
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.06),
        0 24px 80px rgba(0, 0, 0, 0.55),
        0 0 120px rgba(99, 102, 241, 0.08);
}

@media (min-width: 768px) {
    .sp-login-card {
        grid-template-columns: 1fr 1fr;
    }
}

.sp-login-brand {
    background: linear-gradient(155deg, #0f0f18 0%, #151528 50%, #1a1a35 100%);
    padding: 2.5rem 2.25rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 2rem;
    position: relative;
    overflow: hidden;
}

.sp-login-brand::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 90% 70% at 20% 0%, rgba(99, 102, 241, 0.12) 0%, transparent 60%);
    pointer-events: none;
}

.sp-login-brand-inner {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.sp-login-logo {
    width: 2.75rem;
    height: 2.75rem;
}

.sp-login-logo svg {
    width: 100%;
    height: 100%;
    display: block;
    filter: drop-shadow(0 4px 12px rgba(99, 102, 241, 0.4));
}

.sp-login-eyebrow {
    margin: 0;
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #818cf8;
}

.sp-login-title {
    margin: 0;
    font-size: 1.625rem;
    font-weight: 800;
    color: #f9fafb;
    line-height: 1.2;
    letter-spacing: -0.02em;
}

.sp-login-lead {
    margin: 0;
    font-size: 0.875rem;
    line-height: 1.65;
    color: rgba(255, 255, 255, 0.5);
    max-width: 22rem;
}

.sp-login-features {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    margin-top: 0.5rem;
}

.sp-login-feature {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.75rem 0.9rem;
    border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    transition: background 0.2s, border-color 0.2s;
}

.sp-login-feature:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(129, 140, 248, 0.2);
}

.sp-login-feature-icon {
    flex-shrink: 0;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.5rem;
    background: rgba(99, 102, 241, 0.15);
    border: 1px solid rgba(129, 140, 248, 0.2);
    color: #a5b4fc;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sp-login-feature-title {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #e5e7eb;
    line-height: 1.3;
}

.sp-login-feature-desc {
    margin: 0.1rem 0 0;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
    line-height: 1.3;
}

.sp-login-footnote {
    position: relative;
    z-index: 1;
    margin: 0;
    padding-top: 1.25rem;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.35);
    line-height: 1.5;
}

.sp-login-form-side {
    background: #fff;
    padding: 2.5rem 2.25rem;
    display: flex;
    align-items: center;
}

.sp-login-form-wrap {
    width: 100%;
    max-width: 22rem;
    margin: 0 auto;
}

.sp-login-form-head {
    margin-bottom: 2rem;
}

.sp-login-form-eyebrow {
    margin: 0;
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #6366f1;
}

.sp-login-form-title {
    margin: 0.4rem 0 0;
    font-size: 1.75rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.03em;
    line-height: 1.2;
}

.sp-login-form-sub {
    margin: 0.5rem 0 0;
    font-size: 0.875rem;
    color: #64748b;
    line-height: 1.55;
}

.sp-login-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.sp-login-field {
    display: flex;
    flex-direction: column;
}

.sp-login-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 0.45rem;
}

.sp-login-input-wrap {
    position: relative;
}

.sp-login-input-icon {
    position: absolute;
    left: 0.9rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
}

.sp-login-input {
    width: 100%;
    border: 1.5px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 0.75rem 0.9rem 0.75rem 2.65rem;
    font-size: 0.875rem;
    color: #0f172a;
    background: #f8fafc;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}

.sp-login-input::placeholder {
    color: #94a3b8;
}

.sp-login-input:focus {
    border-color: #6366f1;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.sp-login-input--error {
    border-color: #f87171;
    background: #fff;
}

.sp-login-error {
    margin: 0.35rem 0 0;
    font-size: 0.75rem;
    color: #dc2626;
}

.sp-login-btn {
    width: 100%;
    margin-top: 0.25rem;
    padding: 0.85rem 1.25rem;
    border: none;
    border-radius: 0.75rem;
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 50%, #7c3aed 100%);
    color: #fff;
    font-size: 0.9375rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    box-shadow: 0 4px 20px rgba(79, 70, 229, 0.35);
    transition: box-shadow 0.2s, transform 0.1s, opacity 0.15s;
}

.sp-login-btn:hover:not(:disabled) {
    box-shadow: 0 6px 28px rgba(79, 70, 229, 0.45);
}

.sp-login-btn:active:not(:disabled) {
    transform: scale(0.98);
}

.sp-login-btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

@media (max-width: 767px) {
    .sp-login-brand {
        padding: 1.75rem 1.5rem;
    }

    .sp-login-features,
    .sp-login-footnote {
        display: none;
    }

    .sp-login-form-side {
        padding: 1.75rem 1.5rem;
    }

    .sp-login-title {
        font-size: 1.375rem;
    }
}
</style>
