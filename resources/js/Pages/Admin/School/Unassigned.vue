<template>
    <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
            <div class="mx-auto w-16 h-16 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center text-3xl font-bold mb-4">
                ⚠️
            </div>
            <h2 class="text-2xl font-bold text-gray-900">No Assigned Events</h2>
            <p class="mt-2 text-sm text-gray-600">
                Your account is registered as a Coordinator for {{ schoolName }}, but you haven't been assigned to any active event or program scope yet.
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-6 shadow rounded-lg sm:px-10 text-center">
                <p class="text-xs text-gray-500 mb-6">
                    Please contact your School Administrator to grant you access to specific fest programs or exam series.
                </p>
                <div class="flex justify-center gap-4">
                    <a href="/logout" 
                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                       class="w-full inline-flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-primary/90 focus:outline-none">
                        Sign Out
                    </a>
                    <form id="logout-form" action="/logout" method="POST" class="hidden">
                        <input type="hidden" name="_token" :value="csrfToken">
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

defineProps({
    schoolName: {
        type: String,
        default: 'your school'
    }
});

const csrfToken = computed(() => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
});
</script>
