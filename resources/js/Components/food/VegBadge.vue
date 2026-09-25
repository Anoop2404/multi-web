<template>
    <div class="inline-flex items-center gap-1.5" :title="isNonVegItem ? 'Non-Vegetarian' : 'Vegetarian'">
        <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-[3px] border p-0.5 shrink-0"
              :class="isNonVegItem ? 'border-rose-600 bg-white' : 'border-emerald-600 bg-white'">
            <span class="w-1.5 h-1.5 rounded-full"
                  :class="isNonVegItem ? 'bg-rose-600' : 'bg-emerald-600'"></span>
        </span>
        <span v-if="showLabel" class="text-[11px] font-medium"
              :class="isNonVegItem ? 'text-rose-700' : 'text-emerald-700'">
            {{ isNonVegItem ? 'Non-Veg' : 'Veg' }}
        </span>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { isNonVeg } from '@/support/dietDetector.js';

const props = defineProps({
    name: { type: String, default: '' },
    description: { type: String, default: '' },
    isNonVeg: { type: Boolean, default: undefined },
    showLabel: { type: Boolean, default: false },
});

const isNonVegItem = computed(() => {
    if (typeof props.isNonVeg === 'boolean') return props.isNonVeg;
    return isNonVeg(props.name, props.description);
});
</script>
