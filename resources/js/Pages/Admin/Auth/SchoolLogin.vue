<template>
    <div class="login-page" :class="{ 'is-standalone': standalone }" :style="loginTheme">
        <Head :title="standalone ? 'School Login' : 'Member School Login'" />
        <div class="login-bg"></div>
        <div class="login-grid"></div>
        <div class="login-orb login-orb-1"></div>
        <div class="login-orb login-orb-2"></div>

        <div class="login-wrap">
            <div class="login-page-inner">

                <a :href="standalone ? '/' : '/portal'" class="login-back">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    {{ standalone ? 'Back to school website' : 'Back to portal' }}
                </a>

                <div class="login-shell">

                    <div class="login-brand">
                        <div class="login-logo-wrap">
                            <img v-if="logoUrl" :src="logoUrl" :alt="tenantName || 'Logo'" class="login-logo">
                            <div v-else class="login-avatar">{{ schoolInitial }}</div>
                        </div>
                        <div class="login-badge">{{ standalone ? loginContent.badge : 'Member school portal' }}</div>
                        <h1 class="login-title">{{ tenantName || 'School Login' }}</h1>
                        <p class="login-subtitle">
                            {{ standalone ? loginContent.intro : 'Sign in to register students for Kalotsav, sports meet, training, and annual Sahodaya membership.' }}
                        </p>
                        <p v-if="motto" class="login-motto">"{{ motto }}"</p>

                        <ol class="login-steps">
                            <li>{{ standalone ? loginContent.step_one : 'Sign in with your school admin email or username' }}</li>
                            <li>{{ standalone ? loginContent.step_two : 'Add students with date of birth & gender for sports' }}</li>
                            <li>{{ standalone ? loginContent.step_three : 'Register for events and submit annual data' }}</li>
                        </ol>

                        <div v-if="phone || email" class="login-contacts">
                            <span v-if="phone" class="login-contact">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                {{ phone }}
                            </span>
                            <a v-if="email" :href="`mailto:${email}`" class="login-contact">{{ email }}</a>
                        </div>

                        <div class="login-role-guide">
                            <LoginRoleGuide :rows="roleGuideRows" />
                        </div>
                    </div>

                    <div class="login-form-panel">
                        <div class="login-form-inner">
                            <div class="login-form-intro">
                                <p class="login-form-heading">{{ standalone ? loginContent.form_eyebrow : 'Member school' }}</p>
                                <h2 class="login-form-title">{{ standalone ? loginContent.form_title : 'School Login' }}</h2>
                                <p class="login-form-sub">{{ standalone ? loginContent.form_description : 'Use the credentials sent when your school membership was approved.' }}</p>
                            </div>

                            <form @submit.prevent="submit" class="login-form">
                                <AuthLoginAlerts :session-expired="sessionExpired" :auth-error="authError" />

                                <div>
                                    <label class="login-label" for="email">Email or username</label>
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="text"
                                        required
                                        autocomplete="username"
                                        class="login-input"
                                        :class="{ 'login-input-error': fieldErrors.email }"
                                        placeholder="admin@yourschool.edu"
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

                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <label class="flex items-center gap-2 text-slate-600 cursor-pointer">
                                        <input v-model="form.remember" type="checkbox" class="rounded border-slate-300">
                                        Remember me
                                    </label>
                                    <a href="/portal/forgot-password" class="login-inline-link text-xs font-semibold">Forgot password?</a>
                                </div>

                                <button type="submit" :disabled="form.processing" class="login-btn">
                                    {{ form.processing ? 'Signing in…' : (standalone ? loginContent.submit_label : 'Sign in to register') }}
                                </button>
                            </form>

                            <p v-if="showRegisterLink" class="login-alt mt-5 text-center text-sm text-slate-500">
                                Not a member yet?
                                <a href="/school-register" class="login-inline-link font-semibold">Apply for school registration</a>
                            </p>
                            <p v-if="!standalone" class="login-alt mt-3 text-center text-xs text-slate-400">
                                Sahodaya office staff?
                                <a href="/login" class="login-inline-link">Admin login</a>
                            </p>
                        </div>
                    </div>
                </div>

                <p class="login-footer-note">
                    {{ standalone ? `${tenantName || 'School'} · ${loginContent.footer_note}` : 'CBSE Sahodaya · Member schools register students & submit annual data here' }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import AuthLoginAlerts from '@/Components/auth/AuthLoginAlerts.vue';
import LoginRoleGuide from '@/Components/auth/LoginRoleGuide.vue';
import { useAuthLoginForm } from '@/support/useAuthLoginForm.js';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    logoUrl:    { type: String, default: null },
    tenantName: { type: String, default: null },
    motto:      { type: String, default: null },
    phone:      { type: String, default: null },
    email:      { type: String, default: null },
    standalone: { type: Boolean, default: false },
    content:    { type: Object, default: () => ({}) },
    primaryColor: { type: String, default: '#0F3D7A' },
    secondaryColor: { type: String, default: '#1E5AA8' },
    accentColor: { type: String, default: '#FBBF24' },
    showRegisterLink: { type: Boolean, default: true },
    sessionExpired: { type: Boolean, default: false },
});

