<template>
    <main class="page">
        <BrandHeader name="Online exam" sub="Sahodaya Talent Search" />
        <div v-if="app.notice" class="notice warn" role="alert" style="margin-bottom: 14px">{{ app.notice }}</div>
        <form class="card stack" @submit.prevent="go">
            <h1>Find your exam</h1>
            <p class="muted">Enter your Sahodaya’s exam code. It is printed on your hall ticket (for example <b>MLP</b>).</p>
            <label class="field">
                <span>Sahodaya code</span>
                <input
                    ref="input"
                    v-model="code"
                    class="input code"
                    autocomplete="off"
                    autocapitalize="characters"
                    spellcheck="false"
                    maxlength="32"
                    required
                    aria-describedby="code-help"
                />
            </label>
            <small id="code-help" class="help">Letters and numbers only.</small>
            <button class="btn primary block big" type="submit" :disabled="!clean">Continue</button>
        </form>
    </main>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import BrandHeader from '../components/BrandHeader.vue';
import { app, chooseCode } from '../app';
import { normaliseCode } from '../lib/router';

const code = ref('');
const input = ref<HTMLInputElement | null>(null);
const clean = computed(() => normaliseCode(code.value));

function go() {
    if (clean.value) chooseCode(clean.value);
}

onMounted(() => input.value?.focus());
</script>
