<template>
    <div class="rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-4 sm:px-5">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <p v-if="mode === 'item' && headName" class="text-xs font-semibold uppercase tracking-wide text-indigo-600">
                    {{ headName }}
                </p>
                <h3 class="section-title mt-0.5">{{ title }}</h3>
                <p v-if="itemCode" class="text-xs font-mono text-slate-500 mt-0.5">{{ itemCode }}</p>
            </div>
            <span v-if="mode === 'item' && publishStatus"
                  class="status-pill text-xs shrink-0"
                  :class="publishStatus.published ? 'status-pill--published' : (publishStatus.marksReady ? 'status-pill--open' : 'status-pill--draft')">
                {{ publishStatus.label }}
            </span>
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-3 text-sm">
            <div v-for="field in visibleFields" :key="field.key">
                <dt class="text-xs text-slate-500">{{ field.label }}</dt>
                <dd class="font-medium text-slate-900 mt-0.5" :class="field.class">{{ field.value }}</dd>
            </div>
        </dl>

        <p v-if="mode === 'item' && publishStatus?.publishedAt" class="mt-3 text-xs text-emerald-700">
            Published {{ formatDateTime(publishStatus.publishedAt) }}
        </p>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    mode: { type: String, default: 'item' }, // 'head' | 'item'
    head: { type: Object, default: null },
    item: { type: Object, default: null },
    summary: { type: Object, default: null },
});

const headName = computed(() => props.head?.head_name ?? props.item?.head_name ?? props.summary?.head_name ?? null);
const title = computed(() => {
    if (props.mode === 'head') return props.head?.head_name ?? 'Section';
    return props.item?.title ?? props.summary?.title ?? 'Item';
});
const itemCode = computed(() => props.item?.item_code ?? props.summary?.item_code ?? null);

const publishStatus = computed(() => {
    const src = props.summary ?? props.item;
    if (!src || props.mode === 'head') return null;
    const published = src.results_published ?? false;
    const marksReady = src.marks_ready ?? false;
    return {
        published,
        marksReady,
        publishedAt: src.results_published_at ?? null,
        label: published ? 'Published' : (marksReady ? 'Ready to publish' : 'Marks pending'),
    };
});

const visibleFields = computed(() => {
    if (props.mode === 'head') {
        return headFields.value.filter((f) => f.value && f.value !== '—');
    }
    return itemFields.value.filter((f) => f.value && f.value !== '—');
});

const headFields = computed(() => {
    const h = props.head ?? {};
    return [
        { key: 'items', label: 'Items', value: h.item_count != null ? String(h.item_count) : '—' },
        { key: 'participants', label: 'Participants', value: h.participant_count != null ? String(h.participant_count) : '—' },
        { key: 'published', label: 'Published', value: formatPublishRatio(h.published_count, h.item_count) },
        { key: 'reg', label: 'Registration window', value: formatWindow(h.reg_start, h.reg_end) },
        { key: 'comp', label: h.schedule_mode === 'same_time' ? 'Competition date' : 'Competition window', value: formatCompetition(h) },
        h.schedule_mode === 'same_time'
            ? { key: 'mode', label: 'Schedule', value: 'All items same time' }
            : null,
    ].filter(Boolean);
});

const itemFields = computed(() => {
    const s = props.summary ?? props.item ?? {};
    const performers = s.performers ?? s.participant_count ?? 0;
    const marksEntered = s.marks_entered ?? 0;
    return [
        { key: 'age', label: 'Age group', value: s.age_group ?? '—' },
        { key: 'class', label: 'Class', value: formatLabel(s.class_group) },
        { key: 'gender', label: 'Gender', value: formatLabel(s.gender) },
        { key: 'discipline', label: 'Discipline', value: s.sport_discipline ? formatLabel(s.sport_discipline) : '—' },
        { key: 'stage', label: 'Stage', value: s.stage_type ? formatLabel(s.stage_type) : '—' },
        { key: 'participants', label: 'Participants', value: performers ? String(performers) : '—' },
        { key: 'registrations', label: 'Registrations', value: s.registration_count != null ? String(s.registration_count) : '—' },
        { key: 'marks', label: 'Marks entered', value: performers ? `${marksEntered}/${performers}` : '—', class: s.marks_ready ? 'text-emerald-700' : 'text-amber-700' },
        { key: 'judges', label: 'Judges assigned', value: s.judges_assigned != null ? String(s.judges_assigned) : '—' },
        { key: 'reg', label: 'Registration window', value: formatWindow(s.reg_start ?? s.head_reg_start, s.reg_end ?? s.head_reg_end) },
        { key: 'comp', label: 'Competition window', value: formatWindow(
            s.item_competition_start ?? s.competition_start ?? s.head_competition_start,
            s.item_competition_end ?? s.competition_end ?? s.head_competition_end,
        ) },
        (s.competition_time ?? s.head_competition_time)
            ? { key: 'comp_time', label: 'Start time', value: formatTime(s.competition_time ?? s.head_competition_time) }
            : null,
    ].filter(Boolean);
});

function formatPublishRatio(published, total) {
    if (total == null) return '—';
    const p = published ?? 0;
    return `${p}/${total}`;
}

function formatWindow(start, end) {
    if (start && end) return `${formatDate(start)} – ${formatDate(end)}`;
    if (start) return `from ${formatDate(start)}`;
    if (end) return `until ${formatDate(end)}`;
    return '—';
}

function formatCompetition(head) {
    if (head.schedule_mode === 'same_time' && head.competition_start) {
        const time = formatTime(head.competition_time);
        return time !== '—' ? `${formatDate(head.competition_start)}, ${time}` : formatDate(head.competition_start);
    }
    return formatWindow(head.competition_start, head.competition_end);
}

function formatTime(hhmm) {
    if (!hhmm) return '—';
    const [h, m] = String(hhmm).slice(0, 5).split(':');
    const d = new Date();
    d.setHours(Number(h), Number(m), 0, 0);
    return d.toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' });
}

function formatDate(iso) {
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatDateTime(iso) {
    const d = new Date(iso);
    return d.toLocaleString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatLabel(raw) {
    if (!raw || raw === 'open') return raw === 'open' ? 'Open' : '—';
    return String(raw).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
</script>
