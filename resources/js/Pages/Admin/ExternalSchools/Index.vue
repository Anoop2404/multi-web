<template>
    <AdminLayout :title="`Schools — ${sahodaya.name}`">
        <div class="max-w-3xl space-y-4">
            <Link :href="`/admin/state-programs`" class="text-sm text-indigo-600">← State programs</Link>

            <div class="card">
                <h3 class="font-semibold mb-1">{{ sahodaya.name }} — schools</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Each school signs in with its own username and password at
                    <span class="font-mono">{{ portalLoginUrl }}</span>. Reset a school's password here if they've
                    lost it — the new password is shown once below; relay it to the school yourself.
                </p>

                <ul v-if="schools.length" class="divide-y border rounded-lg text-sm">
                    <li v-for="school in schools" :key="school.id" class="py-3 px-3 flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <p class="font-medium text-gray-800">
                                {{ school.name }}
                                <span v-if="school.status !== 'active'" class="ml-1 text-xs text-gray-400">(disabled)</span>
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <span v-if="school.has_login" class="font-mono text-indigo-700">{{ school.username }}</span>
                                <span v-else class="text-amber-700">No login provisioned</span>
                                <span v-if="school.contact_name || school.contact_phone"> · {{ [school.contact_name, school.contact_phone].filter(Boolean).join(' · ') }}</span>
                            </p>
                        </div>
                        <button type="button" class="text-xs px-3 py-1.5 rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50"
                                :disabled="resetForm.processing" @click="resetPassword(school)">
                            Reset password
                        </button>
                    </li>
                </ul>
                <p v-else class="text-sm text-gray-400">No schools added yet.</p>
            </div>
        </div>

        <Modal :show="showModal" title="New password" :subtitle="credentials?.school_name" @close="showModal = false">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3 text-sm">
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">Username</span>
                    <span class="font-mono font-semibold text-slate-900">{{ credentials?.username || '—' }}</span>
                </div>
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">Password</span>
                    <span class="font-mono font-semibold text-emerald-800">{{ credentials?.password || '—' }}</span>
                </div>
            </div>
            <p class="text-xs text-slate-500 mt-3">This is shown once. Relay it to the school's coordinator yourself — no email is sent automatically.</p>
            <template #footer>
                <div class="flex justify-end">
                    <button type="button" class="btn-primary text-sm" @click="showModal = false">Close</button>
                </div>
            </template>
        </Modal>
    </AdminLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    schools: Array,
});

const portalLoginUrl = `${window.location.origin}/state/external/school/login`;

const page = usePage();
const resetForm = useForm({});
const showModal = ref(false);
const credentials = ref(null);

watch(() => page.props.flash?.newCredentials, (creds) => {
    if (!creds) return;
    credentials.value = { ...creds, school_name: props.schools.find((s) => s.username === creds.username)?.name };
    showModal.value = true;
});

function resetPassword(school) {
    resetForm.post(`/admin/state-programs/external-schools/${school.id}/reset-password`, { preserveScroll: true });
}
</script>
