<template>
    <div class="min-h-screen bg-gray-50 p-6">
        <div class="max-w-3xl mx-auto space-y-6">
            <h1 class="text-xl font-bold">Global CBSE Class Categories</h1>
            <form @submit.prevent="add" class="flex gap-2 bg-white border rounded-xl p-4">
                <input v-model="form.code" required placeholder="Code" class="border rounded px-3 py-2 text-sm w-24">
                <input v-model="form.label" required placeholder="Label" class="border rounded px-3 py-2 text-sm flex-1">
                <button class="bg-gray-800 text-white px-4 py-2 rounded text-sm">Add</button>
            </form>
            <div v-for="c in categories" :key="c.id" class="bg-white border rounded-xl p-4 flex justify-between text-sm">
                <span>{{ c.code }} — {{ c.label }} ({{ c.min_class }}-{{ c.max_class }})</span>
                <span :class="c.is_active ? 'text-green-600' : 'text-gray-400'">{{ c.is_active ? 'Active' : 'Inactive' }}</span>
            </div>
            <Link href="/admin/dashboard" class="text-sm text-gray-500">← Dashboard</Link>
        </div>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
defineProps({ categories: Array });
const form = useForm({ code: '', label: '', min_class: null, max_class: null, sort_order: 0 });
function add() { form.post('/admin/master-data/class-categories', { onSuccess: () => form.reset() }); }
</script>
