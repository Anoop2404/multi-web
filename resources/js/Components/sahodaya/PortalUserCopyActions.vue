<template>
    <div class="mt-2 space-y-1">
        <div class="flex flex-wrap gap-2">
            <button type="button" class="link-brand text-xs" @click="copy(credentials.username)">Copy username</button>
            <button type="button" class="link-brand text-xs disabled:opacity-40" :disabled="!credentials.password" @click="copy(credentials.password)">Copy password</button>
            <button type="button" class="btn-secondary text-xs" :disabled="!credentials.share_text" @click="copy(credentials.share_text)">Copy login · WhatsApp</button>
        </div>
        <p v-if="!credentials.password" class="text-xs text-slate-500">Password unavailable. Use Reset PW to create a shareable login.</p>
        <p v-if="message" role="status" class="text-xs text-slate-500">{{ message }}</p>
        <textarea v-if="manualCopy" :value="manualCopy" readonly rows="5" class="field mt-1 w-full text-xs" aria-label="Login details to copy" @focus="$event.target.select()" />
    </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({ credentials: { type: Object, required: true } });
const message = ref('');
const manualCopy = ref('');
async function copy(text) {
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        message.value = 'Copied';
        manualCopy.value = '';
    } catch {
        message.value = 'Select and copy the text below.';
        manualCopy.value = text;
    }
}
</script>
