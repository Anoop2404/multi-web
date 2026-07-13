<template>
    <SahodayaAdminLayout title="Sahodaya Website Builder" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount"
                         :show-header-title="false">
        <PageHeader title="Website builder" eyebrow="Website"
                    description="Design homepage sections, navigation, theme colours, and apply the CKSC-style template." />
        <div class="space-y-5 max-w-5xl">

            <!-- Public website toggle -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-bold text-gray-900">Public Website</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        <template v-if="publicWebsiteEnabled">
                            Visitors see your full marketing website at {{ publicUrl || 'your domain' }}.
                        </template>
                        <template v-else>
                            Visitors see the registration portal with School Registration and Admin Login only.
                        </template>
                    </p>
                </div>
                <label class="flex items-center gap-3 cursor-pointer shrink-0">
                    <span class="text-sm font-semibold text-gray-600">{{ publicWebsiteEnabled ? 'Enabled' : 'Disabled' }}</span>
                    <button type="button" @click="togglePublicWebsite" :disabled="publicWebsiteSaving"
                            class="relative inline-flex h-7 w-12 items-center rounded-full transition"
                            :class="publicWebsiteEnabled ? 'bg-green-500' : 'bg-gray-300'">
                        <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                              :class="publicWebsiteEnabled ? 'translate-x-6' : 'translate-x-1'"></span>
                    </button>
                </label>
            </div>

            <!-- Website template (layout from CKSC reference, content from this Sahodaya) -->
            <div class="rounded-2xl p-5 flex flex-wrap items-center justify-between gap-4 text-white border border-white/10"
                 style="background: linear-gradient(135deg, var(--color-primary, #5b21b6), var(--color-secondary, #7c3aed));">
                <div>
                    <h2 class="font-bold">Apply Website Template</h2>
                    <p class="text-sm text-white/80 mt-1">
                        Pill menu, hero slider, and homepage sections — personalized with {{ sahodaya.name }} contact details, region, and theme colours.
                    </p>
                </div>
                <button @click="applyCkscTemplate" :disabled="ckscTemplateSaving"
                        class="px-4 py-2 text-sm font-bold rounded-xl disabled:opacity-50 shrink-0"
                        style="background: var(--color-accent, #f59e0b); color: #1e1b4b;">
                    {{ ckscTemplateSaving ? 'Applying…' : 'Apply & personalize' }}
                </button>
            </div>

            <!-- Tabs -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-1.5 flex flex-wrap gap-1">
                <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
                        class="px-4 py-2 rounded-xl text-sm font-semibold transition"
                        :class="activeTab === tab.id
                            ? 'bg-[#1e1b4b] text-white shadow-sm'
                            : 'text-gray-600 hover:bg-gray-50'">
                    {{ tab.label }}
                </button>
                <a v-if="publicUrl" :href="publicUrl" target="_blank"
                   class="ml-auto self-center text-xs text-purple-600 hover:text-purple-800 font-semibold px-3">
                    Preview site ↗
                </a>
            </div>

            <!-- ── Navigation & Login ─────────────────────────────────────── -->
            <div v-if="activeTab === 'navigation'" class="space-y-5">
                <div v-if="!navConfig.items?.length"
                     class="bg-amber-50 border border-amber-200 rounded-2xl p-5 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="font-bold text-amber-900">No navigation menu yet</h3>
                        <p class="text-sm text-amber-800 mt-1">Your public site navbar is empty. Load the default Sahodaya menu or add items below.</p>
                    </div>
                    <button @click="loadDefaultNav" :disabled="defaultNavSaving"
                            class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold rounded-xl disabled:opacity-50">
                        {{ defaultNavSaving ? 'Loading…' : 'Load default navigation menu' }}
                    </button>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Navbar style</label>
                        <select v-model="navConfig.layout_variant"
                                class="w-full max-w-md border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-purple-200 focus:outline-none">
                            <option v-for="opt in navLayoutOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="font-bold text-gray-900">Portal Login</h2>
                            <p class="text-sm text-gray-500 mt-1">
                                One Login button in the navbar opens the portal page with registration and admin login options.
                            </p>
                        </div>
                        <button @click="ensurePortalLinks" :disabled="portalSaving"
                                class="btn-primary px-4 py-2 text-sm font-bold rounded-xl transition disabled:opacity-50">
                            {{ portalSaving ? 'Adding…' : '+ Add portal links to menu & footer' }}
                        </button>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4 p-4 bg-purple-50/50 rounded-xl border border-purple-100">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="navConfig.portal_cta.show_in_navbar" class="w-4 h-4 rounded text-purple-600">
                            <span class="text-sm font-medium text-gray-700">Show Login button in navbar</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="navConfig.portal_cta.show_in_menu" class="w-4 h-4 rounded text-purple-600">
                            <span class="text-sm font-medium text-gray-700">Include registration & login in menu</span>
                        </label>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1.5">Navbar button label</label>
                            <input v-model="navConfig.portal_cta.portal_label"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-200 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1.5">Portal page URL</label>
                            <input v-model="navConfig.portal_cta.portal_url"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-purple-200 focus:outline-none">
                        </div>
                    </div>

                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wide pt-2">Portal landing page options</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1.5">Registration button label</label>
                            <input v-model="navConfig.portal_cta.register_label"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-200 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1.5">Registration URL</label>
                            <input v-model="navConfig.portal_cta.register_url"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-purple-200 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1.5">Admin login button label</label>
                            <input v-model="navConfig.portal_cta.login_label"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-200 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1.5">Admin login URL</label>
                            <input v-model="navConfig.portal_cta.login_url"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-purple-200 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-gray-900">Navigation Menu Items</h3>
                        <button @click="addNavItem"
                                class="text-xs px-3 py-1.5 rounded-xl bg-[#1e1b4b] text-white font-semibold hover:bg-[#312e81] transition">
                            + Add Item
                        </button>
                    </div>
                    <div class="space-y-2">
                        <div v-for="(item, idx) in navConfig.items" :key="idx"
                             class="flex flex-wrap items-center gap-3 bg-gray-50 rounded-xl p-3">
                            <input v-model="item.label" placeholder="Label"
                                   class="flex-1 min-w-[120px] border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-200">
                            <input v-model="item.url" placeholder="/url"
                                   class="flex-1 min-w-[120px] border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-purple-200">
                            <button @click="removeNavItem(idx)" class="text-red-400 hover:text-red-600 text-lg px-1">&times;</button>
                        </div>
                        <p v-if="!navConfig.items?.length" class="text-sm text-gray-400 text-center py-4">No menu items yet.</p>
                    </div>
                    <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                        <button @click="saveNav" :disabled="navSaving"
                                class="px-5 py-2.5 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-sm font-bold rounded-xl transition disabled:opacity-50">
                            {{ navSaving ? 'Saving…' : 'Save Navigation' }}
                        </button>
                        <span v-if="navSaved" class="text-sm text-green-600 font-medium">Saved!</span>
                    </div>
                </div>
            </div>

            <!-- ── Footer links ───────────────────────────────────────────── -->
            <div v-if="activeTab === 'footer'" class="space-y-5">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
                    <div>
                        <h2 class="font-bold text-gray-900">Footer Quick Links</h2>
                        <p class="text-sm text-gray-500 mt-1">Registration and login links appear here for visitors scrolling to the footer.</p>
                    </div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" v-model="footerIncludePortal" class="w-4 h-4 rounded text-purple-600">
                        <span class="text-sm font-medium text-gray-700">Include school registration & login links when saving</span>
                    </label>
                    <div class="space-y-2">
                        <div v-for="(link, idx) in footerConfig.quick_links" :key="idx"
                             class="flex flex-wrap items-center gap-3 bg-gray-50 rounded-xl p-3">
                            <input v-model="link.label" placeholder="Label"
                                   class="flex-1 min-w-[120px] border border-gray-200 rounded-lg px-3 py-2 text-sm">
                            <input v-model="link.url" placeholder="/url"
                                   class="flex-1 min-w-[120px] border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono">
                            <button @click="removeFooterLink(idx)" class="text-red-400 hover:text-red-600 text-lg px-1">&times;</button>
                        </div>
                    </div>
                    <button @click="addFooterLink"
                            class="text-xs px-3 py-1.5 rounded-xl border border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition">
                        + Add link
                    </button>
                    <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                        <button @click="saveFooter" :disabled="footerSaving"
                                class="px-5 py-2.5 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-sm font-bold rounded-xl transition disabled:opacity-50">
                            {{ footerSaving ? 'Saving…' : 'Save Footer' }}
                        </button>
                        <span v-if="footerSaved" class="text-sm text-green-600 font-medium">Saved!</span>
                    </div>
                </div>
            </div>

            <!-- ── Theme colours ─────────────────────────────────────────── -->
            <div v-if="activeTab === 'theme'" class="space-y-5">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-6">
                    <div>
                        <h2 class="font-bold text-gray-900">Theme Colours</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            Controls navbar, buttons, hero gradient, section headings, and footer across your public site.
                        </p>
                    </div>

                    <!-- Live preview -->
                    <div class="rounded-2xl overflow-hidden border border-gray-100">
                        <div class="h-28 flex items-end p-5 text-white"
                             :style="{ background: `linear-gradient(135deg, ${themeConfig.primary}, ${themeConfig.secondary})` }">
                            <div>
                                <p class="text-xs uppercase tracking-wider opacity-80">Hero preview</p>
                                <p class="font-bold text-lg">{{ sahodaya.name }}</p>
                            </div>
                        </div>
                        <div class="flex gap-0">
                            <div class="flex-1 h-10 flex items-center justify-center text-xs font-bold text-white"
                                 :style="{ background: themeConfig.primary }">Primary</div>
                            <div class="flex-1 h-10 flex items-center justify-center text-xs font-bold text-white"
                                 :style="{ background: themeConfig.secondary }">Secondary</div>
                            <div class="flex-1 h-10 flex items-center justify-center text-xs font-bold text-gray-900"
                                 :style="{ background: themeConfig.accent_color }">Accent</div>
                        </div>
                    </div>

                    <!-- Presets -->
                    <div>
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Quick presets</p>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="preset in themePresets" :key="preset.id" type="button"
                                    @click="applyThemePreset(preset)"
                                    class="flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 hover:border-purple-300 hover:bg-purple-50 transition text-sm">
                                <span class="flex gap-0.5">
                                    <span class="w-4 h-4 rounded-full border border-white shadow-sm" :style="{ background: preset.primary }"></span>
                                    <span class="w-4 h-4 rounded-full border border-white shadow-sm -ml-2" :style="{ background: preset.secondary }"></span>
                                </span>
                                {{ preset.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Custom colours -->
                    <div class="grid sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1.5">Primary colour</label>
                            <div class="flex items-center gap-2">
                                <input type="color" v-model="themeConfig.primary" class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer">
                                <input type="text" v-model="themeConfig.primary"
                                       class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono uppercase">
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Navbar, headings, buttons</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1.5">Secondary colour</label>
                            <div class="flex items-center gap-2">
                                <input type="color" v-model="themeConfig.secondary" class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer">
                                <input type="text" v-model="themeConfig.secondary"
                                       class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono uppercase">
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Hero gradient end, page headers</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1.5">Accent colour</label>
                            <div class="flex items-center gap-2">
                                <input type="color" v-model="themeConfig.accent_color" class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer">
                                <input type="text" v-model="themeConfig.accent_color"
                                       class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono uppercase">
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Highlights, badges, CTAs</p>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1.5">Heading font</label>
                            <select v-model="themeConfig.font_heading"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white">
                                <option value="Inter">Inter</option>
                                <option value="Roboto">Roboto</option>
                                <option value="Poppins">Poppins</option>
                                <option value="Montserrat">Montserrat</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1.5">Body font</label>
                            <select v-model="themeConfig.font_body"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white">
                                <option value="Inter">Inter</option>
                                <option value="Roboto">Roboto</option>
                                <option value="Poppins">Poppins</option>
                                <option value="Montserrat">Montserrat</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                        <button @click="saveTheme" :disabled="themeSaving"
                                class="px-5 py-2.5 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-sm font-bold rounded-xl transition disabled:opacity-50">
                            {{ themeSaving ? 'Saving…' : 'Save Theme Colours' }}
                        </button>
                        <span v-if="themeSaved" class="text-sm text-green-600 font-medium">Saved! Refresh public site to see changes.</span>
                    </div>
                </div>
            </div>

            <!-- ── Page Sections ──────────────────────────────────────────── -->
            <template v-if="activeTab === 'sections'">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-3.5 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h2 class="font-bold text-gray-900">Page Sections</h2>
                    <span class="text-xs text-gray-400">{{ sections.length }} total · {{ sections.filter(s => s.is_active).length }} active</span>
                </div>
                <div class="flex items-center gap-3">
                    <a v-if="publicUrl" :href="publicUrl" target="_blank"
                       class="text-xs text-purple-600 hover:text-purple-800 font-semibold flex items-center gap-1">
                        Preview site ↗
                    </a>
                    <button @click="openAddModal"
                            class="flex items-center gap-2 px-4 py-2 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-sm font-bold rounded-xl transition">
                        + Add Section
                    </button>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="sections.length === 0"
                 class="bg-white rounded-2xl border border-dashed border-gray-200 p-16 text-center">
                <div class="text-5xl mb-3">🏗️</div>
                <p class="font-semibold text-gray-600">No sections yet</p>
                <p class="text-sm text-gray-400 mt-1 mb-4">Build your website layout by adding sections below.</p>
                <button @click="openAddModal"
                        class="px-5 py-2.5 bg-[#1e1b4b] text-white text-sm font-bold rounded-xl hover:bg-[#312e81] transition">
                    + Add First Section
                </button>
            </div>

            <!-- Section cards -->
            <div class="space-y-3">
                <div v-for="(section, idx) in sections" :key="section.id"
                     class="bg-white rounded-2xl border shadow-sm transition"
                     :class="section.is_active ? 'border-gray-100' : 'border-gray-100 opacity-60'">

                    <!-- Card header row -->
                    <div class="px-5 py-4 flex items-center gap-4">
                        <!-- Reorder handles -->
                        <div class="flex flex-col gap-0.5 shrink-0">
                            <button @click="moveUp(idx)" :disabled="idx === 0"
                                    class="w-6 h-5 flex items-center justify-center text-gray-300 hover:text-gray-600 disabled:opacity-20 rounded transition text-xs font-bold">▲</button>
                            <button @click="moveDown(idx)" :disabled="idx === sections.length - 1"
                                    class="w-6 h-5 flex items-center justify-center text-gray-300 hover:text-gray-600 disabled:opacity-20 rounded transition text-xs font-bold">▼</button>
                        </div>

                        <!-- Type icon + labels -->
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0"
                             :style="{ background: sectionColor(section.section_type) }">
                            {{ sectionIcon(section.section_type) }}
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center flex-wrap gap-2 mb-0.5">
                                <span class="text-sm font-bold text-gray-900 capitalize">
                                    {{ sectionTypeLabel(section.section_type) }}
                                </span>
                                <span class="text-[11px] font-mono bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ section.variant }}</span>
                                <span v-if="!section.is_active" class="text-[11px] font-semibold bg-gray-100 text-gray-400 px-2 py-0.5 rounded-full">Hidden</span>
                            </div>
                            <p class="text-xs text-gray-400 truncate">
                                {{ sectionPreview(section) }}
                            </p>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex items-center gap-2 shrink-0">
                            <button @click="toggleActive(section)"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-xl border transition"
                                    :class="section.is_active
                                        ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'
                                        : 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100'">
                                {{ section.is_active ? 'Hide' : 'Show' }}
                            </button>
                            <button @click="toggleEdit(section)"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-xl border transition"
                                    :class="expandedId === section.id
                                        ? 'border-[#1e1b4b] bg-[#1e1b4b] text-white'
                                        : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'">
                                {{ expandedId === section.id ? '↑ Close' : '✏️ Edit' }}
                            </button>
                            <button @click="removeSection(section)"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-xl border border-red-100 bg-red-50 text-red-500 hover:bg-red-100 transition">
                                Delete
                            </button>
                        </div>
                    </div>

                    <!-- Inline editor (accordion) -->
                    <div v-if="expandedId === section.id"
                         class="border-t border-gray-100 bg-gray-50/50 rounded-b-2xl px-5 py-5 space-y-5">

                        <!-- Variant selector -->
                        <div class="flex flex-wrap items-end gap-4 pb-4 border-b border-gray-100">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5">Layout Variant</label>
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="v in variantsFor(section.section_type)" :key="v"
                                            type="button"
                                            @click="switchVariant(section, v)"
                                            :class="[
                                                'px-3 py-1.5 rounded-xl text-xs font-semibold border transition',
                                                section.variant === v
                                                    ? 'bg-[#1e1b4b] text-white border-[#1e1b4b]'
                                                    : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300'
                                            ]">
                                        {{ v }}
                                    </button>
                                </div>
                            </div>
                            <div v-if="(section.archived_configs || []).length" class="ml-auto">
                                <select @change="restoreArchived(section, $event.target.value)"
                                        class="text-xs border border-gray-200 rounded-xl px-3 py-2 text-gray-500 bg-white focus:ring-2 focus:ring-purple-200 focus:outline-none">
                                    <option value="">↩ Restore previous content…</option>
                                    <option v-for="(arc, ai) in section.archived_configs" :key="ai"
                                            :value="ai">
                                        {{ arc.variant }} — {{ formatDate(arc.archived_at) }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Content fields -->
                        <div v-if="fieldsFor(section.section_type, section.variant).length">
                            <SectionFieldEditor
                                :fields="fieldsFor(section.section_type, section.variant)"
                                :config="editConfigs[section.id] || section.config || {}"
                                :upload-media="uploadSiteMedia"
                                :media-preview="mediaPreviewUrl"
                                @update="val => editConfigs[section.id] = val" />
                        </div>
                        <div v-else class="bg-amber-50 border border-amber-100 rounded-xl px-4 py-3 text-xs text-amber-700">
                            This section has no configurable fields — its content comes from your database
                            (office bearers, member schools, events, etc.).
                        </div>

                        <!-- Save row -->
                        <div class="flex items-center gap-3 pt-2 border-t border-gray-100 flex-wrap">
                            <button @click="saveSection(section)"
                                    :disabled="saving[section.id]"
                                    class="px-5 py-2.5 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-sm font-bold rounded-xl transition disabled:opacity-50">
                                {{ saving[section.id] ? 'Saving…' : 'Save draft' }}
                            </button>
                            <button @click="publishSection(section)"
                                    :disabled="saving[section.id]"
                                    class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl transition disabled:opacity-50">
                                Publish
                            </button>
                            <button @click="expandedId = null"
                                    class="px-4 py-2.5 border border-gray-200 text-sm text-gray-500 rounded-xl hover:bg-gray-50 transition">
                                Cancel
                            </button>
                            <span v-if="section.has_unpublished_changes || section.status === 'draft'"
                                  class="text-xs font-semibold text-amber-700 bg-amber-50 px-2 py-1 rounded-lg">
                                Unpublished changes
                            </span>
                            <a v-if="publicUrl" :href="`${publicUrl.replace(/\/$/, '')}/preview-site`" target="_blank"
                               class="ml-auto text-xs text-purple-600 hover:underline font-semibold">
                                Preview drafts ↗
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            </template>
        </div>

        <!-- Add section modal -->
        <Teleport to="body">
            <div v-if="addModal.open" class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between z-10">
                        <h3 class="font-bold text-gray-900 text-lg">Add Section</h3>
                        <button @click="addModal.open = false"
                                class="w-8 h-8 flex items-center justify-center rounded-xl hover:bg-gray-100 transition text-gray-400 text-xl">×</button>
                    </div>

                    <!-- Section type grid -->
                    <div class="p-6 space-y-5">
                        <div v-if="!addModal.selectedType">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-3">Choose a section type</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <button v-for="(variants, type) in sectionTypes" :key="type"
                                        @click="addModal.selectedType = type; addModal.selectedVariant = variants[0]"
                                        class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:border-purple-200 hover:bg-purple-50/50 transition text-left group">
                                    <span class="text-xl w-9 h-9 rounded-lg flex items-center justify-center bg-gray-50 group-hover:bg-purple-100 transition">
                                        {{ sectionIcon(type) }}
                                    </span>
                                    <span class="text-sm font-semibold text-gray-700">{{ sectionTypeLabel(type) }}</span>
                                </button>
                            </div>
                        </div>

                        <div v-else class="space-y-5">
                            <button @click="addModal.selectedType = null" class="text-xs text-purple-600 hover:underline font-semibold flex items-center gap-1">
                                ← Back
                            </button>
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-3">
                                    Choose a design for {{ sectionTypeLabel(addModal.selectedType) }}
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
                                            {{ fieldDefs[addModal.selectedType]?.[v]?.description || '' }}
                                        </p>
                                    </button>
                                </div>
                            </div>

                            <div class="flex gap-3 pt-2 border-t border-gray-100">
                                <button @click="createSection" :disabled="!addModal.selectedVariant || addModal.saving"
                                        class="flex-1 py-3 bg-[#1e1b4b] hover:bg-[#312e81] text-white font-bold rounded-xl transition disabled:opacity-50 text-sm">
                                    {{ addModal.saving ? 'Adding…' : `Add ${sectionTypeLabel(addModal.selectedType)}` }}
                                </button>
                                <button @click="addModal.open = false"
                                        class="px-6 py-3 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 transition text-sm">
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
import { ref, reactive, computed, defineComponent, h } from 'vue';

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
});

