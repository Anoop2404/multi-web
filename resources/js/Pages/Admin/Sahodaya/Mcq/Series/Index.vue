<template>
    <SahodayaAdminLayout title="Talent Search series" :sahodaya="sahodaya">
        <PageHeader title="Talent Search exam series" eyebrow="Talent Search"
                    description="Group multi-level exams (Level 1, 2, 3…) with promotion rules.">
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
                <h3 class="font-semibold">{{ s.title }}</h3>
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

function createSeries() {
    seriesForm.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-series`, {
        onSuccess: () => seriesForm.reset(),
    });
}
</script>
