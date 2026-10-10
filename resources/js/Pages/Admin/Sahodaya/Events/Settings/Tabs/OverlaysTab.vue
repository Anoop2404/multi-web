<template>
    <div class="card max-w-2xl space-y-5">
        <div>
            <h3 class="section-title">Public Display Overlays</h3>
            <p class="section-desc">
                Control which content appears on the public-facing event results page.
                Enable or disable the Individual Championship section and filter which
                class categories are included in the rankings.
            </p>
        </div>

        <form @submit.prevent="saveOverlays" class="space-y-5">
            <label class="flex items-start gap-3 text-sm text-slate-700">
                <input type="checkbox" v-model="overlaysForm.show_individual_championship" class="mt-0.5 shrink-0">
                <span>
                    <span class="font-medium text-slate-900">Show Individual Championship on public page</span>
                    <span class="block text-xs text-slate-500 mt-0.5">
                        When enabled, a championship ranking table is displayed on the public
                        results page. When disabled, the section is hidden regardless of category filters.
                    </span>
                </span>
            </label>

            <div v-if="overlaysForm.show_individual_championship" class="border-t border-slate-100 pt-4 space-y-3">
                <fieldset>
                    <legend class="text-sm font-medium text-slate-700 mb-2">
                        Categories to include
                    </legend>
                    <p class="text-xs text-slate-500 mb-3">
                        Leave all unchecked to show every category. Select specific categories to limit
                        the championship rankings to those groups only.
                    </p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <label v-for="cat in categoryOptions" :key="cat.value"
                               class="flex items-center gap-2 text-sm text-slate-700 rounded-lg border border-slate-200 px-3 py-2 cursor-pointer hover:bg-slate-50 transition"
                               :class="{ 'bg-indigo-50 border-indigo-300': overlaysForm.category_filter?.includes(cat.value) }">
                            <input type="checkbox" :value="cat.value" v-model="overlaysForm.category_filter" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span>{{ cat.label }}</span>
                        </label>
                    </div>
                </fieldset>
            </div>

            <div v-if="successMessage" class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-2.5 text-sm text-emerald-800">
                {{ successMessage }}
            </div>
            <div v-if="overlaysForm.errors?.general" class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-2.5 text-sm text-rose-800">
                {{ overlaysForm.errors.general }}
            </div>

            <button type="submit" class="btn-primary" :disabled="overlaysForm.processing">
                {{ overlaysForm.processing ? 'Saving…' : 'Save display settings' }}
            </button>
        </form>
    </div>
</template>

<script setup>
import { ref, inject, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';

const { sahodaya, event } = inject('eventSettings');

const overlaysForm = useForm({
    show_individual_championship: true,
    category_filter: [],
});

const successMessage = ref('');
const loading = ref(true);

const categoryOptions = [
    { value: 'lp', label: 'LP' },
    { value: 'up', label: 'UP' },
    { value: 'hs', label: 'HS' },
    { value: 'hss', label: 'HSS' },
    { value: 'open', label: 'Open' },
];

async function fetchConfig() {
    loading.value = true;
    try {
        const response = await fetch(
            `/sahodaya-admin/${sahodaya.value.id}/events/${event.value.id}/settings/public-overlays`
        );
        if (response.ok) {
            const data = await response.json();
            overlaysForm.show_individual_championship = data.show_individual_championship ?? true;
            overlaysForm.category_filter = data.category_filter ?? [];
        }
    } catch (e) {
        // keep defaults on fetch failure
    } finally {
        loading.value = false;
    }
}

function saveOverlays() {
    successMessage.value = '';
    overlaysForm.post(
        `/sahodaya-admin/${sahodaya.value.id}/events/${event.value.id}/settings/public-overlays`,
        {
            preserveScroll: true,
            onSuccess: () => {
                successMessage.value = 'Display settings saved.';
            },
        }
    );
}

onMounted(() => {
    fetchConfig();
});
</script>
