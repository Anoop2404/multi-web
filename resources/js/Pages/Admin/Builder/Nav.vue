<template>
    <AdminLayout title="Navigation Builder">
        <div class="max-w-3xl space-y-6">
            <!-- Tenant selector -->
            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 flex items-center gap-4">
                <label class="text-sm font-semibold text-gray-600 shrink-0">Tenant:</label>
                <select v-model="selectedTenantId" @change="loadNav"
                        class="border border-gray-200 rounded-lg px-3 py-2 text-sm flex-1 focus:outline-none focus:ring-2">
                    <option value="">— Select tenant —</option>
                    <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </div>

            <div v-if="selectedTenantId" class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 space-y-6">
                <!-- Layout variant -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Navbar Style</label>
                    <div class="flex gap-3">
                        <label v-for="style in navbarStyles" :key="style.value"
                               class="flex items-center gap-2 px-4 py-2.5 rounded-lg border-2 cursor-pointer transition"
                               :class="navConfig.style === style.value
                                   ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                   : 'border-gray-200 hover:border-gray-300 text-gray-600'">
                            <input type="radio" v-model="navConfig.style" :value="style.value" class="sr-only">
                            <span class="text-sm font-medium">{{ style.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Nav items -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-sm font-semibold text-gray-700">Navigation Items</label>
                        <button @click="addItem"
                                class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition">
                            + Add Item
                        </button>
                    </div>

                    <div class="space-y-2">
                        <div v-for="(item, idx) in navConfig.items" :key="idx"
                             class="flex items-center gap-3 bg-gray-50 rounded-lg p-3">
                            <span class="text-gray-300 cursor-grab">⠿</span>
                            <input v-model="item.label" placeholder="Label"
                                   class="flex-1 border border-gray-200 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1">
                            <input v-model="item.url" placeholder="/url"
                                   class="flex-1 border border-gray-200 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 font-mono">
                            <label class="flex items-center gap-1.5 text-xs text-gray-500">
                                <input type="checkbox" v-model="item.external" class="rounded">
                                External
                            </label>
                            <button @click="removeItem(idx)" class="text-red-400 hover:text-red-600 transition text-lg">&times;</button>
                        </div>
                    </div>

                    <p v-if="!navConfig.items?.length" class="text-sm text-gray-400 text-center py-4">
                        No items yet. Add navigation links above.
                    </p>
                </div>

                <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                    <button @click="saveNav"
                            class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg font-semibold text-sm hover:bg-indigo-700 transition">
                        Save Navigation
                    </button>
                    <span v-if="saved" class="text-sm text-green-600 font-medium">✓ Saved!</span>
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
});

const selectedTenantId = ref('');
const saved = ref(false);

    const navConfig = reactive({
        style: 'logo-left',
        items: [],
    });

    const navbarStyles = [
        { value: 'logo-left',        label: 'Logo Left' },
        { value: 'logo-center',      label: 'Logo Center' },
        { value: 'centered-below',   label: 'Centered Below' },
        { value: 'sticky-transparent', label: 'Sticky Transparent' },
        { value: 'dark',             label: 'Dark' },
    ];

    async function loadNav() {
        if (!selectedTenantId.value) return;
        const res = await fetch(`/admin/api/tenants/${selectedTenantId.value}/nav`, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        navConfig.style = data.layout_variant ?? data.style ?? 'logo-left';
        navConfig.items = data.items ?? [];
    }

    function addItem() {
        navConfig.items.push({ label: '', url: '/', external: false, children: [] });
    }

    function removeItem(idx) {
        navConfig.items.splice(idx, 1);
    }

    async function saveNav() {
        await fetch(`/admin/api/tenants/${selectedTenantId.value}/nav`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
            },
            body: JSON.stringify({ ...navConfig }),
        });
        saved.value = true;
        setTimeout(() => { saved.value = false; }, 3000);
    }
</script>
