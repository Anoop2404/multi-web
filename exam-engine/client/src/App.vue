<template>
    <main v-if="app.screen === 'boot'" class="page" aria-busy="true">
        <div class="card center row" style="justify-content: center; margin-top: 20vh"><span class="spinner" aria-hidden="true" /> Loading the exam…</div>
    </main>
    <CodeScreen v-else-if="app.screen === 'code'" />
    <LoginScreen v-else-if="app.screen === 'login'" :key="app.code ?? ''" />
    <PrepareScreen v-else-if="app.screen === 'prepare' && app.state" />
    <main v-else-if="app.screen === 'opening'" class="page" aria-live="polite">
        <BrandHeader :name="app.state?.sahodaya_name" :sub="app.state?.title" :logo="app.state?.logo_url" :code="app.code" />
        <div class="card stack center">
            <template v-if="!app.openingError">
                <div class="row" style="justify-content: center"><span class="spinner" aria-hidden="true" /></div>
                <h1>{{ app.openingStep || 'Opening your question paper…' }}</h1>
                <p v-if="app.download" class="faint">{{ Math.round(app.download.loaded / 1024) }} KB downloaded</p>
            </template>
            <template v-else>
                <h1>Couldn’t open the paper</h1>
                <p class="muted" role="alert">{{ app.openingError }}</p>
                <button class="btn primary block big" type="button" @click="retryOpen">Try again</button>
            </template>
        </div>
    </main>
    <ExamScreen v-else-if="app.screen === 'exam' && session" :key="session.run + ':' + session.regId" />
    <DoneScreen v-else-if="app.screen === 'done'" />
    <main v-else-if="app.screen === 'ended'" class="page">
        <BrandHeader :name="app.state?.sahodaya_name" :sub="app.state?.title" :logo="app.state?.logo_url" :code="app.code" />
        <div class="card stack">
            <h1>This exam has ended</h1>
            <p class="muted">The exam is over and can no longer be started. If you think this is a mistake, contact your school coordinator.</p>
            <button class="btn" type="button" @click="logout">Sign out</button>
        </div>
    </main>
    <main v-else-if="app.screen === 'signed_out'" class="page">
        <div class="card stack">
            <h1>Signed out on this device</h1>
            <p class="muted">
                Your exam was opened on another device, or your session ended. Answers already sent are safe on the server<template v-if="lastSaved">
                    (last saved {{ lastSaved }})</template>.
            </p>
            <p class="muted">To continue here, ask your school coordinator to allow this device, then sign in again.</p>
            <button class="btn primary block big" type="button" @click="signInAgain">Sign in again</button>
        </div>
    </main>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import BrandHeader from './components/BrandHeader.vue';
import CodeScreen from './screens/CodeScreen.vue';
import DoneScreen from './screens/DoneScreen.vue';
import ExamScreen from './screens/ExamScreen.vue';
import LoginScreen from './screens/LoginScreen.vue';
import PrepareScreen from './screens/PrepareScreen.vue';
import { app, logout, retryOpen, session, showLogin } from './app';

const lastSaved = computed(() => {
    const t = session.value?.status.lastSavedAt;
    return t ? new Date(t).toLocaleTimeString() : '';
});

function signInAgain() {
    session.value?.stop();
    session.value = null;
    app.state = null;
    showLogin();
}
</script>
