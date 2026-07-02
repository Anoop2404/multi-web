<template>
    <AdminLayout title="Skin Presets">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-800">Skin Presets</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Pre-built color themes that schools can apply with one click.</p>
                </div>
            </div>

            <!-- Presets grid -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <div v-for="preset in presets" :key="preset.id"
                     class="card card--flush">
                    <!-- Color preview bar -->
                    <div class="h-3 flex">
                        <div class="flex-1" :style="{ background: preset.theme?.primary }"></div>
                        <div class="w-12" :style="{ background: preset.theme?.accent }"></div>
                    </div>

                    <div class="p-5">
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <div>
                                <h4 class="font-bold text-gray-800">{{ preset.name }}</h4>
                                <p class="text-xs text-gray-400 mt-0.5">{{ preset.description }}</p>
                            </div>
                            <span :class="preset.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-400'"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium shrink-0">
                                {{ preset.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <!-- Theme details -->
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 rounded-full border-2 border-white shadow"
                                 :style="{ background: preset.theme?.primary }"></div>
                            <div>
                                <p class="text-xs text-gray-500">Primary: <code class="font-mono">{{ preset.theme?.primary }}</code></p>
                                <p class="text-xs text-gray-400">{{ preset.theme?.font_heading }} / {{ preset.theme?.font_body }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 text-xs text-gray-400">
                            <span class="font-mono bg-gray-50 px-2 py-0.5 rounded">{{ preset.slug }}</span>
                            <span>Order: {{ preset.display_order }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="!presets.length"
                     class="sm:col-span-2 lg:col-span-3 bg-white rounded-xl border border-dashed border-gray-200 p-12 text-center">
                    <p class="text-gray-400 mb-3">No skin presets seeded yet.</p>
                    <p class="text-xs text-gray-300">Run <code class="bg-gray-50 px-2 py-0.5 rounded font-mono">php artisan db:seed --class=SkinPresetsSeeder</code></p>
                </div>
            </div>

            <!-- Seed hint -->
            <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-sm text-amber-700">
                <strong>Note:</strong> Skin presets are managed via
                <code class="font-mono bg-amber-100 px-1 rounded">SkinPresetsSeeder</code>.
                To add or update presets, edit the seeder and run
                <code class="font-mono bg-amber-100 px-1 rounded">php artisan db:seed --class=SkinPresetsSeeder</code>.
                Schools select a preset via the Theme builder in their site settings.
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    presets: { type: Array, default: () => [] },
});
</script>
