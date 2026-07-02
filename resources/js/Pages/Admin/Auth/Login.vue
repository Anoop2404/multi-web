<template>
    <div class="login-page">
        <div class="login-bg"></div>
        <div class="login-grid"></div>
        <div class="login-orb login-orb-1"></div>
        <div class="login-orb login-orb-2"></div>

        <div class="login-wrap">
            <div class="login-page-inner">

                <a href="/portal" class="login-back">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    Back to portal
                </a>

                <div class="login-shell">

                    <div class="login-brand">
                        <div class="login-logo-wrap">
                            <img v-if="logoUrl" :src="logoUrl" :alt="tenantName || 'Logo'" class="login-logo">
                            <div v-else class="login-avatar">S</div>
                        </div>
                        <div class="login-badge">{{ eyebrow }}</div>
                        <h1 class="login-title">{{ tenantName || 'Admin Portal' }}</h1>
                        <p v-if="tagline" class="login-subtitle">{{ tagline }}</p>
                        <p v-else class="login-subtitle">Sign in to manage membership, schools, and registrations.</p>
                        <p v-if="motto" class="login-motto">"{{ motto }}"</p>

                        <ol class="login-steps">
                            <li>Sign in with your admin email</li>
                            <li>Manage schools &amp; annual registration</li>
                            <li>Review submissions and payments</li>
                        </ol>

                        <div v-if="phone || email" class="login-contacts">
                            <span v-if="phone" class="login-contact">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                {{ phone }}
                            </span>
                            <a v-if="email" :href="`mailto:${email}`" class="login-contact">{{ email }}</a>
                        </div>
                    </div>

                    <div class="login-form-panel">
                        <div class="login-form-inner">
                            <div class="login-form-intro">
                                <p class="login-form-heading">Admin Access</p>
                                <h2 class="login-form-title">Sign In</h2>
                                <p class="login-form-sub">Enter your credentials to access the Sahodaya admin dashboard.</p>
                            </div>

                            <form @submit.prevent="submit" class="login-form">
                                <AuthLoginAlerts :session-expired="sessionExpired" :auth-error="authError" />

                                <div>
                                    <label class="login-label" for="email">Email</label>
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        required
                                        autocomplete="email"
                                        class="login-input"
                                        :class="{ 'login-input-error': fieldErrors.email }"
                                        placeholder="you@school.edu"
                                    />
                                    <p v-if="fieldErrors.email" class="login-error">{{ fieldErrors.email }}</p>
                                </div>

                                <div>
                                    <label class="login-label" for="password">Password</label>
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        type="password"
                                        required
                                        autocomplete="current-password"
                                        class="login-input"
                                        :class="{ 'login-input-error': fieldErrors.password || authError }"
                                        placeholder="••••••••"
                                    />
                                    <p v-if="fieldErrors.password" class="login-error">{{ fieldErrors.password }}</p>
                                </div>

                                <button type="submit" :disabled="form.processing" class="login-btn">
                                    {{ form.processing ? 'Signing in...' : 'Sign In' }}
                                </button>
                            </form>

                            <p class="login-alt mt-5 text-center text-xs text-slate-400">
                                Member school?
                                <a href="/school-login" class="text-[#1e5aa8] hover:underline">School login for event registration</a>
                            </p>
                        </div>
                    </div>
                </div>

                <p class="login-footer-note">CBSE Sahodaya School Complex · Membership Portal</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import AuthLoginAlerts from '@/Components/auth/AuthLoginAlerts.vue';
import { useAuthLoginForm } from '@/support/useAuthLoginForm.js';

defineProps({
    logoUrl:    { type: String, default: null },
    tenantName: { type: String, default: null },
    eyebrow:    { type: String, default: 'CBSE Sahodaya School Complex' },
    tagline:    { type: String, default: null },
    motto:      { type: String, default: null },
    phone:      { type: String, default: null },
    email:      { type: String, default: null },
    sessionExpired: { type: Boolean, default: false },
});

