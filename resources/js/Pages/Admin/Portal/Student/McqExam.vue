<template>
    <PortalLayout
        role-label="Student Portal"
        :title="exam.title"
        :subtitle="[school.name, student.reg_no].filter(Boolean).join(' · ')"
        accent="navy"
        :nav-items="started ? [] : navItems"
        :focus-mode="started"
    >
        <!-- Pre-exam Instructions & Flight Checklist -->
        <div v-if="!started" class="max-w-3xl mx-auto space-y-5 py-4">
            <div class="rounded-3xl border border-slate-200/90 bg-white p-6 sm:p-8 shadow-sm space-y-6">
                <!-- Header -->
                <div class="text-center space-y-2 border-b border-slate-100 pb-5">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-blue-50 text-[#0f3d7a] border border-blue-200">
                        Assessment Briefing
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        {{ exam.title }}
                    </h1>
                    <p class="text-sm text-slate-500 max-w-lg mx-auto">
                        Please review the exam structure, rules, and device status before starting your timer.
                    </p>
                </div>

                <!-- 3-Column Structured Checklist -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <!-- 1. Structure & Marks -->
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 space-y-2.5">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">📊</span>
                            <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-800">Exam Specs</h2>
                        </div>
                        <ul class="text-xs text-slate-600 space-y-1.5">
                            <li class="flex justify-between">
                                <span class="text-slate-500">Total Questions:</span>
                                <span class="font-bold text-slate-900">{{ questions.length }}</span>
                            </li>
                            <li class="flex justify-between">
                                <span class="text-slate-500">Gradable items:</span>
                                <span class="font-bold text-slate-900">{{ gradableCount }}</span>
                            </li>
                            <li class="flex justify-between">
                                <span class="text-slate-500">Total Marks:</span>
                                <span class="font-bold text-slate-900">{{ totalMarks }}</span>
                            </li>
                            <li v-if="exam.duration_minutes" class="flex justify-between border-t border-slate-200/60 pt-1.5">
                                <span class="text-slate-500">Time Limit:</span>
                                <span class="font-bold text-[#0f3d7a]">{{ exam.duration_minutes }} mins</span>
                            </li>
                            <li v-if="exam.has_negative_marking" class="text-amber-800 font-semibold pt-1">
                                ⚠️ Negative marking applies to some questions
                            </li>
                        </ul>
                    </div>

                    <!-- 2. Technical Safeguards -->
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 space-y-2.5">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🛡️</span>
                            <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-800">Integrity &amp; Sync</h2>
                        </div>
                        <ul class="text-xs text-slate-600 space-y-1.5 leading-relaxed">
                            <li class="flex items-start gap-1.5">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Answers <strong>autosave</strong> automatically while you work.</span>
                            </li>
                            <li class="flex items-start gap-1.5">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Free navigation between all questions anytime.</span>
                            </li>
                            <li v-if="exam.proctor_mode === 'strict_tab'" class="flex items-start gap-1.5 text-amber-800">
                                <span>⚠️</span>
                                <span>Keep tab open: max {{ exam.proctor_max_tab_violations || 3 }} switches before auto-submit.</span>
                            </li>
                            <li v-else class="flex items-start gap-1.5 text-slate-500">
                                <span>ℹ️</span>
                                <span>Tab switches and leaves are logged for review.</span>
                            </li>
                        </ul>
                    </div>

                    <!-- 3. Device Check -->
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 space-y-2.5">
                        <div class="flex items-center justify-between gap-1">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">💻</span>
                                <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-800">Device Health</h2>
                            </div>
                            <a v-if="systemCheckUrl" :href="systemCheckUrl" target="_blank" rel="noopener" class="text-[10px] font-bold text-[#0f3d7a] hover:underline">Full check ↗</a>
                        </div>
                        <p class="text-[11px] text-slate-500">Verify your browser &amp; connection before starting. Extra monitors are not detected automatically.</p>
                        <button
                            type="button"
                            class="w-full btn-secondary text-xs !py-1.5"
                            :disabled="sysCheckRunning"
                            @click="runInlineSystemCheck"
                        >
                            {{ sysCheckRunning ? 'Testing device…' : (sysCheckResult ? 'Run again' : 'Quick device check') }}
                        </button>
                        <ul v-if="sysCheckResult" class="text-[11px] space-y-1 pt-1">
                            <li v-for="item in sysCheckResult.items" :key="item.id" class="flex items-center justify-between">
                                <span class="text-slate-600">{{ item.label }}:</span>
                                <span :class="item.status === 'fail' ? 'text-red-700 font-bold' : (item.status === 'warn' ? 'text-amber-700 font-semibold' : 'text-emerald-700 font-bold')">
                                    {{ item.status === 'fail' ? 'Fail' : (item.status === 'warn' ? 'Warning' : 'Pass') }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Fullscreen requirement banner if applicable -->
                <div v-if="exam.require_fullscreen" class="rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-900 flex items-start gap-2.5">
                    <span class="text-base">🖥️</span>
                    <div>
                        <p class="font-bold">Fullscreen Mode Required</p>
                        <p class="mt-0.5 text-amber-800">This exam requires you to stay in fullscreen mode after clicking Start. Leaving fullscreen will be logged as a proctoring signal.</p>
                    </div>
                </div>

                <!-- Error banner if any -->
                <div v-if="pageErrors" class="rounded-xl border border-red-200 bg-red-50 p-3.5 text-xs text-red-800" role="alert">
                    {{ pageErrors }}
                </div>

                <!-- Accept rules & CTA -->
                <div class="pt-2 space-y-4">
                    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50/60 p-4 cursor-pointer hover:border-slate-300 transition">
                        <input
                            v-model="acceptedRules"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-[#0f3d7a] focus:ring-[#0f3d7a]"
                        >
                        <span class="text-xs sm:text-sm font-medium text-slate-800 leading-snug">
                            I confirm that I am attending this examination independently, have reviewed the instructions, and accept all examination rules.
                        </span>
                    </label>

                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
                        <a :href="`/portal/student/${school.id}/mcq`" class="text-xs font-bold text-slate-500 hover:text-slate-800 order-2 sm:order-1">
                            ← Return to exam center
                        </a>
                        <button
                            type="button"
                            class="w-full sm:w-auto btn-primary min-h-12 px-8 text-sm font-extrabold shadow-md disabled:opacity-50 disabled:cursor-not-allowed order-1 sm:order-2"
                            :disabled="starting || !acceptedRules"
                            @click="startExam"
                        >
                            {{ starting ? 'Launching Exam Hall…' : 'Start Exam Now →' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Exam Hall View (started) -->
        <div v-else class="exam-hall -mx-1">
            <!-- Sticky Exam Hall Chrome -->
            <header
                class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/90 shadow-sm"
                :class="timerUrgent ? 'border-red-200' : ''"
            >
                <div class="flex items-center gap-2 sm:gap-4 px-3 sm:px-4 py-2.5 max-w-6xl mx-auto">
                    <!-- Exam title & progress text -->
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-extrabold text-slate-900 truncate leading-tight">{{ exam.title }}</p>
                        <p class="text-[11px] text-slate-500 truncate tabular-nums flex items-center gap-1.5 mt-0.5">
                            <span class="font-bold text-[#0f3d7a]">Q {{ currentIndex + 1 }} of {{ questions.length }}</span>
                            <span>·</span>
                            <span>{{ answeredCount }} answered</span>
                            <span v-if="markedForReview.size" class="text-amber-800 font-semibold">· {{ markedForReview.size }} review</span>
                            <span class="text-slate-400 hidden sm:inline">· ID {{ student.reg_no }}</span>
                        </p>
                    </div>

                    <!-- Autosave sync indicator -->
                    <div
                        class="hidden sm:inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1.5 rounded-xl border shrink-0"
                        :class="syncPillClass"
                        role="status"
                        aria-live="polite"
                    >
                        <span class="h-2 w-2 rounded-full" :class="syncDotClass" aria-hidden="true" />
                        <span>{{ syncPillText }}</span>
                        <button
                            v-if="saveStatus === 'failed' || offline"
                            type="button"
                            class="ml-1 px-1.5 py-0.5 rounded bg-amber-200 text-amber-900 font-bold hover:bg-amber-300 text-[10px] uppercase cursor-pointer"
                            @click="saveDraft"
                            title="Retry cloud autosave"
                        >
                            Retry
                        </button>
                    </div>

                    <!-- Timer Pill -->
                    <div
                        v-if="expiresAt"
                        class="shrink-0 rounded-xl px-3 py-1.5 text-center min-w-[5.25rem] border shadow-xs transition"
                        :class="timerUrgent ? 'bg-red-50 text-red-700 border-red-300 animate-pulse' : 'bg-slate-100 text-slate-900 border-slate-200'"
                        role="timer"
                        :aria-label="`Time remaining ${timerLabel}`"
                    >
                        <span class="block text-[9px] font-extrabold uppercase tracking-wider opacity-70">Time Left</span>
                        <span class="block text-lg font-black tabular-nums leading-none tracking-tight">{{ timerLabel }}</span>
                    </div>

                    <!-- Tools & text size controls on desktop header -->
                    <div class="hidden lg:flex items-center gap-1 shrink-0 pl-1 border-l border-slate-200">
                        <button
                            type="button"
                            class="btn-secondary !px-2.5 !py-1 text-xs min-h-8 flex items-center gap-1 font-semibold"
                            @click="calculatorOpen = true"
                            title="Open calculator"
                            aria-label="Open on-screen calculator"
                        >
                            <span>🧮</span>
                            <span>Calc</span>
                        </button>
                        <button type="button" class="btn-secondary !px-2 !py-1 text-xs min-h-8" @click="changeFont(-1)" title="Decrease text size" aria-label="Decrease text size">A−</button>
                        <button type="button" class="btn-secondary !px-2 !py-1 text-xs min-h-8" @click="changeFont(1)" title="Increase text size" aria-label="Increase text size">A+</button>
                    </div>
                </div>

                <!-- Visual Progress Bar (Answered % of total) -->
                <div class="h-1 w-full bg-slate-100">
                    <div
                        class="h-full bg-emerald-500 transition-all duration-300"
                        :style="{ width: `${progressPercent}%` }"
                        role="progressbar"
                        :aria-valuenow="progressPercent"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    />
                </div>

                <!-- Live alert strips -->
                <div v-if="resumeBanner" class="px-3 py-1.5 text-xs text-sky-900 bg-sky-50 border-t border-sky-100 flex items-center gap-1.5">
                    <span>🔄</span>
                    <span>Continuing previous attempt — saved answers restored.</span>
                </div>
                <div
                    v-if="offline || saveStatus === 'failed'"
                    class="px-3 py-2 text-xs text-amber-900 bg-amber-50 border-t border-amber-200 flex flex-wrap items-center justify-between gap-2"
                    role="status"
                >
                    <span v-if="offline">⚠️ Network connection lost. Answers are kept locally.</span>
                    <span v-else>⚠️ Cloud save failed. Keep tab open — your answers are kept locally.</span>
                    <div class="flex items-center gap-2 shrink-0">
                        <span v-if="lastSavedAt" class="opacity-80">Last saved: {{ lastSavedLabel }}</span>
                        <button
                            type="button"
                            class="px-2 py-0.5 rounded bg-amber-200 hover:bg-amber-300 text-amber-950 font-bold text-[11px] cursor-pointer"
                            @click="saveDraft"
                        >
                            Retry sync
                        </button>
                    </div>
                </div>
                <div v-if="timerUrgent" class="px-3 py-1.5 text-xs text-red-800 bg-red-50 border-t border-red-100 font-semibold text-center">
                    ⏰ Less than 5 minutes remaining. Your exam will submit automatically when the countdown completes.
                </div>
                <div v-if="proctorWarningVisible" class="px-3 py-2 text-xs text-amber-800 bg-amber-50 border-t border-amber-200 flex justify-between gap-2 items-center">
                    <span>⚠️ {{ proctorMessage }}</span>
                    <button type="button" class="text-xs font-bold underline shrink-0" @click="proctorWarningVisible = false">Dismiss</button>
                </div>
            </header>

            <form @submit.prevent="requestSubmit" class="pb-28">
                <div class="lg:grid lg:grid-cols-[13rem_minmax(0,1fr)] lg:gap-6 lg:items-start lg:pt-4 max-w-6xl mx-auto">
                    <!-- Desktop Question Palette Sidebar -->
                    <aside class="hidden lg:block sticky top-[5rem] space-y-4 rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm">
                        <QuestionNavGrid
                            :questions="questions"
                            :answered-count="answeredCount"
                            :review-count="markedForReview.size"
                            :current-id="currentQuestion?.id"
                            :nav-class="questionNavClass"
                            :nav-label="questionNavLabel"
                            :nav-status="questionNavStatus"
                            @select="goToQuestionId"
                        />

                        <div class="pt-2 border-t border-slate-100 space-y-2">
                            <button
                                type="submit"
                                class="btn-primary w-full min-h-11 text-xs font-extrabold shadow-sm"
                                :disabled="submitting || submitBlocked"
                            >
                                {{ submitting ? 'Submitting…' : (submitBlocked ? 'Waiting for answers to save…' : 'Review & Submit Exam') }}
                            </button>
                            <p v-if="submitBlocked" class="text-xs text-amber-700">Wait until answers are saved before submitting.</p>
                        </div>
                    </aside>

                    <!-- Active Question Canvas -->
                    <div v-if="currentQuestion" class="min-w-0 px-2 pt-4 lg:pt-0" :style="{ fontSize: fontSizeRem + 'rem' }">
                        <article class="rounded-3xl border border-slate-200/90 bg-white p-5 sm:p-7 shadow-sm space-y-5" :aria-labelledby="qHeadingId">
                            <!-- Question Meta & Bookmark Header -->
                            <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h2 :id="qHeadingId" ref="qHeadingRef" tabindex="-1" class="font-extrabold text-lg text-slate-900 outline-none">
                                            Question {{ currentQuestion.number }}
                                            <span class="text-sm font-semibold text-slate-400">of {{ questions.length }}</span>
                                        </h2>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5 mt-1 text-xs">
                                        <span class="font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md">
                                            +{{ currentQuestion.marks }} Mark{{ currentQuestion.marks === 1 ? '' : 's' }}
                                        </span>
                                        <span v-if="currentQuestion.negative_mark > 0" class="font-bold text-red-800 bg-red-50 border border-red-200 px-2 py-0.5 rounded-md">
                                            −{{ currentQuestion.negative_mark }} for wrong
                                        </span>
                                        <span v-if="currentQuestion.type === 'multiple' && currentQuestion.choose_count" class="font-semibold text-sky-800 bg-sky-50 border border-sky-200 px-2 py-0.5 rounded-md">
                                            Choose {{ currentQuestion.choose_count }}
                                        </span>
                                        <span v-else-if="currentQuestion.type === 'multiple'" class="font-semibold text-sky-800 bg-sky-50 border border-sky-200 px-2 py-0.5 rounded-md">
                                            Multiple answers
                                        </span>
                                        <span v-else-if="currentQuestion.type === 'numeric'" class="font-semibold text-purple-800 bg-purple-50 border border-purple-200 px-2 py-0.5 rounded-md">
                                            Enter number
                                        </span>
                                        <span v-else-if="currentQuestion.type === 'true_false'" class="font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md">
                                            True or False
                                        </span>
                                    </div>
                                </div>

                                <!-- Mark for Review Toggle Button -->
                                <button
                                    v-if="isAnswerable(currentQuestion)"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 text-xs font-bold px-3.5 py-2 rounded-xl border transition shrink-0 shadow-xs cursor-pointer"
                                    :class="markedForReview.has(currentQuestion.id)
                                        ? 'border-amber-400 bg-amber-50 text-amber-900 ring-2 ring-amber-400/20'
                                        : 'border-slate-200 text-slate-600 hover:border-amber-300 hover:bg-amber-50/50'"
                                    :aria-pressed="markedForReview.has(currentQuestion.id)"
                                    @click="toggleReview(currentQuestion.id)"
                                >
                                    <span v-if="markedForReview.has(currentQuestion.id)">⭐</span>
                                    <span v-else>☆</span>
                                    <span>{{ markedForReview.has(currentQuestion.id) ? 'Marked for review' : 'Mark for review' }}</span>
                                </button>
                            </div>

                            <!-- Question Statement -->
                            <div class="space-y-4">
                                <McqRichText
                                    v-if="currentQuestion.body_text"
                                    :text="currentQuestion.body_text"
                                    tag="div"
                                    class="text-slate-900 leading-relaxed font-medium text-base sm:text-lg"
                                />

                                <!-- Question Image Attachment -->
                                <button
                                    v-if="currentQuestion.image_url"
                                    type="button"
                                    class="block max-w-full text-left group"
                                    @click="zoomSrc = currentQuestion.image_url"
                                >
                                    <img
                                        :src="currentQuestion.image_url"
                                        :alt="`Question ${currentQuestion.number} illustration — tap to enlarge`"
                                        class="max-h-80 max-w-full rounded-2xl border border-slate-200 bg-slate-50 object-contain cursor-zoom-in group-hover:border-[#0f3d7a]/40 transition"
                                    >
                                    <span class="block text-[11px] font-semibold text-slate-400 mt-1">Tap image to zoom 🔍</span>
                                </button>
                            </div>

                            <!-- Options Interactive Zone -->
                            <!-- 1. Numeric Question Input -->
                            <div v-if="currentQuestion.type === 'numeric'" class="max-w-md space-y-2 pt-2">
                                <label :for="`num-${currentQuestion.id}`" class="block text-xs font-bold text-slate-700">
                                    Type your numeric answer:
                                </label>
                                <div class="flex gap-2 items-stretch">
                                    <button
                                        type="button"
                                        class="btn-secondary !px-4 min-h-14 font-extrabold text-lg shrink-0 rounded-2xl"
                                        aria-label="Switch between positive and negative"
                                        @click="flipNumericSign(currentQuestion.id)"
                                    >±</button>
                                    <input
                                        :id="`num-${currentQuestion.id}`"
                                        type="text"
                                        inputmode="decimal"
                                        autocomplete="off"
                                        spellcheck="false"
                                        class="field min-h-14 text-xl font-bold tabular-nums rounded-2xl"
                                        placeholder="e.g. 42 or 3.14"
                                        :value="answers[currentQuestion.id] ?? ''"
                                        @input="onNumericInput(currentQuestion.id, $event.target.value)"
                                    >
                                </div>
                                <p v-if="answers[currentQuestion.id] && !isNumericValid(answers[currentQuestion.id])" class="text-xs text-amber-700 font-semibold" role="status">
                                    Please enter a valid numeric value (e.g. 15, -4, or 0.85).
                                </p>
                            </div>

                            <!-- 2. True / False Choice Options -->
                            <div
                                v-else-if="currentQuestion.type === 'true_false' && currentQuestion.options?.length"
                                class="grid grid-cols-2 gap-3 pt-2"
                                role="radiogroup"
                                :aria-label="`Question ${currentQuestion.number} True or False`"
                            >
                                <label
                                    v-for="opt in trueFalseOptions(currentQuestion)"
                                    :key="opt.key"
                                    class="flex items-center justify-center gap-2.5 text-base font-extrabold border-2 rounded-2xl px-5 py-4 min-h-[4.5rem] cursor-pointer transition shadow-xs"
                                    :class="answers[currentQuestion.id] === opt.key
                                        ? 'border-[#0f3d7a] bg-blue-50/70 text-[#0f3d7a] ring-2 ring-[#0f3d7a]/20'
                                        : 'border-slate-200 text-slate-700 bg-white hover:border-slate-300'"
                                >
                                    <input
                                        type="radio"
                                        class="sr-only"
                                        :name="`q-${currentQuestion.id}`"
                                        :value="opt.key"
                                        v-model="answers[currentQuestion.id]"
                                        @change="scheduleSave"
                                    >
                                    <span class="h-6 w-6 rounded-full border-2 flex items-center justify-center text-xs"
                                          :class="answers[currentQuestion.id] === opt.key ? 'border-[#0f3d7a] bg-[#0f3d7a] text-white' : 'border-slate-300'">
                                        {{ opt.key.toUpperCase() }}
                                    </span>
                                    <span>{{ opt.text }}</span>
                                </label>
                            </div>

                            <!-- 3. Multiple Choice (Multi-select) -->
                            <div
                                v-else-if="currentQuestion.type === 'multiple' && currentQuestion.options?.length"
                                class="grid gap-3 pt-2"
                                :class="currentQuestion.options.some(o => o.image_url) ? 'sm:grid-cols-2' : ''"
                                role="group"
                            >
                                <label
                                    v-for="opt in currentQuestion.options"
                                    :key="opt.key"
                                    class="flex items-start gap-3.5 text-sm sm:text-base border-2 rounded-2xl p-4 min-h-14 cursor-pointer transition shadow-xs"
                                    :class="isMultiSelected(currentQuestion.id, opt.key)
                                        ? 'border-[#0f3d7a] bg-blue-50/60 ring-1 ring-[#0f3d7a]/20'
                                        : 'border-slate-200 bg-white hover:border-slate-300'"
                                >
                                    <input
                                        type="checkbox"
                                        :aria-label="`Question ${currentQuestion.number}, option ${(opt.display_key || opt.key).toUpperCase()}`"
                                        :checked="isMultiSelected(currentQuestion.id, opt.key)"
                                        class="mt-1 h-5 w-5 rounded border-slate-300 text-[#0f3d7a] focus:ring-[#0f3d7a]"
                                        @change="toggleMulti(currentQuestion.id, opt.key)"
                                    >
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <div class="flex items-start gap-2">
                                            <span class="h-6 w-6 rounded-lg bg-slate-100 text-slate-700 font-extrabold text-xs flex items-center justify-center shrink-0"
                                                  :class="isMultiSelected(currentQuestion.id, opt.key) ? 'bg-[#0f3d7a] text-white' : ''">
                                                {{ (opt.display_key || opt.key).toUpperCase() }}
                                            </span>
                                            <McqRichText :text="opt.label" class="text-slate-800 leading-snug pt-0.5" />
                                        </div>
                                        <img
                                            v-if="opt.image_url"
                                            :src="opt.image_url"
                                            :alt="`Option ${(opt.display_key || opt.key).toUpperCase()} picture`"
                                            class="block max-h-40 max-w-full rounded-xl border bg-white object-contain cursor-zoom-in mt-2"
                                            @click.prevent="zoomSrc = opt.image_url"
                                        >
                                    </div>
                                </label>
                            </div>

                            <!-- 4. Single Choice (Standard Radio) -->
                            <div
                                v-else-if="currentQuestion.options?.length"
                                class="grid gap-3 pt-2"
                                :class="currentQuestion.options.some(o => o.image_url) ? 'sm:grid-cols-2' : ''"
                                role="radiogroup"
                            >
                                <label
                                    v-for="opt in currentQuestion.options"
                                    :key="opt.key"
                                    class="flex items-start gap-3.5 text-sm sm:text-base border-2 rounded-2xl p-4 min-h-14 cursor-pointer transition shadow-xs group"
                                    :class="answers[currentQuestion.id] === opt.key
                                        ? 'border-[#0f3d7a] bg-blue-50/60 ring-2 ring-[#0f3d7a]/20'
                                        : 'border-slate-200 bg-white hover:border-slate-300'"
                                >
                                    <input
                                        type="radio"
                                        :name="`q-${currentQuestion.id}`"
                                        :value="opt.key"
                                        v-model="answers[currentQuestion.id]"
                                        class="mt-1 h-5 w-5 text-[#0f3d7a] border-slate-300 focus:ring-[#0f3d7a]"
                                        @change="scheduleSave"
                                    >
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <div class="flex items-start gap-2">
                                            <span class="h-6 w-6 rounded-lg text-xs font-extrabold flex items-center justify-center shrink-0 transition"
                                                  :class="answers[currentQuestion.id] === opt.key
                                                      ? 'bg-[#0f3d7a] text-white shadow-xs'
                                                      : 'bg-slate-100 text-slate-700 group-hover:bg-slate-200'">
                                                {{ (opt.display_key || opt.key).toUpperCase() }}
                                            </span>
                                            <McqRichText :text="opt.label" class="text-slate-800 leading-snug pt-0.5" />
                                        </div>
                                        <img
                                            v-if="opt.image_url"
                                            :src="opt.image_url"
                                            :alt="`Option ${(opt.display_key || opt.key).toUpperCase()} picture`"
                                            class="block max-h-40 max-w-full rounded-xl border bg-white object-contain cursor-zoom-in mt-2"
                                            @click.prevent="zoomSrc = opt.image_url"
                                        >
                                    </div>
                                </label>
                            </div>

                            <!-- Clear selection action -->
                            <div v-if="isAnswerable(currentQuestion) && hasAnswer(currentQuestion.id)" class="pt-2 flex justify-between items-center text-xs">
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-slate-500 hover:text-red-700 flex items-center gap-1 min-h-9 px-2 rounded-lg hover:bg-red-50 transition"
                                    @click="clearAnswer(currentQuestion.id)"
                                >
                                    <span>✕</span>
                                    <span>Clear answer for this question</span>
                                </button>
                                <span class="text-emerald-700 font-bold flex items-center gap-1">
                                    <span>✓</span>
                                    <span>Saved</span>
                                </span>
                            </div>
                        </article>
                    </div>
                </div>
            </form>

            <!-- Bottom Floating Action Bar: Prev · Palette Overview · Next/Submit -->
            <nav
                class="fixed bottom-0 inset-x-0 z-30 border-t border-slate-200 bg-white/95 backdrop-blur px-3 py-2.5 shadow-[0_-4px_16px_rgba(15,23,42,0.06)] pb-[max(0.65rem,env(safe-area-inset-bottom))]"
                aria-label="Question navigation"
            >
                <div class="max-w-6xl mx-auto flex items-center justify-between gap-2">
                    <!-- Previous button -->
                    <button
                        type="button"
                        class="btn-secondary min-h-12 px-4 text-xs sm:text-sm font-bold flex items-center gap-1.5"
                        :disabled="currentIndex <= 0"
                        @click="go(currentIndex - 1)"
                    >
                        <span>← Prev</span>
                    </button>

                    <!-- Mobile Drawer Question Navigator Trigger -->
                    <button
                        type="button"
                        class="min-h-12 px-3 sm:px-4 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs sm:text-sm font-extrabold tabular-nums text-slate-800 flex items-center gap-2 cursor-pointer shadow-xs transition"
                        @click="mobileDrawerOpen = true"
                        :aria-label="`Question ${currentIndex + 1} of ${questions.length}. Tap to open full question palette`"
                    >
                        <span>📋</span>
                        <span>Q {{ currentIndex + 1 }}/{{ questions.length }}</span>
                        <span class="hidden sm:inline text-xs text-slate-400 font-normal">(Overview)</span>
                    </button>

                    <!-- Next or Submit Button -->
                    <button
                        v-if="currentIndex < questions.length - 1"
                        type="button"
                        class="btn-primary min-h-12 px-5 text-xs sm:text-sm font-extrabold flex items-center gap-1.5 shadow-sm"
                        @click="go(currentIndex + 1)"
                    >
                        <span>Next →</span>
                    </button>
                    <button
                        v-else
                        type="button"
                        class="btn-primary min-h-12 px-5 text-xs sm:text-sm font-extrabold bg-emerald-600 hover:bg-emerald-500 shadow-sm flex items-center gap-1.5"
                        :disabled="submitting || submitBlocked"
                        @click="requestSubmit"
                    >
                        <span>Review &amp; Submit</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </button>
                </div>
            </nav>
        </div>

        <!-- Mobile Question Palette Bottom Sheet Drawer -->
        <div v-if="mobileDrawerOpen" class="lg:hidden fixed inset-0 z-40" @click.self="mobileDrawerOpen = false">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-xs" @click="mobileDrawerOpen = false" />
            <div class="absolute inset-x-0 bottom-0 max-h-[80vh] rounded-t-3xl bg-white shadow-2xl flex flex-col pb-[env(safe-area-inset-bottom)]">
                <div class="flex items-center justify-between gap-2 px-5 py-3.5 border-b border-slate-100">
                    <div>
                        <h2 class="font-extrabold text-sm text-slate-900">Question Navigator</h2>
                        <p class="text-xs text-slate-500">{{ answeredCount }} of {{ questions.length }} answered</p>
                    </div>
                    <button type="button" class="text-xs font-bold text-slate-500 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200" @click="mobileDrawerOpen = false">
                        Done ✕
                    </button>
                </div>
                <div class="overflow-y-auto p-5 space-y-5">
                    <QuestionNavGrid
                        :questions="questions"
                        :answered-count="answeredCount"
                        :review-count="markedForReview.size"
                        :current-id="currentQuestion?.id"
                        :nav-class="questionNavClass"
                        :nav-label="questionNavLabel"
                        :nav-status="questionNavStatus"
                        @select="selectFromDrawer"
                    />

                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100">
                        <button
                            type="button"
                            class="btn-secondary !px-3 !py-1 text-xs flex items-center gap-1 font-semibold"
                            @click="calculatorOpen = true; mobileDrawerOpen = false"
                        >
                            <span>🧮</span>
                            <span>Calculator</span>
                        </button>
                        <div class="flex items-center gap-1">
                            <span class="text-xs text-slate-500 mr-1">Text:</span>
                            <button type="button" class="btn-secondary !px-2.5 !py-1 text-xs" @click="changeFont(-1)">A−</button>
                            <button type="button" class="btn-secondary !px-2.5 !py-1 text-xs" @click="changeFont(1)">A+</button>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="btn-primary w-full min-h-12 text-sm font-extrabold shadow-sm"
                        :disabled="submitting || submitBlocked"
                        @click="mobileDrawerOpen = false; requestSubmit()"
                    >
                        {{ submitting ? 'Submitting…' : 'Review & Submit Exam' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Strict Fullscreen Modal -->
        <div
            v-if="started && exam.require_fullscreen && !fullscreenActive"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="fs-require-title"
        >
            <div class="rounded-3xl border border-slate-200 bg-white max-w-md w-full p-6 space-y-4 shadow-2xl text-center">
                <span class="text-3xl">🖥️</span>
                <h2 id="fs-require-title" class="font-extrabold text-lg text-slate-900">Fullscreen Required</h2>
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                    This examination requires your device to stay in fullscreen mode. Exiting fullscreen is recorded for the assessment committee.
                </p>
                <button type="button" class="btn-primary w-full min-h-12 text-sm font-extrabold" @click="enterFullscreen">
                    Return to Fullscreen Mode
                </button>
            </div>
        </div>

        <!-- Image Lightbox Zoom Modal -->
        <div
            v-if="zoomSrc"
            class="fixed inset-0 z-[70] flex flex-col items-center justify-center gap-3 p-4 bg-black/85 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-label="Enlarged picture"
            @click="zoomSrc = null"
            @keydown.esc="zoomSrc = null"
        >
            <img :src="zoomSrc" alt="Enlarged diagram" class="max-h-[85vh] max-w-full rounded-2xl object-contain shadow-2xl" @click.stop>
            <button type="button" class="btn-secondary text-xs min-h-10 px-5 font-bold" @click.stop="zoomSrc = null">
                Close Zoom ✕
            </button>
        </div>

        <!-- Pre-Submission Confirmation Summary Modal -->
        <div v-if="confirmOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" @click.self="confirmOpen = false">
            <div
                ref="confirmPanelRef"
                class="rounded-3xl border border-slate-200 bg-white max-w-lg w-full p-6 space-y-5 shadow-2xl"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="submit-exam-title"
                aria-describedby="submit-exam-desc"
                tabindex="-1"
                @keydown="onConfirmKeydown"
            >
                <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                    <span class="text-2xl">📝</span>
                    <div>
                        <h2 id="submit-exam-title" class="font-extrabold text-lg text-slate-900">Ready to Submit Exam?</h2>
                        <p class="text-xs text-slate-500">Please review your answer summary before final confirmation</p>
                    </div>
                </div>

                <!-- Answer Summary Stat Pills -->
                <div class="grid grid-cols-3 gap-2.5 text-center">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-3">
                        <span class="text-xl font-extrabold text-emerald-800">{{ answeredCount }}</span>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-emerald-700 mt-0.5">Answered</span>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-3">
                        <span class="text-xl font-extrabold text-amber-800">{{ markedForReview.size }}</span>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-amber-700 mt-0.5">For Review</span>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <span class="text-xl font-extrabold text-slate-700">{{ unansweredCount }}</span>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mt-0.5">Unanswered</span>
                    </div>
                </div>

                <!-- Unanswered prompt warning if applicable -->
                <div v-if="unansweredCount > 0" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 flex items-start gap-2">
                    <span class="text-base">⚠️</span>
                    <div>
                        <p class="font-bold">You still have {{ unansweredCount }} unanswered question{{ unansweredCount === 1 ? '' : 's' }}.</p>
                        <p class="mt-0.5 text-amber-800">Once submitted, you cannot re-enter or change your responses.</p>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-900 flex items-center gap-2">
                    <span class="text-base">🎉</span>
                    <p class="font-semibold">All {{ gradableCount }} questions have been answered!</p>
                </div>

                <div class="flex flex-col-reverse sm:flex-row justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        ref="confirmCancelRef"
                        type="button"
                        class="btn-secondary text-xs font-bold min-h-11 px-4"
                        @click="confirmOpen = false"
                    >
                        ← Continue writing / Review
                    </button>
                    <button
                        type="button"
                        class="btn-primary text-xs font-extrabold min-h-11 px-5 bg-emerald-600 hover:bg-emerald-500 shadow-sm"
                        :disabled="submitting || submitBlocked"
                        @click="submit"
                    >
                        {{ submitting ? 'Finalizing submission…' : 'Yes, Submit My Exam' }}
                    </button>
                    <p v-if="submitBlocked" class="w-full text-xs text-amber-700 text-center sm:text-right">Wait until answers are saved before submitting.</p>
                </div>
            </div>
        </div>

        <!-- On-Screen Calculator Modal -->
        <Modal :show="calculatorOpen" title="On-Screen Calculator" size="sm" @close="calculatorOpen = false">
            <div class="space-y-3 p-1">
                <div class="rounded-xl border border-slate-700 bg-slate-900 text-white p-3 text-right">
                    <div class="text-[11px] text-slate-400 font-mono min-h-[1rem] truncate">{{ calcExpression || ' ' }}</div>
                    <div class="text-2xl font-bold font-mono tracking-wider tabular-nums">{{ calcDisplay }}</div>
                </div>

                <div class="grid grid-cols-4 gap-2 text-sm font-bold">
                    <button type="button" class="btn-secondary !py-2.5 !text-red-600" @click="calcClear">C</button>
                    <button type="button" class="btn-secondary !py-2.5" @click="calcAppend('(')">(</button>
                    <button type="button" class="btn-secondary !py-2.5" @click="calcAppend(')')">)</button>
                    <button type="button" class="btn-secondary !py-2.5 !bg-indigo-50 !text-indigo-800" @click="calcAppend('/')">÷</button>

                    <button type="button" class="btn-secondary !py-2.5 bg-white text-slate-900" @click="calcAppend('7')">7</button>
                    <button type="button" class="btn-secondary !py-2.5 bg-white text-slate-900" @click="calcAppend('8')">8</button>
                    <button type="button" class="btn-secondary !py-2.5 bg-white text-slate-900" @click="calcAppend('9')">9</button>
                    <button type="button" class="btn-secondary !py-2.5 !bg-indigo-50 !text-indigo-800" @click="calcAppend('*')">×</button>

                    <button type="button" class="btn-secondary !py-2.5 bg-white text-slate-900" @click="calcAppend('4')">4</button>
                    <button type="button" class="btn-secondary !py-2.5 bg-white text-slate-900" @click="calcAppend('5')">5</button>
                    <button type="button" class="btn-secondary !py-2.5 bg-white text-slate-900" @click="calcAppend('6')">6</button>
                    <button type="button" class="btn-secondary !py-2.5 !bg-indigo-50 !text-indigo-800" @click="calcAppend('-')">−</button>

                    <button type="button" class="btn-secondary !py-2.5 bg-white text-slate-900" @click="calcAppend('1')">1</button>
                    <button type="button" class="btn-secondary !py-2.5 bg-white text-slate-900" @click="calcAppend('2')">2</button>
                    <button type="button" class="btn-secondary !py-2.5 bg-white text-slate-900" @click="calcAppend('3')">3</button>
                    <button type="button" class="btn-secondary !py-2.5 !bg-indigo-50 !text-indigo-800" @click="calcAppend('+')">+</button>

                    <button type="button" class="btn-secondary !py-2.5 bg-white text-slate-900" @click="calcAppend('0')">0</button>
                    <button type="button" class="btn-secondary !py-2.5 bg-white text-slate-900" @click="calcAppend('.')">.</button>
                    <button type="button" class="btn-secondary !py-2.5" @click="calcSqrt">√</button>
                    <button type="button" class="btn-primary !py-2.5 !bg-emerald-600 hover:!bg-emerald-500 text-white" @click="calcEquals">=</button>
                </div>
            </div>
        </Modal>
    </PortalLayout>
</template>

<script setup>
import Modal from '@/Components/ui/Modal.vue';
import McqRichText from '@/Components/mcq/McqRichText.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import QuestionNavGrid from '@/Components/portal/QuestionNavGrid.vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';
import { runPortalSystemCheck } from '@/support/portalSystemCheck.js';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const FONT_KEY = 'mcq:portal-font';
const FONT_MIN = 0.875;
const FONT_MAX = 1.5;
const FONT_STEP = 0.125;
const NUMERIC_RE = /^-?(?:\d+(?:\.\d*)?|\.\d+)$/;

const props = defineProps({
    school: Object,
    student: Object,
    registration: Object,
    exam: Object,
    questions: Array,
    savedAnswers: { type: Object, default: () => ({}) },
    expiresAt: String,
    started: Boolean,
    systemCheckUrl: { type: String, default: null },
});

const page = usePage();
const answers = reactive({});
const markedForReview = ref(new Set());
const submitting = ref(false);
const starting = ref(false);
const acceptedRules = ref(false);
const confirmOpen = ref(false);
const mobileDrawerOpen = ref(false);
const calculatorOpen = ref(false);
const calcDisplay = ref('0');
const calcExpression = ref('');
const saveStatus = ref('');
const lastSavedAt = ref(null);
const offline = ref(typeof navigator !== 'undefined' ? !navigator.onLine : false);
const remainingSeconds = ref(props.expiresAt ? Math.max(0, Math.ceil((new Date(props.expiresAt).getTime() - Date.now()) / 1000)) : 0);
const proctorWarningVisible = ref(false);
const proctorEventCount = ref(0);
const resumeBanner = ref(false);
const fullscreenActive = ref(false);
const fullscreenDismissed = ref(false);
const confirmPanelRef = ref(null);
const confirmCancelRef = ref(null);
const zoomSrc = ref(null);
const fontSizeRem = ref(1);
const sysCheckRunning = ref(false);
const sysCheckResult = ref(null);
const qHeadingRef = ref(null);
const qHeadingId = 'mcq-q-heading';
const currentIndex = ref(0);
let timerId = null;
let autoSubmitRequested = false;
let saveTimerId = null;
let lastProctorEventAt = 0;
let dirtySinceSave = false;
const PROCTOR_EVENT_THROTTLE_MS = 2000;

const posKey = computed(() => `mcq:pos:${props.school.id}:${props.registration.id}`);

const currentQuestion = computed(() => props.questions[currentIndex.value] ?? null);
const totalMarks = computed(() => props.questions.reduce((total, q) => total + Number(q.marks || 0), 0));
const gradableCount = computed(() => props.questions.filter(isAnswerable).length);
const answeredCount = computed(() => props.questions.filter((q) => isAnswerable(q) && hasAnswer(q.id)).length);
const unansweredCount = computed(() => Math.max(0, gradableCount.value - answeredCount.value));
const progressPercent = computed(() => {
    if (!gradableCount.value) return 0;
    return Math.round((answeredCount.value / gradableCount.value) * 100);
});

const navItems = computed(() => studentPortalNavItems(props.school.id));
const pageErrors = computed(() => {
    const errs = page.props.errors || {};
    const first = Object.values(errs).flat()[0];
    return first ? String(first) : null;
});

const timerLabel = computed(() => {
    const m = Math.floor(remainingSeconds.value / 60);
    const s = remainingSeconds.value % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
});
const timerUrgent = computed(() => props.started && props.expiresAt && remainingSeconds.value > 0 && remainingSeconds.value <= 300);
const lastSavedLabel = computed(() => {
    if (!lastSavedAt.value) return '';
    try {
        return new Date(lastSavedAt.value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch {
        return '';
    }
});
const submitBlocked = computed(() => dirtySinceSave && (saveStatus.value === 'failed' || saveStatus.value === 'saving' || offline.value));
const syncPillText = computed(() => {
    if (offline.value) return 'Offline — retrying';
    if (saveStatus.value === 'saving') return 'Saving…';
    if (saveStatus.value === 'failed') return 'Save failed — retrying';
    if (saveStatus.value === 'saved') {
        return lastSavedLabel.value ? `Saved · ${lastSavedLabel.value}` : 'Saved';
    }
    return 'Autosave active';
});
const syncPillClass = computed(() => {
    if (offline.value || saveStatus.value === 'failed') return 'border-amber-300 bg-amber-50 text-amber-900';
    if (saveStatus.value === 'saved') return 'border-emerald-300 bg-emerald-50 text-emerald-800';
    if (saveStatus.value === 'saving') return 'border-slate-200 bg-slate-50 text-slate-700';
    return 'border-slate-200 bg-white text-slate-600';
});
const syncDotClass = computed(() => {
    if (offline.value || saveStatus.value === 'failed') return 'bg-amber-500';
    if (saveStatus.value === 'saved') return 'bg-emerald-500';
    if (saveStatus.value === 'saving') return 'bg-slate-400 animate-pulse';
    return 'bg-slate-300';
});
const maxTabViolations = computed(() => Number(props.exam?.proctor_max_tab_violations) || 3);
const strictTab = computed(() => props.exam?.proctor_mode === 'strict_tab');

const proctorMessage = computed(() => {
    if (strictTab.value) {
        const left = Math.max(0, maxTabViolations.value - proctorEventCount.value);
        if (left <= 0) {
            return 'Tab-switch limit reached — submitting your saved answers.';
        }
        return `Tab leave recorded (${proctorEventCount.value}/${maxTabViolations.value}). ${left} warning${left === 1 ? '' : 's'} left before auto-submit.`;
    }
    if (proctorEventCount.value <= 1) {
        return 'Leaving this tab during the exam is being recorded for review.';
    }
    return `${proctorEventCount.value} leave/blur signals recorded. Please stay on this tab until you submit.`;
});

function isAnswerable(q) {
    return q.type === 'numeric' || (q.options?.length >= 2);
}

function hasAnswer(id) {
    const v = answers[id];
    if (Array.isArray(v)) return v.length > 0;
    if (v == null || String(v).trim() === '') return false;
    const q = props.questions.find((item) => item.id === id);
    if (q?.type === 'numeric') return isNumericValid(v);
    return true;
}

function isNumericValid(raw) {
    return NUMERIC_RE.test(String(raw ?? '').trim());
}

function trueFalseOptions(q) {
    const opts = q.options || [];
    const a = opts.find((o) => o.key === 'a') || opts[0];
    const b = opts.find((o) => o.key === 'b') || opts[1];
    return [
        { key: a?.key ?? 'a', text: (a?.label || '').trim() || 'True' },
        { key: b?.key ?? 'b', text: (b?.label || '').trim() || 'False' },
    ];
}

function isMultiSelected(qid, key) {
    const v = answers[qid];
    return Array.isArray(v) && v.includes(key);
}

function toggleMulti(qid, key) {
    const current = Array.isArray(answers[qid]) ? [...answers[qid]] : [];
    const idx = current.indexOf(key);
    if (idx >= 0) current.splice(idx, 1);
    else current.push(key);
    answers[qid] = current.sort();
    scheduleSave();
}

function clearAnswer(qid) {
    const q = props.questions.find((item) => item.id === qid);
    if (q?.type === 'multiple') answers[qid] = [];
    else answers[qid] = '';
    scheduleSave();
}

function sanitiseNumeric(raw) {
    let t = String(raw ?? '').replace(/[^\d.\-]/g, '');
    const neg = t.startsWith('-');
    t = t.replace(/-/g, '');
    const parts = t.split('.');
    if (parts.length > 2) t = `${parts[0]}.${parts.slice(1).join('')}`;
    return (neg ? '-' : '') + t;
}

function onNumericInput(qid, raw) {
    answers[qid] = sanitiseNumeric(raw);
    scheduleSave();
}

function flipNumericSign(qid) {
    const t = String(answers[qid] ?? '');
    answers[qid] = t.startsWith('-') ? t.slice(1) : `-${t}`;
    scheduleSave();
}

function csrfToken() {
    return document.querySelector('meta[name=csrf-token]')?.content ?? '';
}

function restoreSavedAnswers() {
    Object.keys(answers).forEach((k) => delete answers[k]);
    for (const [questionId, value] of Object.entries(props.savedAnswers ?? {})) {
        if (Array.isArray(value)) {
            answers[questionId] = [...value];
        } else if (typeof value === 'string' && value.includes(',')) {
            answers[questionId] = value.split(',').map((s) => s.trim()).filter(Boolean);
        } else if (value != null && value !== '') {
            answers[questionId] = value;
        }
    }
}

function questionNavClass(q) {
    if (markedForReview.value.has(q.id)) {
        return 'border-amber-400 bg-amber-50 text-amber-900 font-extrabold shadow-2xs';
    }
    if (hasAnswer(q.id)) {
        return 'border-emerald-300 bg-emerald-50 text-emerald-900 font-extrabold shadow-2xs';
    }
    return 'border-slate-200 bg-white text-slate-700 hover:border-[#0f3d7a]/50 hover:bg-slate-50';
}

function questionNavLabel(q) {
    const parts = [`Question ${q.number}`];
    if (markedForReview.value.has(q.id)) parts.push('marked for review');
    else if (hasAnswer(q.id)) parts.push('answered');
    else parts.push('not answered');
    return parts.join(', ');
}

function questionNavStatus(q) {
    if (markedForReview.value.has(q.id)) return 'review';
    if (hasAnswer(q.id)) return 'answered';
    return 'unanswered';
}

function calcAppend(val) {
    if (calcDisplay.value === '0' && !isNaN(val)) {
        calcDisplay.value = val;
    } else {
        calcDisplay.value += val;
    }
}

function calcClear() {
    calcDisplay.value = '0';
    calcExpression.value = '';
}

function calcSqrt() {
    try {
        const num = parseFloat(calcDisplay.value);
        if (num < 0) {
            calcDisplay.value = 'Error';
        } else {
            calcExpression.value = `√(${calcDisplay.value})`;
            const res = Math.sqrt(num);
            calcDisplay.value = String(Math.round(res * 100000) / 100000);
        }
    } catch {
        calcDisplay.value = 'Error';
    }
}

function calcEquals() {
    try {
        calcExpression.value = calcDisplay.value + ' =';
        const sanitized = calcDisplay.value.replace(/[^0-9.+\-*/()]/g, '');
        if (!sanitized) return;
        const fn = new Function(`return (${sanitized})`);
        const res = fn();
        if (Number.isFinite(res)) {
            calcDisplay.value = String(Math.round(res * 100000) / 100000);
        } else {
            calcDisplay.value = 'Error';
        }
    } catch {
        calcDisplay.value = 'Error';
    }
}

function readPos() {
    try {
        const n = Number(sessionStorage.getItem(posKey.value));
        return Number.isInteger(n) && n >= 0 && n < props.questions.length ? n : 0;
    } catch {
        return 0;
    }
}

function persistPos(i) {
    try {
        sessionStorage.setItem(posKey.value, String(i));
    } catch {
        /* optional */
    }
}

function go(i) {
    if (i < 0 || i >= props.questions.length) return;
    currentIndex.value = i;
}

function goToQuestionId(id) {
    const i = props.questions.findIndex((q) => q.id === id);
    if (i >= 0) go(i);
}

function selectFromDrawer(id) {
    mobileDrawerOpen.value = false;
    goToQuestionId(id);
}

watch(currentIndex, async (i) => {
    persistPos(i);
    window.scrollTo({ top: 0, behavior: 'smooth' });
    await nextTick();
    qHeadingRef.value?.focus?.({ preventScroll: true });
});

function onExamKeydown(e) {
    if (!props.started || confirmOpen.value || mobileDrawerOpen.value || zoomSrc.value) return;
    if (e.altKey || e.ctrlKey || e.metaKey) return;
    const t = e.target;
    const tag = t?.tagName;
    if (tag === 'TEXTAREA') return;
    if (tag === 'INPUT' && t.type !== 'radio' && t.type !== 'checkbox') return;
    if (e.key === 'ArrowRight' || e.key === 'PageDown') {
        go(currentIndex.value + 1);
        e.preventDefault();
    } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
        go(currentIndex.value - 1);
        e.preventDefault();
    }
}

function toggleReview(questionId) {
    const next = new Set(markedForReview.value);
    if (next.has(questionId)) next.delete(questionId);
    else next.add(questionId);
    markedForReview.value = next;
    try { sessionStorage.setItem(`mcq-review-${props.school.id}-${props.registration.id}`, JSON.stringify([...next])); } catch { /* optional */ }
}

function scheduleSave() {
    if (!props.started || submitting.value) return;
    dirtySinceSave = true;
    saveStatus.value = 'saving';
    if (saveTimerId) window.clearTimeout(saveTimerId);
    saveTimerId = window.setTimeout(saveDraft, 600);
}

async function saveDraft() {
    if (!props.started || submitting.value) return;
    try {
        const res = await fetch(
            `/portal/student/${props.school.id}/mcq/${props.registration.id}/save-answers`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ answers }),
            },
        );
        if (res.ok) {
            saveStatus.value = 'saved';
            dirtySinceSave = false;
            const body = await res.json().catch(() => ({}));
            lastSavedAt.value = body.saved_at || new Date().toISOString();
        } else {
            saveStatus.value = 'failed';
        }
    } catch {
        saveStatus.value = 'failed';
    }
}

async function reportProctorEvent(type) {
    if (!props.started || submitting.value) return;
    const now = Date.now();
    if (now - lastProctorEventAt < PROCTOR_EVENT_THROTTLE_MS) return;
    lastProctorEventAt = now;
    if (type === 'tab_hidden' || !strictTab.value) {
        proctorEventCount.value += 1;
        proctorWarningVisible.value = true;
    } else {
        proctorWarningVisible.value = true;
    }
    try {
        const res = await fetch(
            `/portal/student/${props.school.id}/mcq/${props.registration.id}/proctor-event`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ type }),
            },
        );
        if (res.ok) {
            const body = await res.json().catch(() => ({}));
            if (body.auto_submitted) {
                submitting.value = true;
                window.location.href = body.redirect
                    || `/portal/student/${props.school.id}/mcq/${props.registration.id}/exam`;
            }
        }
    } catch { /* best-effort */ }
}

function handleVisibilityChange() {
    if (document.visibilityState === 'hidden') reportProctorEvent('tab_hidden');
}
function handleWindowBlur() {
    if (strictTab.value) return;
    reportProctorEvent('window_blur');
}
function handleOnline() {
    offline.value = false;
    if (dirtySinceSave) scheduleSave();
}
function handleOffline() {
    offline.value = true;
}
function handleBeforeUnload(e) {
    if (!props.started || submitting.value) return;
    e.preventDefault();
    e.returnValue = 'Your exam is in progress. Answers already saved will be kept, but leaving may interrupt you.';
}

function tickTimer() {
    if (!props.expiresAt) return;
    const diff = Math.max(0, Math.ceil((new Date(props.expiresAt).getTime() - Date.now()) / 1000));
    remainingSeconds.value = diff;
    if (diff === 0 && props.started && !submitting.value && !autoSubmitRequested) {
        autoSubmitRequested = true;
        submit();
    }
}

function startExam() {
    if (!acceptedRules.value || starting.value) return;
    starting.value = true;
    router.post(`/portal/student/${props.school.id}/mcq/${props.registration.id}/start`, {}, {
        onFinish: () => { starting.value = false; },
    });
}

function requestSubmit() {
    if (submitBlocked.value) return;
    confirmOpen.value = true;
    nextTick(() => confirmCancelRef.value?.focus?.());
}

function onConfirmKeydown(e) {
    if (e.key === 'Escape') {
        e.preventDefault();
        confirmOpen.value = false;
        return;
    }
    if (e.key !== 'Tab' || !confirmPanelRef.value) return;
    const nodes = [...confirmPanelRef.value.querySelectorAll('button:not([disabled])')];
    if (!nodes.length) return;
    const first = nodes[0];
    const last = nodes[nodes.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
}

async function enterFullscreen() {
    try {
        await document.documentElement.requestFullscreen?.();
        fullscreenActive.value = true;
        fullscreenDismissed.value = true;
    } catch {
        if (!props.exam?.require_fullscreen) {
            fullscreenDismissed.value = true;
        }
    }
}

function handleFullscreenChange() {
    const nowFs = !!document.fullscreenElement;
    if (fullscreenActive.value && !nowFs && props.started && !submitting.value) {
        reportProctorEvent('fullscreen_exit');
    }
    fullscreenActive.value = nowFs;
}

function changeFont(delta) {
    const next = Math.min(FONT_MAX, Math.max(FONT_MIN, fontSizeRem.value + delta * FONT_STEP));
    fontSizeRem.value = Math.round(next * 1000) / 1000;
    try {
        localStorage.setItem(FONT_KEY, String(fontSizeRem.value));
    } catch {
        /* ignore */
    }
}

async function runInlineSystemCheck() {
    sysCheckRunning.value = true;
    try {
        sysCheckResult.value = await runPortalSystemCheck();
    } finally {
        sysCheckRunning.value = false;
    }
}

function submit() {
    if (submitting.value || submitBlocked.value) return;
    confirmOpen.value = false;
    submitting.value = true;
    router.post(`/portal/student/${props.school.id}/mcq/${props.registration.id}/submit`, {
        answers,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            try {
                sessionStorage.removeItem(`mcq-review-${props.school.id}-${props.registration.id}`);
                sessionStorage.removeItem(posKey.value);
            } catch { /* */ }
        },
        onFinish: () => { submitting.value = false; },
    });
}

watch(() => props.savedAnswers, restoreSavedAnswers, { deep: true });
watch(() => props.expiresAt, tickTimer);

onMounted(() => {
    restoreSavedAnswers();
    currentIndex.value = readPos();
    if (props.started && Object.keys(props.savedAnswers ?? {}).length) {
        saveStatus.value = 'saved';
        lastSavedAt.value = new Date().toISOString();
    }
    if (props.started && props.registration?.started_at) {
        resumeBanner.value = true;
        window.setTimeout(() => { resumeBanner.value = false; }, 8000);
    }
    try {
        const ids = JSON.parse(sessionStorage.getItem(`mcq-review-${props.school.id}-${props.registration.id}`) || '[]');
        markedForReview.value = new Set(Array.isArray(ids) ? ids.filter((id) => props.questions.some((q) => q.id === id)) : []);
    } catch { /* */ }
    tickTimer();
    timerId = window.setInterval(tickTimer, 1000);
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('blur', handleWindowBlur);
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    window.addEventListener('beforeunload', handleBeforeUnload);
    window.addEventListener('keydown', onExamKeydown);
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    fullscreenActive.value = !!document.fullscreenElement;
    try {
        const saved = Number(localStorage.getItem(FONT_KEY));
        if (saved >= FONT_MIN && saved <= FONT_MAX) fontSizeRem.value = saved;
    } catch {
        /* ignore */
    }
});

onUnmounted(() => {
    if (timerId) window.clearInterval(timerId);
    if (saveTimerId) window.clearTimeout(saveTimerId);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('blur', handleWindowBlur);
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
    window.removeEventListener('beforeunload', handleBeforeUnload);
    window.removeEventListener('keydown', onExamKeydown);
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
});
</script>
