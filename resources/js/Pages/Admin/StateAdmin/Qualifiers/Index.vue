<template>
    <AdminLayout title="State Qualifier Intakes">
        <div class="max-w-5xl mx-auto space-y-4">
            <h1 class="text-xl font-semibold">Qualifier intakes</h1>
            <div v-for="intake in intakes.data" :key="intake.id" class="card flex justify-between items-center">
                <div>
                    <p class="font-medium">{{ intake.source_tenant_id }} → program {{ intake.state_program_id }}</p>
                    <p class="text-sm text-slate-500">{{ intake.entries_count }} entries · {{ intake.status }}</p>
                </div>
                <Link :href="`/admin/state-workspace/qualifiers/${intake.id}`" class="link-brand text-sm">Review</Link>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ intakes: Object });
</script>
