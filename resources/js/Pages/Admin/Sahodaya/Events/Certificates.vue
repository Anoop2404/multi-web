<template>
    <SahodayaEventsLayout :title="`${event.title} — Certificates`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Certificates`" eyebrow="Operations"
                    description="Generate and manage participant certificates." />

        <div class="mb-4 flex flex-wrap items-center gap-3 text-xs">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/merit`" class="font-semibold text-amber-700 hover:text-amber-900">
                🏆 Open Merit Certificates workspace →
            </Link>
            <Link v-if="!participationHeld" :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/participants`" class="font-semibold text-blue-700 hover:text-blue-900">
                📜 Open Participation Certificates workspace →
            </Link>
            <Link v-else :href="participationParentUrl" class="font-semibold text-blue-700 hover:text-blue-900">
                📜 Participation certificates are issued from the parent event — open them there →
            </Link>
        </div>

        <!-- Certificate date -->
        <div class="card !p-3 mb-6 flex flex-wrap items-center gap-3 text-xs">
            <span class="font-semibold text-gray-700">📅 Certificate date:</span>
            <input type="date" v-model="certificateDateInput" class="field text-xs py-1 px-2 w-40" />
            <button @click="saveCertificateDate" class="btn-secondary py-1 px-3 text-xs">Save</button>
            <button v-if="event.certificate_date" @click="clearCertificateDate" class="btn-subtle text-xs text-gray-500">
                Clear (use event date)
            </button>
            <span class="text-gray-400">
                {{ event.certificate_date ? 'Custom override — printed on every certificate.' : `Defaults to ${defaultCertificateDateLabel} (the event's own date).` }}
            </span>
        </div>

        <EventSignatoriesCard :base="base" :initial-signatories="certificateSignatories" :label-suggestions="signatoryLabelSuggestions" />

        <!-- Certificate pipeline: generate rows, render & cache the files, then download.
             Three explicit stages rather than one flat button row — rendering is now a
             separate, deliberate step from both generation and download (see
             FestCertificateController::generateAndRenderBatch()), so the layout says so. -->
        <div class="card !p-4 mb-6">
            <div class="grid gap-4 lg:grid-cols-[1fr_auto_1fr_auto_1fr] lg:items-start">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-indigo-700 mb-2">Step 1 · Generate</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 rounded p-1">
                            <SearchableSelect v-if="publishedItems.length" v-model="selectedItemId" :options="publishedItemOptions" :all-option="true" all-label="All items" class="max-w-[160px]" />
                            <button @click="generate(selectedItemId)" class="btn-primary py-1.5 px-3 text-xs shrink-0">
                                🏆 Merit{{ selectedItemId ? ' (item)' : '' }}
                            </button>
                        </div>
                        <button v-if="!participationHeld" @click="generateParticipation" class="btn-secondary py-1.5 px-3 text-xs">Participation</button>
                    </div>
                </div>

                <div class="hidden lg:flex items-center pt-6 text-gray-300 text-base" aria-hidden="true">→</div>

                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-indigo-700 mb-2">Step 2 · Render &amp; cache</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <button v-if="certificates.length" @click="renderAndCache()" class="btn-primary py-1.5 px-3 text-xs" :disabled="isBatchRunning">
                            ⚙️ Render &amp; cache files
                        </button>
                        <button v-if="staleCount" @click="regenerateStale" class="btn-secondary py-1.5 px-3 text-xs !text-amber-700 !border-amber-200" :disabled="isBatchRunning">
                            🔁 {{ staleCount }} stale
                        </button>
                        <a v-if="certificates.length" :href="previewSampleUrl" target="_blank" class="text-xs font-semibold text-gray-500 hover:text-gray-800">
                            👁️ Preview worst case ↗
                        </a>
                    </div>
                </div>

                <div class="hidden lg:flex items-center pt-6 text-gray-300 text-base" aria-hidden="true">→</div>

                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-indigo-700 mb-2">Step 3 · Download</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <details v-if="certificates.length" class="relative">
                            <summary class="btn-secondary py-1.5 px-3 text-xs inline-flex list-none cursor-pointer [&::-webkit-details-marker]:hidden">
                                📦 Download / print ▾
                            </summary>
                            <div class="absolute z-20 mt-1 w-64 rounded-lg border border-gray-200 bg-white shadow-lg p-1">
                                <button @click="queueZipDownload({}, $event)" :disabled="isBatchRunning"
                                        class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                    📦 All certificates (ZIP)
                                </button>
                                <button @click="queueZipDownload({ group_by: 'item' }, $event)" :disabled="isBatchRunning"
                                        class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                    📦 All certificates — grouped by item (ZIP)
                                </button>
                                <button @click="queueZipDownload({ group_by: 'school' }, $event)" :disabled="isBatchRunning"
                                        class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                    📦 All certificates — grouped by school (ZIP)
                                </button>
                                <div class="border-t border-gray-100 my-1"></div>
                                <button v-if="winnersByItem.length" @click="queueZipDownload({ published_only: '1' }, $event)" :disabled="isBatchRunning"
                                        class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                    📦 Merit winners only (ZIP)
                                </button>
                                <button v-if="winnersByItem.length" @click="queueZipDownload({ published_only: '1', plain: '1' }, $event)" :disabled="isBatchRunning"
                                        class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                    📦 Merit winners only — no background (ZIP)
                                </button>
                                <button v-if="winnersByItem.length" @click="queueZipDownload({ published_only: '1', group_by: 'item', plain: '1' }, $event)" :disabled="isBatchRunning"
                                        class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                    📦 Merit winners — grouped by item — no background (ZIP)
                                </button>
                                <button v-if="winnersByItem.length" @click="queueZipDownload({ published_only: '1', group_by: 'school', plain: '1' }, $event)" :disabled="isBatchRunning"
                                        class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                    📦 Merit winners — grouped by school — no background (ZIP)
                                </button>
                                <button v-if="participationBySchool.length" @click="queueZipDownload({ cert_type: 'participation' }, $event)" :disabled="isBatchRunning"
                                        class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                    📦 Participation only (ZIP)
                                </button>
                                <button v-if="participationBySchool.length" @click="queueZipDownload({ cert_type: 'participation', plain: '1' }, $event)" :disabled="isBatchRunning"
                                        class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                    📦 Participation only — no background (ZIP)
                                </button>
                                <button v-if="participationBySchool.length" @click="queueZipDownload({ cert_type: 'participation', group_by: 'school' }, $event)" :disabled="isBatchRunning"
                                        class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                    📦 Participation — grouped by school (ZIP)
                                </button>
                                <button v-if="participationBySchool.length" @click="queueZipDownload({ cert_type: 'participation', group_by: 'school', plain: '1' }, $event)" :disabled="isBatchRunning"
                                        class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                    📦 Participation — grouped by school — no background (ZIP)
                                </button>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/print-all`" target="_blank" class="block px-3 py-2 text-xs rounded hover:bg-gray-50">🖨️ Print all (with background) ↗</a>
                                <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/print-all?plain=1`" target="_blank" class="block px-3 py-2 text-xs rounded hover:bg-gray-50">🖨️ Print all (plain) ↗</a>
                            </div>
                        </details>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/tally`" class="text-xs font-semibold text-gray-500 hover:text-gray-800">
                            How many to print? →
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Render/cache/ZIP-export batch progress -->
            <div v-if="jobStatus" class="mt-4 pt-4 border-t border-gray-100 text-sm">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <p class="font-semibold capitalize">
                        {{ jobStatusLabel }}: {{ jobStatus.status.replace('_', ' ') }}
                    </p>
                    <span class="text-xs text-gray-500 tabular-nums">{{ jobStatus.processed_count }} / {{ jobStatus.total_count }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div class="bg-indigo-600 h-2 rounded-full transition-all"
                         :style="{ width: (jobStatus.total_count ? (jobStatus.processed_count / jobStatus.total_count * 100) : 0) + '%' }"></div>
                </div>
                <p v-if="['completed', 'completed_with_errors'].includes(jobStatus.status)" class="mt-2 text-xs text-gray-600">
                    {{ jobStatus.succeeded_count }} succeeded<span v-if="jobStatus.failed_count"> · {{ jobStatus.failed_count }} failed</span>
                </p>
                <a v-if="jobStatus.batch_type === 'zip_export' && ['completed', 'completed_with_errors'].includes(jobStatus.status)"
                   :href="`${base}/batches/${jobStatus.id}/download`"
                   class="btn-primary mt-2 inline-flex py-1.5 px-3 text-xs">
                    ⬇️ Download ZIP
                </a>
                <p v-if="jobStatus.error" class="mt-2 text-xs text-red-600">{{ jobStatus.error }}</p>
            </div>
        </div>

        <!-- View mode tabs -->
        <div class="border-b border-gray-200 mb-6 overflow-x-auto">
            <nav class="-mb-px flex gap-6 w-max" aria-label="Tabs">
                <button @click="activeTab = 'winners_item'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        :class="activeTab === 'winners_item'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                    🏆 Merit Winners (Grouped by Item)
                </button>
                <button @click="activeTab = 'winners_school'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        :class="activeTab === 'winners_school'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                    🏫 Merit Winners (Grouped by School)
                </button>
                <button v-if="!participationHeld" @click="activeTab = 'participation_school'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        :class="activeTab === 'participation_school'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                    📜 Participation (by School)
                </button>
                <button @click="activeTab = 'all'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        :class="activeTab === 'all'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                    📜 All Certificates ({{ certificates.length }})
                </button>
            </nav>
        </div>

        <!-- TAB 1: Merit Winners Grouped by Item -->
        <div v-if="activeTab === 'winners_item'" class="mb-6">
            <div class="flex items-center justify-between gap-4 mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Merit Winners Grouped by Item</h3>
                    <p class="text-xs text-gray-500">Items whose results have been published (Merit Ranks 1–3).</p>
                </div>
            </div>

            <div v-if="winnersByItem.length" class="card divide-y divide-gray-100">
                <div v-for="group in winnersByItem" :key="group.item_id" class="py-3 first:pt-0 last:pb-0">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0 flex items-center gap-3">
                            <span class="shrink-0 text-xs font-semibold text-gray-400 tabular-nums">{{ group.sl_no }}.</span>
                            <p class="font-semibold text-sm text-gray-900">
                                {{ group.item_title }}<span v-if="[group.category_label, group.type_label, group.gender_label].some(Boolean)" class="font-normal text-gray-500"> ({{ [group.category_label, group.type_label, group.gender_label].filter(Boolean).join(' · ') }})</span>
                            </p>
                            <span class="shrink-0 text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-medium">
                                {{ group.winners.length }} merit winner{{ group.winners.length === 1 ? '' : 's' }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-xs shrink-0">
                            <button @click="generate(group.item_id)" class="font-semibold text-amber-700 hover:text-amber-900">
                                ⚡ Generate
                            </button>
                            <button @click="renderAndCache({ item_id: group.item_id, cert_type: 'winner' })"
                                    class="font-semibold text-indigo-600 hover:text-indigo-800 disabled:opacity-40 disabled:cursor-not-allowed"
                                    :disabled="isBatchRunning">
                                ⚙️ Render
                            </button>
                            <a :href="`${base}/preview-sample?cert_type=winner&item_id=${group.item_id}`" target="_blank" class="text-gray-500 hover:text-gray-800" title="Preview worst case">
                                👁️
                            </a>
                            <details class="relative">
                                <summary class="font-semibold text-gray-600 hover:text-gray-800 inline-flex items-center gap-1 list-none cursor-pointer [&::-webkit-details-marker]:hidden">
                                    📦 Download ▾
                                </summary>
                                <div class="absolute z-20 right-0 mt-1 w-56 rounded-lg border border-gray-200 bg-white shadow-lg p-1 text-left">
                                    <a :href="`${base}/download-zip?item_id=${group.item_id}&cert_type=winner`" class="block px-3 py-2 rounded hover:bg-gray-50">📦 ZIP</a>
                                    <a :href="`${base}/print-all?item_id=${group.item_id}&cert_type=winner`" target="_blank" class="block px-3 py-2 rounded hover:bg-gray-50">🖨️ Print (with background) ↗</a>
                                    <a :href="`${base}/print-all?item_id=${group.item_id}&cert_type=winner&plain=1`" target="_blank" class="block px-3 py-2 rounded hover:bg-gray-50">🖨️ Print (plain) ↗</a>
                                </div>
                            </details>
                        </div>
                    </div>
                    <ul class="mt-2 flex flex-wrap gap-x-5 gap-y-1.5">
                        <li v-for="w in group.winners" :key="w.id" class="flex items-center gap-2 text-xs">
                            <span class="shrink-0 rounded-full bg-amber-500 text-white font-bold w-5 h-5 flex items-center justify-center text-[10px]">
                                {{ w.position ?? '—' }}
                            </span>
                            <span class="font-medium text-gray-800">{{ w.name }}</span>
                            <span class="flex items-center gap-2 text-[11px]">
                                <a :href="`/certificates/print/${w.uuid}`" target="_blank" class="text-indigo-600 font-medium hover:underline">Print (With BG) ↗</a>
                                <a :href="`/certificates/print/${w.uuid}?plain=1`" target="_blank" class="text-gray-500 hover:underline">Plain ↗</a>
                                <template v-if="w.is_rendered && !w.is_stale">
                                    <a :href="`/certificates/pdf/${w.uuid}`" target="_blank" class="text-emerald-600 font-medium hover:underline">View PDF ↗</a>
                                    <a :href="`/certificates/pdf/${w.uuid}?download=1`" class="text-emerald-600 font-medium hover:underline">Download PDF</a>
                                </template>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
            <div v-else class="card p-6 text-center text-gray-500 text-sm">
                No published merit winners by item yet. Publish item results to generate merit certificates.
            </div>
        </div>

        <!-- TAB 2: Merit Winners Grouped by School -->
        <div v-if="activeTab === 'winners_school'" class="mb-6">
            <div class="flex items-center justify-between gap-4 mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Merit Winners Grouped by School</h3>
                    <p class="text-xs text-gray-500">Merit winners organized by school for distribution.</p>
                </div>
            </div>

            <div class="mb-3 flex flex-wrap items-center gap-3">
                <input v-model="schoolSearch" type="search" placeholder="Search school or student…"
                       class="field text-xs py-1.5 px-3 w-full sm:w-72" />
                <select v-model="schoolReadiness" class="field text-xs py-1.5 px-2 w-auto">
                    <option value="all">All schools</option>
                    <option value="ready">✓ Results complete ({{ winnersBySchool.filter(isSchoolReady).length }})</option>
                    <option value="pending">⏳ Results pending ({{ winnersBySchool.length - winnersBySchool.filter(isSchoolReady).length }})</option>
                </select>
                <select v-model="schoolDownloaded" class="field text-xs py-1.5 px-2 w-auto">
                    <option value="all">Downloaded: any</option>
                    <option value="yes">✓ Downloaded ({{ winnersBySchool.filter(g => g.downloaded).length }})</option>
                    <option value="no">Not downloaded ({{ winnersBySchool.filter(g => !g.downloaded).length }})</option>
                </select>
                <span v-if="schoolSearch.trim() || schoolReadiness !== 'all' || schoolDownloaded !== 'all'" class="text-xs text-gray-500">
                    {{ filteredWinnersBySchool.length }} of {{ winnersBySchool.length }} schools
                </span>
                <details v-if="readyCertificateIds(filteredWinnersBySchool).length" class="relative ml-auto">
                    <summary class="btn-secondary py-1.5 px-3 text-xs inline-flex list-none cursor-pointer [&::-webkit-details-marker]:hidden">
                        📦 Results-complete schools shown ({{ filteredWinnersBySchool.filter(isSchoolReady).length }}) ▾
                    </summary>
                    <div class="absolute z-20 right-0 mt-1 w-72 rounded-lg border border-gray-200 bg-white shadow-lg p-1 text-left">
                        <p class="px-3 pt-2 pb-1 text-[11px] text-gray-500">One folder per school — only schools whose every registered item has published results.</p>
                        <button @click="queueZipDownload({ certificate_ids: readyCertificateIds(filteredWinnersBySchool).join(','), group_by: 'school' }, $event)" :disabled="isBatchRunning"
                                class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                            📦 ZIP (with background)
                        </button>
                        <button @click="queueZipDownload({ certificate_ids: readyCertificateIds(filteredWinnersBySchool).join(','), group_by: 'school', plain: '1' }, $event)" :disabled="isBatchRunning"
                                class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                            📦 ZIP — no background
                        </button>
                        <p v-if="isBatchRunning" class="px-3 pb-2 text-[11px] text-amber-700">Available once the current run finishes.</p>
                    </div>
                </details>
            </div>

            <div v-if="filteredWinnersBySchool.length" class="card divide-y divide-gray-100">
                <div v-for="group in filteredWinnersBySchool" :key="group.school_id" class="py-3 first:pt-0 last:pb-0">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0 flex flex-wrap items-center gap-x-3 gap-y-1">
                            <p class="font-semibold text-sm text-gray-900">{{ group.school_name }}</p>
                            <span class="shrink-0 text-xs px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 font-medium">
                                {{ group.winners.length }} merit winner{{ group.winners.length === 1 ? '' : 's' }}
                            </span>
                            <span v-if="isSchoolReady(group)" class="shrink-0 text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-medium"
                                  :title="`All ${group.results.total} registered item(s) have published results — safe to bulk-download`">
                                ✓ Results complete ({{ group.results.total }}/{{ group.results.total }})
                            </span>
                            <span v-else-if="group.results?.total" class="shrink-0 text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-medium"
                                  :title="`Results pending: ${group.results.pending.join(', ')}`">
                                ⏳ {{ group.results.pending.length }} of {{ group.results.total }} items pending
                            </span>
                            <span v-if="group.downloaded" class="shrink-0 text-xs px-2 py-0.5 rounded bg-emerald-600 text-white font-medium"
                                  :title="group.downloaded_at ? `Marked downloaded ${new Date(group.downloaded_at).toLocaleString()}` : 'Marked downloaded'">
                                ✓ Downloaded
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-xs shrink-0">
                            <label class="inline-flex items-center gap-1.5 cursor-pointer select-none"
                                   :title="group.downloaded ? 'Marked as downloaded — click to undo' : 'Tick once you have downloaded / handed over this school\'s certificates'">
                                <input type="checkbox" class="rounded border-gray-300 text-emerald-600"
                                       :checked="group.downloaded" :disabled="!group.school_id"
                                       @change="markDownloaded(group, 'winner', $event.target.checked)" />
                                <span :class="group.downloaded ? 'font-semibold text-emerald-700' : 'text-gray-500'">
                                    {{ group.downloaded ? '✓ Downloaded' : 'Mark downloaded' }}
                                </span>
                            </label>
                            <button @click="renderAndCache({ school_id: group.school_id, cert_type: 'winner' })"
                                    class="font-semibold text-indigo-600 hover:text-indigo-800 disabled:opacity-40 disabled:cursor-not-allowed"
                                    :disabled="isBatchRunning">
                                ⚙️ Render
                            </button>
                            <details class="relative">
                                <summary class="font-semibold text-gray-600 hover:text-gray-800 inline-flex items-center gap-1 list-none cursor-pointer [&::-webkit-details-marker]:hidden">
                                    📦 Download ▾
                                </summary>
                                <div class="absolute z-20 right-0 mt-1 w-56 rounded-lg border border-gray-200 bg-white shadow-lg p-1 text-left">
                                    <!-- While another run is going, a queued ZIP would wait behind all of
                                         its jobs — download directly instead (reuses rendered PDFs). -->
                                    <template v-if="isBatchRunning">
                                        <a :href="`${base}/download-zip?school_id=${group.school_id}&cert_type=winner`" class="block px-3 py-2 rounded hover:bg-gray-50">📦 ZIP (with background)</a>
                                        <a :href="`${base}/download-zip?school_id=${group.school_id}&cert_type=winner&plain=1`" class="block px-3 py-2 rounded hover:bg-gray-50">📦 ZIP — no background</a>
                                    </template>
                                    <template v-else>
                                        <button @click="queueZipDownload({ school_id: group.school_id, cert_type: 'winner' }, $event)"
                                                class="block w-full text-left px-3 py-2 rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">📦 ZIP (with background)</button>
                                        <button @click="queueZipDownload({ school_id: group.school_id, cert_type: 'winner', plain: '1' }, $event)"
                                                class="block w-full text-left px-3 py-2 rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">📦 ZIP — no background</button>
                                    </template>
                                    <a :href="`${base}/print-all?school_id=${group.school_id}&cert_type=winner`" target="_blank" class="block px-3 py-2 rounded hover:bg-gray-50">🖨️ Print (with background) ↗</a>
                                    <a :href="`${base}/print-all?school_id=${group.school_id}&cert_type=winner&plain=1`" target="_blank" class="block px-3 py-2 rounded hover:bg-gray-50">🖨️ Print (plain) ↗</a>
                                </div>
                            </details>
                        </div>
                    </div>
                    <details v-if="group.results?.pending?.length" class="mt-1">
                        <summary class="text-xs font-medium text-amber-700 hover:text-amber-900 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                            ▸ {{ group.results.pending.length }} item{{ group.results.pending.length === 1 ? '' : 's' }} still awaiting results
                        </summary>
                        <p class="mt-1 text-[11px] text-gray-600">{{ group.results.pending.join(' · ') }}</p>
                    </details>
                    <ul class="mt-2 flex flex-wrap gap-x-5 gap-y-1.5">
                        <li v-for="w in group.winners" :key="w.id" class="flex items-center gap-2 text-xs">
                            <span class="shrink-0 rounded-full bg-amber-500 text-white font-bold w-5 h-5 flex items-center justify-center text-[10px]">
                                {{ w.position ?? '—' }}
                            </span>
                            <span class="font-medium text-gray-800">{{ w.name }}</span>
                            <span class="text-[11px] text-gray-500">{{ w.item_title }}<template v-if="w.category_label"> ({{ w.category_label }})</template></span>
                            <span class="flex items-center gap-2 text-[11px]">
                                <a :href="`/certificates/print/${w.uuid}`" target="_blank" class="text-indigo-600 font-medium hover:underline">Print (With BG) ↗</a>
                                <a :href="`/certificates/print/${w.uuid}?plain=1`" target="_blank" class="text-gray-500 hover:underline">Plain ↗</a>
                                <template v-if="w.is_rendered && !w.is_stale">
                                    <a :href="`/certificates/pdf/${w.uuid}`" target="_blank" class="text-emerald-600 font-medium hover:underline">View PDF ↗</a>
                                    <a :href="`/certificates/pdf/${w.uuid}?download=1`" class="text-emerald-600 font-medium hover:underline">Download PDF</a>
                                </template>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
            <div v-else class="card p-6 text-center text-gray-500 text-sm">
                {{ winnersBySchool.length ? 'No school matches this search / filter.' : 'No merit winners grouped by school available yet.' }}
            </div>
        </div>

        <!-- TAB: Participation Certificates Grouped by School -->
        <div v-if="activeTab === 'participation_school'" class="mb-6">
            <div class="flex items-center justify-between gap-4 mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Participation Certificates by School</h3>
                    <p class="text-xs text-gray-500">One certificate per student, listing every item they took part in — organized by school for distribution.</p>
                </div>
                <div v-if="participationBySchool.length" class="flex flex-wrap items-center gap-2 shrink-0">
                    <details v-if="totalComplete || totalPrinted" class="relative">
                        <summary class="btn-primary py-1.5 px-3 text-xs inline-flex list-none cursor-pointer [&::-webkit-details-marker]:hidden"
                                 title="Print every student (all schools) whose items all have published results and who was not printed before — or reprint the ones already printed">
                            🖨️ {{ totalReadyToPrint ? `Print complete students — all schools (${totalReadyToPrint})` : `Reprint complete — all schools (${totalComplete})` }} ▾
                        </summary>
                        <div class="absolute z-20 right-0 mt-1 w-64 rounded-lg border border-gray-200 bg-white shadow-lg p-1 text-left text-xs">
                            <template v-if="totalReadyToPrint">
                                <p class="px-3 pt-1 pb-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-400">Print complete ({{ totalReadyToPrint }}) — marks them printed</p>
                                <button @click="printComplete(null, false, $event)" :disabled="printingComplete"
                                        class="block w-full text-left px-3 py-2 rounded hover:bg-gray-50 disabled:opacity-40">🖨️ With background</button>
                                <button @click="printComplete(null, true, $event)" :disabled="printingComplete"
                                        class="block w-full text-left px-3 py-2 rounded hover:bg-gray-50 disabled:opacity-40">🖨️ Without background (plain)</button>
                            </template>
                            <template v-if="totalComplete">
                                <p class="px-3 pt-1 pb-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-400">Reprint all complete ({{ totalComplete }}) — printed + ready</p>
                                <button @click="printComplete(null, false, $event, true)" :disabled="printingComplete"
                                        class="block w-full text-left px-3 py-2 rounded hover:bg-gray-50 disabled:opacity-40">🔁 With background</button>
                                <button @click="printComplete(null, true, $event, true)" :disabled="printingComplete"
                                        class="block w-full text-left px-3 py-2 rounded hover:bg-gray-50 disabled:opacity-40">🔁 Without background (plain)</button>
                            </template>
                            <template v-if="totalPrinted">
                                <div class="my-1 border-t border-gray-100"></div>
                                <button @click="clearPrinted(null, 'ALL schools', totalPrinted, $event)"
                                        class="block w-full text-left px-3 py-2 rounded hover:bg-red-50 text-red-700">↺ Clear printed status — all schools ({{ totalPrinted }})</button>
                            </template>
                        </div>
                    </details>
                <details class="relative shrink-0">
                    <summary class="btn-secondary py-1.5 px-3 text-xs inline-flex list-none cursor-pointer [&::-webkit-details-marker]:hidden">
                        📦 All schools — folder per school ▾
                    </summary>
                    <div class="absolute z-20 right-0 mt-1 w-64 rounded-lg border border-gray-200 bg-white shadow-lg p-1 text-left">
                        <button @click="queueZipDownload({ cert_type: 'participation', group_by: 'school' }, $event)" :disabled="isBatchRunning"
                                class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                            📦 ZIP (with background)
                        </button>
                        <button @click="queueZipDownload({ cert_type: 'participation', group_by: 'school', plain: '1' }, $event)" :disabled="isBatchRunning"
                                class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                            📦 ZIP — no background
                        </button>
                    </div>
                </details>
                </div>
            </div>

            <div class="mb-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs">
                <span class="font-semibold text-slate-700">📋 Student print status by school:</span>
                <select v-model="statusFilter" class="field text-xs py-1 px-2 w-auto">
                    <option value="all">All students</option>
                    <option value="unprinted">Not printed yet</option>
                    <option value="printed">Printed</option>
                </select>
                <a :href="statusUrl('pdf', { preview: 1 })" target="_blank" rel="noopener" class="btn-secondary py-1 px-3 text-xs">👁️ Preview PDF</a>
                <a :href="statusUrl('pdf')" class="btn-secondary py-1 px-3 text-xs">⬇️ PDF</a>
                <a :href="statusUrl('xls')" class="btn-secondary py-1 px-3 text-xs">⬇️ Excel</a>
                <span class="text-slate-500">One page per school: Sl No, student, Fest ID, category (C1, C2…), items, Complete when every item has results, and a Verification box.</span>
            </div>
            <div v-if="printRun" class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs text-emerald-900 flex flex-wrap items-center gap-x-4 gap-y-1">
                <span>✓ Sent {{ printRun.count }} certificate{{ printRun.count === 1 ? '' : 's' }} from {{ printRun.schools }} school{{ printRun.schools === 1 ? '' : 's' }} to print<template v-if="printRun.newly_printed"> — {{ printRun.newly_printed }} newly marked printed</template>.</span>
                <a :href="printRun.print_url_with_background" target="_blank" rel="noopener" class="font-semibold underline">Print page (with background) ↗</a>
                <a :href="printRun.print_url_plain" target="_blank" rel="noopener" class="font-semibold underline">Print page (no background) ↗</a>
                <template v-if="printRun.report_url">
                    <a :href="`${printRun.report_url}&preview=1`" target="_blank" rel="noopener" class="font-semibold underline">👁️ Report sheet ↗</a>
                    <a :href="printRun.report_url" class="font-semibold underline">⬇️ Report sheet (PDF)</a>
                </template>
            </div>
            <p v-if="printError" class="mb-3 text-xs text-red-700">{{ printError }}</p>

            <div class="mb-3 flex flex-wrap items-center gap-3">
                <input v-model="schoolSearch" type="search" placeholder="Search school or student…"
                       class="field text-xs py-1.5 px-3 w-full sm:w-72" />
                <select v-model="schoolReadiness" class="field text-xs py-1.5 px-2 w-auto">
                    <option value="all">All schools</option>
                    <option value="ready">✓ Results complete ({{ participationBySchool.filter(isSchoolReady).length }})</option>
                    <option value="pending">⏳ Results pending ({{ participationBySchool.length - participationBySchool.filter(isSchoolReady).length }})</option>
                </select>
                <select v-model="schoolDownloaded" class="field text-xs py-1.5 px-2 w-auto">
                    <option value="all">Downloaded: any</option>
                    <option value="yes">✓ Downloaded ({{ participationBySchool.filter(g => g.downloaded).length }})</option>
                    <option value="no">Not downloaded ({{ participationBySchool.filter(g => !g.downloaded).length }})</option>
                </select>
                <span v-if="schoolSearch.trim() || schoolReadiness !== 'all' || schoolDownloaded !== 'all'" class="text-xs text-gray-500">
                    {{ filteredParticipationBySchool.length }} of {{ participationBySchool.length }} schools
                </span>
                <details v-if="readyCertificateIds(filteredParticipationBySchool).length" class="relative ml-auto">
                    <summary class="btn-secondary py-1.5 px-3 text-xs inline-flex list-none cursor-pointer [&::-webkit-details-marker]:hidden">
                        📦 Results-complete schools shown ({{ filteredParticipationBySchool.filter(isSchoolReady).length }}) ▾
                    </summary>
                    <div class="absolute z-20 right-0 mt-1 w-72 rounded-lg border border-gray-200 bg-white shadow-lg p-1 text-left">
                        <p class="px-3 pt-2 pb-1 text-[11px] text-gray-500">One folder per school — only schools whose every registered item has published results.</p>
                        <button @click="queueZipDownload({ certificate_ids: readyCertificateIds(filteredParticipationBySchool).join(','), group_by: 'school' }, $event)" :disabled="isBatchRunning"
                                class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                            📦 ZIP (with background)
                        </button>
                        <button @click="queueZipDownload({ certificate_ids: readyCertificateIds(filteredParticipationBySchool).join(','), group_by: 'school', plain: '1' }, $event)" :disabled="isBatchRunning"
                                class="block w-full text-left px-3 py-2 text-xs rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                            📦 ZIP — no background
                        </button>
                        <p v-if="isBatchRunning" class="px-3 pb-2 text-[11px] text-amber-700">Available once the current run finishes.</p>
                    </div>
                </details>
            </div>
            <div v-if="filteredParticipationBySchool.length" class="card divide-y divide-gray-100">
                <div v-for="group in filteredParticipationBySchool" :key="group.school_id" class="py-3 first:pt-0 last:pb-0">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0 flex flex-wrap items-center gap-x-3 gap-y-1">
                            <p class="font-semibold text-sm text-gray-900">{{ group.school_name }}</p>
                            <span class="shrink-0 text-xs px-2 py-0.5 rounded bg-sky-100 text-sky-800 font-medium">
                                {{ group.winners.length }} student{{ group.winners.length === 1 ? '' : 's' }}
                            </span>
                            <span v-if="isSchoolReady(group)" class="shrink-0 text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-medium"
                                  :title="`All ${group.results.total} registered item(s) have published results — safe to bulk-download`">
                                ✓ Results complete ({{ group.results.total }}/{{ group.results.total }})
                            </span>
                            <span v-else-if="group.results?.total" class="shrink-0 text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-medium"
                                  :title="`Results pending: ${group.results.pending.join(', ')}`">
                                ⏳ {{ group.results.pending.length }} of {{ group.results.total }} items pending
                            </span>
                            <span v-if="group.downloaded" class="shrink-0 text-xs px-2 py-0.5 rounded bg-emerald-600 text-white font-medium"
                                  :title="group.downloaded_at ? `Marked downloaded ${new Date(group.downloaded_at).toLocaleString()}` : 'Marked downloaded'">
                                ✓ Downloaded
                            </span>
                            <span v-if="printedCount(group)" class="shrink-0 text-xs px-2 py-0.5 rounded bg-teal-100 text-teal-800 font-medium"
                                  title="Students already sent to print by a 'Print complete students' run">
                                🖨️ {{ printedCount(group) }}/{{ group.winners.length }} printed
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-xs shrink-0">
                            <label class="inline-flex items-center gap-1.5 cursor-pointer select-none"
                                   :title="group.downloaded ? 'Marked as downloaded — click to undo' : 'Tick once you have downloaded / handed over this school\'s certificates'">
                                <input type="checkbox" class="rounded border-gray-300 text-emerald-600"
                                       :checked="group.downloaded" :disabled="!group.school_id"
                                       @change="markDownloaded(group, 'participation', $event.target.checked)" />
                                <span :class="group.downloaded ? 'font-semibold text-emerald-700' : 'text-gray-500'">
                                    {{ group.downloaded ? '✓ Downloaded' : 'Mark downloaded' }}
                                </span>
                            </label>
                            <a :href="statusUrl('pdf', { preview: 1, school_id: group.school_id })" target="_blank" rel="noopener"
                               class="font-semibold text-slate-600 hover:text-slate-800" title="This school's students with printed / ready / awaiting status">📋 List</a>
                            <button v-if="printedCount(group)" @click="clearPrinted(group.school_id, group.school_name, printedCount(group))"
                                    class="font-semibold text-red-600 hover:text-red-800"
                                    title="Clear this school's printed status so its students can be printed together again">
                                ↺ Clear printed ({{ printedCount(group) }})
                            </button>
                            <details v-if="completeCount(group)" class="relative">
                                <summary class="font-semibold text-emerald-700 hover:text-emerald-900 inline-flex items-center gap-1 list-none cursor-pointer [&::-webkit-details-marker]:hidden"
                                         title="Print students whose every item has published results, or reprint the ones already printed">
                                    🖨️ {{ readyToPrint(group) ? `Print complete (${readyToPrint(group)})` : `Reprint complete (${completeCount(group)})` }} ▾
                                </summary>
                                <div class="absolute z-20 right-0 mt-1 w-64 rounded-lg border border-gray-200 bg-white shadow-lg p-1 text-left">
                                    <template v-if="readyToPrint(group)">
                                        <p class="px-3 pt-1 pb-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-400">Print complete ({{ readyToPrint(group) }}) — marks them printed</p>
                                        <button @click="printComplete(group.school_id, false, $event)" :disabled="printingComplete"
                                                class="block w-full text-left px-3 py-2 rounded hover:bg-gray-50 disabled:opacity-40">🖨️ With background</button>
                                        <button @click="printComplete(group.school_id, true, $event)" :disabled="printingComplete"
                                                class="block w-full text-left px-3 py-2 rounded hover:bg-gray-50 disabled:opacity-40">🖨️ Without background (plain)</button>
                                    </template>
                                    <template v-if="completeCount(group)">
                                        <p class="px-3 pt-1 pb-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-400">Reprint all complete ({{ completeCount(group) }}) — printed + ready</p>
                                        <button @click="printComplete(group.school_id, false, $event, true)" :disabled="printingComplete"
                                                class="block w-full text-left px-3 py-2 rounded hover:bg-gray-50 disabled:opacity-40">🔁 With background</button>
                                        <button @click="printComplete(group.school_id, true, $event, true)" :disabled="printingComplete"
                                                class="block w-full text-left px-3 py-2 rounded hover:bg-gray-50 disabled:opacity-40">🔁 Without background (plain)</button>
                                    </template>
                                    <template v-if="printedCount(group)">
                                        <div class="my-1 border-t border-gray-100"></div>
                                        <button @click="clearPrinted(group.school_id, group.school_name, printedCount(group), $event)"
                                                class="block w-full text-left px-3 py-2 rounded hover:bg-red-50 text-red-700">↺ Clear printed status ({{ printedCount(group) }})</button>
                                    </template>
                                </div>
                            </details>
                            <button @click="renderAndCache({ school_id: group.school_id, cert_type: 'participation' })"
                                    class="font-semibold text-indigo-600 hover:text-indigo-800 disabled:opacity-40 disabled:cursor-not-allowed"
                                    :disabled="isBatchRunning">
                                ⚙️ Render
                            </button>
                            <details class="relative">
                                <summary class="font-semibold text-gray-600 hover:text-gray-800 inline-flex items-center gap-1 list-none cursor-pointer [&::-webkit-details-marker]:hidden">
                                    📦 Download ▾
                                </summary>
                                <div class="absolute z-20 right-0 mt-1 w-56 rounded-lg border border-gray-200 bg-white shadow-lg p-1 text-left">
                                    <!-- While another run is going, a queued ZIP would wait behind all of
                                         its jobs — download directly instead (reuses rendered PDFs). -->
                                    <template v-if="isBatchRunning">
                                        <a :href="`${base}/download-zip?school_id=${group.school_id}&cert_type=participation`" class="block px-3 py-2 rounded hover:bg-gray-50">📦 ZIP (with background)</a>
                                        <a :href="`${base}/download-zip?school_id=${group.school_id}&cert_type=participation&plain=1`" class="block px-3 py-2 rounded hover:bg-gray-50">📦 ZIP — no background</a>
                                    </template>
                                    <template v-else>
                                        <button @click="queueZipDownload({ school_id: group.school_id, cert_type: 'participation' }, $event)"
                                                class="block w-full text-left px-3 py-2 rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">📦 ZIP (with background)</button>
                                        <button @click="queueZipDownload({ school_id: group.school_id, cert_type: 'participation', plain: '1' }, $event)"
                                                class="block w-full text-left px-3 py-2 rounded hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">📦 ZIP — no background</button>
                                    </template>
                                    <a :href="`${base}/print-all?school_id=${group.school_id}&cert_type=participation`" target="_blank" class="block px-3 py-2 rounded hover:bg-gray-50">🖨️ Print (with background) ↗</a>
                                    <a :href="`${base}/print-all?school_id=${group.school_id}&cert_type=participation&plain=1`" target="_blank" class="block px-3 py-2 rounded hover:bg-gray-50">🖨️ Print (plain) ↗</a>
                                </div>
                            </details>
                        </div>
                    </div>
                    <details v-if="group.results?.pending?.length" class="mt-1">
                        <summary class="text-xs font-medium text-amber-700 hover:text-amber-900 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                            ▸ {{ group.results.pending.length }} item{{ group.results.pending.length === 1 ? '' : 's' }} still awaiting results
                        </summary>
                        <p class="mt-1 text-[11px] text-gray-600">{{ group.results.pending.join(' · ') }}</p>
                    </details>
                    <details class="mt-2">
                        <summary class="text-xs font-medium text-gray-500 hover:text-gray-700 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                            ▸ View {{ group.winners.length }} name{{ group.winners.length === 1 ? '' : 's' }}
                        </summary>
                        <ol class="mt-2 divide-y divide-gray-50">
                            <li v-for="(w, index) in group.winners" :key="w.id" class="py-1.5 flex flex-wrap items-baseline gap-x-3 gap-y-0.5 text-xs">
                                <span class="w-6 shrink-0 text-right text-[11px] text-gray-400 tabular-nums">{{ index + 1 }}.</span>
                                <span class="font-medium text-gray-800">{{ w.name }}</span>
                                <span v-if="w.printed" class="text-[10px] px-1.5 py-0.5 rounded bg-teal-100 text-teal-800 font-semibold">🖨️ printed</span>
                                <span v-else-if="w.complete" class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">ready</span>
                                <span v-else class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-semibold">awaiting results</span>
                                <span class="min-w-0 flex-1 text-[11px] text-gray-500">{{ participationItemsText(w) }}</span>
                                <span class="flex items-center gap-2 text-[11px] shrink-0">
                                    <a :href="`/certificates/print/${w.uuid}`" target="_blank" class="text-indigo-600 font-medium hover:underline">Print (With BG) ↗</a>
                                    <a :href="`/certificates/print/${w.uuid}?plain=1`" target="_blank" class="text-gray-500 hover:underline">Plain ↗</a>
                                    <template v-if="w.is_rendered && !w.is_stale">
                                        <a :href="`/certificates/pdf/${w.uuid}`" target="_blank" class="text-emerald-600 font-medium hover:underline">View PDF ↗</a>
                                        <a :href="`/certificates/pdf/${w.uuid}?download=1`" class="text-emerald-600 font-medium hover:underline">Download PDF</a>
                                    </template>
                                </span>
                            </li>
                        </ol>
                    </details>
                </div>
            </div>
            <div v-else class="card p-6 text-center text-gray-500 text-sm">
                {{ participationBySchool.length ? 'No school matches this search / filter.' : 'No participation certificates generated yet.' }}
            </div>
        </div>

        <!-- TAB 3: All Certificates with School Filter, Search, Pagination, & Bulk Actions -->
        <div v-if="activeTab === 'all'" class="space-y-4">
            <!-- Filter & Search Controls Bar -->
            <div class="card p-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3 flex-1 min-w-[280px]">
                    <!-- Search Input -->
                    <div class="relative min-w-[200px] flex-1">
                        <input v-model="searchQuery" type="text" placeholder="Search student or item..."
                               class="w-full text-xs py-2 pl-8 pr-3 rounded border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <span class="absolute left-2.5 top-2 text-gray-400 text-xs">🔍</span>
                    </div>

                    <!-- School Filter -->
                    <SearchableSelect v-model="selectedSchoolId" :options="schools" :all-option="true" :all-label="`All Schools (${schools.length})`" class="max-w-[240px]" />

                    <!-- Certificate Type Filter -->
                    <SearchableSelect v-model="selectedCertType" :options="[{ value: 'winner', label: 'Merit Winners' }, { value: 'participation', label: 'Participation' }]" :all-option="true" all-label="All Types" />
                </div>

                <!-- Page Size & Download School ZIP -->
                <div class="flex items-center gap-3 text-xs text-gray-600 shrink-0">
                    <button v-if="selectedSchoolId" @click="queueZipDownload({ school_id: selectedSchoolId })" :disabled="isBatchRunning"
                            class="btn-secondary py-1.5 px-3 text-xs flex items-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed">
                        📦 Download School ZIP
                    </button>
                    <div class="flex items-center gap-1.5">
                        <span>Show:</span>
                        <SearchableSelect v-model="perPage" :options="[{ value: 25, label: '25' }, { value: 50, label: '50' }, { value: 100, label: '100' }, { value: 'all', label: 'All' }]" :all-option="false" />
                    </div>
                </div>
            </div>

            <!-- Bulk Action Toolbar (when checkboxes are selected) -->
            <div v-if="selectedCertIds.length" class="bg-indigo-50 border border-indigo-200 rounded p-3 flex flex-wrap items-center justify-between gap-3 text-xs">
                <span class="font-semibold text-indigo-900">
                    Selected {{ selectedCertIds.length }} certificate{{ selectedCertIds.length === 1 ? '' : 's' }}
                </span>
                <div class="flex flex-wrap items-center gap-2">
                    <button @click="bulkPrint(false)" class="btn-primary py-1 px-3 text-xs">
                        🖨️ Bulk Print (With BG)
                    </button>
                    <button @click="bulkPrint(true)" class="btn-secondary py-1 px-3 text-xs">
                        📄 Bulk Print Plain (No BG)
                    </button>
                    <button @click="bulkDownload" :disabled="isBatchRunning" class="btn-secondary py-1 px-3 text-xs disabled:opacity-40 disabled:cursor-not-allowed">
                        📦 Bulk Download ZIP
                    </button>
                    <button @click="selectedCertIds = []" class="text-gray-500 hover:text-gray-700 ml-2">
                        Clear Selection
                    </button>
                </div>
            </div>

            <!-- Certificate Table / List -->
            <div class="card p-4">
                <div v-if="paginatedCertificates.length" class="divide-y divide-gray-100">
                    <div class="py-2 px-1 flex items-center justify-between text-xs font-semibold text-gray-500 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" :checked="isAllSelectedOnPage" @change="toggleSelectAllPage" class="rounded border-gray-300">
                            <span>Student / Item / School</span>
                        </div>
                        <span>Actions</span>
                    </div>

                    <div v-for="c in paginatedCertificates" :key="c.id" class="py-3 flex items-center justify-between gap-4">
                        <div class="flex items-start gap-3 min-w-0">
                            <input type="checkbox" :value="c.id" v-model="selectedCertIds" class="mt-1 rounded border-gray-300">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-sm text-gray-900">{{ c.student?.name ?? c.participant?.student?.name ?? 'Participant' }}</span>
                                    <span class="text-[10px] px-2 py-0.5 rounded font-semibold uppercase tracking-wider"
                                          :class="c.cert_type === 'winner' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'">
                                        {{ certificateTypeLabel(c.cert_type) }}
                                    </span>
                                    <span v-if="c.is_stale" class="text-[10px] px-2 py-0.5 rounded font-semibold uppercase tracking-wider bg-amber-100 text-amber-800" title="Source data or template changed since this was last rendered">
                                        ⚠️ Stale
                                    </span>
                                    <span v-else-if="!c.is_rendered" class="text-[10px] px-2 py-0.5 rounded font-semibold uppercase tracking-wider bg-gray-100 text-gray-500" title="Not yet rendered — use Render &amp; Cache Files">
                                        Not rendered
                                    </span>
                                </div>
                                <p class="text-xs text-gray-600 mt-0.5 truncate">
                                    {{ c.item?.title ?? 'Event Participant' }}
                                    <span v-if="c.registration?.school?.name || c.participant?.registration?.school?.name" class="text-gray-400"> · </span>
                                    <span class="text-gray-500 font-medium">{{ c.registration?.school?.name ?? c.participant?.registration?.school?.name }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 text-xs shrink-0">
                            <a :href="`/certificates/verify/${c.uuid}`" target="_blank" class="text-gray-500 hover:text-gray-700">Verify ↗</a>
                            <a :href="`/certificates/print/${c.uuid}?preview=1`" target="_blank" class="text-gray-500 hover:text-gray-700">Preview ↗</a>
                            <a :href="`/certificates/print/${c.uuid}`" target="_blank" class="font-semibold text-indigo-600 hover:underline">Print (With BG) ↗</a>
                            <a :href="`/certificates/print/${c.uuid}?plain=1`" target="_blank" class="text-gray-500 hover:underline">Print Plain ↗</a>
                            <template v-if="c.is_rendered && !c.is_stale">
                                <a :href="`/certificates/pdf/${c.uuid}`" target="_blank" class="font-semibold text-emerald-600 hover:underline">View PDF ↗</a>
                                <a :href="`/certificates/pdf/${c.uuid}?download=1`" class="font-semibold text-emerald-600 hover:underline">Download PDF</a>
                            </template>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center text-gray-500 text-sm py-8">
                    No matching certificates found.
                </div>

                <!-- Pagination Footer -->
                <div v-if="totalPages > 1" class="mt-4 pt-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-600">
                    <span>
                        Showing {{ ((currentPage - 1) * perPageNum) + 1 }} to {{ Math.min(currentPage * perPageNum, filteredCertificates.length) }} of {{ filteredCertificates.length }} certificates
                    </span>
                    <div class="flex items-center gap-1">
                        <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1"
                                class="px-2.5 py-1 rounded border border-gray-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-50">
                            Previous
                        </button>
                        <span class="px-2 font-medium">Page {{ currentPage }} of {{ totalPages }}</span>
                        <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages"
                                class="px-2.5 py-1 rounded border border-gray-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-50">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent render/regenerate/export runs -->
        <div v-if="recentBatches.length" class="card p-4 mt-8">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Recent render &amp; export runs</h3>
            <div class="divide-y divide-gray-100 text-xs">
                <div v-for="b in recentBatches" :key="b.id" class="py-2 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <span class="font-medium text-gray-800">{{ batchTypeLabel(b.batch_type) }}</span>
                        <span class="text-gray-500"> · {{ b.scope_description ?? 'Whole event' }}</span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a v-if="b.batch_type === 'zip_export' && ['completed', 'completed_with_errors'].includes(b.status)"
                           :href="`${base}/batches/${b.id}/download`" class="font-semibold text-indigo-600 hover:underline">
                            ⬇️ Download
                        </a>
                        <span class="text-gray-500">{{ b.succeeded_count }}/{{ b.total_count }}<span v-if="b.failed_count" class="text-red-600"> ({{ b.failed_count }} failed)</span></span>
                        <span class="px-2 py-0.5 rounded font-semibold capitalize"
                              :class="{
                                  'bg-green-100 text-green-800': b.status === 'completed',
                                  'bg-amber-100 text-amber-800': b.status === 'completed_with_errors',
                                  'bg-red-100 text-red-800': b.status === 'failed',
                                  'bg-gray-100 text-gray-600': !['completed', 'completed_with_errors', 'failed'].includes(b.status),
                              }">
                            {{ b.status.replace('_', ' ') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import EventSignatoriesCard from '@/Components/certificates/EventSignatoriesCard.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, certificates: Array,
    participationHeld: { type: Boolean, default: false },
    participationParentUrl: { type: String, default: null },
    publishedItems: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    winnersByItem: { type: Array, default: () => [] },
    winnersBySchool: { type: Array, default: () => [] },
    participationBySchool: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    recentBatches: { type: Array, default: () => [] },
    staleCount: { type: Number, default: 0 },
    certificateSignatories: { type: Array, default: () => [] },
    signatoryLabelSuggestions: { type: Array, default: () => [] },
});

const page = usePage();
const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates`;

