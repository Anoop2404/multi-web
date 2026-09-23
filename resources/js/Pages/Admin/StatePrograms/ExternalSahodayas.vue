<template>
    <AdminLayout :title="`Outside Sahodayas — ${program.title}`">
        <div class="max-w-4xl space-y-4">
            <Link :href="`/admin/state-programs/${program.id}`" class="text-sm link-brand">← {{ program.title }}</Link>

            <div class="card">
                <h3 class="font-semibold mb-1">Outside Sahodayas</h3>
                <p class="text-xs text-gray-500 mb-3">
                    For Sahodayas that aren't platform tenants. Add one here, then share its access code and portal
                    link with their coordinator — no subdomain or login needed. The coordinator adds their schools,
                    each school enters its own qualified students, and the coordinator submits the batch to State
                    review when ready.
                </p>

                <div v-if="promotedCount" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                    <span class="font-semibold">{{ promotedCount }} of {{ sahodayas.length }}</span>
                    now run on the platform with their own portal. Their access codes no longer open a roster —
                    don't hand them out; send the coordinator to their Sahodaya address instead.
                </div>

                <form @submit.prevent="submit" class="grid sm:grid-cols-2 gap-2 mb-5">
                    <input v-model="form.name" class="field sm:col-span-2" placeholder="Sahodaya name" required>
                    <input v-model="form.contact_name" class="field" placeholder="Contact name (optional)">
                    <input v-model="form.contact_phone" class="field" placeholder="Contact phone (optional)">
                    <input v-model="form.contact_email" class="field sm:col-span-2" type="email" placeholder="Contact email (optional)">
                    <button class="btn-primary sm:col-span-2" :disabled="form.processing">Add Sahodaya</button>
                </form>

                <ul v-if="sahodayas.length" class="divide-y border rounded-lg text-sm">
                    <li v-for="s in sahodayas" :key="s.id" class="py-3 px-3 flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <p class="font-medium text-gray-800">
                                {{ s.name }}
                                <span v-if="s.tenant_id" class="ml-1 inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-emerald-700">On the platform</span>
                                <span v-else-if="s.promotion_status === 'failed'" class="ml-1 inline-block rounded-full bg-red-100 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-red-700">Promotion failed</span>
                                <span v-if="s.is_appeal_pool" class="ml-1 text-xs font-semibold text-amber-600">(Appeal pool)</span>
                                <span v-if="s.status !== 'active'" class="ml-1 text-xs text-gray-400">(disabled)</span>
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <span v-if="s.district">{{ s.district }} · </span>
                                {{ s.schools_count }} school{{ s.schools_count === 1 ? '' : 's' }}
                                <span v-if="s.contact_name || s.contact_phone"> · {{ [s.contact_name, s.contact_phone].filter(Boolean).join(' · ') }}</span>
                            </p>

                            <!-- Promoted: the access code still resolves, but only to a "moved" page, so showing
                                 it here as if it were a working roster link would be actively misleading. -->
                            <p v-if="s.tenant_id" class="text-xs mt-1">
                                <a v-if="s.tenant_url" :href="s.tenant_url" target="_blank" class="link-brand font-medium">{{ s.tenant_url }} ↗</a>
                                <span v-else class="text-gray-400">Portal address not published yet</span>
                            </p>
                            <p v-else class="text-xs mt-1">
                                <span class="font-mono font-semibold text-[color:var(--brand-blue)]">{{ s.access_code }}</span>
                                <a :href="`${portalUrl}/${s.access_code}`" target="_blank" class="ml-2 link-brand">Open portal ↗</a>
                            </p>
                            <p v-if="!s.tenant_id && s.promotion_error" class="text-xs mt-1 text-red-600">{{ s.promotion_error }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link :href="`/admin/state-programs/external-sahodayas/${s.id}/schools`" class="text-xs px-3 py-1.5 rounded-lg border border-[color:var(--brand-blue)]/30 text-[color:var(--brand-blue)] hover:bg-[color:var(--brand-blue)]/10">
                                Manage schools →
                            </Link>
                            <button v-if="!s.tenant_id" type="button" @click="toggleStatus(s)" class="text-xs px-3 py-1.5 rounded-lg border"
                                    :class="s.status === 'active' ? 'border-red-200 text-red-600 hover:bg-red-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'">
                                {{ s.status === 'active' ? 'Disable' : 'Re-enable' }}
                            </button>
                        </div>
                    </li>
                </ul>
                <p v-else class="text-sm text-gray-400">None added yet.</p>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    program: Object,
    sahodayas: Array,
    portalUrl: String,
});

const promotedCount = computed(() => props.sahodayas.filter((s) => s.tenant_id).length);

const form = useForm({
    name: '',
    contact_name: '',
    contact_phone: '',
    contact_email: '',
});

function submit() {
    form.post(`/admin/state-programs/${props.program.id}/external-sahodayas`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function toggleStatus(sahodaya) {
    router.post(`/admin/state-programs/external-sahodayas/${sahodaya.id}/toggle-status`, {}, { preserveScroll: true });
}
</script>
