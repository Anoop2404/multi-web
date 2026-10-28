<template>
    <SahodayaAdminLayout title="Exam series" :sahodaya="sahodaya">
        <PageHeader title="Exam series" eyebrow="Multi-level exams"
                    description="Group Level 1, 2, 3… exams with promotion rules. Works for Talent Search and model exams.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams`" class="btn-secondary text-sm">All exams</Link>
            </template>
        </PageHeader>

        <form class="card max-w-xl space-y-3 mb-6" @submit.prevent="createSeries">
            <h2 class="font-semibold">New series</h2>
            <input v-model="seriesForm.title" class="field" placeholder="Series title" required>
            <textarea v-model="seriesForm.description" class="field" rows="2" placeholder="Description"></textarea>
            <button type="submit" class="btn-primary" :disabled="seriesForm.processing">Create series</button>
        </form>

        <div class="grid md:grid-cols-2 gap-4">
            <Link v-for="s in series" :key="s.id"
                  :href="`/sahodaya-admin/${sahodaya.id}/mcq-series/${s.id}`"
                  class="card hover:border-indigo-200">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="font-semibold">{{ s.title }}</h3>
                    <span v-if="seriesModeBadge(s)"
                          class="shrink-0 px-2 py-0.5 rounded-full text-[11px] font-semibold"
                          :class="seriesModeBadge(s).className">
                        {{ seriesModeBadge(s).label }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">{{ s.exams?.length || 0 }} level(s) · {{ s.status }}</p>
            </Link>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({ sahodaya: Object, series: Array });

const seriesForm = useForm({ title: '', description: '' });

function seriesModeBadge(s) {
    const exams = s.exams ?? [];
    if (!exams.length) return null;
    const hasModel = exams.some((e) => e.is_model_exam || e.product_mode === 'sahodaya_model_exam' || e.product_mode === 'school_model_exam');
    const hasTalent = exams.some((e) => !(e.is_model_exam || e.product_mode === 'sahodaya_model_exam' || e.product_mode === 'school_model_exam'));
    if (hasModel && hasTalent) {
        return { label: 'Mixed modes', className: 'bg-slate-100 text-slate-700' };
    }
    if (hasModel) {
        return { label: 'Model exam', className: 'bg-sky-50 text-sky-800' };
    }
    return { label: 'Talent Search', className: 'bg-indigo-50 text-indigo-800' };
}

function createSeries() {
    seriesForm.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-series`, {
        onSuccess: () => seriesForm.reset(),
    });
}
</script>