const { form, authError, fieldErrors, submit } = useAuthLoginForm();
</script>

<style scoped>
.login-page {
    min-height: 100dvh;
    background: #041525;
    position: relative;
    font-family: 'Inter', system-ui, sans-serif;
}
.login-bg {
    position: fixed; inset: 0;
    background:
        radial-gradient(ellipse 65% 50% at 20% 5%, rgba(234,179,8,.14) 0%, transparent 55%),
        radial-gradient(ellipse 55% 45% at 80% 95%, rgba(37,99,235,.18) 0%, transparent 50%),
        linear-gradient(165deg, #041525 0%, #0a2744 40%, #0f3d7a 100%);
}
.login-grid {
    position: fixed; inset: 0; opacity: .03;
    background-image:
        linear-gradient(rgba(255,255,255,.9) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.9) 1px, transparent 1px);
    background-size: 56px 56px;
}
.login-orb { position: fixed; border-radius: 50%; filter: blur(80px); pointer-events: none; }
.login-orb-1 { width: 320px; height: 320px; top: -80px; right: -60px; background: rgba(234,179,8,.12); }
.login-orb-2 { width: 240px; height: 240px; bottom: -40px; left: -40px; background: rgba(37,99,235,.14); }
.login-wrap {
    position: relative; z-index: 1;
    min-height: 100dvh;
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
}
.login-page-inner { width: 100%; max-width: 58rem; }
.login-back {
    display: inline-flex; align-items: center; gap: .4rem;
    font-size: .8125rem; font-weight: 600; color: rgba(255,255,255,.55);
    text-decoration: none; margin-bottom: 1.25rem; transition: color .15s;
}
.login-back:hover { color: #fbbf24; }
.login-shell {
    width: 100%;
    display: grid; border-radius: 1.5rem; overflow: hidden;
    box-shadow: 0 0 0 1px rgba(255,255,255,.08), 0 32px 80px rgba(0,0,0,.55);
}
@media (max-width: 639px) {
    .login-shell { max-width: 26rem; margin: 0 auto; grid-template-columns: 1fr; }
}
@media (min-width: 640px) {
    .login-shell { grid-template-columns: .95fr 1.05fr; }
}
.login-brand {
    background: linear-gradient(155deg, #0a2744 0%, #0f3d7a 45%, #1a4f8c 100%);
    padding: 2.5rem 2rem;
    display: flex; flex-direction: column; justify-content: center; gap: .75rem;
    position: relative; overflow: hidden;
}
.login-brand::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 80% 60% at 30% 15%, rgba(234,179,8,.1) 0%, transparent 55%);
}
.login-brand > * { position: relative; z-index: 1; }
@media (max-width: 639px) {
    .login-brand { padding: 1.75rem 1.5rem; align-items: center; text-align: center; gap: .6rem; }
}
.login-logo-wrap {
    display: flex; align-items: center; justify-content: center;
    width: 7rem; height: 7rem; border-radius: 50%;
    background: transparent;
    border: 2px solid rgba(255,255,255,.35);
    box-shadow: 0 12px 32px rgba(0,0,0,.3);
    overflow: hidden; padding: 0;
}
@media (max-width: 639px) {
    .login-logo-wrap { width: 5.5rem; height: 5.5rem; margin: 0 auto; }
}
.login-logo { width: 100%; height: 100%; object-fit: cover; display: block; transform: scale(1.18); transform-origin: center center; }
.login-avatar {
    width: 100%; height: 100%;
    background: linear-gradient(135deg, #0f3d7a, #1e5aa8);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: 800; color: #fbbf24;
}
.login-badge {
    display: inline-flex; align-self: flex-start;
    font-size: .625rem; font-weight: 700; letter-spacing: .14em;
    text-transform: uppercase; color: #fbbf24;
    background: rgba(234,179,8,.12); border: 1px solid rgba(234,179,8,.28);
    padding: .35rem .8rem; border-radius: 999px;
}
@media (max-width: 639px) { .login-badge { align-self: center; } }
.login-title { font-size: 1.5rem; font-weight: 800; color: #fff; line-height: 1.25; margin: 0; }
.login-subtitle { font-size: .875rem; color: rgba(255,255,255,.6); line-height: 1.55; max-width: 20rem; margin: 0; }
.login-motto {
    font-size: .8125rem; font-style: italic; color: #fbbf24;
    padding-left: .75rem; border-left: 2px solid rgba(251,191,36,.45); margin: 0;
}
@media (max-width: 639px) { .login-motto { display: none; } }
.login-steps {
    list-style: none; display: flex; flex-direction: column; gap: .5rem;
    margin: .5rem 0 0; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,.1);
    counter-reset: login-step;
}
.login-steps li {
    counter-increment: login-step;
    display: flex; align-items: flex-start; gap: .65rem;
    font-size: .8125rem; color: rgba(255,255,255,.65); line-height: 1.45;
}
.login-steps li::before {
    content: counter(login-step);
    flex-shrink: 0; width: 1.35rem; height: 1.35rem; border-radius: 50%;
    background: rgba(234,179,8,.15); border: 1px solid rgba(234,179,8,.35);
    color: #fbbf24; font-size: .6875rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
}
@media (max-width: 639px) { .login-steps, .login-contacts { display: none; } }
.login-contacts {
    display: flex; flex-direction: column; gap: .4rem;
    margin-top: .5rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,.1);
}
.login-contact {
    display: inline-flex; align-items: center; gap: .5rem;
    font-size: .75rem; color: rgba(255,255,255,.5); text-decoration: none;
}
a.login-contact:hover { color: #fbbf24; }
.login-form-panel {
    background: #fff; padding: 2.5rem 2rem;
    display: flex; align-items: center;
}
@media (max-width: 639px) { .login-form-panel { padding: 1.75rem 1.5rem; } }
.login-form-inner { width: 100%; }
.login-form-intro {
    display: flex; flex-direction: column; gap: .5rem; margin-bottom: 1.5rem;
}
.login-form-heading {
    font-size: .6875rem; font-weight: 700; letter-spacing: .1em;
    text-transform: uppercase; color: #1e5aa8; margin: 0;
}
.login-form-title {
    font-size: 1.375rem; font-weight: 800; color: #041525;
    line-height: 1.3; margin: 0;
}
.login-form-sub { font-size: .875rem; color: #64748b; line-height: 1.6; margin: 0; }
.login-form { display: flex; flex-direction: column; gap: 1.1rem; }
.login-label { display: block; font-size: .75rem; font-weight: 600; color: #334155; margin-bottom: .4rem; }
.login-input {
    width: 100%; border: 1.5px solid #e2e8f0; border-radius: .75rem;
    padding: .7rem .9rem; font-size: .875rem; color: #041525;
    background: #f8fafc; outline: none;
    transition: border-color .15s, box-shadow .15s, background .15s;
}
.login-input:focus { border-color: #1e5aa8; background: #fff; box-shadow: 0 0 0 3px rgba(30,90,168,.15); }
.login-input-error { border-color: #f87171; background: #fff; }
.login-error { font-size: .75rem; color: #dc2626; margin-top: .35rem; }
.login-btn {
    width: 100%; padding: .85rem 1.25rem; border: none; border-radius: .75rem;
    background: linear-gradient(135deg, #0f3d7a, #1e5aa8); color: #fff;
    font-size: .875rem; font-weight: 700; cursor: pointer;
    box-shadow: 0 4px 14px rgba(15,61,122,.4);
    transition: box-shadow .15s, transform .1s; margin-top: .25rem;
}
.login-btn:hover:not(:disabled) { box-shadow: 0 6px 20px rgba(15,61,122,.5); }
.login-btn:active:not(:disabled) { transform: scale(.98); }
.login-btn:disabled { opacity: .6; cursor: not-allowed; }
.login-footer-note {
    text-align: center; font-size: .6875rem; color: rgba(255,255,255,.35);
    margin-top: 1.25rem;
}
</style>