const activeTab = ref('winners_item');
const plainMode = ref(false);
const selectedItemId = ref(null);

const certificateDateInput = ref(props.event.certificate_date || '');

const defaultCertificateDateLabel = computed(() => {
    const raw = props.event.event_end || props.event.event_start;
    if (!raw) return 'today (no event dates set)';
    return new Date(raw).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
});

// A participation certificate is one per student and prints every item they entered, so
// list them all (falls back to the anchor item for a row without the aggregated list).
function participationItemsText(row) {
    const items = row.items?.length ? row.items : [{ title: row.item_title, category_label: row.category_label }];
    return items
        .filter(i => i.title)
        .map(i => (i.category_label ? `${i.title} (${i.category_label})` : i.title))
        .join(', ');
}

function saveCertificateDate() {
    router.post(`${base}/certificate-date`, { certificate_date: certificateDateInput.value || null }, { preserveScroll: true });
}

function clearCertificateDate() {
    certificateDateInput.value = '';
    saveCertificateDate();
}

const publishedItemOptions = computed(() => props.publishedItems.map(item => {
    // Same-titled items (e.g. three separate "Book Review" items, one per class-group
    // category, or split further by gender) are otherwise indistinguishable in this
    // dropdown — category/type/gender always shown alongside the item_code, not just
    // as a fallback when item_code is missing.
    const meta = [item.category_label, item.type_label, item.gender_label].filter(Boolean).join(' · ');
    const codePrefix = item.item_code ? `[${item.item_code}] ` : '';
    return {
        value: item.id,
        label: meta ? `${codePrefix}${item.title} (${meta})` : `${codePrefix}${item.title}`,
    };
}));

