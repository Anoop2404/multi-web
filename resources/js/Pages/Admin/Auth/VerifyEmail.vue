<template>
    <div class="min-h-screen bg-gradient-to-b from-[#f0f9ff] to-gray-50 flex items-center justify-center p-6">
        <div class="max-w-md w-full bg-white rounded-2xl border border-gray-100 shadow-sm p-8 space-y-5">
            <div class="text-center space-y-3">
                <img v-if="logoUrl" :src="logoUrl" :alt="tenantName || 'Portal'" class="h-14 mx-auto object-contain">
                <div v-else class="w-14 h-14 mx-auto rounded-full bg-amber-50 flex items-center justify-center text-2xl">✉️</div>
                <div>
                    <p v-if="eyebrow" class="text-[10px] font-bold uppercase tracking-widest text-[#0f3d7a]/70">{{ eyebrow }}</p>
                    <h1 class="text-xl font-bold text-gray-900 mt-1">Verify your Gmail</h1>
                </div>
            </div>

            <p class="text-sm text-gray-500 leading-relaxed text-center">
                We sent a verification link to
                <strong class="text-gray-700">{{ $page.props.auth?.user?.email }}</strong>.
                Click the link in your email to open the school portal, or use the button below to resend.
            </p>

            <div class="bg-amber-50 border border-amber-100 rounded-lg px-4 py-3 text-xs text-amber-900 leading-relaxed">
                <strong>Tip:</strong> Use the same portal website shown in your registration email (e.g. your Sahodaya domain). The link signs you in automatically.
            </div>

            <p v-if="$page.props.flash?.success" class="text-sm text-green-700 bg-green-50 border border-green-100 rounded-lg px-3 py-2">
                {{ $page.props.flash.success }}
            </p>

            <form @submit.prevent="resend" class="space-y-3">
                <button type="submit" :disabled="form.processing"
                        class="btn-primary w-full disabled:opacity-50">
                    Resend verification email
                </button>
            </form>

            <div class="flex items-center justify-between pt-2">
                <Link href="/login" class="text-xs text-gray-500 hover:text-gray-700">Back to login</Link>
                <Link href="/logout" method="post" as="button" class="text-xs text-gray-400 hover:text-gray-600">
                    Sign out
                </Link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';

defineProps({
    logoUrl:    { type: String, default: null },
    tenantName: { type: String, default: null },
    portalUrl:  { type: String, default: null },
    eyebrow:    { type: String, default: null },
});

const form = useForm({});

function resend() {
    form.post('/email/verification-notification');
}
</script>
