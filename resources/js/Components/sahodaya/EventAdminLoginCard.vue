<template>
    <div :class="compact ? 'mt-2' : 'rounded-xl border border-slate-200 bg-white p-4'">
        <template v-if="!compact">
            <h4 class="font-semibold text-slate-900">{{ credentials.event_name }}</h4>
            <p class="mt-1 text-xs text-slate-500">Event admin · Finance excluded · Change password after first login</p>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex items-center gap-2"><dt class="w-24 text-slate-500">Username</dt><dd class="font-mono">{{ credentials.username }}</dd><button type="button" class="link-brand text-xs" @click="copy(credentials.username)">Copy</button></div>
                <div class="flex items-center gap-2"><dt class="w-24 text-slate-500">Password</dt><dd class="font-mono">{{ credentials.password || 'Changed — reset to share a new password' }}</dd><button v-if="credentials.password" type="button" class="link-brand text-xs" @click="copy(credentials.password)">Copy</button></div>
            </dl>
        </template>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <span v-if="compact" class="text-xs text-slate-500">Finance excluded</span>
            <button v-if="credentials.share_text" type="button" class="btn-secondary text-xs" @click="copy(credentials.share_text)">Copy WhatsApp login message</button>
            <button type="button" class="link-brand text-xs" :disabled="resetting" @click="resetPassword">Reset temporary password</button>
            <span role="status" class="text-xs text-slate-500">{{ message }}</span>
        </div>
        <textarea v-if="manualCopy" :value="manualCopy" readonly class="field mt-2 w-full text-xs" rows="5" aria-label="Login message to copy" @focus="$event.target.select()" />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({ credentials: { type: Object, required: true }, tenantId: { type: String, required: true }, compact: Boolean });
const message = ref('');
const manualCopy = ref('');
const resetting = ref(false);

async function copy(text) {
    try {
        await navigator.clipboard.writeText(text);
        manualCopy.value = '';
        message.value = 'Copied';
    } catch {
        manualCopy.value = text;
        message.value = 'Select and copy the text below.';
    }
}

function resetPassword() {
    if (!confirm(`Reset the event admin password for ${props.credentials.event_name}? The previous password will stop working.`)) return;
    resetting.value = true;
    router.post(`/sahodaya-admin/${props.tenantId}/events/${props.credentials.event_id}/event-admin-login/reset`, {}, {
        preserveScroll: true,
        onFinish: () => { resetting.value = false; manualCopy.value = ''; message.value = ''; },
    });
}
</script>
