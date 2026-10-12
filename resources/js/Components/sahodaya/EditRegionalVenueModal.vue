<template>
    <Modal :show="show || modelValue" @close="closeModal" size="lg">
        <div class="p-6">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm">📍</span>
                    <h3 class="text-base font-bold text-slate-900">
                        {{ isNew ? 'Add Regional Venue' : `Edit Venue & Details — ${form.cluster_label || form.name || 'Region'}` }}
                    </h3>
                </div>
                <button type="button" @click="closeModal" class="text-slate-400 hover:text-slate-600 text-lg leading-none">✕</button>
            </div>

            <form @submit.prevent="submit" class="space-y-4 text-xs">
                <!-- If adding new and regions are available, offer selection -->
                <div v-if="isNew && availableRegions?.length">
                    <label class="block font-semibold text-slate-700 mb-1">Select Sahodaya Region</label>
                    <SearchableSelect v-model="form.region_id"
                                      :options="regionSelectOptions"
                                      :all-option="false"
                                      placeholder="Choose an existing region..."
                                      @update:modelValue="onRegionSelected" />
                    <p class="text-[11px] text-slate-400 mt-1">Or leave blank to create a custom independent region below.</p>
                </div>

                <!-- Region Name / Display Label -->
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Region / Zone Name *</label>
                    <input v-model="form.cluster_label"
                           type="text"
                           class="field w-full text-xs"
                           placeholder="e.g. Manjeri, Tirur, Nilambur Zone"
                           required />
                </div>

                <!-- Venue Name -->
                <div>
                    <label class="block font-semibold text-slate-700 mb-1 flex items-center justify-between">
                        <span>Venue / Ground Name *</span>
                        <span class="text-[11px] font-normal text-slate-400">Host school or ground</span>
                    </label>
                    <input v-model="form.venue"
                           type="text"
                           list="existing-venues-list"
                           class="field w-full text-xs"
                           placeholder="e.g. NOBLE PUBLIC SCHOOL, MANJERI"
                           required />
                    <datalist id="existing-venues-list">
                        <option v-for="v in venueSuggestions" :key="v" :value="v" />
                    </datalist>
                </div>

                <!-- Competition Dates -->
                <div class="grid grid-cols-2 gap-3 bg-slate-50 p-3 rounded-xl border border-slate-100">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Event Start Date</label>
                        <input v-model="form.event_start" type="date" class="field w-full text-xs bg-white" />
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Event End Date</label>
                        <input v-model="form.event_end" type="date" class="field w-full text-xs bg-white" />
                    </div>
                </div>

                <!-- Optional Registration Window & Status -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Registration Opens</label>
                        <input v-model="form.registration_open" type="date" class="field w-full text-xs" />
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Registration Closes</label>
                        <input v-model="form.registration_close" type="date" class="field w-full text-xs" />
                    </div>
                </div>

                <!-- Partition Status -->
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Region Status</label>
                    <SearchableSelect v-model="form.status"
                                      :options="statusOptions"
                                      :all-option="false" />
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <div>
                        <button v-if="!isNew && form.partition_id" type="button" @click="deletePartition"
                                class="text-rose-600 hover:text-rose-800 text-xs font-semibold flex items-center gap-1 hover:bg-rose-50 px-2.5 py-1.5 rounded-lg transition"
                                :disabled="saving || deleting">
                            <span>🗑️</span>
                            <span>{{ deleting ? 'Deleting…' : 'Delete Region Partition' }}</span>
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn-ghost text-xs !py-2" @click="closeModal" :disabled="saving || deleting">
                            Cancel
                        </button>
                        <button type="submit" class="btn-primary text-xs !py-2 font-semibold" :disabled="saving || deleting">
                            <span>{{ saving ? 'Saving…' : (isNew ? 'Create Regional Venue' : 'Save Changes') }}</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/ui/Modal.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    show: { type: Boolean, default: false },
    modelValue: { type: Boolean, default: false },
    sahodayaId: { type: [String, Number], default: null },
    tenantId: { type: [String, Number], default: null },
    eventId: { type: [String, Number], required: true },
    parentEvent: { type: Object, default: () => ({}) },
    regionData: { type: Object, default: () => ({}) },
    isNew: { type: Boolean, default: false },
    venuesList: { type: Array, default: () => [] },
    existingVenues: { type: Array, default: () => [] },
    availableRegions: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'close', 'saved']);