const contentDefaults = {
    badge: 'School administration',
    intro: 'Sign in to manage the school website, admissions, announcements, gallery, staff, and contact details.',
    step_one: 'Update website sections, page text, colours, navigation, and footer links',
    step_two: 'Publish news, events, gallery albums, staff profiles, and results',
    step_three: 'Review admission enquiries and keep school contact details current',
    form_eyebrow: 'Secure school access',
    form_title: 'School Administration Login',
    form_description: 'Use the administrator credentials created for this school.',
    submit_label: 'Sign in to dashboard',
    footer_note: 'School administration access',
};

const loginContent = computed(() => ({ ...contentDefaults, ...(props.content ?? {}) }));
const schoolInitial = computed(() => (props.tenantName || 'School').trim().charAt(0).toUpperCase());
const loginTheme = computed(() => ({
    '--login-primary': props.primaryColor,
    '--login-secondary': props.secondaryColor,
    '--login-accent': props.accentColor,
}));
const roleGuideRows = computed(() => props.standalone
    ? [
        { label: 'School administration', detail: 'You are here — manage the website and school information' },
        { label: 'Public website', detail: 'Use the back link to return to the visitor-facing website' },
    ]
    : [
        { label: 'School admin login', detail: 'You are here — register students and fest entries' },
        { label: 'Portal login', detail: 'Students & teachers use /portal/login' },
        { label: 'Sahodaya staff', detail: 'Cluster admin uses /login' },
    ]);

const { form, authError, fieldErrors, submit } = useAuthLoginForm();
</script>

