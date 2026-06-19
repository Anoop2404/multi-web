<template>
    <AdminLayout title="Theme & Skin">
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Skin preset picker -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Tenant selector -->
                <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 flex items-center gap-4">
                    <label class="text-sm font-semibold text-gray-600 shrink-0">Tenant:</label>
                    <select v-model="selectedTenantId" @change="loadTheme"
                            class="border border-gray-200 rounded-lg px-3 py-2 text-sm flex-1 focus:outline-none focus:ring-2">
                        <option value="">— Select tenant —</option>
                        <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>

                <!-- Presets -->
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                    <h3 class="font-bold text-gray-900 mb-4">Skin Presets</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <button v-for="preset in presets" :key="preset.id"
                                @click="applyPreset(preset)"
                                class="text-left p-3 rounded-xl border-2 transition"
                                :class="activePreset === preset.slug
                                    ? 'border-indigo-500 bg-indigo-50'
                                    : 'border-gray-100 hover:border-gray-300'">
                            <!-- Color swatches -->
                            <div class="flex gap-1.5 mb-2">
                                <div class="w-6 h-6 rounded-full border border-black/10"
                                     :style="{ backgroundColor: preset.theme?.primary ?? '#6366f1' }"></div>
                                <div class="w-6 h-6 rounded-full border border-black/10"
                                     :style="{ backgroundColor: preset.theme?.secondary ?? '#f59e0b' }"></div>
                            </div>
                            <p class="text-sm font-semibold text-gray-800">{{ preset.name }}</p>
                            <p class="text-xs text-gray-400">{{ preset.theme?.font_heading ?? 'System font' }}</p>
                        </button>
                    </div>
                </div>

                <!-- Custom overrides -->
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                    <h3 class="font-bold text-gray-900 mb-4">Custom Theme</h3>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div v-for="field in themeFields" :key="field.key">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">{{ field.label }}</label>
                            <div v-if="field.type === 'color'" class="flex items-center gap-2">
                                <input type="color" v-model="theme[field.key]"
                                       class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer p-0.5">
                                <input type="text" v-model="theme[field.key]"
                                       class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2">
                            </div>
                            <select v-else-if="field.type === 'select'" v-model="theme[field.key]"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 bg-white">
                                <option v-for="opt in field.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <input v-else type="text" v-model="theme[field.key]"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2">
                        </div>
                    </div>

                    <button @click="saveTheme" :disabled="!selectedTenantId"
                            class="mt-6 bg-indigo-600 text-white px-6 py-2.5 rounded-lg font-semibold text-sm hover:bg-indigo-700 transition disabled:opacity-50">
                        Save Theme
                    </button>
                    <span v-if="saved" class="ml-3 text-sm text-green-600 font-medium">✓ Saved!</span>
                </div>
            </div>

            <!-- Live preview -->
            <div class="space-y-4">
                <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 sticky top-4">
                    <h3 class="font-bold text-gray-900 mb-3 text-sm">Preview</h3>
                    <div class="rounded-xl overflow-hidden border border-gray-100 space-y-3">
                        <!-- Primary color bar -->
                        <div class="h-16 flex items-center justify-center text-white font-bold text-sm"
                             :style="{ backgroundColor: theme.primary || '#6366f1' }">
                            Primary Color
                        </div>
                        <!-- Secondary -->
                        <div class="h-8 flex items-center justify-center text-white text-xs font-semibold"
                             :style="{ backgroundColor: theme.secondary || '#f59e0b' }">
                            Secondary
                        </div>
                        <!-- Typography -->
                        <div class="p-4">
                            <p class="text-lg font-bold mb-1"
                               :style="{ fontFamily: theme.font_heading || 'inherit' }">
                                Heading Font
                            </p>
                            <p class="text-sm text-gray-600"
                               :style="{ fontFamily: theme.font_body || 'inherit' }">
                                Body text appears in this font. Clear, readable, professional.
                            </p>
                            <div class="mt-3 inline-flex items-center gap-2">
                                <button class="px-3 py-1.5 rounded-full text-xs font-semibold text-white"
                                        :style="{ backgroundColor: theme.primary || '#6366f1',
                                                  borderRadius: theme.border_radius === 'none' ? '0' : theme.border_radius === 'sm' ? '4px' : theme.border_radius === 'lg' ? '12px' : theme.border_radius === 'full' ? '9999px' : '8px' }">
                                    Button
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    tenants: { type: Array, default: () => [] },
    presets: { type: Array, default: () => [] },
});

const selectedTenantId = ref('');
const activePreset = ref('');
const saved = ref(false);

const theme = reactive({
    primary: '#4f46e5',
    secondary: '#f59e0b',
    accent: '#10b981',
    font_heading: 'Poppins, sans-serif',
    font_body: 'Inter, sans-serif',
    border_radius: 'md',
    navbar_style: 'logo-left',
    footer_style: 'three-column',
});

const themeFields = [
    { key: 'primary',      label: 'Primary Color',   type: 'color' },
    { key: 'secondary',    label: 'Secondary Color',  type: 'color' },
    { key: 'accent',       label: 'Accent Color',     type: 'color' },
    { key: 'font_heading', label: 'Heading Font',     type: 'text' },
    { key: 'font_body',    label: 'Body Font',        type: 'text' },
    { key: 'border_radius',label: 'Border Radius',    type: 'select', options: [
        { value: 'none', label: 'Sharp (0px)' },
        { value: 'sm',   label: 'Small (4px)' },
        { value: 'md',   label: 'Medium (8px)' },
        { value: 'lg',   label: 'Large (12px)' },
        { value: 'full', label: 'Pill (full)' },
    ]},
    { key: 'navbar_style', label: 'Navbar Style', type: 'select', options: [
        { value: 'logo-left',          label: 'Logo Left' },
        { value: 'logo-center',        label: 'Logo Center' },
        { value: 'centered-below',     label: 'Centered Below' },
        { value: 'sticky-transparent', label: 'Sticky Transparent' },
        { value: 'dark',               label: 'Dark Navbar' },
    ]},
    { key: 'footer_style', label: 'Footer Style', type: 'select', options: [
        { value: 'two-column-logo',    label: 'Two Column + Logo' },
        { value: 'three-column',       label: 'Three Column' },
        { value: 'four-column',        label: 'Four Column' },
        { value: 'minimal',            label: 'Minimal' },
        { value: 'minimal-single-row', label: 'Minimal Single Row' },
    ]},
];

    async function loadTheme() {
        if (!selectedTenantId.value) return;
        const res = await fetch(`/admin/api/tenants/${selectedTenantId.value}/theme`, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        Object.assign(theme, data ?? {});
        activePreset.value = data.preset_slug ?? '';
    }

    function applyPreset(preset) {
        activePreset.value = preset.slug;
        Object.assign(theme, preset.theme ?? {});
    }

    async function saveTheme() {
        if (!selectedTenantId.value) return;
        saved.value = false;
        await fetch(`/admin/api/tenants/${selectedTenantId.value}/theme`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
            },
            body: JSON.stringify({ ...theme, preset_slug: activePreset.value }),
        });
        saved.value = true;
        setTimeout(() => { saved.value = false; }, 3000);
    }
</script>