const effectiveTenantId = computed(() => props.tenantId || props.sahodayaId);

const saving = ref(false);

const form = ref({
    partition_id: null,
    region_id: null,
    cluster_label: '',
    name: '',
    title: '',
    venue: '',
    event_start: '',
    event_end: '',
    registration_open: '',
    registration_close: '',
    status: 'published',
});

const statusOptions = [
    { value: 'published', label: 'Published (Upcoming)' },
    { value: 'registration_open', label: 'Registration Open' },
    { value: 'ongoing', label: 'Ongoing' },
    { value: 'completed', label: 'Completed' },
    { value: 'draft', label: 'Draft' },
];

const venueSuggestions = computed(() => {
    const list = props.venuesList?.length ? props.venuesList : (props.existingVenues || []);
    const names = list.map(v => typeof v === 'string' ? v : v.name).filter(Boolean);
    return [...new Set(names)];
});

const regionSelectOptions = computed(() => {
    return (props.availableRegions || []).map(r => ({
        value: r.id,
        label: r.name,
    }));
});

watch(() => props.show || props.modelValue, (isOpen) => {
    if (isOpen) {
        initForm();
    }
}, { immediate: true });

function initForm() {
    const d = props.regionData || {};
    const parent = props.parentEvent || {};
    form.value = {
        partition_id: d.partition_id || d.id || null,
        region_id: d.region_id || null,
        cluster_label: d.cluster_label || d.name || d.label || '',
        name: d.name || '',
        title: d.title || '',
        venue: d.venue || '',
        event_start: (d.event_start || (props.isNew ? parent.event_start : null))?.slice(0, 10) || '',
        event_end: (d.event_end || (props.isNew ? parent.event_end : null))?.slice(0, 10) || '',
        registration_open: (d.registration_open || (props.isNew ? parent.registration_open : null))?.slice(0, 10) || '',
        registration_close: (d.registration_close || (props.isNew ? parent.registration_close : null))?.slice(0, 10) || '',
        status: d.status || parent.status || 'registration_open',
    };
}

function onRegionSelected(regionId) {
    const reg = (props.availableRegions || []).find(r => r.id === regionId);
    if (reg) {
        if (!form.value.cluster_label) {
            form.value.cluster_label = reg.name;
        }
        if (reg.venue && !form.value.venue) {
            form.value.venue = reg.venue;
        }
    }
}

function closeModal() {
    emit('update:modelValue', false);
    emit('close');
}

function submit() {
    if (saving.value) return;
    saving.value = true;

    router.post(
        `/sahodaya-admin/${effectiveTenantId.value}/events/${props.eventId}/save-regional-venue`,
        {
            partition_id: form.value.partition_id,
            region_id: form.value.region_id,
            cluster_label: form.value.cluster_label,
            venue: form.value.venue,
            event_start: form.value.event_start || null,
            event_end: form.value.event_end || null,
            registration_open: form.value.registration_open || null,
            registration_close: form.value.registration_close || null,
            status: form.value.status,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                saving.value = false;
                emit('saved');
                closeModal();
            },
            onError: () => {
                saving.value = false;
            },
            onFinish: () => {
                saving.value = false;
            },
        }
    );
}

const { confirm } = useConfirm();
const deleting = ref(false);

async function deletePartition() {
    if (!form.value.partition_id) return;
    if (!await confirm({
        title: 'Delete Regional Partition',
        message: `Are you sure you want to delete the regional partition "${form.value.cluster_label || 'this partition'}"? This will remove it from the event.`,
        destructive: true,
    })) {
        return;
    }

    deleting.value = true;
    router.delete(
        `/sahodaya-admin/${effectiveTenantId.value}/events/${props.eventId}/partitions/${form.value.partition_id}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                deleting.value = false;
                emit('saved');
                closeModal();
            },
            onError: () => {
                deleting.value = false;
            },
            onFinish: () => {
                deleting.value = false;
            },
        }
    );
}
</script>
