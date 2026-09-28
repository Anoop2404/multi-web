<template>
    <SahodayaAdminLayout title="Sahodaya Website Builder" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount"
                         :show-header-title="false">
        <PageHeader title="Website Builder" eyebrow="Public Website"
                    description="Design, customize, and manage every page section, navigation menu, and theme for this Sahodaya public website." />

        <div class="space-y-6 max-w-7xl pb-16">
            <!-- Top Controls Card: Site Selector, Status & Public Toggle -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-5 flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-[240px] flex-1">
                    <label for="website-site-selector" class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">
                        Active Website Workspace
                    </label>
                    <div class="flex items-center gap-3">
                        <select id="website-site-selector" :value="currentSite?.id" @change="switchSite($event.target.value)"
                                class="w-full max-w-md border border-gray-200 rounded-xl px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-purple-200 focus:outline-none">
                            <option v-for="site in sites" :key="site.id" :value="site.id">
                                {{ site.name }}{{ site.is_primary ? ' — Primary Website' : ' — Microsite' }}
                            </option>
                        </select>
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full shrink-0"
                              :class="currentSite?.is_primary ? 'bg-purple-50 text-purple-700 border border-purple-200/50' : 'bg-sky-50 text-sky-700 border border-sky-200/50'">
                            {{ currentSite?.is_primary ? 'Primary Website' : 'Microsite' }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center flex-wrap gap-3">
                    <!-- Public Website Toggle -->
                    <div class="flex items-center gap-3 px-3.5 py-2 rounded-xl border border-gray-100 bg-gray-50/70">
                        <div class="text-right">
                            <span class="block text-xs font-bold text-gray-700">Public Website</span>
                            <span class="text-[11px] text-gray-500">{{ publicWebsiteEnabled ? 'Live Online' : 'Portal Mode Only' }}</span>
                        </div>
                        <button type="button" @click="isSuperAdmin && togglePublicWebsite()" :disabled="!isSuperAdmin || publicWebsiteSaving"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                :class="[publicWebsiteEnabled ? 'bg-green-500' : 'bg-gray-300', isSuperAdmin ? 'cursor-pointer' : 'cursor-not-allowed opacity-60']"
                                :title="isSuperAdmin ? 'Toggle public website availability' : 'Superadmin permission required'">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition"
                                  :class="publicWebsiteEnabled ? 'translate-x-6' : 'translate-x-1'"></span>
                        </button>
                    </div>

                    <a v-if="selectedPreviewUrl" :href="selectedPreviewUrl" target="_blank"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-purple-200 bg-purple-50 hover:bg-purple-100 text-purple-700 text-xs font-bold transition">
                        <span>Preview Draft</span>
                        <span>↗</span>
                    </a>

                    <a v-if="selectedPublicUrl" :href="selectedPublicUrl" target="_blank"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#1e1b4b] hover:bg-[#312e81] text-white text-xs font-bold transition shadow-xs">
                        <span>View Published</span>
                        <span>↗</span>
                    </a>
                </div>
            </div>

            <!-- Super Admin Access Only Guard -->
            <div v-if="!isSuperAdmin" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center max-w-xl mx-auto space-y-4 my-8">
                <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-3xl mx-auto">
                    🔒
                </div>
                <h2 class="text-xl font-extrabold text-gray-900">Super Admin Access Only</h2>
                <p class="text-sm text-gray-500 leading-relaxed">
                    The Website Builder, theme styling, and homepage section layouts are centrally managed by Platform Super Administrators to ensure CBSE Sahodaya network brand and design integrity.
                </p>
                <div class="pt-2">
                    <a :href="`/sahodaya-admin/${sahodaya.id}/public-content`"
                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#1e1b4b] text-white text-xs font-bold hover:bg-[#312e81] transition shadow-xs">
                        <span>Go to Public Content (News, Circulars & Bearers) →</span>
                    </a>
                </div>
            </div>

            <template v-else>
                <div class="bg-gradient-to-r from-purple-900 via-indigo-900 to-slate-900 text-white rounded-2xl p-4 border border-purple-800 shadow-xs flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-xs font-bold uppercase tracking-wider text-purple-200">Super Admin Mode</span>
                        <span class="text-xs text-slate-300 hidden sm:inline">• Full authority to add/edit page sections, switch layout variants, adjust theme tokens, and publish live.</span>
                    </div>
                </div>

            <!-- Unpublished Experience Draft Banner -->
            <div v-if="isSuperAdmin && experienceDraft" class="rounded-2xl p-5 flex flex-wrap items-center justify-between gap-4 text-white bg-gradient-to-r from-indigo-950 to-purple-800">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-purple-200">Unpublished experience draft</p>
                    <h2 class="font-bold mt-1 text-lg">{{ experienceName(experienceDraft.template_key) }}</h2>
                    <p class="text-sm text-white/75 mt-1">The live website remains unchanged until you publish this draft blueprint.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a :href="selectedPreviewUrl" target="_blank" class="px-4 py-2 text-sm font-bold rounded-xl bg-white/10 hover:bg-white/20">Preview Draft ↗</a>
                    <button @click="cancelExperienceDraft" class="px-4 py-2 text-sm font-bold rounded-xl bg-white/10 hover:bg-white/20">Cancel Draft</button>
                    <button @click="publishExperienceDraft" :disabled="experienceSaving || !readinessReport.ready" class="px-4 py-2 text-sm font-bold rounded-xl bg-amber-400 text-indigo-950 font-bold disabled:opacity-50">Publish Experience</button>
                </div>
            </div>

            <!-- 2-Column Website Builder Workspace -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- ── LEFT BUILDER SIDEBAR ────────────────────────────────────── -->
                <aside class="lg:col-span-4 xl:col-span-3 space-y-4">

                    <!-- Workspace Pages Navigation -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-3">
                        <div class="px-3 pt-2 pb-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">
                            Website Architecture
                        </div>
                        <nav class="space-y-1">
                            <button type="button"
                                    @click="selectTab('overview')"
                                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold transition text-left"
                                    :class="activeTab === 'overview'
                                        ? 'bg-[#1e1b4b] text-white shadow-xs font-bold'
                                        : 'text-gray-700 hover:bg-gray-50'">
                                <div class="flex items-center gap-2.5">
                                    <SvgIcon name="layers" class="w-4 h-4 shrink-0" />
                                    <span>Site Overview</span>
                                </div>
                                <span v-if="activeTab === 'overview'" class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            </button>

                            <button type="button"
                                    @click="selectTab('sections')"
                                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold transition text-left"
                                    :class="(activeTab === 'sections' || activeTab === 'section-edit')
                                        ? 'bg-[#1e1b4b] text-white shadow-xs font-bold'
                                        : 'text-gray-700 hover:bg-gray-50'">
                                <div class="flex items-center gap-2.5">
                                    <SvgIcon name="grid" class="w-4 h-4 shrink-0" />
                                    <span>Page Sections Hub</span>
                                </div>
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                                      :class="(activeTab === 'sections' || activeTab === 'section-edit') ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600'">
                                    {{ sections.length }}
                                </span>
                            </button>

                            <button type="button"
                                    @click="selectTab('navigation')"
                                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold transition text-left"
                                    :class="activeTab === 'navigation'
                                        ? 'bg-[#1e1b4b] text-white shadow-xs font-bold'
                                        : 'text-gray-700 hover:bg-gray-50'">
                                <div class="flex items-center gap-2.5">
                                    <SvgIcon name="compass" class="w-4 h-4 shrink-0" />
                                    <span>Navigation & Menu</span>
                                </div>
                                <span v-if="activeTab === 'navigation'" class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            </button>

                            <button type="button"
                                    @click="selectTab('theme')"
                                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold transition text-left"
                                    :class="activeTab === 'theme'
                                        ? 'bg-[#1e1b4b] text-white shadow-xs font-bold'
                                        : 'text-gray-700 hover:bg-gray-50'">
                                <div class="flex items-center gap-2.5">
                                    <SvgIcon name="palette" class="w-4 h-4 shrink-0" />
                                    <span>Theme & Styling</span>
                                </div>
                                <span v-if="activeTab === 'theme'" class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            </button>

                            <button type="button"
                                    @click="selectTab('footer')"
                                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold transition text-left"
                                    :class="activeTab === 'footer'
                                        ? 'bg-[#1e1b4b] text-white shadow-xs font-bold'
                                        : 'text-gray-700 hover:bg-gray-50'">
                                <div class="flex items-center gap-2.5">
                                    <SvgIcon name="layout" class="w-4 h-4 shrink-0" />
                                    <span>Footer Links</span>
                                </div>
                                <span v-if="activeTab === 'footer'" class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            </button>

                            <!-- Super Admin Exclusive Sections -->
                            <template v-if="isSuperAdmin">
                                <div class="pt-2 pb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-purple-600">
                                    Super Admin Tools
                                </div>

                                <button type="button"
                                        @click="selectTab('experience')"
                                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold transition text-left"
                                        :class="activeTab === 'experience'
                                            ? 'bg-purple-900 text-white shadow-xs font-bold'
                                            : 'text-gray-700 hover:bg-purple-50/60'">
                                    <div class="flex items-center gap-2.5">
                                        <SvgIcon name="sliders" class="w-4 h-4 shrink-0" />
                                        <span>Templates & Drafts</span>
                                    </div>
                                    <span v-if="activeTab === 'experience'" class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                </button>

                                <button type="button"
                                        @click="selectTab('readiness')"
                                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold transition text-left"
                                        :class="activeTab === 'readiness'
                                            ? 'bg-purple-900 text-white shadow-xs font-bold'
                                            : 'text-gray-700 hover:bg-purple-50/60'">
                                    <div class="flex items-center gap-2.5">
                                        <SvgIcon name="check-circle" class="w-4 h-4 shrink-0" />
                                        <span>Publish & Readiness</span>
                                    </div>
                                    <span v-if="readinessReport?.ready" class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                    <span v-else class="w-2 h-2 rounded-full bg-amber-400"></span>
                                </button>
                            </template>
                        </nav>
                    </div>

                    <!-- Page Sections Menu Tree -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-3">
                        <div class="flex items-center justify-between px-3 pt-2 pb-2">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Page Sections</span>
                                <span class="text-[10px] font-bold bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full">{{ sections.length }}</span>
                            </div>
                            <button v-if="isSuperAdmin" @click="openAddModal"
                                    class="text-[11px] font-bold text-purple-700 hover:text-purple-900 hover:underline">
                                + Add
                            </button>
                        </div>

                        <div class="space-y-1 max-h-[460px] overflow-y-auto pr-1">
                            <button v-for="(sec, idx) in sections" :key="sec.id"
                                    type="button"
                                    @click="selectSection(sec)"
                                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-left text-xs transition group"
                                    :class="activeTab === 'section-edit' && selectedSectionId === sec.id
                                        ? 'bg-purple-50 text-purple-900 font-bold border border-purple-200/70 shadow-xs'
                                        : 'text-gray-700 hover:bg-gray-50 border border-transparent'">
                                <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold shrink-0"
                                      :style="{ background: sectionColor(sec.section_type) }">
                                    {{ sectionIcon(sec.section_type) }}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate font-semibold capitalize">{{ sectionTypeLabel(sec.section_type) }}</span>
                                        <span v-if="!sec.is_active" class="text-[9px] bg-gray-100 text-gray-400 px-1 py-0.2 rounded shrink-0">Hidden</span>
                                    </div>
                                    <p class="text-[10px] text-gray-400 truncate">{{ sec.variant }}</p>
                                </div>
                                <span class="w-2 h-2 rounded-full shrink-0"
                                      :class="sec.is_active ? 'bg-emerald-500' : 'bg-gray-300'"></span>
                            </button>

                            <p v-if="!sections.length" class="text-xs text-gray-400 text-center py-4">
                                No sections created yet.
                            </p>
                        </div>
                    </div>

                </aside>

                <!-- ── RIGHT MAIN WORKSPACE CONTENT ───────────────────────────── -->
                <main class="lg:col-span-8 xl:col-span-9 min-w-0 space-y-6">

                    <!-- 1. DEDICATED SECTION ADMIN PAGE -->
                    <div v-if="activeTab === 'section-edit' && currentEditingSection" class="space-y-6">

                        <!-- Breadcrumbs & Pager Row -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-4 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2 text-xs">
                                <button @click="selectTab('overview')" class="text-gray-500 hover:text-purple-700 font-medium">Site Overview</button>
                                <span class="text-gray-300">/</span>
                                <button @click="selectTab('sections')" class="text-gray-500 hover:text-purple-700 font-medium">Page Sections</button>
                                <span class="text-gray-300">/</span>
                                <span class="font-bold text-[#1e1b4b] capitalize">{{ sectionTypeLabel(currentEditingSection.section_type) }}</span>
                            </div>

                            <!-- Pager buttons to step between sections -->
                            <div class="flex items-center gap-2">
                                <button @click="prevSection && selectSection(prevSection)" :disabled="!prevSection"
                                        class="px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed">
                                    ← Prev Section
                                </button>
                                <span class="text-xs text-gray-400 font-mono">
                                    {{ currentSectionIndex + 1 }} of {{ sections.length }}
                                </span>
                                <button @click="nextSection && selectSection(nextSection)" :disabled="!nextSection"
                                        class="px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed">
                                    Next Section →
                                </button>
                            </div>
                        </div>

                        <!-- Section Header Banner Card -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 flex flex-wrap items-start justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl shrink-0 shadow-xs"
                                     :style="{ background: sectionColor(currentEditingSection.section_type) }">
                                    {{ sectionIcon(currentEditingSection.section_type) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h2 class="text-xl font-extrabold text-[#041525] capitalize">
                                            {{ sectionTypeLabel(currentEditingSection.section_type) }} Section
                                        </h2>
                                        <span class="text-xs font-mono bg-purple-50 text-purple-700 px-2.5 py-0.5 rounded-full font-bold">
                                            {{ currentEditingSection.variant }}
                                        </span>
                                        <span class="text-xs font-semibold bg-sky-50 text-sky-700 px-2.5 py-0.5 rounded-full">
                                            Data: {{ sourceBadge(currentEditingSection.section_type) }}
                                        </span>
                                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full"
                                              :class="currentEditingSection.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'">
                                            {{ currentEditingSection.is_active ? '● Live on Site' : '○ Hidden' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1 max-w-xl">
                                        {{ sectionPreview(currentEditingSection) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Header Section Actions -->
                            <div class="flex items-center gap-2">
                                <button v-if="isSuperAdmin" @click="toggleActive(currentEditingSection)"
                                        class="text-xs font-semibold px-3 py-2 rounded-xl border transition"
                                        :class="currentEditingSection.is_active
                                            ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'
                                            : 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100'">
                                    {{ currentEditingSection.is_active ? 'Hide Section' : 'Show Section' }}
                                </button>
                                <button v-if="isSuperAdmin" @click="duplicateSection(currentEditingSection)" :disabled="saving[currentEditingSection.id]"
                                        class="text-xs font-semibold px-3 py-2 rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 transition">
                                    Duplicate
                                </button>
                                <button v-if="isSuperAdmin" @click="removeSection(currentEditingSection)"
                                        class="text-xs font-semibold px-3 py-2 rounded-xl border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 transition">
                                    Delete
                                </button>
                            </div>
                        </div>

                        <!-- Card 1: Layout Variant Selector -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 class="font-bold text-gray-900 text-sm">Layout Design Variant</h3>
                                    <p class="text-xs text-gray-500 mt-0.5">Select how this {{ sectionTypeLabel(currentEditingSection.section_type) }} content is visually structured on the page.</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div v-if="isSuperAdmin && (currentEditingSection.archived_configs || []).length">
                                        <select @change="restoreArchived(currentEditingSection, $event.target.value)"
                                                class="text-xs border border-gray-200 rounded-xl px-3 py-1.5 text-gray-500 bg-white focus:ring-2 focus:ring-purple-200 focus:outline-none">
                                            <option value="">↩ Restore previous content…</option>
                                            <option v-for="(arc, ai) in currentEditingSection.archived_configs" :key="ai" :value="ai">
                                                {{ arc.variant }} — {{ formatDate(arc.archived_at) }}
                                            </option>
                                        </select>
                                    </div>
                                    <div v-if="isSuperAdmin && sectionVersions[currentEditingSection.id]?.length">
                                        <select @change="restorePublishedVersion(currentEditingSection, $event.target.value)" class="text-xs border border-gray-200 rounded-xl px-3 py-1.5 text-gray-500 bg-white">
                                            <option value="">Restore saved version…</option>
                                            <option v-for="version in sectionVersions[currentEditingSection.id]" :key="version.id" :value="version.id">
                                                {{ version.note || 'Saved' }} — {{ formatDate(version.created_at) }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 pt-2">
                                <button v-for="v in variantsFor(currentEditingSection.section_type)" :key="v"
                                        type="button"
                                        @click="isSuperAdmin && switchVariant(currentEditingSection, v)"
                                        :class="[
                                            'p-3.5 rounded-xl text-left border-2 transition',
                                            currentEditingSection.variant === v
                                                ? 'border-[#1e1b4b] bg-[#1e1b4b]/5 shadow-xs'
                                                : 'border-gray-100 hover:border-purple-200 bg-white',
                                            !isSuperAdmin ? 'cursor-default' : ''
                                        ]">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs font-bold text-gray-800 capitalize">{{ v.replace(/-/g, ' ') }}</span>
                                        <span v-if="currentEditingSection.variant === v" class="text-xs text-[#1e1b4b]">✓</span>
                                    </div>
                                    <p class="text-[11px] text-gray-400 line-clamp-2">
                                        {{ fieldDefs[currentEditingSection.section_type]?.[v]?.description || 'Variant template design' }}
                                    </p>
                                </button>
                            </div>
                        </div>

                        <!-- Card 2: Section Content Configuration -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 space-y-5">
                            <div>
                                <h3 class="font-bold text-gray-900 text-sm">Section Content Fields</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Customize headlines, descriptions, call-to-actions, and uploaded images for this section.</p>
                            </div>

                            <fieldset :disabled="!isSuperAdmin" :class="{ 'pointer-events-none opacity-85': !isSuperAdmin }">
                                <div v-if="fieldsFor(currentEditingSection.section_type, currentEditingSection.variant).length">
                                    <SectionFieldEditor
                                        :fields="fieldsFor(currentEditingSection.section_type, currentEditingSection.variant)"
                                        :config="editConfigs[currentEditingSection.id] || currentEditingSection.config || {}"
                                        :upload-media="uploadSiteMedia"
                                        :media-preview="mediaPreviewUrl"
                                        @update="val => editConfigs[currentEditingSection.id] = val" />
                                </div>
                                <div v-else class="bg-sky-50 border border-sky-100 rounded-xl p-5 text-center space-y-2">
                                    <div class="text-3xl">🗄️</div>
                                    <h4 class="text-sm font-bold text-sky-900">Automated Data-Driven Section</h4>
                                    <p class="text-xs text-sky-700 max-w-md mx-auto">
                                        This section dynamically pulls content directly from your CBSE Sahodaya database records (e.g. registered member schools, active office bearers, upcoming calendar events, or published circulars). No manual text inputs are required.
                                    </p>
                                </div>
                            </fieldset>
                        </div>

                        <!-- Card 3: Section Spacing & Layout Appearance -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 space-y-4">
                            <div>
                                <h3 class="font-bold text-gray-900 text-sm">Appearance, Spacing & Container Width</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Adjust background style, padding density, and alignment for this section.</p>
                            </div>

                            <fieldset :disabled="!isSuperAdmin" :class="{ 'pointer-events-none opacity-85': !isSuperAdmin }">
                                <SectionLayoutEditor v-model="editLayouts[currentEditingSection.id]" />
                            </fieldset>
                        </div>

                        <!-- Bottom Sticky Toolbar for Section Admin -->
                        <div class="sticky bottom-4 z-20 bg-white/95 backdrop-blur-md rounded-2xl border border-gray-200/80 shadow-lg p-4 flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <button @click="selectTab('sections')"
                                        class="px-4 py-2 border border-gray-200 text-xs font-bold text-gray-600 rounded-xl hover:bg-gray-50 transition">
                                    ← All Sections
                                </button>
                                <span v-if="currentEditingSection.has_unpublished_changes || currentEditingSection.status === 'draft'"
                                      class="text-xs font-bold text-amber-700 bg-amber-50 border border-amber-200/60 px-2.5 py-1 rounded-lg">
                                    Unpublished draft changes
                                </span>
                                <span v-else class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg">
                                    All changes published
                                </span>
                            </div>

                            <div class="flex items-center gap-3">
                                <template v-if="isSuperAdmin">
                                    <button @click="saveSection(currentEditingSection)"
                                            :disabled="saving[currentEditingSection.id]"
                                            class="px-5 py-2.5 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-xs font-bold rounded-xl transition disabled:opacity-50 shadow-xs">
                                        {{ saving[currentEditingSection.id] ? 'Saving…' : 'Save Section Draft' }}
                                    </button>
                                    <button @click="publishSection(currentEditingSection)"
                                            :disabled="saving[currentEditingSection.id]"
                                            class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition disabled:opacity-50 shadow-xs">
                                        Publish Live
                                    </button>
                                </template>
                                <span v-else class="text-xs text-gray-500 font-medium bg-gray-100 px-3 py-1.5 rounded-lg flex items-center gap-1.5">
                                    🔒 Read-only view. Superadmin privileges required to modify.
                                </span>

                                <a v-if="selectedPreviewUrl" :href="selectedPreviewUrl" target="_blank"
                                   class="text-xs text-purple-700 hover:underline font-bold px-2">
                                    Preview Site ↗
                                </a>
                            </div>
                        </div>

                    </div>


                    <!-- 2. SITE OVERVIEW DASHBOARD -->
                    <div v-else-if="activeTab === 'overview'" class="space-y-6">

                        <!-- Overview Summary Cards -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-4 space-y-1">
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Sections</span>
                                <div class="text-2xl font-black text-[#1e1b4b]">{{ sections.length }}</div>
                                <span class="text-xs text-emerald-600 font-semibold">{{ sections.filter(s => s.is_active).length }} Active on Site</span>
                            </div>

                            <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-4 space-y-1">
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Theme Colors</span>
                                <div class="flex items-center gap-1.5 pt-1">
                                    <span class="w-6 h-6 rounded-lg border border-white shadow-xs" :style="{ background: themeConfig.primary }" :title="`Primary: ${themeConfig.primary}`"></span>
                                    <span class="w-6 h-6 rounded-lg border border-white shadow-xs" :style="{ background: themeConfig.secondary }" :title="`Secondary: ${themeConfig.secondary}`"></span>
                                    <span class="w-6 h-6 rounded-lg border border-white shadow-xs" :style="{ background: themeConfig.accent_color }" :title="`Accent: ${themeConfig.accent_color}`"></span>
                                </div>
                                <span class="text-xs text-gray-400 font-mono">{{ themeConfig.font_heading }} font</span>
                            </div>

                            <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-4 space-y-1">
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Experience Blueprint</span>
                                <div class="text-sm font-bold text-gray-900 truncate mt-1">
                                    {{ currentSite?.template_key || 'Classic Modern' }}
                                </div>
                                <span class="text-xs text-purple-700 font-semibold">{{ currentSite?.experience_version || 'V2' }} Design</span>
                            </div>

                            <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-4 space-y-1">
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Public Status</span>
                                <div class="text-sm font-bold truncate mt-1" :class="publicWebsiteEnabled ? 'text-emerald-700' : 'text-gray-600'">
                                    {{ publicWebsiteEnabled ? '● Fully Enabled' : '○ Registration Only' }}
                                </div>
                                <a v-if="selectedPublicUrl" :href="selectedPublicUrl" target="_blank" class="text-xs text-purple-600 hover:underline">
                                    Open domain ↗
                                </a>
                            </div>
                        </div>

                        <!-- Homepage Layout Sequence -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 space-y-5">
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h3 class="font-extrabold text-[#041525] text-base">Homepage Blueprint & Section Flow</h3>
                                    <p class="text-xs text-gray-500 mt-0.5">The sequence below represents the visual flow of sections appearing from top to bottom on the homepage.</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button @click="selectTab('sections')"
                                            class="px-3.5 py-1.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50 transition">
                                        Reorder Sections Hub →
                                    </button>
                                    <button v-if="isSuperAdmin" @click="openAddModal"
                                            class="px-3.5 py-1.5 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-xs font-bold rounded-xl transition shadow-xs">
                                        + Add Section
                                    </button>
                                </div>
                            </div>

                            <!-- List of Section Cards -->
                            <div class="space-y-3">
                                <div v-for="(sec, idx) in sections" :key="sec.id"
                                     class="flex flex-wrap items-center justify-between gap-4 p-4 rounded-xl border transition group"
                                     :class="sec.is_active ? 'border-gray-100 bg-white hover:border-purple-200 hover:shadow-xs' : 'border-dashed border-gray-200 bg-gray-50/60 opacity-70'">

                                    <div class="flex items-center gap-3.5 min-w-0">
                                        <span class="w-6 h-6 rounded-full bg-gray-100 text-gray-500 text-[11px] font-bold flex items-center justify-center font-mono shrink-0">
                                            {{ idx + 1 }}
                                        </span>
                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0"
                                             :style="{ background: sectionColor(sec.section_type) }">
                                            {{ sectionIcon(sec.section_type) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <h4 class="font-bold text-sm text-gray-900 capitalize">{{ sectionTypeLabel(sec.section_type) }}</h4>
                                                <span class="text-[11px] font-mono bg-purple-50 text-purple-700 px-2 py-0.5 rounded-full font-semibold">{{ sec.variant }}</span>
                                                <span class="text-[11px] font-semibold bg-sky-50 text-sky-700 px-2 py-0.5 rounded-full">Data: {{ sourceBadge(sec.section_type) }}</span>
                                                <span v-if="!sec.is_active" class="text-[11px] bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Hidden from site</span>
                                            </div>
                                            <p class="text-xs text-gray-400 truncate max-w-lg mt-0.5">
                                                {{ sectionPreview(sec) }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <button v-if="isSuperAdmin" @click="toggleActive(sec)"
                                                class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition"
                                                :class="sec.is_active
                                                    ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'
                                                    : 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100'">
                                            {{ sec.is_active ? 'Hide' : 'Show' }}
                                        </button>
                                        <button @click="selectSection(sec)"
                                                class="px-3.5 py-1.5 rounded-lg bg-[#1e1b4b] hover:bg-[#312e81] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                                            <span>{{ isSuperAdmin ? 'Edit Section Page' : 'View Section Page' }}</span>
                                            <span>→</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Architecture Quick Links -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div @click="selectTab('navigation')" class="bg-white rounded-2xl border border-gray-100 shadow-xs p-5 hover:border-purple-200 transition cursor-pointer group">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center font-bold">
                                        <SvgIcon name="compass" class="w-4 h-4" />
                                    </div>
                                    <h4 class="font-bold text-sm text-gray-900 group-hover:text-purple-700 transition">Navigation & Header</h4>
                                </div>
                                <p class="text-xs text-gray-500">Configure navbar styles, menu links, and portal registration CTA buttons.</p>
                            </div>

                            <div @click="selectTab('theme')" class="bg-white rounded-2xl border border-gray-100 shadow-xs p-5 hover:border-purple-200 transition cursor-pointer group">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center font-bold">
                                        <SvgIcon name="palette" class="w-4 h-4" />
                                    </div>
                                    <h4 class="font-bold text-sm text-gray-900 group-hover:text-purple-700 transition">Theme & Styling</h4>
                                </div>
                                <p class="text-xs text-gray-500">Tune color palette tokens, typography scales, card density, and surface elevations.</p>
                            </div>

                            <div @click="selectTab('footer')" class="bg-white rounded-2xl border border-gray-100 shadow-xs p-5 hover:border-purple-200 transition cursor-pointer group">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center font-bold">
                                        <SvgIcon name="layout" class="w-4 h-4" />
                                    </div>
                                    <h4 class="font-bold text-sm text-gray-900 group-hover:text-purple-700 transition">Footer Links</h4>
                                </div>
                                <p class="text-xs text-gray-500">Manage footer quick links, copyright notices, and public contact information.</p>
                            </div>
                        </div>

                    </div>


                    <!-- 3. PAGE SECTIONS MANAGEMENT & REORDER HUB -->
                    <div v-else-if="activeTab === 'sections'" class="space-y-6">

                        <div class="bg-white rounded-2xl border border-gray-100 shadow-xs px-6 py-4 flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h3 class="font-bold text-gray-900 text-base">Page Sections Hub</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Reorder sections using the drag handles or up/down buttons, toggle visibility, or select any section to open its dedicated page editor.</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-gray-400 font-semibold">
                                    {{ sections.length }} total · {{ sections.filter(s => s.is_active).length }} active
                                </span>
                                <button v-if="isSuperAdmin" @click="openAddModal"
                                        class="flex items-center gap-2 px-4 py-2 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-xs font-bold rounded-xl transition shadow-xs">
                                    + Add New Section
                                </button>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div v-if="sections.length === 0"
                             class="bg-white rounded-2xl border border-dashed border-gray-200 p-16 text-center">
                            <div class="text-5xl mb-3">🏗️</div>
                            <p class="font-semibold text-gray-600">No sections added yet</p>
                            <p class="text-sm text-gray-400 mt-1 mb-4">Build your public website layout by adding sections below.</p>
                            <button v-if="isSuperAdmin" @click="openAddModal"
                                    class="px-5 py-2.5 bg-[#1e1b4b] text-white text-xs font-bold rounded-xl hover:bg-[#312e81] transition shadow-xs">
                                + Add First Section
                            </button>
                        </div>

                        <!-- Section cards list -->
                        <div class="space-y-3">
                            <div v-for="(section, idx) in sections" :key="section.id"
                                 class="bg-white rounded-2xl border shadow-xs transition"
                                 :class="section.is_active ? 'border-gray-100' : 'border-gray-100 opacity-60'">

                                <div class="px-5 py-4 flex items-center gap-4" :draggable="isSuperAdmin" @dragstart="isSuperAdmin && (dragIndex = idx)" @dragover.prevent @drop="isSuperAdmin && dropSection(idx)" @dragend="dragIndex = null">
                                    <!-- Reorder handles -->
                                    <div v-if="isSuperAdmin" class="flex flex-col gap-0.5 shrink-0">
                                        <button @click="moveUp(idx)" :disabled="idx === 0"
                                                 class="w-6 h-5 flex items-center justify-center text-gray-300 hover:text-gray-600 disabled:opacity-20 rounded transition text-xs font-bold" title="Move Up">▲</button>
                                        <button @click="moveDown(idx)" :disabled="idx === sections.length - 1"
                                                 class="w-6 h-5 flex items-center justify-center text-gray-300 hover:text-gray-600 disabled:opacity-20 rounded transition text-xs font-bold" title="Move Down">▼</button>
                                    </div>

                                    <!-- Type icon + labels -->
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0 shadow-xs"
                                         :style="{ background: sectionColor(section.section_type) }">
                                        {{ sectionIcon(section.section_type) }}
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center flex-wrap gap-2 mb-0.5">
                                            <span class="text-sm font-bold text-gray-900 capitalize">
                                                {{ sectionTypeLabel(section.section_type) }}
                                            </span>
                                            <span class="text-[11px] font-mono bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ section.variant }}</span>
                                            <span class="text-[11px] font-semibold bg-sky-50 text-sky-700 px-2 py-0.5 rounded-full">{{ sourceBadge(section.section_type) }}</span>
                                            <span v-if="!section.is_active" class="text-[11px] font-semibold bg-gray-100 text-gray-400 px-2 py-0.5 rounded-full">Hidden</span>
                                            <span v-if="section.has_unpublished_changes || section.status === 'draft'" class="text-[11px] font-bold bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full">Draft Changes</span>
                                        </div>
                                        <p class="text-xs text-gray-400 truncate">
                                            {{ sectionPreview(section) }}
                                        </p>
                                    </div>

                                    <!-- Action buttons -->
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button v-if="isSuperAdmin" @click="toggleActive(section)"
                                                class="text-xs font-semibold px-3 py-1.5 rounded-xl border transition"
                                                :class="section.is_active
                                                    ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'
                                                    : 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100'">
                                            {{ section.is_active ? 'Hide' : 'Show' }}
                                        </button>

                                        <button @click="selectSection(section)"
                                                class="text-xs font-bold px-3.5 py-1.5 rounded-xl bg-[#1e1b4b] hover:bg-[#312e81] text-white transition flex items-center gap-1.5 shadow-xs">
                                            <span>{{ isSuperAdmin ? '✏️ Edit Page' : '👁️ View Page' }}</span>
                                            <span>→</span>
                                        </button>

                                        <button v-if="isSuperAdmin" @click="duplicateSection(section)" :disabled="saving[section.id]"
                                                class="text-xs font-semibold px-2.5 py-1.5 rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 transition" title="Duplicate Section">
                                            Duplicate
                                        </button>

                                        <button v-if="isSuperAdmin" @click="removeSection(section)"
                                                class="text-xs font-semibold px-2.5 py-1.5 rounded-xl border border-red-100 bg-red-50 text-red-500 hover:bg-red-100 transition" title="Delete Section">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>


                    <!-- 4. NAVIGATION & LOGIN MENU -->
                    <div v-else-if="activeTab === 'navigation'" class="space-y-6">

                        <div v-if="!isSuperAdmin" class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs text-amber-800 flex items-center gap-3">
                            <span class="text-base">🔒</span>
                            <div>
                                <p class="font-bold">Navigation configuration is locked</p>
                                <p class="text-amber-700 mt-0.5">Only Super Admins can alter navbar links, portal URLs, and navigation styles. Sahodaya administrators have read-only access.</p>
                            </div>
                        </div>

                        <div v-if="!navConfig.items?.length"
                             class="bg-amber-50 border border-amber-200 rounded-2xl p-5 flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h3 class="font-bold text-amber-900">No navigation menu configured yet</h3>
                                <p class="text-sm text-amber-800 mt-1">Your public site navbar is empty. Load the default Sahodaya menu or add items below.</p>
                            </div>
                            <button v-if="isSuperAdmin" @click="loadDefaultNav" :disabled="defaultNavSaving"
                                    class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl disabled:opacity-50">
                                {{ defaultNavSaving ? 'Loading…' : 'Load default navigation menu' }}
                            </button>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5">Navbar Style Preset</label>
                                <select v-model="navConfig.layout_variant" :disabled="!isSuperAdmin"
                                        class="w-full max-w-md border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-purple-200 focus:outline-none disabled:bg-gray-50 disabled:cursor-not-allowed">
                                    <option v-for="opt in navLayoutOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </div>
                        </div>

                        <fieldset :disabled="!isSuperAdmin" class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 space-y-6">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <h2 class="font-bold text-gray-900">Portal Login & Registration CTA</h2>
                                    <p class="text-sm text-gray-500 mt-1">
                                        The prominent Login button in the header navbar redirects visitors directly to your portal options.
                                    </p>
                                </div>
                                <button v-if="isSuperAdmin" @click="ensurePortalLinks" :disabled="portalSaving"
                                        class="px-4 py-2 text-xs font-bold rounded-xl border border-purple-200 bg-purple-50 text-purple-700 hover:bg-purple-100 transition disabled:opacity-50">
                                    {{ portalSaving ? 'Adding…' : '+ Sync Portal links to menu & footer' }}
                                </button>
                            </div>

                            <div class="grid sm:grid-cols-2 gap-4 p-4 bg-purple-50/50 rounded-xl border border-purple-100">
                                <label class="flex items-center gap-3" :class="isSuperAdmin ? 'cursor-pointer' : 'cursor-not-allowed'">
                                    <input type="checkbox" v-model="navConfig.portal_cta.show_in_navbar" :disabled="!isSuperAdmin" class="w-4 h-4 rounded text-purple-600">
                                    <span class="text-sm font-medium text-gray-700">Show Login button in header navbar</span>
                                </label>
                                <label class="flex items-center gap-3" :class="isSuperAdmin ? 'cursor-pointer' : 'cursor-not-allowed'">
                                    <input type="checkbox" v-model="navConfig.portal_cta.show_in_menu" :disabled="!isSuperAdmin" class="w-4 h-4 rounded text-purple-600">
                                    <span class="text-sm font-medium text-gray-700">Include registration & login in dropdown menu</span>
                                </label>
                            </div>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1.5">Navbar button label</label>
                                    <input v-model="navConfig.portal_cta.portal_label" :disabled="!isSuperAdmin"
                                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-purple-200 focus:outline-none disabled:bg-gray-50 disabled:cursor-not-allowed">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1.5">Portal page URL</label>
                                    <input v-model="navConfig.portal_cta.portal_url" :disabled="!isSuperAdmin"
                                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-purple-200 focus:outline-none disabled:bg-gray-50 disabled:cursor-not-allowed">
                                </div>
                            </div>

                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wide pt-2">Direct Destination URLs</p>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1.5">Registration button label</label>
                                    <input v-model="navConfig.portal_cta.register_label" :disabled="!isSuperAdmin"
                                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-purple-200 focus:outline-none disabled:bg-gray-50 disabled:cursor-not-allowed">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1.5">Registration URL</label>
                                    <input v-model="navConfig.portal_cta.register_url" :disabled="!isSuperAdmin"
                                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-purple-200 focus:outline-none disabled:bg-gray-50 disabled:cursor-not-allowed">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1.5">Admin login button label</label>
                                    <input v-model="navConfig.portal_cta.login_label" :disabled="!isSuperAdmin"
                                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-purple-200 focus:outline-none disabled:bg-gray-50 disabled:cursor-not-allowed">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1.5">Admin login URL</label>
                                    <input v-model="navConfig.portal_cta.login_url" :disabled="!isSuperAdmin"
                                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-purple-200 focus:outline-none disabled:bg-gray-50 disabled:cursor-not-allowed">
                                </div>
                            </div>
                        </fieldset>

                        <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-bold text-gray-900">Header Menu Navigation Items</h3>
                                    <p class="text-xs text-gray-500 mt-0.5">Top navbar links displayed to public visitors.</p>
                                </div>
                                <button v-if="isSuperAdmin" @click="addNavItem"
                                        class="text-xs px-3.5 py-2 rounded-xl bg-[#1e1b4b] text-white font-semibold hover:bg-[#312e81] transition shadow-xs">
                                    + Add Menu Item
                                </button>
                            </div>
                            <div class="space-y-2">
                                <div v-for="(item, idx) in navConfig.items" :key="idx"
                                     class="flex flex-wrap items-center gap-3 bg-gray-50 rounded-xl p-3 border border-gray-100">
                                    <input v-model="item.label" :disabled="!isSuperAdmin" placeholder="Label (e.g. About Us)"
                                           class="flex-1 min-w-[140px] border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-200 disabled:bg-gray-100 disabled:cursor-not-allowed">
                                    <input v-model="item.url" :disabled="!isSuperAdmin" placeholder="/url-or-anchor"
                                           class="flex-1 min-w-[140px] border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-purple-200 disabled:bg-gray-100 disabled:cursor-not-allowed">
                                    <button v-if="isSuperAdmin" @click="removeNavItem(idx)" class="text-red-400 hover:text-red-600 text-lg px-2" title="Remove Item">&times;</button>
                                </div>
                                <p v-if="!navConfig.items?.length" class="text-sm text-gray-400 text-center py-4">No menu items added yet.</p>
                            </div>
                            <div v-if="isSuperAdmin" class="flex items-center gap-3 pt-3 border-t border-gray-100">
                                <button @click="saveNav" :disabled="navSaving"
                                        class="px-5 py-2.5 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-xs font-bold rounded-xl transition disabled:opacity-50 shadow-xs">
                                    {{ navSaving ? 'Saving…' : 'Save Navigation Menu' }}
                                </button>
                                <span v-if="navSaved" class="text-xs text-green-600 font-bold">Saved successfully!</span>
                            </div>
                        </div>

                    </div>


                    <!-- 5. THEME & STYLING TOKENS -->
                    <div v-else-if="activeTab === 'theme'" class="space-y-6">

                        <div v-if="!isSuperAdmin" class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs text-amber-800 flex items-center gap-3">
                            <span class="text-base">🔒</span>
                            <div>
                                <p class="font-bold">Design system & theme tokens are locked</p>
                                <p class="text-amber-700 mt-0.5">Colors, typography scales, density tokens, and design presets are centrally managed by Super Admins. Sahodaya administrators have read-only preview access.</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 space-y-6">
                            <div>
                                <h2 class="font-bold text-gray-900 text-base">Design Character & Brand Identity</h2>
                                <p class="text-xs text-gray-500 mt-1">
                                    Controls headers, buttons, hero gradients, typography, and section styling across your public website.
                                </p>
                            </div>

                            <!-- Live preview card -->
                            <div class="rounded-2xl overflow-hidden border border-gray-200/80 shadow-xs">
                                <div class="h-28 flex items-end p-5 text-white"
                                     :style="{ background: `linear-gradient(135deg, ${themeConfig.primary}, ${themeConfig.secondary})` }">
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wider opacity-80">Live Header Preview</p>
                                        <p class="font-bold text-lg leading-tight">{{ sahodaya.name }}</p>
                                    </div>
                                </div>
                                <div class="flex gap-0 text-center font-bold text-xs">
                                    <div class="flex-1 py-2.5 text-white" :style="{ background: themeConfig.primary }">Primary Token</div>
                                    <div class="flex-1 py-2.5 text-white" :style="{ background: themeConfig.secondary }">Secondary Token</div>
                                    <div class="flex-1 py-2.5 text-gray-900" :style="{ background: themeConfig.accent_color }">Accent Token</div>
                                </div>
                            </div>

                            <!-- Presets library -->
                            <div v-if="isSuperAdmin">
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Curated Color Presets</p>
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="preset in themePresets" :key="preset.id" type="button"
                                            @click="applyThemePreset(preset)"
                                            class="flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 hover:border-purple-300 hover:bg-purple-50 transition text-xs font-medium bg-white">
                                        <span class="flex gap-0.5">
                                            <span class="w-3.5 h-3.5 rounded-full border border-white shadow-xs" :style="{ background: preset.primary }"></span>
                                            <span class="w-3.5 h-3.5 rounded-full border border-white shadow-xs -ml-1.5" :style="{ background: preset.secondary }"></span>
                                        </span>
                                        {{ preset.label }}
                                    </button>
                                </div>
                            </div>

                            <fieldset :disabled="!isSuperAdmin" class="space-y-6">
                                <!-- Custom colours -->
                                <div class="grid sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Primary Colour</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" v-model="themeConfig.primary" :disabled="!isSuperAdmin" class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer disabled:cursor-not-allowed">
                                            <input type="text" v-model="themeConfig.primary" :disabled="!isSuperAdmin"
                                                   class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono uppercase disabled:bg-gray-50">
                                        </div>
                                        <p class="text-[11px] text-gray-400 mt-1">Navbar, primary CTAs, headings</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Secondary Colour</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" v-model="themeConfig.secondary" :disabled="!isSuperAdmin" class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer disabled:cursor-not-allowed">
                                            <input type="text" v-model="themeConfig.secondary" :disabled="!isSuperAdmin"
                                                   class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono uppercase disabled:bg-gray-50">
                                        </div>
                                        <p class="text-[11px] text-gray-400 mt-1">Hero gradient end, banners</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Accent Highlight Colour</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" v-model="themeConfig.accent_color" :disabled="!isSuperAdmin" class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer disabled:cursor-not-allowed">
                                            <input type="text" v-model="themeConfig.accent_color" :disabled="!isSuperAdmin"
                                                   class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono uppercase disabled:bg-gray-50">
                                        </div>
                                        <p class="text-[11px] text-gray-400 mt-1">Highlights, badges, badges</p>
                                    </div>
                                </div>

                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Heading Font Family</label>
                                        <select v-model="themeConfig.font_heading" :disabled="!isSuperAdmin"
                                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm bg-white disabled:bg-gray-50 disabled:cursor-not-allowed">
                                            <option value="Inter">Inter</option>
                                            <option value="Roboto">Roboto</option>
                                            <option value="Poppins">Poppins</option>
                                            <option value="Montserrat">Montserrat</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Body Font Family</label>
                                        <select v-model="themeConfig.font_body" :disabled="!isSuperAdmin"
                                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm bg-white disabled:bg-gray-50 disabled:cursor-not-allowed">
                                            <option value="Inter">Inter</option>
                                            <option value="Roboto">Roboto</option>
                                            <option value="Poppins">Poppins</option>
                                            <option value="Montserrat">Montserrat</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-4 border-t border-gray-100">
                                    <label class="text-xs font-bold text-gray-600">Type scale<select v-model="designConfig.type_scale" :disabled="!isSuperAdmin" class="mt-1.5 w-full rounded-xl border-gray-200 text-sm disabled:bg-gray-50"><option value="compact">Compact</option><option value="balanced">Balanced</option><option value="editorial">Editorial</option></select></label>
                                    <label class="text-xs font-bold text-gray-600">Density<select v-model="designConfig.density" :disabled="!isSuperAdmin" class="mt-1.5 w-full rounded-xl border-gray-200 text-sm disabled:bg-gray-50"><option value="compact">Compact</option><option value="comfortable">Comfortable</option><option value="spacious">Spacious</option></select></label>
                                    <label class="text-xs font-bold text-gray-600">Surface<select v-model="designConfig.surface" :disabled="!isSuperAdmin" class="mt-1.5 w-full rounded-xl border-gray-200 text-sm disabled:bg-gray-50"><option value="flat">Flat</option><option value="bordered">Bordered</option><option value="soft">Soft</option><option value="elevated">Elevated</option></select></label>
                                    <label class="text-xs font-bold text-gray-600">Corners<select v-model="designConfig.corners" :disabled="!isSuperAdmin" class="mt-1.5 w-full rounded-xl border-gray-200 text-sm disabled:bg-gray-50"><option value="square">Square</option><option value="soft">Soft</option><option value="rounded">Rounded</option></select></label>
                                    <label class="text-xs font-bold text-gray-600">Buttons<select v-model="designConfig.buttons" :disabled="!isSuperAdmin" class="mt-1.5 w-full rounded-xl border-gray-200 text-sm disabled:bg-gray-50"><option value="solid">Solid</option><option value="bordered">Bordered</option><option value="understated">Understated</option></select></label>
                                    <label class="text-xs font-bold text-gray-600">Images<select v-model="designConfig.images" :disabled="!isSuperAdmin" class="mt-1.5 w-full rounded-xl border-gray-200 text-sm disabled:bg-gray-50"><option value="documentary">Documentary</option><option value="vibrant">Vibrant</option><option value="formal">Formal</option><option value="monochrome">Monochrome</option></select></label>
                                    <label class="text-xs font-bold text-gray-600">Motion<select v-model="designConfig.motion" :disabled="!isSuperAdmin" class="mt-1.5 w-full rounded-xl border-gray-200 text-sm disabled:bg-gray-50"><option value="none">None</option><option value="restrained">Restrained</option><option value="expressive">Expressive</option></select></label>
                                    <label class="text-xs font-bold text-gray-600">Homepage mode<select v-model="designConfig.homepage_mode" :disabled="!isSuperAdmin" class="mt-1.5 w-full rounded-xl border-gray-200 text-sm disabled:bg-gray-50"><option value="evergreen">Evergreen</option><option value="registration_open">Registration open</option><option value="event_live">Event live</option><option value="results_published">Results published</option></select></label>
                                </div>
                            </fieldset>

                            <div v-if="isSuperAdmin" class="flex items-center gap-3 pt-3 border-t border-gray-100">
                                <button @click="saveDesign" :disabled="themeSaving"
                                        class="px-5 py-2.5 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-xs font-bold rounded-xl transition disabled:opacity-50 shadow-xs">
                                    {{ themeSaving ? 'Saving…' : 'Save Design Tokens' }}
                                </button>
                                <span v-if="themeSaved" class="text-xs text-green-600 font-bold">Saved! Refresh preview to see styling updates.</span>
                            </div>
                        </div>

                    </div>


                    <!-- 6. FOOTER LINKS & CONTACT -->
                    <div v-else-if="activeTab === 'footer'" class="space-y-6">

                        <div v-if="!isSuperAdmin" class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs text-amber-800 flex items-center gap-3">
                            <span class="text-base">🔒</span>
                            <div>
                                <p class="font-bold">Footer configuration is locked</p>
                                <p class="text-amber-700 mt-0.5">Only Super Admins can alter footer quick links, branding taglines, and contact strips. Sahodaya administrators have read-only access.</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 space-y-4">
                            <div>
                                <h2 class="font-bold text-gray-900 text-base">Footer Quick Links & Contact Details</h2>
                                <p class="text-xs text-gray-500 mt-1">Manage links, contact phone/email, and copyright statements displayed in the footer.</p>
                            </div>

                            <label class="flex items-center gap-3 pt-2" :class="isSuperAdmin ? 'cursor-pointer' : 'cursor-not-allowed'">
                                <input type="checkbox" v-model="footerIncludePortal" :disabled="!isSuperAdmin" class="w-4 h-4 rounded text-purple-600">
                                <span class="text-xs font-medium text-gray-700">Include school registration & admin login links automatically when saving</span>
                            </label>

                            <div class="space-y-2 pt-2">
                                <div v-for="(link, idx) in footerConfig.quick_links" :key="idx"
                                     class="flex flex-wrap items-center gap-3 bg-gray-50 rounded-xl p-3 border border-gray-100">
                                    <input v-model="link.label" :disabled="!isSuperAdmin" placeholder="Label (e.g. CBSE Academic Portal)"
                                           class="flex-1 min-w-[140px] border border-gray-200 rounded-lg px-3 py-2 text-sm disabled:bg-gray-100 disabled:cursor-not-allowed">
                                    <input v-model="link.url" :disabled="!isSuperAdmin" placeholder="/url-or-anchor"
                                           class="flex-1 min-w-[140px] border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono disabled:bg-gray-100 disabled:cursor-not-allowed">
                                    <button v-if="isSuperAdmin" @click="removeFooterLink(idx)" class="text-red-400 hover:text-red-600 text-lg px-2" title="Remove Link">&times;</button>
                                </div>
                            </div>

                            <button v-if="isSuperAdmin" @click="addFooterLink"
                                    class="text-xs px-3.5 py-1.5 rounded-xl border border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition">
                                + Add Footer Link
                            </button>

                            <div v-if="isSuperAdmin" class="flex items-center gap-3 pt-3 border-t border-gray-100">
                                <button @click="saveFooter" :disabled="footerSaving"
                                        class="px-5 py-2.5 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-xs font-bold rounded-xl transition disabled:opacity-50 shadow-xs">
                                    {{ footerSaving ? 'Saving…' : 'Save Footer Settings' }}
                                </button>
                                <span v-if="footerSaved" class="text-xs text-green-600 font-bold">Saved successfully!</span>
                            </div>
                        </div>

                    </div>


                    <!-- 7. TEMPLATES & DRAFTS (Super Admin) -->
                    <div v-else-if="activeTab === 'experience' && isSuperAdmin" class="space-y-6">
                        <div class="flex justify-end">
                            <PreviewToolbar v-if="selectedPreviewUrl" :url="selectedPreviewUrl" />
                        </div>
                        <ExperiencePicker :experiences="experiences"
                                          :selected-key="selectedExperienceKey"
                                          :current-key="currentSite?.template_key"
                                          @select="selectedExperienceKey = $event"
                                          @apply="applyExperienceDraft" />
                    </div>


                    <!-- 8. READINESS & PUBLISH (Super Admin) -->
                    <div v-else-if="activeTab === 'readiness' && isSuperAdmin" class="space-y-6">
                        <ReadinessPanel :report="readinessReport" />

                        <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h3 class="font-bold text-gray-950">Publication and rollback snapshot</h3>
                                <p class="text-xs text-gray-500 mt-1">Publishing a V2 experience stores a complete restore point for the current website.</p>
                            </div>
                            <div class="flex gap-2">
                                <button @click="refreshReadiness" class="px-4 py-2 rounded-xl border border-gray-200 text-xs font-bold">Run Checks</button>
                                <button v-if="experienceDraft" @click="publishExperienceDraft" :disabled="!readinessReport.ready || experienceSaving" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold disabled:opacity-50">Publish V2 Draft</button>
                            </div>
                        </div>

                        <div v-if="siteVersions.length" class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6">
                            <h3 class="font-bold text-gray-950 mb-4 text-sm">Automated Restore Points</h3>
                            <div class="divide-y divide-gray-100">
                                <div v-for="version in siteVersions" :key="version.id" class="py-3 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-bold text-gray-800">{{ experienceName(version.template_key) || 'Classic Website' }}</p>
                                        <p class="text-[11px] text-gray-400">{{ version.action.replace(/_/g, ' ') }} · {{ formatDate(version.created_at) }}</p>
                                    </div>
                                    <button @click="restoreSiteVersion(version)" class="text-xs font-bold text-purple-700 hover:underline">Restore</button>
                                </div>
                            </div>
                        </div>

                        <div v-if="currentSite?.is_primary" class="rounded-2xl border border-gray-200 bg-gray-50 p-5 flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h3 class="font-bold text-gray-800 text-sm">Legacy Confederation Classic</h3>
                                <p class="text-xs text-gray-500 mt-1">Kept only for legacy sites that still need the original CKSC-compatible layout.</p>
                            </div>
                            <button @click="applyCkscTemplate" :disabled="ckscTemplateSaving" class="px-4 py-2 text-xs font-bold rounded-xl border border-gray-300 bg-white hover:bg-gray-50">
                                {{ ckscTemplateSaving ? 'Applying…' : 'Apply Legacy Template' }}
                            </button>
                        </div>
                    </div>

                </main>
            </div>
            </template>
        </div>

        <!-- Add Section Modal -->
        <Teleport to="body">
            <div v-if="addModal.open" class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between z-10">
                        <h3 class="font-bold text-gray-900 text-base">Add Section to Website</h3>
                        <button @click="addModal.open = false"
                                class="w-8 h-8 flex items-center justify-center rounded-xl hover:bg-gray-100 transition text-gray-400 text-xl">&times;</button>
                    </div>

                    <!-- Section type grid -->
                    <div class="p-6 space-y-5">
                        <div v-if="!addModal.selectedType">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-3">Choose a section type</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <button v-for="(variants, type) in sectionTypes" :key="type"
                                        @click="addModal.selectedType = type; addModal.selectedVariant = variants[0]"
                                        class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:border-purple-200 hover:bg-purple-50/50 transition text-left group">
                                    <span class="text-xl w-9 h-9 rounded-lg flex items-center justify-center bg-gray-50 group-hover:bg-purple-100 transition shrink-0">
                                        {{ sectionIcon(type) }}
                                    </span>
                                    <span class="text-xs font-semibold text-gray-700 capitalize">{{ sectionTypeLabel(type) }}</span>
                                </button>
                            </div>
                        </div>

                        <div v-else class="space-y-5">
                            <button @click="addModal.selectedType = null" class="text-xs text-purple-600 hover:underline font-semibold flex items-center gap-1">
                                ← Back to section types
                            </button>
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-3">
                                    Choose design for {{ sectionTypeLabel(addModal.selectedType) }}
                                </p>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    <button v-for="v in (sectionTypes[addModal.selectedType] || [])" :key="v"
                                            @click="addModal.selectedVariant = v"
                                            :class="[
                                                'p-4 rounded-xl border-2 text-left transition space-y-1',
                                                addModal.selectedVariant === v
                                                    ? 'border-[#1e1b4b] bg-[#1e1b4b]/5'
                                                    : 'border-gray-100 hover:border-purple-200'
                                            ]">
                                        <p class="text-xs font-bold text-gray-700 capitalize">{{ v.replace(/-/g,' ') }}</p>
                                        <p class="text-[11px] text-gray-400">
                                            {{ fieldDefs[addModal.selectedType]?.[v]?.description || 'Variant design layout' }}
                                        </p>
                                    </button>
                                </div>
                            </div>

                            <div class="flex gap-3 pt-2 border-t border-gray-100">
                                <button @click="createSection" :disabled="!addModal.selectedVariant || addModal.saving"
                                        class="flex-1 py-2.5 bg-[#1e1b4b] hover:bg-[#312e81] text-white font-bold rounded-xl transition disabled:opacity-50 text-xs shadow-xs">
                                    {{ addModal.saving ? 'Adding…' : `Add ${sectionTypeLabel(addModal.selectedType)} Section` }}
                                </button>
                                <button @click="addModal.open = false"
                                        class="px-5 py-2.5 border border-gray-200 text-gray-600 font-semibold rounded-xl hover:bg-gray-50 transition text-xs">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { ref, reactive, computed, defineComponent, h, onMounted, watch } from 'vue';
import ExperiencePicker from '@/Components/sahodaya/website/ExperiencePicker.vue';
import ReadinessPanel from '@/Components/sahodaya/website/ReadinessPanel.vue';
import SectionLayoutEditor from '@/Components/sahodaya/website/SectionLayoutEditor.vue';
import PreviewToolbar from '@/Components/sahodaya/website/PreviewToolbar.vue';
import SvgIcon from '@/Components/icons/SvgIcon.vue';
import { useConfirm } from '@/composables/useConfirm';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    sahodaya:                Object,
    publicUrl:               { type: String, default: null },
    pendingSchoolsCount:     { type: Number, default: 0 },
    pendingSubmissionsCount: { type: Number, default: 0 },
    pendingPaymentsCount:    { type: Number, default: 0 },
    sections:                { type: Array,  default: () => [] },
    sectionTypes:            { type: Object, default: () => ({}) },
    fieldDefs:               { type: Object, default: () => ({}) },
    navConfig:               { type: Object, default: () => ({}) },
    footerConfig:            { type: Object, default: () => ({}) },
    portalDefaults:          { type: Object, default: () => ({}) },
    publicWebsiteEnabled:    { type: Boolean, default: true },
    defaultNavConfig:        { type: Object, default: () => ({}) },
    navLayoutOptions:        { type: Array,  default: () => [] },
    navNeedsSetup:           { type: Boolean, default: false },
    themeConfig:             { type: Object, default: () => ({}) },
    themePresets:            { type: Array,  default: () => [] },
    sites:                   { type: Array,  default: () => [] },
    currentSite:             { type: Object, default: null },
    experiences:             { type: Array, default: () => [] },
    readiness:               { type: Object, default: () => ({ ready: false, errors: [], warnings: [], score: 0 }) },
    isSuperAdmin:            { type: Boolean, default: false },
});

const page = usePage();
const { confirm } = useConfirm();

const activeTab = ref('overview');
const selectedSectionId = ref(null);

const sections    = ref([...(props.sections ?? [])]);
const editConfigs = reactive({});
const editLayouts = reactive({});
const saving      = reactive({});
const sectionVersions = reactive({});
const dragIndex = ref(null);

const navConfig = reactive({
    layout_variant: props.navConfig?.layout_variant ?? 'sahodaya-modern',
    items: [...(props.navConfig?.items ?? [])],
    portal_cta: {
        ...(props.portalDefaults ?? {}),
        ...(props.navConfig?.portal_cta ?? {}),
    },
});
const footerConfig = reactive({
    quick_links: [...(props.footerConfig?.quick_links ?? [])],
    tagline: props.footerConfig?.tagline ?? '',
    copyright: props.footerConfig?.copyright ?? '',
    phone: props.footerConfig?.phone ?? '',
    email: props.footerConfig?.email ?? '',
    layout_variant: props.footerConfig?.layout_variant ?? 'three-column',
});
const footerIncludePortal = ref(true);
const navSaving = ref(false);
const navSaved = ref(false);
const footerSaving = ref(false);
const footerSaved = ref(false);
const portalSaving = ref(false);
const publicWebsiteEnabled = ref(props.publicWebsiteEnabled ?? true);
const publicWebsiteSaving = ref(false);
const defaultNavSaving = ref(false);
const ckscTemplateSaving = ref(false);
const themeSaving = ref(false);
const themeSaved = ref(false);
const themeConfig = reactive({
    primary: props.themeConfig?.primary ?? '#1e40af',
    secondary: props.themeConfig?.secondary ?? '#7c3aed',
    accent_color: props.themeConfig?.accent_color ?? '#f59e0b',
    font_heading: props.themeConfig?.font_heading ?? 'Inter',
    font_body: props.themeConfig?.font_body ?? 'Inter',
});
const siteDesign = props.currentSite?.design_json ?? {};
const designConfig = reactive({
    type_scale: siteDesign.type_scale ?? 'balanced',
    density: siteDesign.density ?? 'comfortable',
    surface: siteDesign.surface ?? 'bordered',
    corners: siteDesign.corners ?? 'soft',
    buttons: siteDesign.buttons ?? 'solid',
    images: siteDesign.images ?? 'documentary',
    motion: siteDesign.motion ?? 'restrained',
    navigation: siteDesign.navigation ?? 'directory',
    footer: siteDesign.footer ?? 'directory',
    homepage_mode: props.currentSite?.homepage_mode ?? 'evergreen',
    homepage_mode_override_until: props.currentSite?.homepage_mode_override_until ?? '',
});
const themePresets = props.themePresets?.length ? props.themePresets : [];
const experiences = props.experiences ?? [];
const selectedExperienceKey = ref(props.currentSite?.draft_template_json?.template_key ?? props.currentSite?.template_key ?? props.experiences?.[0]?.key ?? null);
const experienceDraft = ref(props.currentSite?.draft_template_json ?? null);
const readinessReport = ref({ ...(props.readiness ?? {}) });
const experienceSaving = ref(false);
const siteVersions = ref([]);
const currentSite = computed(() => props.currentSite ?? props.sites?.[0] ?? null);
const selectedPublicUrl = computed(() => {
    if (!props.publicUrl || !currentSite.value) return null;
    const base = props.publicUrl.replace(/\/$/, '');
    return currentSite.value.is_primary ? base : `${base}/m/${currentSite.value.slug}`;
});
const selectedPreviewUrl = computed(() => {
    if (!props.publicUrl || !currentSite.value) return null;
    return `${props.publicUrl.replace(/\/$/, '')}/preview-site?site_id=${currentSite.value.id}`;
});

const currentEditingSection = computed(() => {
    if (!selectedSectionId.value) return null;
    return sections.value.find(s => s.id === selectedSectionId.value) || null;
});

const currentSectionIndex = computed(() => {
    if (!selectedSectionId.value) return -1;
    return sections.value.findIndex(s => s.id === selectedSectionId.value);
});

const prevSection = computed(() => {
    const idx = currentSectionIndex.value;
    return idx > 0 ? sections.value[idx - 1] : null;
});

const nextSection = computed(() => {
    const idx = currentSectionIndex.value;
    return (idx >= 0 && idx < sections.value.length - 1) ? sections.value[idx + 1] : null;
});

const addModal = reactive({
    open: false, selectedType: null, selectedVariant: null, saving: false,
});

// ── Tab & Section Selection with URL synchronization ─────────────────────────

function selectTab(tabId) {
    activeTab.value = tabId;
    selectedSectionId.value = null;
    syncUrl();
}

function selectSection(section) {
    if (!section) return;
    selectedSectionId.value = section.id;
    activeTab.value = 'section-edit';
    if (!editConfigs[section.id]) {
        editConfigs[section.id] = { ...(section.config ?? {}) };
    }
    if (!editLayouts[section.id]) {
        editLayouts[section.id] = { ...(section.layout_json ?? {}) };
    }
    loadSectionVersions(section);
    syncUrl();
}

function syncUrl() {
    try {
        const url = new URL(window.location.href);
        if (activeTab.value === 'section-edit' && selectedSectionId.value) {
            url.searchParams.set('tab', 'section');
            url.searchParams.set('section', selectedSectionId.value);
        } else if (activeTab.value === 'overview') {
            url.searchParams.delete('tab');
            url.searchParams.delete('section');
        } else {
            url.searchParams.set('tab', activeTab.value);
            url.searchParams.delete('section');
        }
        window.history.replaceState({}, '', url.toString());
        page.url = url.pathname + url.search;
    } catch (e) {
        console.error(e);
    }
}

function syncFromUrl(targetUrl = window.location.href) {
    try {
        const parsed = new URL(targetUrl, window.location.origin);
        const tab = parsed.searchParams.get('tab');
        const sectionParam = parsed.searchParams.get('section');

        if ((tab === 'section' || tab === 'section-edit') && sectionParam) {
            const secId = Number(sectionParam) || sectionParam;
            const found = sections.value.find(s => String(s.id) === String(secId));
            if (found) {
                selectedSectionId.value = found.id;
                activeTab.value = 'section-edit';
                if (!editConfigs[found.id]) {
                    editConfigs[found.id] = { ...(found.config ?? {}) };
                }
                if (!editLayouts[found.id]) {
                    editLayouts[found.id] = { ...(found.layout_json ?? {}) };
                }
                loadSectionVersions(found);
                return;
            }
        }

        if (tab && ['sections', 'navigation', 'theme', 'footer', 'experience', 'readiness', 'overview'].includes(tab)) {
            activeTab.value = tab;
            selectedSectionId.value = null;
            return;
        }

        // Fallbacks
        if (props.navNeedsSetup) {
            activeTab.value = 'navigation';
        } else if (props.isSuperAdmin && props.currentSite?.experience_version !== 'v2') {
            activeTab.value = 'experience';
        } else {
            activeTab.value = 'overview';
        }
        selectedSectionId.value = null;
    } catch (e) {
        console.error(e);
    }
}

onMounted(() => {
    syncFromUrl();
    window.addEventListener('popstate', () => syncFromUrl());
    if (props.isSuperAdmin) {
        loadVersions();
    }
});

watch(() => page.url, (newUrl) => {
    syncFromUrl(newUrl);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

const iconMap = {
    hero: '🖼️', about_sahodaya: '📖', office_bearers: '👥', member_schools: '🏫',
    news_circulars: '📰', events_programs: '📅', kalotsav: '🏆', sports_meet: '🏅',
    statistics: '📊', programmes: '🎓', academic_quicklinks: '🔗', downloads_sahodaya: '📥',
    circulars: '📄', testimonials_sahodaya: '💬', useful_links: '🌐', gallery: '🖼',
    contact: '📞', newsletter: '📧', sahodaya_home: '🏠',
};
const colorMap = {
    hero: '#f3f0ff', about_sahodaya: '#f0fdf4', office_bearers: '#fdf4ff',
    member_schools: '#eff6ff', news_circulars: '#fffbeb', events_programs: '#f0fdf4',
    kalotsav: '#fdf4ff', sports_meet: '#f0fdfa', statistics: '#eff6ff',
    programmes: '#faf5ff', circulars: '#fefce8', contact: '#f0fdf4',
    newsletter: '#fdf2f8', gallery: '#f5f3ff',
};

function sectionIcon(type)  { return iconMap[type] ?? '⚡'; }
function sectionColor(type) { return colorMap[type] ?? '#f9fafb'; }
function sectionTypeLabel(type) {
    return (type ?? '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}
function variantsFor(type) { return props.sectionTypes[type] ?? []; }
function fieldsFor(type, variant) { return props.fieldDefs?.[type]?.[variant]?.fields ?? []; }
function sectionPreview(s) {
    const cfg = s.config ?? {};
    return cfg.heading ?? cfg.title ?? cfg.tagline ?? (fieldsFor(s.section_type, s.variant).length ? 'Click to configure section content' : 'Data-driven database section');
}
function sourceBadge(type) {
    if (['office_bearers', 'member_schools', 'events_programs', 'news_circulars', 'sports_meet', 'resource_centre', 'sahodaya_action_hub'].includes(type)) return 'ERP';
    if (['statistics', 'programmes', 'downloads_sahodaya', 'academic_quicklinks'].includes(type)) return 'Mixed';
    return 'Manual';
}
function formatDate(d) {
    if (!d) return '';
    try { return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }); } catch { return ''; }
}

// ── API calls ────────────────────────────────────────────────────────────────

function csrf() { return document.querySelector('meta[name=csrf-token]')?.content ?? ''; }
const baseUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/site-builder/api`);

function switchSite(siteId) {
    const url = new URL(window.location.href);
    url.searchParams.set('site_id', siteId);
    window.location.assign(url.toString());
}

function scopedBody(path, body = {}) {
    if ((path.startsWith('/sections') || path.startsWith('/experience') || path === '/design' || path === '/apply-cksc-template') && currentSite.value?.id) {
        return { ...body, site_id: currentSite.value.id };
    }
    return body;
}

async function apiPatch(path, body) {
    const r = await fetch(`${baseUrl.value}${path}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(scopedBody(path, body)),
    });
    const data = await r.json();
    if (!r.ok) throw new Error(data.message || 'Unable to save changes.');
    return data;
}
async function apiPost(path, body) {
    const r = await fetch(`${baseUrl.value}${path}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(scopedBody(path, body)),
    });
    const data = await r.json();
    if (!r.ok) throw new Error(data.message || 'Unable to save changes.');
    return data;
}
async function apiGet(path) {
    const join = path.includes('?') ? '&' : '?';
    const siteQuery = currentSite.value?.id ? `${join}site_id=${currentSite.value.id}` : '';
    const r = await fetch(`${baseUrl.value}${path}${siteQuery}`, { headers: { 'Accept': 'application/json' } });
    const data = await r.json();
    if (!r.ok) throw new Error(data.message || 'Unable to load data.');
    return data;
}
async function apiDelete(path) {
    const query = currentSite.value?.id ? `?site_id=${currentSite.value.id}` : '';
    await fetch(`${baseUrl.value}${path}${query}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
    });
}

function mediaPreviewUrl(stored) {
    if (!stored) return '';
    if (stored.startsWith('http') || stored.startsWith('/')) return stored;
    return `/storage/${stored.replace(/^\//, '')}`;
}

async function uploadSiteMedia(file) {
    const fd = new FormData();
    fd.append('file', file);
    const r = await fetch(`${baseUrl.value}/media`, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: fd,
    });
    if (!r.ok) {
        const err = await r.json().catch(() => ({}));
        throw new Error(err.message || 'Image upload failed');
    }
    const data = await r.json();
    return data.path ?? data.url;
}

// ── Nav & footer actions ──────────────────────────────────────────────────────

function addNavItem() {
    navConfig.items.push({ label: '', url: '/', external: false, children: [] });
}
function removeNavItem(idx) {
    navConfig.items.splice(idx, 1);
}
async function saveNav() {
    navSaving.value = true;
    navSaved.value = false;
    try {
        const r = await apiPost('/nav', {
            layout_variant: navConfig.layout_variant,
            items: navConfig.items,
            portal_cta: navConfig.portal_cta,
        });
        if (r.nav?.items) navConfig.items = r.nav.items;
        navSaved.value = true;
        setTimeout(() => { navSaved.value = false; }, 2500);
    } finally {
        navSaving.value = false;
    }
}

function addFooterLink() {
    if (!Array.isArray(footerConfig.quick_links)) footerConfig.quick_links = [];
    footerConfig.quick_links.push({ label: '', url: '/' });
}
function removeFooterLink(idx) {
    footerConfig.quick_links.splice(idx, 1);
}
async function saveFooter() {
    footerSaving.value = true;
    footerSaved.value = false;
    try {
        const r = await apiPost('/footer', {
            ...footerConfig,
            include_portal_links: footerIncludePortal.value,
        });
        if (r.footer?.quick_links) footerConfig.quick_links = r.footer.quick_links;
        footerSaved.value = true;
        setTimeout(() => { footerSaved.value = false; }, 2500);
    } finally {
        footerSaving.value = false;
    }
}

async function ensurePortalLinks() {
    portalSaving.value = true;
    try {
        const r = await apiPost('/portal-links', {});
        if (r.nav) {
            navConfig.items = r.nav.items ?? navConfig.items;
            navConfig.portal_cta = { ...navConfig.portal_cta, ...(r.nav.portal_cta ?? {}) };
        }
        if (r.footer?.quick_links) footerConfig.quick_links = r.footer.quick_links;
        navSaved.value = true;
        footerSaved.value = true;
        setTimeout(() => { navSaved.value = false; footerSaved.value = false; }, 2500);
    } finally {
        portalSaving.value = false;
    }
}

const navLayoutOptions = props.navLayoutOptions?.length
    ? props.navLayoutOptions
    : [{ value: 'sahodaya-modern', label: 'Sahodaya Modern' }];

async function loadDefaultNav() {
    defaultNavSaving.value = true;
    try {
        const r = await apiPost('/default-nav', {});
        if (r.nav) {
            navConfig.layout_variant = r.nav.layout_variant ?? navConfig.layout_variant;
            navConfig.items = r.nav.items ?? [];
            navConfig.portal_cta = { ...navConfig.portal_cta, ...(r.nav.portal_cta ?? {}) };
        }
        if (r.footer?.quick_links) footerConfig.quick_links = r.footer.quick_links;
        navSaved.value = true;
        setTimeout(() => { navSaved.value = false; }, 2500);
    } finally {
        defaultNavSaving.value = false;
    }
}

async function applyCkscTemplate() {
    if (!(await confirm({ message: 'Replace homepage sections with the CKSC layout (pill menu, hero slider, About, Services, Journey, Gallery, etc.)? Your saved theme colours will be kept.' }))) {
        return;
    }
    ckscTemplateSaving.value = true;
    try {
        const r = await apiPost('/apply-cksc-template', { replace_sections: true });
        if (r.nav) {
            navConfig.layout_variant = r.nav.layout_variant ?? 'cksc-pill';
            navConfig.items = r.nav.items ?? [];
            navConfig.portal_cta = { ...navConfig.portal_cta, ...(r.nav.portal_cta ?? {}) };
        }
        if (r.sections) sections.value = r.sections;
        navSaved.value = true;
        setTimeout(() => { navSaved.value = false; }, 3000);
    } finally {
        ckscTemplateSaving.value = false;
    }
}

function applyThemePreset(preset) {
    themeConfig.primary = preset.primary;
    themeConfig.secondary = preset.secondary;
    themeConfig.accent_color = preset.accent_color ?? themeConfig.accent_color;
}

function experienceName(key) {
    return experiences.find(item => item.key === key)?.name ?? (key ? key.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : '');
}

async function applyExperienceDraft(key, mode = 'full') {
    if (!(await confirm({ message: `${mode === 'style' ? 'Apply the design character from' : 'Create a complete draft using'} “${experienceName(key)}”? The published website will not change.`, destructive: false }))) return;
    experienceSaving.value = true;
    try {
        const response = await apiPost('/experience/draft', { template_key: key, mode });
        experienceDraft.value = response.draft;
        readinessReport.value = response.readiness;
        selectedExperienceKey.value = key;
    } finally { experienceSaving.value = false; }
}

async function cancelExperienceDraft() {
    if (!(await confirm({ message: 'Cancel this experience draft? The published website will remain unchanged.' }))) return;
    await apiPost('/experience/cancel', {});
    experienceDraft.value = null;
    await refreshReadiness();
}

async function publishExperienceDraft() {
    if (!(await confirm({ message: 'Publish this complete V2 experience now? A restore point will be created automatically.', destructive: false }))) return;
    experienceSaving.value = true;
    try {
        const response = await apiPost('/experience/publish', {});
        experienceDraft.value = null;
        sections.value = response.sections ?? sections.value;
        if (response.site) Object.assign(props.currentSite, response.site);
        await Promise.all([refreshReadiness(), loadVersions()]);
    } finally { experienceSaving.value = false; }
}

async function refreshReadiness() { readinessReport.value = await apiGet('/readiness'); }
async function loadVersions() { siteVersions.value = await apiGet('/experience/versions'); }
async function restoreSiteVersion(version) {
    if (!(await confirm({ message: `Restore the website version from ${formatDate(version.created_at)}? The current website will also be saved as a restore point.` }))) return;
    const response = await apiPost(`/experience/versions/${version.id}/restore`, {});
    sections.value = response.sections ?? sections.value;
    if (response.site) Object.assign(props.currentSite, response.site);
    await Promise.all([refreshReadiness(), loadVersions()]);
}

async function saveDesign() {
    themeSaving.value = true;
    const supportedHeading = ['Inter', 'Manrope', 'Merriweather', 'Roboto'].includes(themeConfig.font_heading) ? themeConfig.font_heading : 'Inter';
    const supportedBody = ['Inter', 'Manrope', 'Roboto'].includes(themeConfig.font_body) ? themeConfig.font_body : 'Inter';
    try {
        await apiPost('/design', {
            primary: themeConfig.primary, secondary: themeConfig.secondary, accent_color: themeConfig.accent_color,
            display_font: supportedHeading, body_font: supportedBody, ...designConfig,
        });
        await saveTheme();
        themeSaved.value = true;
    } finally { themeSaving.value = false; }
}

async function saveTheme() {
    themeSaving.value = true;
    try {
        const r = await apiPost('/theme', { ...themeConfig });
        if (r.theme) {
            Object.assign(themeConfig, r.theme);
        }
        themeSaved.value = true;
        setTimeout(() => { themeSaved.value = false; }, 3000);
    } finally {
        themeSaving.value = false;
    }
}

async function togglePublicWebsite() {
    publicWebsiteSaving.value = true;
    try {
        const enabled = !publicWebsiteEnabled.value;
        const r = await apiPost('/public-website', { enabled });
        publicWebsiteEnabled.value = r.enabled ?? enabled;
    } finally {
        publicWebsiteSaving.value = false;
    }
}

// ── Section actions ───────────────────────────────────────────────────────────

async function loadSectionVersions(section) {
    if (!section?.id) return;
    sectionVersions[section.id] = await apiGet(`/sections/${section.id}/versions`);
}

async function restorePublishedVersion(section, versionId) {
    if (!versionId || !(await confirm({ message: 'Restore this saved version as an unpublished draft?', destructive: false }))) return;
    const updated = await apiPost(`/sections/${section.id}/versions/${versionId}/restore`, {});
    Object.assign(section, updated);
    editConfigs[section.id] = { ...(updated.config ?? {}) };
    editLayouts[section.id] = { ...(updated.layout_json ?? {}) };
    await loadSectionVersions(section);
}

async function toggleActive(section) {
    const updated = await apiPost(`/sections/${section.id}/toggle`, {});
    Object.assign(section, updated);
}

async function saveSection(section) {
    saving[section.id] = true;
    try {
        const config = editConfigs[section.id] ?? section.config ?? {};
        const updated = await apiPatch(`/sections/${section.id}`, { config, layout_json: editLayouts[section.id] ?? section.layout_json ?? {}, status: 'draft' });
        const idx = sections.value.findIndex(s => s.id === section.id);
        if (idx !== -1) Object.assign(sections.value[idx], updated);
    } finally {
        saving[section.id] = false;
    }
}

async function publishSection(section) {
    saving[section.id] = true;
    try {
        const config = editConfigs[section.id] ?? section.config ?? {};
        await apiPatch(`/sections/${section.id}`, { config, layout_json: editLayouts[section.id] ?? section.layout_json ?? {}, status: 'draft' });
        const updated = await apiPost(`/sections/${section.id}/publish`, {});
        const idx = sections.value.findIndex(s => s.id === section.id);
        if (idx !== -1) Object.assign(sections.value[idx], updated);
    } finally {
        saving[section.id] = false;
    }
}

async function switchVariant(section, newVariant) {
    if (newVariant === section.variant) return;
    const msg = `Switch layout from "${section.variant}" to "${newVariant}"?\n\nCurrent content will be archived and you can restore it anytime.`;
    if (!(await confirm({ message: msg, destructive: false }))) return;
    const updated = await apiPatch(`/sections/${section.id}`, { variant: newVariant });
    const idx = sections.value.findIndex(s => s.id === section.id);
    if (idx !== -1) Object.assign(sections.value[idx], updated);
    editConfigs[section.id] = {};
}

function restoreArchived(section, archiveIdx) {
    if (archiveIdx === '') return;
    const arc = section.archived_configs?.[archiveIdx];
    if (!arc) return;
    editConfigs[section.id] = { ...(arc.config ?? {}) };
}

async function removeSection(section) {
    if (!(await confirm({ message: `Delete the "${sectionTypeLabel(section.section_type)} / ${section.variant}" section?\n\nThis cannot be undone.` }))) return;
    await apiDelete(`/sections/${section.id}`);
    sections.value = sections.value.filter(s => s.id !== section.id);
    if (selectedSectionId.value === section.id) {
        selectTab('sections');
    }
}

async function duplicateSection(section) {
    saving[section.id] = true;
    try {
        const copy = await apiPost(`/sections/${section.id}/duplicate`, {});
        sections.value.push(copy);
        selectSection(copy);
    } finally { saving[section.id] = false; }
}

async function moveUp(idx) {
    if (idx === 0) return;
    [sections.value[idx - 1], sections.value[idx]] = [sections.value[idx], sections.value[idx - 1]];
    await saveOrder();
}
async function moveDown(idx) {
    if (idx === sections.value.length - 1) return;
    [sections.value[idx], sections.value[idx + 1]] = [sections.value[idx + 1], sections.value[idx]];
    await saveOrder();
}
async function saveOrder() {
    await apiPost('/sections/reorder', { ids: sections.value.map(s => s.id) });
}
async function dropSection(targetIndex) {
    if (dragIndex.value === null || dragIndex.value === targetIndex) return;
    const [moved] = sections.value.splice(dragIndex.value, 1);
    sections.value.splice(targetIndex, 0, moved);
    dragIndex.value = null;
    await saveOrder();
}

function openAddModal() {
    addModal.open = true;
    addModal.selectedType = null;
    addModal.selectedVariant = null;
    addModal.saving = false;
}

async function createSection() {
    if (!addModal.selectedType || !addModal.selectedVariant) return;
    addModal.saving = true;
    try {
        const r = await fetch(`${baseUrl.value}/sections`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({
                section_type: addModal.selectedType,
                variant:      addModal.selectedVariant,
                config:       {},
                is_active:    false,
                site_id:      currentSite.value?.id,
            }),
        });
        const newSection = await r.json();
        sections.value.push(newSection);
        addModal.open = false;
        // Open dedicated page editor for newly created section
        selectSection(newSection);
    } finally {
        addModal.saving = false;
    }
}

// ── Inline Field Editor Component ────────────────────────────────────────────

const SectionFieldEditor = defineComponent({
    props: {
        fields: Array,
        config: Object,
        uploadMedia: Function,
        mediaPreview: Function,
    },
    emits: ['update'],
    setup(props, { emit }) {
        const local = reactive({ ...(props.config ?? {}) });
        const mediaUploading = reactive({});
        const mediaError = reactive({});

        function onInput(key, val) {
            local[key] = val;
            emit('update', { ...local });
        }

        function mediaKey(scope, key, idx = null) {
            return idx === null ? scope : `${scope}-${idx}-${key}`;
        }

        function renderMediaField(label, value, onChange, key) {
            const preview = props.mediaPreview?.(value) ?? value;
            const uploading = !!mediaUploading[key];

            return h('div', { class: 'space-y-2 col-span-2' }, [
                h('label', { class: 'text-xs text-gray-500 font-bold block' }, label),
                preview
                    ? h('div', { class: 'relative rounded-xl overflow-hidden border border-gray-200 bg-gray-50' }, [
                        h('img', {
                            src: preview,
                            alt: label,
                            class: 'w-full h-32 object-cover',
                        }),
                        h('button', {
                            type: 'button',
                            onClick: () => onChange(''),
                            class: 'absolute top-2 right-2 bg-white/95 text-red-600 text-xs px-2.5 py-1 rounded-lg font-bold shadow-xs hover:bg-white',
                        }, 'Remove Image'),
                    ])
                    : null,
                h('input', {
                    type: 'file',
                    accept: 'image/jpeg,image/png,image/webp,image/gif',
                    disabled: uploading,
                    onChange: async (e) => {
                        const file = e.target.files?.[0];
                        if (!file || !props.uploadMedia) return;
                        mediaUploading[key] = true;
                        mediaError[key] = '';
                        try {
                            const path = await props.uploadMedia(file);
                            onChange(path);
                        } catch (err) {
                            mediaError[key] = err.message || 'Upload failed';
                        } finally {
                            mediaUploading[key] = false;
                            e.target.value = '';
                        }
                    },
                    class: 'block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100',
                }),
                mediaError[key]
                    ? h('p', { class: 'text-xs text-red-600 font-medium' }, mediaError[key])
                    : null,
                h('input', {
                    type: 'url',
                    value: value ?? '',
                    placeholder: 'Or paste external image URL',
                    onInput: e => onChange(e.target.value),
                    class: 'w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-purple-200 focus:outline-none',
                }),
                uploading ? h('p', { class: 'text-xs text-purple-600 font-semibold' }, 'Uploading image…') : null,
            ]);
        }

        function onRepeaterAdd(key, itemDef) {
            if (!Array.isArray(local[key])) local[key] = [];
            const blank = Object.fromEntries((itemDef ?? []).map(f => [f.key, '']));
            local[key] = [...local[key], blank];
            emit('update', { ...local });
        }

        function onRepeaterRemove(key, idx) {
            local[key] = local[key].filter((_, i) => i !== idx);
            emit('update', { ...local });
        }

        function onRepeaterMove(key, idx, direction) {
            const arr = [...(local[key] ?? [])];
            const target = idx + direction;
            if (target < 0 || target >= arr.length) return;
            [arr[idx], arr[target]] = [arr[target], arr[idx]];
            local[key] = arr;
            emit('update', { ...local });
        }

        function onRepeaterField(key, idx, fieldKey, val) {
            const arr = [...(local[key] ?? [])];
            arr[idx] = { ...arr[idx], [fieldKey]: val };
            local[key] = arr;
            emit('update', { ...local });
        }

        return () => h('div', { class: 'space-y-4' }, (props.fields ?? []).map(field => {
            if (field.type === 'repeater') {
                return h('div', { key: field.key, class: 'space-y-3' }, [
                    h('div', { class: 'flex items-center justify-between' }, [
                        h('label', { class: 'text-xs font-bold text-gray-800' }, field.label),
                        h('button', {
                            type: 'button',
                            onClick: () => onRepeaterAdd(field.key, field.fields),
                            class: 'text-xs px-2.5 py-1 rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-700 font-bold transition',
                        }, '+ Add Item'),
                    ]),
                    ...(local[field.key] ?? []).map((item, idx) =>
                        h('div', { key: idx, class: 'border border-gray-200 bg-white rounded-xl p-4 space-y-3 shadow-2xs' }, [
                            h('div', { class: 'grid grid-cols-2 gap-3' },
                                (field.fields ?? []).map(sub =>
                                    sub.type === 'media'
                                        ? renderMediaField(
                                            sub.label,
                                            item[sub.key] ?? '',
                                            val => onRepeaterField(field.key, idx, sub.key, val),
                                            mediaKey(field.key, sub.key, idx),
                                        )
                                        : h('div', { key: sub.key }, [
                                        h('label', { class: 'text-[11px] text-gray-500 font-bold block mb-1' }, sub.label),
                                        sub.type === 'textarea'
                                            ? h('textarea', {
                                                rows: 2,
                                                value: item[sub.key] ?? '',
                                                onInput: e => onRepeaterField(field.key, idx, sub.key, e.target.value),
                                                class: 'w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-purple-200 focus:outline-none',
                                            })
                                            : h('input', {
                                                type: sub.type === 'url' ? 'url' : sub.type === 'color' ? 'color' : 'text',
                                                value: item[sub.key] ?? '',
                                                onInput: e => onRepeaterField(field.key, idx, sub.key, e.target.value),
                                                class: 'w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-purple-200 focus:outline-none',
                                            }),
                                    ])
                                )
                            ),
                            h('div', { class: 'flex items-center justify-between pt-2 border-t border-gray-100' }, [
                                h('div', { class: 'flex items-center gap-2' }, [
                                    h('button', {
                                        type: 'button',
                                        onClick: () => onRepeaterMove(field.key, idx, -1),
                                        disabled: idx === 0,
                                        class: 'text-xs text-gray-500 hover:text-gray-800 disabled:opacity-30 disabled:cursor-not-allowed px-2 py-0.5 rounded border border-gray-200',
                                        title: 'Move up',
                                    }, '↑'),
                                    h('button', {
                                        type: 'button',
                                        onClick: () => onRepeaterMove(field.key, idx, 1),
                                        disabled: idx === (local[field.key] ?? []).length - 1,
                                        class: 'text-xs text-gray-500 hover:text-gray-800 disabled:opacity-30 disabled:cursor-not-allowed px-2 py-0.5 rounded border border-gray-200',
                                        title: 'Move down',
                                    }, '↓'),
                                ]),
                                h('button', {
                                    type: 'button',
                                    onClick: () => onRepeaterRemove(field.key, idx),
                                    class: 'text-xs text-red-500 hover:text-red-700 font-semibold',
                                }, 'Delete Item'),
                            ]),
                        ])
                    ),
                ]);
            }

            if (field.type === 'switch') {
                return h('label', { key: field.key, class: 'flex items-center gap-3 cursor-pointer p-3 bg-gray-50 rounded-xl' }, [
                    h('input', {
                        type: 'checkbox',
                        checked: !!local[field.key],
                        onChange: e => onInput(field.key, e.target.checked),
                        class: 'w-4 h-4 rounded text-purple-600',
                    }),
                    h('span', { class: 'text-xs font-bold text-gray-700' }, field.label),
                ]);
            }

            if (field.type === 'textarea' || field.type === 'wysiwyg') {
                return h('div', { key: field.key }, [
                    h('label', { class: 'block text-xs font-bold text-gray-700 mb-1.5' }, field.label),
                    h('textarea', {
                        rows: 3,
                        value: local[field.key] ?? '',
                        onInput: e => onInput(field.key, e.target.value),
                        class: 'w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-200 focus:outline-none',
                    }),
                ]);
            }

            if (field.type === 'select') {
                return h('div', { key: field.key }, [
                    h('label', { class: 'block text-xs font-bold text-gray-700 mb-1.5' }, field.label),
                    h('select', {
                        value: local[field.key] ?? field.default ?? '',
                        onChange: e => onInput(field.key, e.target.value),
                        class: 'w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-200 focus:outline-none bg-white',
                    }, (field.options ?? []).map(opt =>
                        h('option', { value: opt.value ?? opt, key: opt.value ?? opt }, opt.label ?? opt)
                    )),
                ]);
            }

            if (field.type === 'color') {
                return h('div', { key: field.key, class: 'flex items-center gap-3' }, [
                    h('label', { class: 'text-xs font-bold text-gray-700 flex-1' }, field.label),
                    h('div', { class: 'flex items-center gap-2' }, [
                        h('input', {
                            type: 'color',
                            value: local[field.key] ?? field.default ?? '#5b21b6',
                            onInput: e => onInput(field.key, e.target.value),
                            class: 'w-9 h-9 rounded-lg border border-gray-200 cursor-pointer',
                        }),
                        h('input', {
                            type: 'text',
                            value: local[field.key] ?? field.default ?? '#5b21b6',
                            onInput: e => onInput(field.key, e.target.value),
                            class: 'w-28 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-purple-200 focus:outline-none',
                        }),
                    ]),
                ]);
            }

            if (field.type === 'media') {
                return h('div', { key: field.key }, [
                    renderMediaField(
                        field.label,
                        local[field.key] ?? '',
                        val => onInput(field.key, val),
                        mediaKey('top', field.key),
                    ),
                ]);
            }

            // Default: text / number / url / email
            return h('div', { key: field.key }, [
                h('label', { class: 'block text-xs font-bold text-gray-700 mb-1.5' }, [
                    field.label,
                    field.required ? h('span', { class: 'text-red-500 ml-0.5' }, '*') : null,
                ]),
                h('input', {
                    type: field.type === 'number' ? 'number'
                        : field.type === 'url' ? 'url'
                        : field.type === 'email' ? 'email'
                        : 'text',
                    value: local[field.key] ?? field.default ?? '',
                    placeholder: field.placeholder ?? '',
                    onInput: e => onInput(field.key, e.target.value),
                    class: 'w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-200 focus:outline-none',
                }),
            ]);
        }));
    },
});
</script>