// Render/cache batch progress — same dispatch -> flash key -> poll /progress pattern as
// Settings/StorageMigration.vue's async job UX.
const jobStatus = ref(null);
let pollTimer = null;

// Shared by both by-school tabs: matches the school name or any student listed under it,
// and the results-readiness filter.
const schoolSearch = ref('');
const schoolReadiness = ref('all');
const schoolDownloaded = ref('all');

// --- "Print complete students": participation certificates of students whose every item has
// published results, for schools that are otherwise still pending. The server records them as
// printed (so they are never printed twice), opens the print page, and builds a per-school report.
const statusFilter = ref('all');
function statusUrl(format, extra = {}) {
    const params = new URLSearchParams({ status: statusFilter.value, ...extra });
    return `${base}/print-status/${format}?${params.toString()}`;
}

const printingComplete = ref(false);
const printRun = ref(null);
const printError = ref('');

function readyToPrint(group) {
    return (group.winners ?? []).filter((w) => w.complete && !w.printed).length;
}
function printedCount(group) {
    return (group.winners ?? []).filter((w) => w.printed).length;
}
const totalPrinted = computed(() => props.participationBySchool.reduce((n, g) => n + printedCount(g), 0));

function clearPrinted(schoolId, name, count, clickEvent = null) {
    clickEvent?.target?.closest('details')?.removeAttribute('open');
    if (!window.confirm(`Clear the printed status of ${count} student${count === 1 ? '' : 's'} (${name})? They will count as not printed again and can be printed together later. The certificates themselves are not deleted.`)) return;
    router.post(`${base}/print-status/clear`, { school_id: schoolId ?? null }, { preserveScroll: true });
}

