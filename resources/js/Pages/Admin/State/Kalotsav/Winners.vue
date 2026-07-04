<template>
    <AdminLayout :title="`${program.title} — Winners`">
        <PageHeader :title="`${program.title} — Qualified winners`" eyebrow="State Kalotsav"
                    description="Participants promoted to the next level across clusters.">
            <template #actions>
                <a :href="`/admin/kalotsav/${program.id}/winners/export`" class="btn-secondary text-sm">Export CSV</a>
                <Link :href="`/admin/kalotsav/${program.id}`" class="btn-primary text-sm">← Program</Link>
            </template>
        </PageHeader>

        <div class="card card--flush overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Reg No</th>
                        <th>School</th>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Grade</th>
                        <th>From event</th>
                        <th>Next level</th>
                        <th>Promoted</th>
                        <th>Poster</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(w, i) in winners" :key="i">
                        <td class="font-medium">{{ w.participant || '—' }}</td>
                        <td class="font-mono text-xs">{{ w.reg_no || '—' }}</td>
                        <td>{{ w.school || '—' }}</td>
                        <td>{{ w.item || '—' }}</td>
                        <td class="capitalize text-xs">{{ w.category || '—' }}</td>
                        <td>{{ w.grade || '—' }}</td>
                        <td class="text-xs">{{ w.from_event || '—' }}</td>
                        <td class="text-xs capitalize">{{ w.next_level || '—' }}</td>
                        <td class="text-xs">{{ w.promoted_at || '—' }}</td>
                        <td class="text-xs">
                            <template v-if="w.poster_url">
                                <a :href="w.poster_url" target="_blank" rel="noopener" class="text-[#6366f1] hover:underline">View</a>
                                <button type="button" class="ml-2 text-slate-500 hover:text-slate-800" @click="copyPosterLink(w.poster_url)">
                                    {{ copiedUrl === w.poster_url ? 'Copied' : 'Copy link' }}
                                </button>
                            </template>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                    </tr>
                    <tr v-if="!winners.length"><td colspan="10" class="p-8 text-center text-slate-400">No qualified winners yet.</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ program: Object, winners: { type: Array, default: () => [] } });

const copiedUrl = ref('');

async function copyPosterLink(url) {
    try {
        await navigator.clipboard.writeText(url);
        copiedUrl.value = url;
        setTimeout(() => {
            if (copiedUrl.value === url) {
                copiedUrl.value = '';
            }
        }, 2000);
    } catch {
        window.prompt('Copy poster link:', url);
    }
}
</script>
