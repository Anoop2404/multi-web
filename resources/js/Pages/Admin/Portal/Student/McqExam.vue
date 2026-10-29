<template>
    <PortalLayout
        role-label="Student Portal"
        :title="exam.title"
        :subtitle="[school.name, student.reg_no].filter(Boolean).join(' · ')"
        accent="indigo"
        :nav-items="started ? [] : navItems"
        :focus-mode="started"
    >
        <div v-if="!started" class="card py-8 px-5 space-y-4 max-w-2xl mx-auto">
            <div class="text-center space-y-2">
                <h2 class="font-semibold text-base text-slate-900">Before you start</h2>
                <p class="text-sm text-gray-600">Read these instructions, then start when you are ready.</p>
            </div>
            <ul class="text-sm text-slate-700 space-y-2 list-disc pl-5">
                <li><strong>{{ questions.length }}</strong> question(s) · <strong>{{ gradableCount }}</strong> graded · <strong>{{ totalMarks }}</strong> marks</li>
                <li v-if="exam.duration_minutes">Duration: <strong>{{ exam.duration_minutes }} minutes</strong> — timer starts when you click Start</li>
                <li v-if="exam.has_negative_marking">Some questions have <strong>negative marking</strong> for wrong answers</li>
                <li>Answers <strong>autosave</strong> while you work — keep this tab open</li>
                <li>Refresh or closing the tab may interrupt you; use <strong>Submit</strong> when finished</li>
                <li>Use a stable connection; wait for “Saved” before submitting if you see an offline warning</li>
                <li v-if="exam.proctor_mode === 'strict_tab'">
                    Leaving this tab is a <strong>violation</strong>. After
                    <strong>{{ exam.proctor_max_tab_violations || 3 }}</strong> times, your exam is
                    <strong>auto-submitted</strong> from saved answers.
                </li>
                <li v-else>Tab switches and leaving this window are <strong>recorded</strong> for review (not auto-disqualification)</li>
                <li v-if="exam.no_pin_required">No hall ticket or Start PIN — you start from this page</li>
                <li v-else>If your venue uses a Start PIN, an invigilator will give it on the exam site — not your portal password</li>
            </ul>
            <p v-if="pageErrors" class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2" role="alert">{{ pageErrors }}</p>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-sm space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-medium text-slate-800">Device check</p>
                    <a v-if="systemCheckUrl" :href="systemCheckUrl" class="text-xs font-semibold text-indigo-700 underline" target="_blank" rel="noopener">Full practice check ↗</a>
                </div>
                <button type="button" class="btn-secondary text-xs" :disabled="sysCheckRunning" @click="runInlineSystemCheck">
                    {{ sysCheckRunning ? 'Checking…' : (sysCheckResult ? 'Run again' : 'Run quick check') }}
                </button>
                <ul v-if="sysCheckResult" class="text-xs space-y-1">
                    <li v-for="item in sysCheckResult.items" :key="item.id">
                        <span :class="item.status === 'fail' ? 'text-red-700' : (item.status === 'warn' ? 'text-amber-700' : 'text-emerald-700')">{{ item.label }}:</span>
                        {{ item.detail }}
                    </li>
                </ul>
            </div>
            <p v-if="exam.require_fullscreen" class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                This exam requires fullscreen after you start. Leaving fullscreen is recorded as a signal — not an automatic fail. Extra monitors are not detected.
            </p>
            <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-800 cursor-pointer">
                <input v-model="acceptedRules" type="checkbox" class="mt-1 rounded border-slate-300 text-[#0f3d7a] focus:ring-[#0f3d7a]">
                <span>I have read and accept the exam rules and instructions.</span>
            </label>
            <div class="text-center pt-2">
                <button
                    type="button"
                    class="btn-primary min-h-12 px-8 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="starting || !acceptedRules"
                    @click="startExam"
                >Start exam</button>
            </div>
        </div>

        <div v-else class="exam-hall -mx-1">
            <!-- Sticky top: title · timer · save -->
            <header
                class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/90"
                :class="timerUrgent ? 'border-red-200' : ''"
            >
                <div class="flex items-center gap-2 sm:gap-3 px-2 sm:px-3 py-2.5">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-slate-900 truncate leading-tight">{{ exam.title }}</p>
                        <p class="text-[11px] text-slate-500 truncate tabular-nums">
                            {{ currentQuestion ? `Q ${currentQuestion.number} of ${questions.length}` : '' }}
                            · {{ answeredCount }}/{{ questions.length }} answered
                            <span v-if="markedForReview.size"> · {{ markedForReview.size }} review</span>
                            <span class="text-slate-400"> · {{ student.reg_no || school.name }}</span>
                        </p>
                    </div>
                    <SignOutButton class="hidden sm:inline-flex text-[11px] font-semibold text-slate-500 hover:text-red-700 shrink-0" />
                    <div
                        v-if="expiresAt"
                        class="shrink-0 rounded-lg px-2.5 py-1.5 text-center min-w-[4.75rem]"
                        :class="timerUrgent ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-900'"
                        role="timer"
                        :aria-label="`Time remaining ${timerLabel}`"
                    >
                        <span class="block text-[10px] font-semibold uppercase tracking-wide opacity-70">Time left</span>
                        <span class="block text-lg font-extrabold tabular-nums leading-none">{{ timerLabel }}</span>
                    </div>
                    <span
                        class="hidden sm:inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1.5 rounded-lg border shrink-0"
                        :class="syncPillClass"
                        role="status"
                        aria-live="polite"
                    >
                        <span class="h-1.5 w-1.5 rounded-full" :class="syncDotClass" aria-hidden="true" />
                        {{ syncPillText }}
                    </span>
                    <span
                        class="sm:hidden inline-flex items-center justify-center h-8 w-8 rounded-full border shrink-0"
                        :class="syncPillClass"
                        role="status"
                        :aria-label="syncPillText"
                        :title="syncPillText"
                    >
                        <span class="h-2 w-2 rounded-full" :class="syncDotClass" aria-hidden="true" />
                    </span>
                </div>

                <div v-if="resumeBanner" class="px-3 py-1.5 text-xs text-sky-900 bg-sky-50 border-t border-sky-100">
                    Continuing your attempt — answers restored
                </div>
                <div
                    v-if="offline || saveStatus === 'failed'"
                    class="px-3 py-2 text-sm text-amber-900 bg-amber-50 border-t border-amber-200"
                    role="status"
                >
                    <span v-if="offline">You appear offline. Answers will retry when the connection returns.</span>
                    <span v-else>Answers could not be saved. Check your connection — Submit stays disabled until they save.</span>
                    <span v-if="lastSavedAt" class="block text-xs mt-0.5 opacity-80">Last saved: {{ lastSavedLabel }}</span>
                </div>
                <div v-if="timerUrgent" class="px-3 py-1.5 text-xs text-red-800 bg-red-50 border-t border-red-100">
                    Less than 5 minutes left. Your exam will submit automatically when the timer reaches zero.
                </div>
                <div v-if="proctorWarningVisible" class="px-3 py-2 text-sm text-amber-800 bg-amber-50 border-t border-amber-200 flex justify-between gap-2 items-start">
                    <span>{{ proctorMessage }}</span>
                    <button type="button" class="text-xs font-semibold underline shrink-0" @click="proctorWarningVisible = false">Dismiss</button>
                </div>
                <div
                    v-if="!fullscreenActive && !exam.require_fullscreen && !fullscreenDismissed"
                    class="px-3 py-2 text-xs text-slate-700 bg-slate-50 border-t border-slate-200 flex flex-wrap justify-between gap-2 items-center"
                >
                    <span>Optional fullscreen reduces distractions. Leaving it is recorded as a signal — not an automatic fail.</span>
                    <div class="flex gap-2 shrink-0">
                        <button type="button" class="btn-secondary text-xs min-h-9 !py-1.5" @click="enterFullscreen">Enter fullscreen</button>
                        <button type="button" class="text-xs font-semibold text-slate-500 underline" @click="fullscreenDismissed = true">Not now</button>
                    </div>
                </div>
            </header>

            <form @submit.prevent="requestSubmit" class="pb-28">
                <div class="lg:grid lg:grid-cols-[11.5rem_minmax(0,1fr)] lg:gap-5 lg:items-start lg:pt-4">
                    <aside class="hidden lg:block sticky top-[4.75rem] space-y-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                        <QuestionNavGrid
                            :questions="questions"
                            :answered-count="answeredCount"
                            :current-id="currentQuestion?.id"
                            :nav-class="questionNavClass"
                            :nav-label="questionNavLabel"
                            @select="goToQuestionId"
                        />
                        <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                            <span class="text-[11px] text-slate-500">Text</span>
                            <button type="button" class="btn-secondary !px-2.5 !py-1.5 text-xs min-h-9" @click="changeFont(-1)" aria-label="Decrease text size">A−</button>
                            <button type="button" class="btn-secondary !px-2.5 !py-1.5 text-xs min-h-9" @click="changeFont(1)" aria-label="Increase text size">A+</button>
                        </div>
                        <button
                            type="submit"
                            class="btn-secondary w-full min-h-11 text-sm"
                            :disabled="submitting || submitBlocked"
                        >
                            {{ submitting ? 'Submitting…' : (submitBlocked ? 'Wait until answers are saved' : 'Review & submit') }}
                        </button>
                    </aside>

                    <div v-if="currentQuestion" class="min-w-0 px-1 pt-4 lg:pt-0" :style="{ fontSize: fontSizeRem + 'rem' }">
                        <article class="space-y-4" :aria-labelledby="qHeadingId">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h2 :id="qHeadingId" ref="qHeadingRef" tabindex="-1" class="font-bold text-base text-slate-900 outline-none">
                                        Question {{ currentQuestion.number }}
                                        <span class="font-medium text-slate-400">of {{ questions.length }}</span>
                                    </h2>
                                    <p class="text-xs font-medium text-slate-500 mt-1">
                                        {{ currentQuestion.marks }} mark{{ currentQuestion.marks === 1 ? '' : 's' }}
                                        <span v-if="currentQuestion.negative_mark > 0"> · −{{ currentQuestion.negative_mark }} for a wrong answer</span>
                                        <span v-if="currentQuestion.type === 'multiple' && currentQuestion.choose_count"> · Choose {{ currentQuestion.choose_count }}</span>
                                        <span v-else-if="currentQuestion.type === 'multiple'"> · Select all that apply</span>
                                        <span v-else-if="currentQuestion.type === 'numeric'"> · Enter a number</span>
                                        <span v-else-if="currentQuestion.type === 'true_false'"> · True or false</span>
                                    </p>
                                </div>
                                <button
                                    v-if="isAnswerable(currentQuestion)"
                                    type="button"
                                    class="text-xs font-semibold px-3 py-2 min-h-10 rounded-lg border transition shrink-0"
                                    :class="markedForReview.has(currentQuestion.id)
                                        ? 'border-amber-400 bg-amber-50 text-amber-900'
                                        : 'border-slate-200 text-slate-600 hover:border-amber-300'"
                                    :aria-pressed="markedForReview.has(currentQuestion.id)"
                                    @click="toggleReview(currentQuestion.id)"
                                >
                                    {{ markedForReview.has(currentQuestion.id) ? 'Marked for review' : 'Mark for review' }}
                                </button>
                            </div>

                            <McqRichText v-if="currentQuestion.body_text" :text="currentQuestion.body_text" tag="p" class="text-slate-800 leading-relaxed" />
                            <button
                                v-if="currentQuestion.image_url"
                                type="button"
                                class="block max-w-full text-left"
                                @click="zoomSrc = currentQuestion.image_url"
                            >
                                <img
                                    :src="currentQuestion.image_url"
                                    :alt="`Question ${currentQuestion.number} image — click to enlarge`"
                                    class="max-h-80 max-w-full rounded-lg border bg-white object-contain cursor-zoom-in"
                                >
                            </button>

                            <!-- Numeric -->
                            <div v-if="currentQuestion.type === 'numeric'" class="max-w-sm space-y-1.5">
                                <label :for="`num-${currentQuestion.id}`" class="text-xs font-semibold text-slate-600">Type your answer (a number)</label>
                                <div class="flex gap-2 items-stretch">
                                    <button
                                        type="button"
                                        class="btn-secondary !px-3 min-h-14 shrink-0"
                                        aria-label="Switch between positive and negative"
                                        @click="flipNumericSign(currentQuestion.id)"
                                    >±</button>
                                    <input
                                        :id="`num-${currentQuestion.id}`"
                                        type="text"
                                        inputmode="decimal"
                                        autocomplete="off"
                                        spellcheck="false"
                                        class="field min-h-14 text-lg tabular-nums"
                                        placeholder="e.g. 12.5"
                                        :value="answers[currentQuestion.id] ?? ''"
                                        @input="onNumericInput(currentQuestion.id, $event.target.value)"
                                    >
                                </div>
                                <p v-if="answers[currentQuestion.id] && !isNumericValid(answers[currentQuestion.id])" class="text-xs text-amber-700" role="status">
                                    Enter a number such as 42, -3 or 0.75.
                                </p>
                            </div>

                            <!-- Multiple correct -->
                            <div
                                v-else-if="currentQuestion.type === 'multiple' && currentQuestion.options?.length"
                                class="grid gap-3"
                                :class="currentQuestion.options.some(o => o.image_url) ? 'sm:grid-cols-2' : ''"
                                role="group"
                            >
                                <label
                                    v-for="opt in currentQuestion.options"
                                    :key="opt.key"
                                    class="flex items-start gap-3 text-sm border-2 rounded-xl px-4 py-3.5 min-h-14 cursor-pointer transition hover:border-indigo-300"
                                    :class="isMultiSelected(currentQuestion.id, opt.key) ? 'border-indigo-500 bg-indigo-50/80' : 'border-slate-200 bg-white'"
                                >
                                    <input
                                        type="checkbox"
                                        :aria-label="`Question ${currentQuestion.number}, option ${(opt.display_key || opt.key).toUpperCase()}`"
                                        :checked="isMultiSelected(currentQuestion.id, opt.key)"
                                        class="mt-1 h-4 w-4"
                                        @change="toggleMulti(currentQuestion.id, opt.key)"
                                    >
                                    <span class="min-w-0 space-y-1.5">
                                        <span class="flex gap-1.5"><strong class="uppercase shrink-0">{{ opt.display_key || opt.key }}.</strong> <McqRichText :text="opt.label" /></span>
                                        <img
                                            v-if="opt.image_url"
                                            :src="opt.image_url"
                                            :alt="`Option ${(opt.display_key || opt.key).toUpperCase()} — click to enlarge`"
                                            class="block max-h-40 max-w-full rounded border bg-white object-contain cursor-zoom-in"
                                            @click.prevent="zoomSrc = opt.image_url"
                                        >
                                    </span>
                                </label>
                            </div>

                            <!-- True / false -->
                            <div
                                v-else-if="currentQuestion.type === 'true_false' && currentQuestion.options?.length"
                                class="grid grid-cols-2 gap-3"
                                role="radiogroup"
                                :aria-label="`Question ${currentQuestion.number} true or false`"
                            >
                                <label
                                    v-for="opt in trueFalseOptions(currentQuestion)"
                                    :key="opt.key"
                                    class="flex items-center justify-center gap-2 text-base font-bold border-2 rounded-xl px-4 py-4 min-h-[4.5rem] cursor-pointer transition"
                                    :class="answers[currentQuestion.id] === opt.key ? 'border-indigo-500 bg-indigo-50 text-indigo-900' : 'border-slate-200 text-slate-800 bg-white hover:border-indigo-300'"
                                >
                                    <input
                                        type="radio"
                                        class="sr-only"
                                        :name="`q-${currentQuestion.id}`"
                                        :value="opt.key"
                                        v-model="answers[currentQuestion.id]"
                                        @change="scheduleSave"
                                    >
                                    {{ opt.text }}
                                </label>
                            </div>

                            <!-- Single choice -->
                            <div
                                v-else-if="currentQuestion.options?.length"
                                class="grid gap-3"
                                :class="currentQuestion.options.some(o => o.image_url) ? 'sm:grid-cols-2' : ''"
                                role="radiogroup"
                            >
                                <label
                                    v-for="opt in currentQuestion.options"
                                    :key="opt.key"
                                    class="flex items-start gap-3 text-sm border-2 rounded-xl px-4 py-3.5 min-h-14 cursor-pointer transition hover:border-indigo-300"
                                    :class="answers[currentQuestion.id] === opt.key ? 'border-indigo-500 bg-indigo-50/80' : 'border-slate-200 bg-white'"
                                >
                                    <input
                                        type="radio"
                                        :name="`q-${currentQuestion.id}`"
                                        :aria-label="`Question ${currentQuestion.number}, option ${(opt.display_key || opt.key).toUpperCase()}: ${opt.label || 'Image option'}`"
                                        :value="opt.key"
                                        v-model="answers[currentQuestion.id]"
                                        class="mt-1 h-4 w-4"
                                        @change="scheduleSave"
                                    >
                                    <span class="min-w-0 space-y-1.5">
                                        <span class="flex gap-1.5"><strong class="uppercase shrink-0">{{ opt.display_key || opt.key }}.</strong> <McqRichText :text="opt.label" /></span>
                                        <img
                                            v-if="opt.image_url"
                                            :src="opt.image_url"
                                            :alt="`Option ${(opt.display_key || opt.key).toUpperCase()} — click to enlarge`"
                                            class="block max-h-40 max-w-full rounded border bg-white object-contain cursor-zoom-in"
                                            @click.prevent="zoomSrc = opt.image_url"
                                        >
                                    </span>
                                </label>
                            </div>
                            <p v-else class="text-xs text-slate-400">Reference material — no online answer required.</p>

                            <div v-if="isAnswerable(currentQuestion) && hasAnswer(currentQuestion.id)" class="pt-1">
                                <button type="button" class="text-xs font-semibold text-slate-500 underline min-h-9" @click="clearAnswer(currentQuestion.id)">
                                    Clear answer
                                </button>
                            </div>
                        </article>
                    </div>
                </div>
            </form>

            <!-- Bottom nav: Prev · palette · Next / Submit -->
            <nav
                class="fixed bottom-0 inset-x-0 z-30 border-t border-slate-200 bg-white/95 backdrop-blur px-3 py-2.5 pb-[max(0.65rem,env(safe-area-inset-bottom))]"
                aria-label="Question navigation"
            >
                <div class="max-w-6xl mx-auto grid grid-cols-[1fr_auto_1fr] gap-2 items-center">
                    <button
                        type="button"
                        class="btn-secondary min-h-12 text-sm justify-self-start"
                        :disabled="currentIndex <= 0"
                        @click="go(currentIndex - 1)"
                    >
                        ← Prev
                    </button>
                    <button
                        type="button"
                        class="min-h-12 px-4 rounded-lg border border-slate-200 bg-slate-50 text-sm font-bold tabular-nums text-slate-800 lg:pointer-events-none"
                        :class="{'hover:bg-slate-100': true}"
                        @click="mobileDrawerOpen = true"
                        :aria-label="`Question ${currentIndex + 1} of ${questions.length}. Open question list`"
                    >
                        {{ currentIndex + 1 }}/{{ questions.length }}
                    </button>
                    <button
                        v-if="currentIndex < questions.length - 1"
                        type="button"
                        class="btn-primary min-h-12 text-sm justify-self-end"
                        @click="go(currentIndex + 1)"
                    >
                        Next →
                    </button>
                    <button
                        v-else
                        type="button"
                        class="btn-primary min-h-12 text-sm justify-self-end"
                        :disabled="submitting || submitBlocked"
                        @click="requestSubmit"
                    >
                        {{ submitting ? 'Submitting…' : (submitBlocked ? 'Wait to save' : 'Submit') }}
                    </button>
                </div>
            </nav>
        </div>

        <div v-if="mobileDrawerOpen" class="lg:hidden fixed inset-0 z-40" @click.self="mobileDrawerOpen = false">
            <div class="absolute inset-0 bg-black/40" @click="mobileDrawerOpen = false" />
            <div class="absolute inset-x-0 bottom-0 max-h-[75vh] rounded-t-2xl bg-white shadow-xl flex flex-col pb-[env(safe-area-inset-bottom)]">
                <div class="flex items-center justify-between gap-2 px-4 py-3 border-b">
                    <h2 class="font-semibold text-sm">Question navigator</h2>
                    <button type="button" class="text-xs font-semibold text-slate-500 px-2 py-2 min-h-10" @click="mobileDrawerOpen = false">Close</button>
                </div>
                <div class="overflow-y-auto p-4 space-y-4">
                    <QuestionNavGrid
                        :questions="questions"
                        :answered-count="answeredCount"
                        :current-id="currentQuestion?.id"
                        :nav-class="questionNavClass"
                        :nav-label="questionNavLabel"
                        @select="selectFromDrawer"
                    />
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-500">Text size</span>
                        <button type="button" class="btn-secondary !px-2.5 !py-1.5 text-xs min-h-9" @click="changeFont(-1)" aria-label="Decrease text size">A−</button>
                        <button type="button" class="btn-secondary !px-2.5 !py-1.5 text-xs min-h-9" @click="changeFont(1)" aria-label="Increase text size">A+</button>
                    </div>
                    <button
                        type="button"
                        class="btn-secondary w-full min-h-12"
                        :disabled="submitting || submitBlocked"
                        @click="mobileDrawerOpen = false; requestSubmit()"
                    >
                        {{ submitting ? 'Submitting…' : (submitBlocked ? 'Wait until answers are saved' : 'Review & submit') }}
                    </button>
                    <SignOutButton class="w-full justify-center text-xs font-semibold text-slate-500" />
                </div>
            </div>
        </div>

        <div
            v-if="started && exam.require_fullscreen && !fullscreenActive"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/70"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="fs-require-title"
        >
            <div class="card max-w-md w-full space-y-3 shadow-xl text-center">
                <h2 id="fs-require-title" class="font-semibold text-base">Fullscreen required</h2>
                <p class="text-sm text-slate-600">
                    This exam asks you to stay in fullscreen while writing. Leaving fullscreen is recorded for review — not an automatic fail. Extra monitors are not detected.
                </p>
                <button type="button" class="btn-primary min-h-12" @click="enterFullscreen">Enter fullscreen to continue</button>
            </div>
        </div>

        <div
            v-if="zoomSrc"
            class="fixed inset-0 z-[70] flex flex-col items-center justify-center gap-3 p-4 bg-black/80"
            role="dialog"
            aria-modal="true"
            aria-label="Enlarged picture"
            @click="zoomSrc = null"
            @keydown.esc="zoomSrc = null"
        >
            <img :src="zoomSrc" alt="Enlarged picture" class="max-h-[85vh] max-w-full object-contain" @click.stop>
            <button type="button" class="btn-secondary text-sm min-h-10" @click.stop="zoomSrc = null">Close</button>
        </div>

        <div v-if="confirmOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="confirmOpen = false">
            <div
                ref="confirmPanelRef"
                class="card max-w-md w-full space-y-4 shadow-xl"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="submit-exam-title"
                aria-describedby="submit-exam-desc"
                tabindex="-1"
                @keydown="onConfirmKeydown"
            >
                <h2 id="submit-exam-title" class="font-semibold text-base">Submit exam?</h2>
                <p id="submit-exam-desc" class="text-sm text-gray-600">
                    You have answered {{ answeredCount }} of {{ gradableCount }} gradable questions.
                    <span v-if="markedForReview.size"> {{ markedForReview.size }} question(s) are marked for review.</span>
                    Once submitted, you cannot change your answers.
                </p>
                <div class="flex flex-wrap justify-end gap-2">
                    <button ref="confirmCancelRef" type="button" class="btn-secondary text-sm min-h-10" @click="confirmOpen = false">Cancel</button>
                    <button type="button" class="btn-primary text-sm min-h-10" :disabled="submitting || submitBlocked" @click="submit">
                        {{ submitting ? 'Submitting…' : 'Submit now' }}
                    </button>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>

