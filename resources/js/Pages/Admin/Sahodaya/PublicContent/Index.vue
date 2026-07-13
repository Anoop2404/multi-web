<template>
    <SahodayaAdminLayout :title="pageTitle" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount"
                         :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Website"
                    description="Edit hero text, contact details, announcements, and links shown on your public site or registration portal." />
        <div class="max-w-4xl space-y-5">
            <!-- Public website status -->
            <div v-if="websiteEnabled" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-bold text-gray-900">Public Website</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ publicSiteEnabled
                            ? 'Full marketing website is live. Disable to show only registration and login.'
                            : 'Portal mode — visitors see School Registration and Admin Login. The full CMS (site builder, circulars, etc.) is hidden until you re-enable.' }}
                    </p>
                </div>
                <label class="flex items-center gap-3 cursor-pointer shrink-0">
                    <span class="text-sm font-semibold text-gray-600">{{ publicSiteEnabled ? 'Enabled' : 'Disabled' }}</span>
                    <input type="checkbox" v-model="publicSiteEnabled" class="w-5 h-5 rounded text-purple-600">
                </label>
            </div>

            <!-- Save bar -->
            <div class="flex items-center justify-between bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-3">
                <div class="flex border-b-0 gap-1 flex-wrap">
                    <button v-for="t in tabs" :key="t.key"
                            @click="activeTab = t.key"
                            :class="['px-3.5 py-2 text-sm font-semibold rounded-xl transition',
                                     activeTab === t.key ? 'bg-[#1e1b4b] text-white' : 'text-gray-500 hover:bg-gray-100']">
                        {{ t.label }}
                    </button>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <a v-if="publicUrl" :href="publicUrl" target="_blank"
                       class="text-xs text-purple-600 font-semibold hover:underline">Preview ↗</a>
                    <button @click="save" :disabled="form.processing"
                            class="px-5 py-2 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-sm font-bold rounded-xl transition disabled:opacity-50">
                        {{ form.processing ? 'Saving…' : 'Save Changes' }}
                    </button>
                </div>
            </div>

            <!-- Tab: Hero & Contact -->
            <div v-show="activeTab === 'hero'" class="space-y-5">
                <ContentCard title="Hero Section" :hint="websiteEnabled ? 'Main banner — heading, tagline, eyebrow text.' : 'Shown on the registration portal landing page.'">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <Field label="Sahodaya Name / Heading" class-extra="sm:col-span-2">
                            <input v-model="form.heading" type="text" class="field">
                        </Field>
                        <Field label="Eyebrow (small text above heading)">
                            <input v-model="form.eyebrow" type="text" placeholder="CBSE Sahodaya School Complex" class="field">
                        </Field>
                        <Field label="Motto">
                            <input v-model="form.motto" type="text" placeholder="Caring and Sharing" class="field">
                        </Field>
                        <Field label="Tagline" class-extra="sm:col-span-2">
                            <input v-model="form.tagline" type="text" class="field">
                        </Field>
                    </div>
                </ContentCard>

                <ContentCard title="Contact Info" :hint="websiteEnabled ? 'Phone, email, address shown in footer and contact section.' : 'Phone and email shown on the registration portal.'">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <Field label="Phone">
                            <input v-model="form.phone" type="tel" class="field">
                        </Field>
                        <Field label="Email">
                            <input v-model="form.email" type="email" class="field">
                        </Field>
                        <Field label="Office Address" class-extra="sm:col-span-2">
                            <textarea v-model="form.address" rows="2" class="field"></textarea>
                        </Field>
                    </div>
                </ContentCard>
            </div>

            <!-- Tab: About -->
            <div v-show="activeTab === 'about'" class="space-y-5">
                <ContentCard title="About / Mission" hint="Shown in the About section on the homepage.">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <Field label="Section Heading">
                            <input v-model="form.about_heading" type="text" class="field">
                        </Field>
                        <Field label="Motto">
                            <input v-model="form.motto" type="text" class="field">
                        </Field>
                        <Field label="About Text" class-extra="sm:col-span-2">
                            <textarea v-model="form.about_text" rows="5" class="field" placeholder="An association of CBSE-affiliated schools fostering…"></textarea>
                        </Field>
                    </div>
                </ContentCard>
            </div>

            <!-- Tab: Announcements -->
            <div v-show="activeTab === 'announcements'" class="space-y-5">
                <ContentCard title="News Ticker Announcements"
                             hint="Appear in the scrolling ticker on the homepage. Circulars also show automatically.">
                    <div v-if="form.announcements.length === 0"
                         class="bg-gray-50 border border-dashed border-gray-200 rounded-xl p-8 text-center text-gray-400 text-sm">
                        No manual announcements yet. Uploaded circulars still appear automatically.
                    </div>
                    <div class="space-y-2">
                        <div v-for="(item, i) in form.announcements" :key="i"
                             class="grid grid-cols-12 gap-2 items-center bg-amber-50/50 border border-amber-100 rounded-xl p-3">
                            <input v-model="item.badge" placeholder="Badge" class="field col-span-2 text-xs text-center">
                            <input v-model="item.title" placeholder="Announcement title *" class="field col-span-5">
                            <input v-model="item.url" placeholder="URL (optional)" class="field col-span-3 text-xs">
                            <input v-model="item.date" placeholder="Date" class="field col-span-1 text-xs">
                            <button type="button" @click="form.announcements.splice(i, 1)"
                                    class="col-span-1 text-red-400 hover:text-red-600 text-sm flex items-center justify-center">✕</button>
                        </div>
                    </div>
                    <button type="button" @click="addAnnouncement"
                            class="mt-3 flex items-center gap-2 px-4 py-2 bg-purple-50 text-purple-700 text-sm font-semibold rounded-xl hover:bg-purple-100 transition border border-purple-100">
                        + Add Announcement
                    </button>
                </ContentCard>
            </div>

            <!-- Tab: Programmes -->
            <div v-show="activeTab === 'programmes'" class="space-y-5">
                <ContentCard title="Programmes & Services"
                             hint="The programme tiles shown on the homepage. (Kalotsav, Sports Meet, Teacher Fest, etc.)">
                    <Field label="Section Heading" class-extra="mb-4">
                        <input v-model="form.programmes_heading" placeholder="Programmes & Services" class="field max-w-sm">
                    </Field>
                    <div class="space-y-2">
                        <div v-for="(prog, i) in form.programmes" :key="i"
                             class="grid grid-cols-12 gap-2 items-start bg-gray-50 border border-gray-100 rounded-xl p-3">
                            <input v-model="prog.icon" placeholder="🏆" class="field col-span-1 text-center text-lg">
                            <input v-model="prog.label" placeholder="Programme name" class="field col-span-3">
                            <input v-model="prog.description" placeholder="Short description" class="field col-span-5">
                            <input v-model="prog.url" placeholder="URL" class="field col-span-2 text-xs">
                            <button type="button" @click="form.programmes.splice(i, 1)"
                                    class="col-span-1 text-red-400 hover:text-red-600 text-sm flex items-center justify-center mt-2">✕</button>
                        </div>
                    </div>
                    <button type="button" @click="addProgramme"
                            class="mt-3 flex items-center gap-2 px-4 py-2 bg-purple-50 text-purple-700 text-sm font-semibold rounded-xl hover:bg-purple-100 transition border border-purple-100">
                        + Add Programme
                    </button>
                </ContentCard>
            </div>

            <!-- Tab: Academic Links -->
            <div v-show="activeTab === 'academic'" class="space-y-5">
                <ContentCard title="Academic Year Links"
                             hint="Quick-access links grouped by year (registration, manuals, results).">
                    <Field label="Section Heading" class-extra="mb-4">
                        <input v-model="form.academic_heading" placeholder="Programs & Results" class="field max-w-sm">
                    </Field>
                    <div class="space-y-4">
                        <div v-for="(yearBlock, yi) in form.years" :key="yi"
                             class="border border-purple-100 bg-purple-50/20 rounded-2xl p-4 space-y-3">
                            <div class="flex items-center gap-3">
                                <input v-model="yearBlock.year" placeholder="2025-26"
                                       class="field w-32 font-bold font-mono text-center">
                                <button type="button" @click="addYearLink(yi)"
                                        class="px-3 py-1.5 bg-white border border-purple-200 text-purple-700 text-xs font-semibold rounded-lg hover:bg-purple-50 transition">
                                    + Link
                                </button>
                                <button type="button" @click="form.years.splice(yi, 1)"
                                        class="ml-auto text-xs text-red-400 hover:text-red-600">Remove year</button>
                            </div>
                            <div class="space-y-2 pl-2">
                                <div v-for="(link, li) in yearBlock.links" :key="li"
                                     class="grid grid-cols-12 gap-2 items-center">
                                    <input v-model="link.icon" placeholder="🔗" class="field col-span-1 text-center">
                                    <input v-model="link.label" placeholder="Link label" class="field col-span-4">
                                    <input v-model="link.url" placeholder="URL" class="field col-span-6 text-xs">
                                    <button type="button" @click="yearBlock.links.splice(li, 1)"
                                            class="col-span-1 text-red-400 text-sm flex items-center justify-center">✕</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" @click="addYear"
                            class="mt-3 flex items-center gap-2 px-4 py-2 bg-purple-50 text-purple-700 text-sm font-semibold rounded-xl hover:bg-purple-100 transition border border-purple-100">
                        + Add Academic Year Block
                    </button>
                </ContentCard>
            </div>

            <!-- Tab: Useful Links -->
            <div v-show="activeTab === 'links'" class="space-y-5">
                <ContentCard title="Useful Links"
                             hint="Quick links shown in the footer area (CBSE portal, Sahodaya registration, etc.)">
                    <Field label="Section Heading" class-extra="mb-4">
                        <input v-model="form.links_heading" placeholder="Useful Links" class="field max-w-sm">
                    </Field>
                    <div class="space-y-2">
                        <div v-for="(link, i) in form.links" :key="i"
                             class="grid grid-cols-12 gap-2 items-center bg-gray-50 border border-gray-100 rounded-xl p-3">
                            <input v-model="link.icon" placeholder="🔗" class="field col-span-1 text-center">
                            <input v-model="link.label" placeholder="Label" class="field col-span-4">
                            <input v-model="link.url" placeholder="https://..." class="field col-span-6 text-xs">
                            <button type="button" @click="form.links.splice(i, 1)"
                                    class="col-span-1 text-red-400 hover:text-red-600 text-sm flex items-center justify-center">✕</button>
                        </div>
                    </div>
                    <button type="button" @click="addLink"
                            class="mt-3 flex items-center gap-2 px-4 py-2 bg-purple-50 text-purple-700 text-sm font-semibold rounded-xl hover:bg-purple-100 transition border border-purple-100">
                        + Add Link
                    </button>
                </ContentCard>
            </div>

        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, defineComponent, h } from 'vue';

