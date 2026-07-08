<template>
    <SahodayaAdminLayout title="Teacher verification" :sahodaya="sahodaya" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Teacher verification" eyebrow="Membership" description="Verify teachers before training nomination." />
        <div class="grid sm:grid-cols-3 gap-4 mb-6">
            <div class="card !py-4 text-center"><p class="text-2xl font-bold">{{ counts.total }}</p><p class="text-xs text-slate-500">Active</p></div>
            <div class="card !py-4 text-center"><p class="text-2xl font-bold text-emerald-700">{{ counts.verified }}</p><p class="text-xs text-slate-500">Verified</p></div>
            <div class="card !py-4 text-center"><p class="text-2xl font-bold text-amber-700">{{ counts.unverified }}</p><p class="text-xs text-slate-500">Pending</p></div>
        </div>
        <form class="card !p-4 mb-4 flex flex-wrap gap-3 items-end" @submit.prevent="apply">
            <select v-model="f.school_id" class="field text-sm"><option value="">All schools</option><option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option></select>
            <select v-model="f.verification" class="field text-sm"><option value="all">All</option><option value="unverified">Pending</option><option value="verified">Verified</option></select>
            <input v-model="f.search" class="field text-sm" placeholder="Search">
            <button class="btn-secondary text-sm">Apply</button>
        </form>
        <div class="card overflow-x-auto p-0">
            <table class="data-table">
                <thead><tr><th>School</th><th>Teacher</th><th>Category</th><th>Subjects</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="t in teachers.data" :key="t.id">
                        <td>{{ t.school_name }}</td>
                        <td><strong>{{ t.name }}</strong><p class="text-xs text-slate-500">{{ t.email }}</p></td>
                        <td>{{ t.category || '—' }}</td>
                        <td class="text-xs">{{ (t.subjects || []).join(', ') || '—' }}</td>
                        <td><span class="status-pill text-xs" :class="t.is_verified ? 'status-pill--completed' : 'status-pill--open'">{{ t.is_verified ? 'Verified' : 'Pending' }}</span></td>
                        <td class="text-right">
                            <button v-if="!t.is_verified" type="button" class="text-xs font-semibold text-emerald-700" @click="verify(t)">Verify</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>
<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
const props = defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, teachers: Object, counts: Object, filters: Object, schools: Array });
const f = reactive({ ...props.filters });
function apply() { router.get(`/sahodaya-admin/${props.sahodaya.id}/teachers/verification`, f, { preserveState: true }); }
function verify(t) { router.post(`/sahodaya-admin/${props.sahodaya.id}/teachers/${t.id}/verify`, {}, { preserveScroll: true }); }
</script>
