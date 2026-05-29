<template>
    <AdminLayout title="Footer Builder">
        <div class="max-w-3xl space-y-6">
            <!-- Tenant selector -->
            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 flex items-center gap-4">
                <label class="text-sm font-semibold text-gray-600 shrink-0">Tenant:</label>
                <select v-model="selectedTenantId" @change="loadFooter"
                        class="border border-gray-200 rounded-lg px-3 py-2 text-sm flex-1 focus:outline-none focus:ring-2">
                    <option value="">— Select tenant —</option>
                    <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </div>

            <div v-if="selectedTenantId" class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 space-y-6">
                <!-- Layout variant -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Footer Style</label>
                    <div class="flex gap-3 flex-wrap">
                        <label v-for="style in footerStyles" :key="style.value"
                               class="flex items-center gap-2 px-4 py-2.5 rounded-lg border-2 cursor-pointer transition"
                               :class="footerConfig.style === style.value
                                   ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                   : 'border-gray-200 hover:border-gray-300 text-gray-600'">
                            <input type="radio" v-model="footerConfig.style" :value="style.value" class="sr-only">
                            <span class="text-sm font-medium">{{ style.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Contact info -->
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Tagline</label>
                        <textarea v-model="footerConfig.tagline" rows="2"
                                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 resize-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Copyright Text</label>
                        <input v-model="footerConfig.copyright" type="text"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Address</label>
                        <textarea v-model="footerConfig.address" rows="2"
                                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 resize-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Phone</label>
                        <input v-model="footerConfig.phone" type="text"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2">
                        <label class="block text-xs font-semibold text-gray-600 mb-1 mt-2">Email</label>
                        <input v-model="footerConfig.email" type="email"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2">
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-sm font-semibold text-gray-700">Quick Links</label>
                        <button @click="addQuickLink"
                                class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition">
                            + Add Link
                        </button>
                    </div>
                    <div class="space-y-2">
                        <div v-for="(link, idx) in footerConfig.quick_links" :key="idx"
                             class="flex items-center gap-2 bg-gray-50 rounded-lg p-3">
                            <input v-model="link.label" placeholder="Label"
                                   class="flex-1 border border-gray-200 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1">
                            <input v-model="link.url" placeholder="/url"
                                   class="flex-1 border border-gray-200 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 font-mono">
                            <button @click="removeQuickLink(idx)" class="text-red-400 hover:text-red-600 transition text-lg">&times;</button>
                        </div>
                    </div>
                </div>

                <!-- Sahodaya Link -->
                <div class="border-t border-gray-100 pt-4">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Sahodaya Link (optional)</label>
                    <div class="flex gap-2">
                        <input v-model="footerConfig.sahodaya_link.label" placeholder="Label"
                               class="flex-1 border border-gray-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1">
                        <input v-model="footerConfig.sahodaya_link.url" placeholder="URL"
                               class="flex-1 border border-gray-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 font-mono">
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                    <button @click="saveFooter"
                            class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg font-semibold text-sm hover:bg-indigo-700 transition">
                        Save Footer
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

const footerConfig = reactive({
    style: 'two-column-logo',
    tagline: '',
    copyright: '',
    address: '',
    phone: '',
    email: '',
    quick_links: [],
    social_links: {},
    sahodaya_link: { label: '', url: '' },
});

const footerStyles = [
    { value: 'two-column-logo',   label: 'Two Column + Logo' },
    { value: 'minimal-single-row', label: 'Minimal Single Row' },
    { value: 'dark',              label: 'Dark Footer' },
    { value: 'light',             label: 'Light Footer' },
];

async function loadFooter() {
    if (!selectedTenantId.value) return;
    const res = await fetch(`/admin/api/tenants/${selectedTenantId.value}/footer`, {
        headers: { 'Accept': 'application/json' },
    });
    const data = await res.json();
    Object.assign(footerConfig, {
        style: data.style ?? 'two-column-logo',
        tagline: data.tagline ?? '',
        copyright: data.copyright ?? '',
        address: data.address ?? '',
        phone: data.phone ?? '',
        email: data.email ?? '',
        quick_links: data.quick_links ?? [],
        social_links: data.social_links ?? {},
        sahodaya_link: data.sahodaya_link ?? { label: '', url: '' },
    });
}

function addQuickLink() {
    footerConfig.quick_links.push({ label: '', url: '' });
}

function removeQuickLink(idx) {
    footerConfig.quick_links.splice(idx, 1);
}

async function saveFooter() {
    await fetch(`/admin/api/tenants/${selectedTenantId.value}/footer`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
        },
        body: JSON.stringify({ ...footerConfig }),
    });
    saved.value = true;
    setTimeout(() => { saved.value = false; }, 3000);
}
</script>