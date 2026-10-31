<template>
    <details v-if="candidate.is_team" open class="mt-3 border-t border-slate-100 pt-2 text-xs">
        <summary class="cursor-pointer font-semibold text-indigo-700">Team roster · {{ candidate.team_size || members.length }} participants</summary>
        <div v-if="members.length" class="mt-2 grid gap-2 sm:grid-cols-2">
            <div v-for="member in members" :key="member.id" class="flex items-start gap-2 min-w-0 rounded-lg bg-slate-50 p-2">
                <img v-if="photo(member)" :src="photo(member)" :alt="member.name" loading="lazy" class="h-14 w-11 shrink-0 rounded-md bg-white object-contain border border-slate-200" @error="failPhoto(member)">
                <span v-else class="h-14 w-11 shrink-0 rounded-md bg-slate-200 flex items-center justify-center font-bold text-slate-600" :aria-label="`No photo for ${member.name}`">{{ initials(member.name) }}</span>
                <div class="min-w-0 break-words text-slate-600">
                    <p class="font-semibold text-slate-900 leading-snug">{{ member.name }}</p>
                    <p v-if="member.class_name">Class: {{ member.class_name }}</p>
                    <p v-if="member.reg_no">Reg: {{ member.reg_no }}</p>
                    <p v-if="member.admission_number && member.admission_number !== member.reg_no">Admission: {{ member.admission_number }}</p>
                    <p :class="member.role === 'Standby / Substitute' ? 'text-amber-700 font-medium' : 'text-slate-500'">{{ member.role }}</p>
                    <p v-if="member.disqualified" class="text-rose-700">Disqualified</p>
                </div>
            </div>
        </div>
        <p v-else class="mt-2 text-slate-500">Team roster is unavailable. Review the registration before offering this slot.</p>
    </details>
</template>
<script setup>
import { computed, reactive } from 'vue';
const props = defineProps({ candidate: { type: Object, required: true } });
const members = computed(() => props.candidate.team_members ?? []);
const failed = reactive({});
const photo = member => [member.sahodaya_photo_url, member.photo_url].find(url => url && !failed[`${member.id}:${url}`]);
function failPhoto(member) { const url = photo(member); if (url) failed[`${member.id}:${url}`] = true; }
const initials = name => String(name || '?').trim().split(/\s+/).slice(0, 2).map(word => word[0]).join('').toUpperCase();
</script>