<script setup>
import McqRichText from '@/Components/mcq/McqRichText.vue';
import SignOutButton from '@/Components/SignOutButton.vue';
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
        return new Date(lastSavedAt.value).toLocaleTimeString();
    } catch {
        return '';
    }
});
const submitBlocked = computed(() => dirtySinceSave && (saveStatus.value === 'failed' || saveStatus.value === 'saving' || offline.value));
const syncPillText = computed(() => {
    if (offline.value) return 'Offline — will retry';
    if (saveStatus.value === 'saving') return 'Saving…';
    if (saveStatus.value === 'failed') return 'Save failed — retrying';
    if (saveStatus.value === 'saved') {
        return lastSavedLabel.value ? `Saved · ${lastSavedLabel.value}` : 'Saved';
    }
    return 'Answers autosave';
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
        return 'Leaving this tab during the exam is being recorded for review — not an automatic fail.';
    }
    return `${proctorEventCount.value} leave/blur signals recorded. Stay on this tab until you submit.`;
});

const answeredCount = computed(() => props.questions.filter((q) => isAnswerable(q) && hasAnswer(q.id)).length);

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
    if (markedForReview.value.has(q.id)) return 'border-amber-400 bg-amber-100 text-amber-900';
    if (hasAnswer(q.id)) return 'border-indigo-400 bg-indigo-100 text-indigo-900';
    return 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300';
}

function questionNavLabel(q) {
    const parts = [`Question ${q.number}`];
    if (markedForReview.value.has(q.id)) parts.push('marked for review');
    else if (hasAnswer(q.id)) parts.push('answered');
    else parts.push('not answered');
    return parts.join(', ');
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
    // Strict mode counts visibility only (mcq_v2 parity); blur stays advisory.
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
    if (e.key !== 'Tab' || ! confirmPanelRef.value) return;
    const nodes = [...confirmPanelRef.value.querySelectorAll('button:not([disabled])')];
    if (! nodes.length) return;
    const first = nodes[0];
    const last = nodes[nodes.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (! e.shiftKey && document.activeElement === last) {
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
        if (! props.exam?.require_fullscreen) {
            fullscreenDismissed.value = true;
        }
    }
}

function handleFullscreenChange() {
    const nowFs = !!document.fullscreenElement;
    if (fullscreenActive.value && ! nowFs && props.started && ! submitting.value) {
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
