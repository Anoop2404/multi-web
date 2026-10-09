<template>
    <AdminLayout title="Live public visitors">
        <div class="card space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><p class="text-sm text-slate-600">Public visitors</p><p class="text-4xl font-bold">{{ monitor.active_visitors }}</p></div>
                <div><p class="text-sm text-slate-600">TV screens</p><p class="text-4xl font-bold">{{ monitor.active_tv_screens ?? 0 }}</p></div>
            </div>
            <p class="text-sm text-slate-600">Overall active unique browser visitors across public website pages in the last five minutes. TV screens are excluded. Refreshes every 10 seconds.</p>
            <p class="text-xs text-slate-500">Updated {{ monitor.updated_at }}</p>
            <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>

        </div>
    </AdminLayout>
</template>
<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ monitor: Object });
const monitor = ref(props.monitor);
const error = ref('');
let timer;
let controller;
async function refresh() {
    if (document.visibilityState !== 'visible') return;
    controller = new AbortController();
    try {
        const response = await fetch('/admin/public-visitors/data', { headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal });
        if (!response.ok) throw new Error('Could not refresh visitor counts.');
        monitor.value = await response.json();
        error.value = '';
    } catch (e) { if (e.name !== 'AbortError') error.value = 'Could not refresh visitor counts; showing the last successful count.'; }
}
onMounted(() => { timer = setInterval(refresh, 10000); });
onBeforeUnmount(() => { clearInterval(timer); controller?.abort(); });
</script>
