<template>
    <div class="space-y-2">
        <div class="overflow-x-auto rounded-xl border border-slate-100">
            <table class="data-table">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-slate-600">Min students</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-slate-600">Max students</th>
                        <th class="text-right px-4 py-2.5 text-xs font-semibold text-slate-600">Amount (₹)</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="(slab, index) in modelValue" :key="index">
                        <td class="px-4 py-2">
                            <input :value="slab.min_count" type="number" min="0" class="field w-24" @input="updateSlab(index, 'min_count', $event.target.value)">
                        </td>
                        <td class="px-4 py-2">
                            <input :value="slab.max_count" type="number" min="0" class="field w-24" placeholder="∞" @input="updateSlab(index, 'max_count', $event.target.value)">
                        </td>
                        <td class="px-4 py-2 text-right">
                            <input :value="slab.amount" type="number" min="0" class="field w-32 ml-auto text-right" @input="updateSlab(index, 'amount', $event.target.value)">
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button type="button" class="text-xs text-red-400 hover:text-red-600" @click="removeSlab(index)">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="!modelValue.length">
                        <td colspan="4" class="px-4 py-3 text-sm text-slate-500">No bands yet — add one below.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn-secondary text-sm" @click="addSlab">+ Add band</button>
    </div>
</template>

<script setup>
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue']);

function updateSlab(index, key, rawValue) {
    const next = props.modelValue.map((slab, i) => (i === index ? { ...slab, [key]: rawValue === '' ? '' : Number(rawValue) } : slab));
    emit('update:modelValue', next);
}

function addSlab() {
    emit('update:modelValue', [...props.modelValue, { min_count: 0, max_count: '', amount: '' }]);
}

function removeSlab(index) {
    emit('update:modelValue', props.modelValue.filter((_, i) => i !== index));
}
</script>