const page = usePage();
const websiteEnabled = computed(() => page.props.features?.website_enabled ?? false);
const pageTitle = computed(() => websiteEnabled.value ? 'Website Content' : 'Portal Content');

const props = defineProps({
    sahodaya:                Object,
    publicUrl:               { type: String, default: null },
    pendingSchoolsCount:     { type: Number, default: 0 },
    pendingSubmissionsCount: { type: Number, default: 0 },
    pendingPaymentsCount:    { type: Number, default: 0 },
    content:                 Object,
    publicWebsiteEnabled:    { type: Boolean, default: true },
});

const publicSiteEnabled = ref(props.publicWebsiteEnabled ?? true);

const activeTab = ref('hero');

const allTabs = [
    { key: 'hero',          label: 'Portal & Contact', portalOnly: true },
    { key: 'about',         label: 'About',            portalOnly: true },
    { key: 'announcements', label: 'Announcements',    portalOnly: false },
    { key: 'programmes',    label: 'Programmes',       portalOnly: false },
    { key: 'academic',      label: 'Academic Links',   portalOnly: false },
    { key: 'links',         label: 'Useful Links',     portalOnly: false },
];

const tabs = computed(() =>
    websiteEnabled.value ? allTabs : allTabs.filter(t => t.portalOnly)
);

