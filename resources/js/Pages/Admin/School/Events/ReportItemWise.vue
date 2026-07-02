<template>
    <SchoolAdminLayout :title="`Item-wise — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Item-wise — ${event.title}`"
            :eyebrow="programLabel"
            description="Participants and marks for a selected item."
        >
            <template #actions>
                <Link :href="`${programBase}/reports`" class="btn-secondary text-sm">← Reports</Link>
                <a :href="exportUrl" class="btn-primary text-sm">Export CSV ↓</a>
            </template>
        </PageHeader>

        <form class="flex flex-wrap gap-2 mb-4" @submit.prevent="applyItem">
            <select v-model="selectedItem" class="field max-w-xs">
                <option v-for="item in items" :key="item.id" :value="item.id">{{ item.title }}</option>
            </select>
            <button type="submit" class="btn-secondary text-sm">Apply</button>
        </form>

        <div class="card card--flush overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Reg No</th>
                        <th>Grade</th>
                        <th>Position</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in participants" :key="p.id">
                        <td>{{ p.student?.name ?? p.teacher?.name }}</td>
                        <td class="font-mono text-xs">{{ p.student?.reg_no ?? p.teacher?.reg_no }}</td>
                        <td>{{ p.mark?.grade ?? '—' }}</td>
                        <td>{{ p.mark?.position ?? '—' }}</td>
                        <td>{{ p.mark?.score ?? '—' }}</td>
                    </tr>
                    <tr v-if="!participants.length"><td colspan="5" class="p-6 text-center text-slate-400">No participants</td></tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({ school: Object, program: [String, Object], programMeta: { type: Object, default: null }, event: Object, items: Array, itemId: Number, participants: Array });
const selectedItem = ref(props.itemId);
const { programLabel, programBase } = useSchoolProgramContext(props);
const exportUrl = computed(() => `${programBase.value}/reports/${props.event.id}/item-wise/export?item_id=${selectedItem.value}`);

function applyItem() {
    router.get(`${programBase.value}/reports/${props.event.id}/item-wise`, { item_id: selectedItem.value }, { preserveScroll: true });
}
</script>
