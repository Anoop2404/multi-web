<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
        <div class="max-w-md w-full bg-white rounded-2xl border border-gray-100 shadow-sm p-8 space-y-5 text-center">
            <div class="w-14 h-14 mx-auto rounded-full bg-amber-50 flex items-center justify-center text-2xl">✉️</div>
            <h1 class="text-xl font-bold text-gray-900">Verify your Gmail</h1>
            <p class="text-sm text-gray-500 leading-relaxed">
                We sent a verification link to
                <strong class="text-gray-700">{{ $page.props.auth?.user?.email }}</strong>.
                Please verify before using the school portal.
            </p>
            <p v-if="$page.props.flash?.success" class="text-sm text-green-700 bg-green-50 border border-green-100 rounded-lg px-3 py-2">
                {{ $page.props.flash.success }}
            </p>
            <form @submit.prevent="resend" class="space-y-3">
                <button type="submit" :disabled="form.processing"
                        class="w-full bg-[#0f3d7a] hover:bg-[#1a4f8c] text-white py-2.5 rounded-lg text-sm font-semibold disabled:opacity-50">
                    Resend verification email
                </button>
            </form>
            <Link href="/logout" method="post" as="button"
                  class="text-xs text-gray-400 hover:text-gray-600">
                Sign out
            </Link>
        </div>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({});

function resend() {
    form.post('/email/verification-notification');
}
</script>