function completeCount(group) {
    return (group.winners ?? []).filter((w) => w.complete).length;
}
const totalComplete = computed(() => props.participationBySchool.reduce((n, g) => n + completeCount(g), 0));
function reprintUrl(schoolId, plain) {
    const params = new URLSearchParams({ reprint: '1' });
    if (schoolId) params.set('school_id', schoolId);
    if (plain) params.set('plain', '1');
    return `${base}/print-all?${params.toString()}`;
}
const totalReadyToPrint = computed(() => props.participationBySchool.reduce((n, g) => n + readyToPrint(g), 0));

function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function printComplete(schoolId, plain = false, clickEvent = null, includePrinted = false) {
    clickEvent?.target?.closest('details')?.removeAttribute('open');
    const what = schoolId ? 'this school' : 'all schools';
    const prompt = includePrinted
        ? `Reprint every complete student of ${what} (already printed and ready) ${plain ? 'without background' : 'with background'}? Students not printed yet are marked printed.`
        : `Print every complete student of ${what} ${plain ? 'without background' : 'with background'} and mark them as printed?`;
    if (!window.confirm(prompt)) return;
    printingComplete.value = true;
    printError.value = '';
    // Opened synchronously so the browser doesn't block it as a pop-up after the request.
    const printWindow = window.open('', '_blank');
    try {
        const res = await fetch(`${base}/print-complete`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ school_id: schoolId ?? null, plain, include_printed: includePrinted }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            printWindow?.close();
            printError.value = data.message || 'Could not start the print run.';
            return;
        }
        printRun.value = data;
        if (printWindow) printWindow.location = data.print_url;
        router.reload({ preserveScroll: true, preserveState: true });
    } catch (e) {
        printWindow?.close();
        printError.value = 'Could not start the print run.';
    } finally {
        printingComplete.value = false;
    }
}