const tabs = [
    { id: 'sections', label: 'Page Sections' },
    { id: 'theme', label: 'Theme Colours' },
    { id: 'navigation', label: 'Navigation & Login' },
    { id: 'footer', label: 'Footer Links' },
];
const activeTab = ref(props.navNeedsSetup ? 'navigation' : 'sections');

const sections    = ref([...(props.sections ?? [])]);
const expandedId  = ref(null);
const editConfigs = reactive({});
const saving      = reactive({});

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
const themePresets = props.themePresets?.length ? props.themePresets : [];

const addModal = reactive({
    open: false, selectedType: null, selectedVariant: null, saving: false,
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
    return cfg.heading ?? cfg.title ?? cfg.tagline ?? (fieldsFor(s.section_type, s.variant).length ? 'Click Edit to configure content' : 'Data-driven section');
}
function formatDate(d) {
    if (!d) return '';
    try { return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }); } catch { return ''; }
}

// ── API calls (using fetch directly — same pattern as super-admin builder) ───

function csrf() { return document.querySelector('meta[name=csrf-token]')?.content ?? ''; }
const baseUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/site-builder/api`);

async function apiPatch(path, body) {
    const r = await fetch(`${baseUrl.value}${path}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(body),
    });
    return r.json();
}
async function apiPost(path, body) {
    const r = await fetch(`${baseUrl.value}${path}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(body),
    });
    return r.json();
}
async function apiDelete(path) {
    await fetch(`${baseUrl.value}${path}`, {
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
    if (!confirm('Replace homepage sections with the CKSC layout (pill menu, hero slider, About, Services, Journey, Gallery, etc.)? Your saved theme colours will be kept.')) {
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

function toggleEdit(section) {
    if (expandedId.value === section.id) {
        expandedId.value = null;
    } else {
        expandedId.value = section.id;
        if (!editConfigs[section.id]) {
            editConfigs[section.id] = { ...(section.config ?? {}) };
        }
    }
}

async function toggleActive(section) {
    const updated = await apiPost(`/sections/${section.id}/toggle`, {});
    Object.assign(section, updated);
}

async function saveSection(section) {
    saving[section.id] = true;
    try {
        const config = editConfigs[section.id] ?? section.config ?? {};
        const updated = await apiPatch(`/sections/${section.id}`, { config, status: 'draft' });
        const idx = sections.value.findIndex(s => s.id === section.id);
        if (idx !== -1) Object.assign(sections.value[idx], updated);
        expandedId.value = null;
    } finally {
        saving[section.id] = false;
    }
}

async function publishSection(section) {
    saving[section.id] = true;
    try {
        const config = editConfigs[section.id] ?? section.config ?? {};
        await apiPatch(`/sections/${section.id}`, { config, status: 'draft' });
        const updated = await apiPost(`/sections/${section.id}/publish`, {});
        const idx = sections.value.findIndex(s => s.id === section.id);
        if (idx !== -1) Object.assign(sections.value[idx], updated);
        expandedId.value = null;
    } finally {
        saving[section.id] = false;
    }
}

async function switchVariant(section, newVariant) {
    if (newVariant === section.variant) return;
    const msg = `Switch from "${section.variant}" to "${newVariant}"?\n\nCurrent content will be archived and you can restore it any time.`;
    if (!confirm(msg)) return;
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
    if (!confirm(`Delete the "${sectionTypeLabel(section.section_type)} / ${section.variant}" section?\n\nThis cannot be undone.`)) return;
    await apiDelete(`/sections/${section.id}`);
    sections.value = sections.value.filter(s => s.id !== section.id);
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
            }),
        });
        const newSection = await r.json();
        sections.value.push(newSection);
        addModal.open = false;
        // Auto-open editor for the new section
        expandedId.value = newSection.id;
        editConfigs[newSection.id] = {};
    } finally {
        addModal.saving = false;
    }
}

// ── Inline field editor component ─────────────────────────────────────────────

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
                h('label', { class: 'text-[11px] text-gray-400 font-medium block' }, label),
                preview
                    ? h('div', { class: 'relative rounded-lg overflow-hidden border border-gray-200 bg-gray-50' }, [
                        h('img', {
                            src: preview,
                            alt: label,
                            class: 'w-full h-28 object-cover',
                        }),
                        h('button', {
                            type: 'button',
                            onClick: () => onChange(''),
                            class: 'absolute top-1 right-1 bg-white/90 text-red-500 text-xs px-2 py-0.5 rounded shadow',
                        }, 'Remove'),
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
                        try {
                            const path = await props.uploadMedia(file);
                            onChange(path);
                        } catch (err) {
                            alert(err.message || 'Upload failed');
                        } finally {
                            mediaUploading[key] = false;
                            e.target.value = '';
                        }
                    },
                    class: 'block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100',
                }),
                h('input', {
                    type: 'url',
                    value: value ?? '',
                    placeholder: 'Or paste image URL',
                    onInput: e => onChange(e.target.value),
                    class: 'w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-purple-200 focus:outline-none',
                }),
                uploading ? h('p', { class: 'text-xs text-purple-600' }, 'Uploading…') : null,
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

        function onRepeaterField(key, idx, fieldKey, val) {
            const arr = [...(local[key] ?? [])];
            arr[idx] = { ...arr[idx], [fieldKey]: val };
            local[key] = arr;
            emit('update', { ...local });
        }

        return () => h('div', { class: 'space-y-4' }, (props.fields ?? []).map(field => {
            if (field.type === 'repeater') {
                return h('div', { key: field.key, class: 'space-y-2' }, [
                    h('div', { class: 'flex items-center justify-between' }, [
                        h('label', { class: 'text-xs font-bold text-gray-700' }, field.label),
                        h('button', {
                            type: 'button',
                            onClick: () => onRepeaterAdd(field.key, field.fields),
                            class: 'text-xs text-purple-600 hover:text-purple-800 font-semibold',
                        }, '+ Add'),
                    ]),
                    ...(local[field.key] ?? []).map((item, idx) =>
                        h('div', { key: idx, class: 'border border-gray-200 bg-white rounded-xl p-3 space-y-2' }, [
                            h('div', { class: 'grid grid-cols-2 gap-2' },
                                (field.fields ?? []).map(sub =>
                                    sub.type === 'media'
                                        ? renderMediaField(
                                            sub.label,
                                            item[sub.key] ?? '',
                                            val => onRepeaterField(field.key, idx, sub.key, val),
                                            mediaKey(field.key, sub.key, idx),
                                        )
                                        : h('div', { key: sub.key }, [
                                        h('label', { class: 'text-[11px] text-gray-400 font-medium' }, sub.label),
                                        sub.type === 'textarea'
                                            ? h('textarea', {
                                                rows: 2,
                                                value: item[sub.key] ?? '',
                                                onInput: e => onRepeaterField(field.key, idx, sub.key, e.target.value),
                                                class: 'w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-purple-200 focus:outline-none',
                                            })
                                            : h('input', {
                                                type: sub.type === 'url' ? 'url' : sub.type === 'color' ? 'color' : 'text',
                                                value: item[sub.key] ?? '',
                                                onInput: e => onRepeaterField(field.key, idx, sub.key, e.target.value),
                                                class: 'w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-purple-200 focus:outline-none',
                                            }),
                                    ])
                                )
                            ),
                            h('button', {
                                type: 'button',
                                onClick: () => onRepeaterRemove(field.key, idx),
                                class: 'text-xs text-red-400 hover:text-red-600',
                            }, 'Remove'),
                        ])
                    ),
                ]);
            }

            if (field.type === 'switch') {
                return h('label', { key: field.key, class: 'flex items-center gap-3 cursor-pointer' }, [
                    h('input', {
                        type: 'checkbox',
                        checked: !!local[field.key],
                        onChange: e => onInput(field.key, e.target.checked),
                        class: 'w-4 h-4 rounded text-purple-600',
                    }),
                    h('span', { class: 'text-sm font-medium text-gray-700' }, field.label),
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
                            class: 'w-8 h-8 rounded-lg border border-gray-200 cursor-pointer',
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
