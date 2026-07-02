<template>
    <SchoolAdminLayout title="Notifications" :school="school" :show-header-title="false">
        <PageHeader title="Notifications" eyebrow="Programs"
            description="Fest programs, exams, training, and Sahodaya circulars." />


        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">{{ unreadCount }} unread notification{{ unreadCount !== 1 ? 's' : '' }}</p>
                <button v-if="unreadCount > 0" @click="markAllRead"
                        class="px-3 py-1.5 text-xs font-semibold border border-gray-300 rounded-lg hover:bg-gray-50">
                    Mark all as read
                </button>
            </div>

            <div v-if="!notifications.length" class="text-center py-16 text-gray-400">
                <p class="text-4xl mb-3">🔔</p>
                <p>No notifications yet.</p>
            </div>

            <div v-for="n in notifications" :key="n.id"
                 class="card flex gap-3 transition"
                 :class="!n.read_at ? 'border-indigo-200 bg-indigo-50/30' : ''">
                <div class="w-2 h-2 rounded-full mt-2 shrink-0"
                     :class="!n.read_at ? 'bg-indigo-500' : 'bg-gray-200'"></div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900">{{ n.title }}</p>
                    <p class="text-sm text-gray-600 mt-0.5">{{ n.body }}</p>
                    <div class="flex items-center gap-3 mt-1.5">
                        <span class="text-xs text-gray-400">{{ formatDate(n.created_at) }}</span>
                        <a v-if="n.action_url" :href="n.action_url"
                           class="text-xs text-indigo-600 font-semibold hover:underline">
                            View →
                        </a>
                    </div>
                </div>
                <button v-if="!n.read_at" @click="markRead(n)"
                        class="text-xs text-gray-400 hover:text-indigo-600 shrink-0 self-start pt-1">
                    Mark read
                </button>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({
    school: Object,
    notifications: Array,
});

const unreadCount = computed(() => props.notifications.filter(n => !n.read_at).length);

function formatDate(dt) {
    if (!dt) return '';
    const d = new Date(dt);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function markRead(notification) {
    router.post(`/school-admin/${props.school.id}/notifications/mark-read`,
        { notification_id: notification.id },
        { preserveScroll: true }
    );
}

function markAllRead() {
    router.post(`/school-admin/${props.school.id}/notifications/mark-read`,
        { all: true },
        { preserveScroll: true }
    );
}
</script>