const form = useForm({
    ...props.content,
    announcements: props.content.announcements?.length ? [...props.content.announcements] : [],
    programmes:    [...(props.content.programmes ?? [])],
    years:         (props.content.years ?? []).map(y => ({ ...y, links: [...(y.links ?? [])] })),
    links:         [...(props.content.links ?? [])],
});

function addAnnouncement() { form.announcements.push({ title: '', url: '#', date: '', badge: 'News' }); }
function addProgramme()    { form.programmes.push({ label: '', description: '', url: '#academic', icon: '📌' }); }
function addYear()         { form.years.push({ year: '', links: [] }); }
function addYearLink(yi)   { form.years[yi].links.push({ label: '', url: '#', icon: '🔗' }); }
function addLink()         { form.links.push({ label: '', url: 'https://', icon: '🔗' }); }
function save() {
    form.transform(data => ({
        ...data,
        ...(websiteEnabled.value ? { public_website_enabled: publicSiteEnabled.value } : {}),
    })).put(`/sahodaya-admin/${props.sahodaya.id}/public-content`);
}

const ContentCard = defineComponent({
    props: { title: String, hint: String },
    setup(props, { slots }) {
        return () => h('div', { class: 'bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4' }, [
            h('div', { class: 'mb-1' }, [
                h('h3', { class: 'font-bold text-gray-900' }, props.title),
                props.hint ? h('p', { class: 'text-xs text-gray-400 mt-0.5' }, props.hint) : null,
            ]),
            slots.default?.(),
        ]);
    },
});

const Field = defineComponent({
    props: { label: String, classExtra: String },
    setup(props, { slots }) {
        return () => h('div', { class: props.classExtra ?? '' }, [
            props.label ? h('label', { class: 'block text-xs font-semibold text-gray-600 mb-1.5' }, props.label) : null,
            slots.default?.(),
        ]);
    },
});
</script>

