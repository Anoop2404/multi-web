<template>
    <AdminLayout title="Widgets Configuration">
        <div class="max-w-3xl space-y-6">
            <!-- Tenant selector -->
            <div class="card flex items-center gap-4">
                <label class="text-sm font-semibold text-gray-600 shrink-0">Tenant:</label>
                <select v-model="selectedTenantId" @change="loadWidgets"
                        class="border border-gray-200 rounded-lg px-3 py-2 text-sm flex-1 focus:outline-none focus:ring-2">
                    <option value="">— Select tenant —</option>
                    <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </div>

            <div v-if="selectedTenantId">
                <!-- WhatsApp -->
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 mb-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-gray-900">WhatsApp Button</h3>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="widgets.whatsapp_enabled" class="sr-only peer">
                            <div class="w-10 h-6 bg-gray-200 peer-checked:bg-green-500 rounded-full transition peer-focus:ring-2 peer-focus:ring-green-300 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:bg-white after:rounded-full after:transition peer-checked:after:translate-x-4"></div>
                        </label>
                    </div>
                    <div v-if="widgets.whatsapp_enabled">
                        <label class="form-label mb-1.5">WhatsApp Number (with country code)</label>
                        <input v-model="widgets.whatsapp_number" placeholder="+919876543210"
                               class="field">
                    </div>
                </div>

                <!-- Topbar -->
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 mb-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-gray-900">Top Bar (phone, email, socials)</h3>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="widgets.topbar.show" class="sr-only peer">
                            <div class="w-10 h-6 bg-gray-200 peer-checked:bg-indigo-500 rounded-full transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:bg-white after:rounded-full after:transition peer-checked:after:translate-x-4"></div>
                        </label>
                    </div>
                    <div v-if="widgets.topbar.show" class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label mb-1.5">Phone</label>
                            <input v-model="widgets.topbar.phone" placeholder="+91 484 123 4567"
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Email</label>
                            <input v-model="widgets.topbar.email" placeholder="info@school.edu.in"
                                   class="field">
                        </div>
                    </div>

                    <div v-if="widgets.topbar.show" class="mt-4">
                        <p class="text-xs font-semibold text-gray-600 mb-2">Social Links</p>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div v-for="platform in ['facebook','youtube','instagram','twitter','linkedin']" :key="platform">
                                <label class="block text-xs text-gray-500 mb-1 capitalize">{{ platform }}</label>
                                <input v-model="widgets.social_links[platform]" :placeholder="`https://www.${platform}.com/yourpage`"
                                       class="field text-xs font-mono">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admission Banner -->
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 mb-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-gray-900">Admission Alert Banner</h3>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="widgets.admission_banner.show" class="sr-only peer">
                            <div class="w-10 h-6 bg-gray-200 peer-checked:bg-amber-500 rounded-full transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:bg-white after:rounded-full after:transition peer-checked:after:translate-x-4"></div>
                        </label>
                    </div>
                    <div v-if="widgets.admission_banner.show" class="space-y-3">
                        <div>
                            <label class="form-label mb-1.5">Banner Message</label>
                            <input v-model="widgets.admission_banner.message"
                                   placeholder="Admissions open for 2025-26! Limited seats available."
                                   class="field">
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label class="form-label mb-1.5">Link Text</label>
                                <input v-model="widgets.admission_banner.link_text" placeholder="Apply Now"
                                       class="field">
                            </div>
                            <div>
                                <label class="form-label mb-1.5">Link URL</label>
                                <input v-model="widgets.admission_banner.link_url" placeholder="/admissions"
                                       class="field font-mono">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- News Ticker -->
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 mb-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900">News Ticker</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Shows latest 6 news headlines below the navbar.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="widgets.news_ticker.show" class="sr-only peer">
                            <div class="w-10 h-6 bg-gray-200 peer-checked:bg-indigo-500 rounded-full transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:bg-white after:rounded-full after:transition peer-checked:after:translate-x-4"></div>
                        </label>
                    </div>
                </div>

                <!-- CBSE Badge -->
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 mb-4">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-bold text-gray-900">CBSE Affiliation Badge</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Floating badge in the bottom-left corner.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="widgets.cbse_badge_show" class="sr-only peer">
                            <div class="w-10 h-6 bg-gray-200 peer-checked:bg-[#041525] rounded-full transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:bg-white after:rounded-full after:transition peer-checked:after:translate-x-4"></div>
                        </label>
                    </div>
                    <div v-if="widgets.cbse_badge_show">
                        <label class="form-label mb-1.5">Affiliation Number</label>
                        <input v-model="widgets.cbse_affiliation_number" placeholder="930123"
                               class="field">
                    </div>
                </div>

                <!-- Visitor Counter -->
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 mb-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900">Visitor Counter</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Shows total page views at the bottom of the site.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="widgets.visitor_counter.active" class="sr-only peer">
                            <div class="w-10 h-6 bg-gray-200 peer-checked:bg-indigo-500 rounded-full transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:bg-white after:rounded-full after:transition peer-checked:after:translate-x-4"></div>
                        </label>
                    </div>
                </div>

                <!-- Social Media Strip -->
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 mb-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900">Social Media Strip</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Icon row above the footer (uses social links from top bar settings).</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="widgets.social_strip.show" class="sr-only peer">
                            <div class="w-10 h-6 bg-gray-200 peer-checked:bg-indigo-500 rounded-full transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:bg-white after:rounded-full after:transition peer-checked:after:translate-x-4"></div>
                        </label>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button @click="saveWidgets"
                            class="btn-primary text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">
                        Save Widgets
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

const widgets = reactive({
    whatsapp_enabled: false,
    whatsapp_number: '',
    topbar: { show: true, phone: '', email: '' },
    social_links: { facebook: '', youtube: '', instagram: '', twitter: '', linkedin: '' },
    admission_banner: { show: false, message: '', link_text: '', link_url: '' },
    news_ticker: { show: false },
    cbse_badge_show: false,
    cbse_affiliation_number: '',
    visitor_counter: { active: false },
    social_strip: { show: false },
});

async function loadWidgets() {
    if (!selectedTenantId.value) return;
    const res = await fetch(`/admin/api/tenants/${selectedTenantId.value}/widgets`, {
        headers: { 'Accept': 'application/json' },
    });
    const data = await res.json();
    Object.assign(widgets, data ?? {});
    if (!widgets.topbar) widgets.topbar = { show: true, phone: '', email: '' };
    if (!widgets.social_links) widgets.social_links = {};
    if (!widgets.admission_banner) widgets.admission_banner = { show: false, message: '', link_text: '', link_url: '' };
    if (!widgets.news_ticker) widgets.news_ticker = { show: false };
    if (!widgets.visitor_counter) widgets.visitor_counter = { active: false };
    if (!widgets.social_strip) widgets.social_strip = { show: false };
}

async function saveWidgets() {
    if (!selectedTenantId.value) return;
    saved.value = false;
    await fetch(`/admin/api/tenants/${selectedTenantId.value}/widgets`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
        },
        body: JSON.stringify({ ...widgets }),
    });
    saved.value = true;
    setTimeout(() => { saved.value = false; }, 3000);
}
</script>