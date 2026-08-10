<template>
    <AdminLayout title="State Fest Workspace">
        <div class="max-w-5xl mx-auto space-y-4">
            <h1 class="text-xl font-semibold">State championship events</h1>
            <ul class="space-y-2">
                <li v-for="e in events.data" :key="e.id">
                    <Link :href="`/admin/state-workspace/fest/${e.id}`" class="link-brand">{{ e.name }}</Link>
                    <span class="text-xs text-slate-400 ml-2">{{ e.status }}</span>
                </li>
            </ul>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ events: Object });
</script>
