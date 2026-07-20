<template>
    <div>
        <template v-if="!hasRegistrations">
            <div class="card p-8 text-center text-gray-400">No registrations yet.</div>
        </template>
        <div v-else v-for="(group, groupKey) in groupedRegistrations" :key="groupKey" class="card card--flush overflow-hidden mb-4">
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 bg-slate-50 border-b border-slate-100">
                <span class="font-semibold text-sm text-slate-800">{{ groupKey }}</span>
                <div class="flex gap-3 text-xs">
                    <span class="text-amber-700 font-semibold" v-if="group.filter(r => r.status === 'submitted').length">
                        {{ group.filter(r => r.status === 'submitted').length }} pending
                    </span>
                    <span class="text-slate-500">{{ group.length }} total</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="bg-gray-50/60 text-left">
                        <tr>
                            <th class="p-3 w-8"></th>
                            <th class="p-3">School</th>
                            <th class="p-3">Event</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Athlete</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="reg in group" :key="reg.id" class="border-t align-top">
                            <td class="p-3">
                                <input v-if="reg.status === 'submitted'" type="checkbox" :value="reg.id"
                                       :checked="selectedIds.includes(reg.id)"
                                       @change="$emit('toggle-select', reg.id)">
                            </td>
                            <td class="p-3 text-xs">{{ schools[reg.school_id] ?? reg.school_id }}</td>
                            <td class="p-3">
                                <p class="font-medium text-slate-800 text-xs">{{ reg.item?.title ?? '—' }}</p>
                                <p v-if="reg.item?.age_group" class="text-[11px] text-indigo-600 mt-0.5">
                                    {{ String(reg.item.age_group).toUpperCase() }}
                                    <span v-if="reg.item.gender && !['open','mixed'].includes(reg.item.gender)"
                                          class="ml-1">· {{ genderLabel(reg.item.gender) }}</span>
                                </p>
                            </td>
                            <td class="p-3">
                                <span :class="statusClass(reg.status)" class="text-xs font-semibold px-2 py-0.5 rounded">
                                    {{ reg.status }}
                                </span>
                            </td>
                            <td class="p-3 text-xs space-y-1">
                                <div v-for="p in reg.participants" :key="p.id" class="flex flex-wrap items-center gap-1.5">
                                    <span class="font-medium text-slate-800">{{ p.student?.name ?? p.teacher?.name ?? '—' }}</span>
                                    <span v-if="p.student?.reg_no" class="text-gray-400">· {{ p.student.reg_no }}</span>
                                    <span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider"
                                          :class="p.participant_role === 'standby' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-indigo-50 text-indigo-800 border border-indigo-200'">
                                        {{ p.participant_role || 'performer' }}
                                    </span>
                                </div>
                                <div v-if="reg.status === 'approved' && standbyCount(reg)" class="mt-1">
                                    <button type="button" class="text-indigo-600 font-semibold" @click="$emit('substitute', reg)">Substitute</button>
                                </div>
                            </td>
                            <td class="p-3 text-right space-x-2">
                                <template v-if="reg.status === 'submitted'">
                                    <button @click="$emit('approve', reg.id)" class="text-green-600 text-xs font-semibold">Approve</button>
                                    <button @click="$emit('reject', reg.id)" class="text-red-600 text-xs font-semibold">Reject</button>
                                </template>
                                <button v-if="canCancel(reg)" @click="$emit('cancel', reg.id)" class="text-gray-600 text-xs font-semibold">Cancel</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    groupedRegistrations: { type: Object, default: () => ({}) },
    hasRegistrations: Boolean,
    selectedIds: { type: Array, default: () => [] },
    schools: { type: Object, default: () => ({}) },
    genderLabel: Function,
    statusClass: Function,
    standbyCount: Function,
    canCancel: Function,
});

defineEmits(['toggle-select', 'substitute', 'approve', 'reject', 'cancel']);
</script>