<style scoped>
.login-page {
    min-height: 100dvh;
    --login-primary: #0f3d7a;
    --login-secondary: #1e5aa8;
    --login-accent: #fbbf24;
    --login-deep: color-mix(in srgb, var(--login-primary) 24%, #02090f);
    background: var(--login-deep);
    position: relative;
    font-family: 'Inter', system-ui, sans-serif;
}
.login-bg {
    position: fixed; inset: 0;
    background:
        radial-gradient(ellipse 65% 50% at 20% 5%, color-mix(in srgb, var(--login-accent) 18%, transparent) 0%, transparent 55%),
        radial-gradient(ellipse 55% 45% at 80% 95%, color-mix(in srgb, var(--login-secondary) 20%, transparent) 0%, transparent 50%),
        linear-gradient(165deg, var(--login-deep) 0%, color-mix(in srgb, var(--login-primary) 58%, var(--login-deep)) 42%, var(--login-primary) 100%);
}
.login-grid {
    position: fixed; inset: 0; opacity: .03;
    background-image:
        linear-gradient(rgba(255,255,255,.9) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.9) 1px, transparent 1px);
    background-size: 56px 56px;
}
.login-orb { position: fixed; border-radius: 50%; filter: blur(80px); pointer-events: none; }
.login-orb-1 { width: 320px; height: 320px; top: -80px; right: -60px; background: color-mix(in srgb, var(--login-accent) 18%, transparent); }
.login-orb-2 { width: 240px; height: 240px; bottom: -40px; left: -40px; background: color-mix(in srgb, var(--login-secondary) 18%, transparent); }
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
.login-back:hover { color: var(--login-accent); }
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
    background: linear-gradient(155deg, color-mix(in srgb, var(--login-primary) 70%, var(--login-deep)) 0%, var(--login-primary) 45%, var(--login-secondary) 100%);
    padding: 2.5rem 2rem;
    display: flex; flex-direction: column; justify-content: center; gap: .75rem;
    position: relative; overflow: hidden;
}
.login-brand::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 80% 60% at 30% 15%, color-mix(in srgb, var(--login-accent) 14%, transparent) 0%, transparent 55%);
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
    background: linear-gradient(135deg, var(--login-primary), var(--login-secondary));
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: 800; color: var(--login-accent);
}
.login-badge {
    display: inline-flex; align-self: flex-start;
    font-size: .625rem; font-weight: 700; letter-spacing: .14em;
    text-transform: uppercase; color: var(--login-accent);
    background: color-mix(in srgb, var(--login-accent) 14%, transparent); border: 1px solid color-mix(in srgb, var(--login-accent) 34%, transparent);
    padding: .35rem .8rem; border-radius: 999px;
}
@media (max-width: 639px) { .login-badge { align-self: center; } }
.login-title { font-size: 1.5rem; font-weight: 800; color: #fff; line-height: 1.25; margin: 0; }
.login-subtitle { font-size: .875rem; color: rgba(255,255,255,.6); line-height: 1.55; max-width: 20rem; margin: 0; }
.login-motto {
    font-size: .8125rem; font-style: italic; color: var(--login-accent);
    padding-left: .75rem; border-left: 2px solid color-mix(in srgb, var(--login-accent) 52%, transparent); margin: 0;
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
    background: color-mix(in srgb, var(--login-accent) 17%, transparent); border: 1px solid color-mix(in srgb, var(--login-accent) 40%, transparent);
    color: var(--login-accent); font-size: .6875rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
}
.login-contacts {
    display: flex; flex-direction: column; gap: .4rem;
    margin-top: .5rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,.1);
}
@media (max-width: 639px) {
    .login-steps, .login-contacts, .login-role-guide { display: none; }
}
.login-contact {
    display: inline-flex; align-items: center; gap: .5rem;
    font-size: .75rem; color: rgba(255,255,255,.5); text-decoration: none;
}
a.login-contact:hover { color: var(--login-accent); }
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
    text-transform: uppercase; color: var(--login-primary); margin: 0;
}
.login-form-title {
    font-size: 1.375rem; font-weight: 800; color: var(--login-deep);
    line-height: 1.3; margin: 0;
}
.login-form-sub { font-size: .875rem; color: #64748b; line-height: 1.6; margin: 0; }
.login-form { display: flex; flex-direction: column; gap: 1.1rem; }
.login-label { display: block; font-size: .75rem; font-weight: 600; color: #334155; margin-bottom: .4rem; }
.login-input {
    width: 100%; border: 1.5px solid #e2e8f0; border-radius: .75rem;
    padding: .7rem .9rem; font-size: .875rem; color: var(--login-deep);
    background: #f8fafc; outline: none;
    transition: border-color .15s, box-shadow .15s, background .15s;
}
.login-input:focus { border-color: var(--login-primary); background: #fff; box-shadow: 0 0 0 3px color-mix(in srgb, var(--login-primary) 16%, transparent); }
.login-input-error { border-color: #f87171; background: #fff; }
.login-error { font-size: .75rem; color: #dc2626; margin-top: .35rem; }
.login-btn {
    width: 100%; padding: .85rem 1.25rem; border: none; border-radius: .75rem;
    background: linear-gradient(135deg, var(--login-primary), var(--login-secondary)); color: #fff;
    font-size: .875rem; font-weight: 700; cursor: pointer;
    box-shadow: 0 4px 14px color-mix(in srgb, var(--login-primary) 42%, transparent);
    transition: box-shadow .15s, transform .1s; margin-top: .25rem;
}
.login-btn:hover:not(:disabled) { box-shadow: 0 6px 20px color-mix(in srgb, var(--login-primary) 52%, transparent); }
.login-btn:active:not(:disabled) { transform: scale(.98); }
.login-btn:disabled { opacity: .6; cursor: not-allowed; }
.login-inline-link { color: var(--login-primary); }
.login-inline-link:hover { text-decoration: underline; }
.login-footer-note {
    text-align: center; font-size: .6875rem; color: rgba(255,255,255,.35);
    margin-top: 1.25rem;
}
</style>
