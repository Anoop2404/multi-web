<template>
    <!--
        The shared State filter bar. The hierarchy is fixed across every State screen and report:
        State Program → State Event → Sahodaya → School → Category → Item → Participant.
        Sahodaya is always primary; School is a dependent second level, never a replacement for it.
    -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-3">
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Sahodaya</label>
                <select v-model="local.sahodaya_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option :value="null">All Sahodayas</option>
                    <option v-for="s in sahodayas" :key="s.id" :value="s.id">
                        {{ s.name }}<span v-if="s.district"> — {{ s.district }}</span>
                    </option>
                </select>
            </div>

            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">School</label>
                <select v-model="local.school_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                        :disabled="!local.sahodaya_id">
                    <option :value="null">{{ local.sahodaya_id ? 'All schools' : 'Pick a Sahodaya first' }}</option>
                    <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>

            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Source</label>
                <select v-model="local.origin" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option :value="null">Managed and outside</option>
                    <option value="managed">On the platform</option>
                    <option value="external">Arrived from outside</option>
                </select>
            </div>

            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Search</label>
                <input v-model="local.search" type="search" placeholder="Participant, item or chest no."
                       class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div v-if="active" class="mt-2 flex items-center gap-2 text-xs">
            <button type="button" class="text-slate-500 underline hover:text-slate-800" @click="clear">Clear filters</button>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    sahodayas: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    modelValue: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:modelValue']);

const local = reactive({
    sahodaya_id: props.modelValue.sahodaya_id ?? null,
    school_id: props.modelValue.school_id ?? null,
    origin: props.modelValue.origin ?? null,
    search: props.modelValue.search ?? '',
});

// School is meaningless without its Sahodaya, so changing the Sahodaya drops it rather than leaving
// a filter behind that silently matches nothing.
watch(() => local.sahodaya_id, () => { local.school_id = null; });
watch(local, () => emit('update:modelValue', { ...local }), { deep: true });

const active = computed(() => local.sahodaya_id || local.school_id || local.origin || local.search);

function clear() {
    local.sahodaya_id = null;
    local.school_id = null;
    local.origin = null;
    local.search = '';
}
</script>