// Manual checklist: tick a school once its certificates have been downloaded / handed over.
function markDownloaded(group, certType, downloaded) {
    router.post(`${base}/school-downloaded`, {
        school_id: group.school_id,
        cert_type: certType,
        downloaded,
    }, { preserveScroll: true, preserveState: true });
}

// Every item the school has approved registrations in has published results (see
// FestCertificateController::schoolResultsStatus()) — no merit result or grade still to come.
function isSchoolReady(group) {
    return (group.results?.total ?? 0) > 0 && (group.results?.pending?.length ?? 0) === 0;
}

function matchesSchoolSearch(group) {
    if (schoolReadiness.value === 'ready' && !isSchoolReady(group)) return false;
    if (schoolReadiness.value === 'pending' && isSchoolReady(group)) return false;
    if (schoolDownloaded.value === 'yes' && !group.downloaded) return false;
    if (schoolDownloaded.value === 'no' && group.downloaded) return false;
    const q = schoolSearch.value.trim().toLowerCase();
    if (!q) return true;
    return (group.school_name ?? '').toLowerCase().includes(q)
        || (group.winners ?? []).some(w => (w.name ?? '').toLowerCase().includes(q));
}

function readyCertificateIds(groups) {
    return groups.filter(isSchoolReady).flatMap(group => group.winners.map(w => w.id));
}
const filteredParticipationBySchool = computed(() => props.participationBySchool.filter(matchesSchoolSearch));
const filteredWinnersBySchool = computed(() => props.winnersBySchool.filter(matchesSchoolSearch));

