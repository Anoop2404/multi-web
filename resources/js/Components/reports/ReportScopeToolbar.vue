<template>
  <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-6 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <!-- Left side: Scope Mode / Region Selector -->
      <div class="flex items-center space-x-3">
        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Scope:</span>

        <!-- Region Restricted Admin Badge -->
        <div v-if="isRestricted" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
          <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          Region: {{ currentRegionName }}
        </div>

        <!-- Full Admin Scope Selector -->
        <div v-else class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            @click="selectScope('combined', null)"
            :class="[
              'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
              currentMode === 'combined' && !selectedRegionId
                ? 'bg-indigo-600 text-white shadow-sm'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
            ]"
          >
            Combined Result
          </button>

          <button
            v-for="region in regions"
            :key="region.id"
            type="button"
            @click="selectScope('region', region.id)"
            :class="[
              'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
              currentMode === 'region' && selectedRegionId === region.id
                ? 'bg-indigo-600 text-white shadow-sm'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
            ]"
          >
            {{ region.name }} ({{ region.code }})
          </button>
        </div>
      </div>

      <!-- Right side: Competition Phase Selector (if enabled) -->
      <div v-if="hasPhases" class="flex items-center space-x-2">
        <label for="competition-phase-select" class="text-xs font-medium text-gray-600 dark:text-gray-400">Phase:</label>
        <SearchableSelect
          id="competition-phase-select"
          :model-value="selectedPhaseId"
          @update:model-value="onPhaseChange"
          :options="phases"
          :all-option="true"
          all-label="All Phases"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
  regions: { type: Array, default: () => [] },
  phases: { type: Array, default: () => [] },
  currentMode: { type: String, default: 'combined' },
  selectedRegionId: { type: [Number, String], default: null },
  selectedPhaseId: { type: [Number, String], default: null },
  isRestricted: { type: Boolean, default: false },
});

const emit = defineEmits(['change-scope', 'change-phase']);

const hasPhases = computed(() => props.phases && props.phases.length > 0);

const currentRegionName = computed(() => {
  if (!props.selectedRegionId) return 'Assigned Region';
  const found = props.regions.find(r => r.id === Number(props.selectedRegionId));
  return found ? found.name : `Region #${props.selectedRegionId}`;
});

function selectScope(mode, regionId) {
  emit('change-scope', { mode, regionId });
}

function onPhaseChange(value) {
  const val = value ? Number(value) : null;
  emit('change-phase', val);
}
</script>