function startPolling(batchId) {
    if (!batchId) return;
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(async () => {
        const res = await fetch(`${base}/batches/${batchId}/progress`, { headers: { Accept: 'application/json' } });
        jobStatus.value = await res.json();
        if (['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(jobStatus.value?.status)) {
            clearInterval(pollTimer);
            if (['completed', 'completed_with_errors'].includes(jobStatus.value.status)) {
                router.reload({ only: ['certificates', 'recentBatches', 'staleCount'] });
            }
        }
    }, 3000);
}

function renderAndCache(scope = {}) {
    router.post(`${base}/batches`, scope, {
        preserveScroll: true,
        onSuccess: () => startPolling(page.props.flash?.certificate_batch_id),
    });
}

function regenerateStale() {
    router.post(`${base}/regenerate-stale`, {}, {
        preserveScroll: true,
        onSuccess: () => startPolling(page.props.flash?.certificate_batch_id),
    });
}

// Queues a ZIP export instead of downloading synchronously — an event with hundreds to
// thousands of certificates could blow past the web server/proxy's own request timeout
// long before it finished. Shares the same dispatch -> flash key -> poll /progress ->
// download-when-ready flow as renderAndCache()/regenerateStale() above.
function queueZipDownload(scope = {}, closeEvent = null) {
    const payload = { ...scope };
    if (plainMode.value) payload.plain = '1';
    router.post(`${base}/download-zip/queue`, payload, {
        preserveScroll: true,
        onSuccess: () => startPolling(page.props.flash?.certificate_batch_id),
    });
    // The dropdown is a native <details>, which only auto-closes on a real navigation —
    // this is an AJAX dispatch, so close it explicitly rather than leaving it open on the
    // option the admin just clicked.
    closeEvent?.target?.closest('details')?.removeAttribute('open');
}

const previewSampleUrl = computed(() => `${base}/preview-sample?cert_type=participation`);
const isBatchRunning = computed(() => jobStatus.value && !['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(jobStatus.value.status));

const jobStatusLabel = computed(() => {
    if (!jobStatus.value) return '';
    if (jobStatus.value.batch_type === 'zip_export') return 'Preparing ZIP download';
    if (jobStatus.value.batch_type === 'regenerate_stale') return 'Regenerating stale certificates';
    return 'Rendering certificates';
});

function batchTypeLabel(batchType) {
    if (batchType === 'zip_export') return 'ZIP export';
    if (batchType === 'regenerate_stale') return 'Regenerate stale';
    return 'Render';
}

// Every Download / print menu on this page is a native <details class="relative">, which
// only closes when its own summary is clicked again — so menus piled up open (one per
// school row). Close them like normal menus: clicking anywhere outside (including opening
// another menu), picking an option inside, or Escape. The "View names" / pending-items
// expanders aren't .relative, so they're left alone.
function closeDropdownMenus(event) {
    document.querySelectorAll('details.relative[open]').forEach((menu) => {
        const inside = event?.target instanceof Node && menu.contains(event.target);
        const pickedOption = inside && !event.target.closest('summary') && event.target.closest('a, button');
        if (!inside || pickedOption) {
            menu.removeAttribute('open');
        }
    });
}
function closeDropdownMenusOnEscape(event) {
    if (event.key === 'Escape') closeDropdownMenus();
}

onMounted(() => {
    const lastBatch = props.recentBatches?.[0];
    if (lastBatch && !['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(lastBatch.status)) {
        startPolling(lastBatch.id);
    }
    document.addEventListener('click', closeDropdownMenus);
    document.addEventListener('keydown', closeDropdownMenusOnEscape);
});
onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
    document.removeEventListener('click', closeDropdownMenus);
    document.removeEventListener('keydown', closeDropdownMenusOnEscape);
});

// Search, Filter, & Pagination state
const searchQuery = ref('');
const selectedSchoolId = ref(null);
const selectedCertType = ref(null);
const perPage = ref(25);
const currentPage = ref(1);
const selectedCertIds = ref([]);

const filteredCertificates = computed(() => {
    return props.certificates.filter(c => {
        if (selectedCertType.value && c.cert_type !== selectedCertType.value) {
            return false;
        }
        if (selectedSchoolId.value) {
            const schoolId = c.registration?.school?.id ?? c.participant?.registration?.school?.id;
            if (schoolId !== selectedSchoolId.value) return false;
        }
        if (searchQuery.value.trim()) {
            const q = searchQuery.value.toLowerCase().trim();
            const studentName = (c.student?.name ?? c.participant?.student?.name ?? '').toLowerCase();
            const itemTitle = (c.item?.title ?? '').toLowerCase();
            const schoolName = (c.registration?.school?.name ?? c.participant?.registration?.school?.name ?? '').toLowerCase();
            if (!studentName.includes(q) && !itemTitle.includes(q) && !schoolName.includes(q)) {
                return false;
            }
        }
        return true;
    });
});

const perPageNum = computed(() => perPage.value === 'all' ? filteredCertificates.value.length || 1 : Number(perPage.value));

const totalPages = computed(() => Math.ceil(filteredCertificates.value.length / perPageNum.value) || 1);

const paginatedCertificates = computed(() => {
    if (perPage.value === 'all') return filteredCertificates.value;
    const start = (currentPage.value - 1) * perPageNum.value;
    return filteredCertificates.value.slice(start, start + perPageNum.value);
});

watch([searchQuery, selectedSchoolId, selectedCertType, perPage], () => {
    currentPage.value = 1;
});

const isAllSelectedOnPage = computed(() => {
    if (!paginatedCertificates.value.length) return false;
    return paginatedCertificates.value.every(c => selectedCertIds.value.includes(c.id));
});

function toggleSelectAllPage() {
    if (isAllSelectedOnPage.value) {
        const pageIds = new Set(paginatedCertificates.value.map(c => c.id));
        selectedCertIds.value = selectedCertIds.value.filter(id => !pageIds.has(id));
    } else {
        const set = new Set(selectedCertIds.value);
        paginatedCertificates.value.forEach(c => set.add(c.id));
        selectedCertIds.value = Array.from(set);
    }
}

function bulkPrint(plain = false) {
    if (!selectedCertIds.value.length) return;
    const ids = selectedCertIds.value.join(',');
    const url = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/print-all?certificate_ids=${ids}${plain ? '&plain=1' : ''}`;
    window.open(url, '_blank');
}

function bulkDownload() {
    if (!selectedCertIds.value.length) return;
    queueZipDownload({ certificate_ids: selectedCertIds.value.join(',') });
}

const printAllUrl = computed(() =>
    `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/print-all${plainMode.value ? '?plain=1' : ''}`);

function generate(itemId = null) {
    const targetId = itemId || selectedItemId.value;
    const payload = targetId ? { item_id: targetId } : {};
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/generate`, payload, { preserveScroll: true });
}

function generateParticipation() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/participation`, {}, { preserveScroll: true });
}

function certificateTypeLabel(type) {
    return type === 'winner' ? 'Winner' : 'Participation';
}
</script>
