<template>
    <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4 space-y-4">
        <div>
            <p class="form-label mb-1">Event assignments</p>
            <p class="text-xs text-slate-600">Pick whole programs or specific events (fest, Talent Search exam, training).</p>
            <p v-if="error" class="text-xs text-red-600 mt-1">{{ error }}</p>
        </div>

        <div v-for="prog in scopeOptions.programs" :key="prog.slug" class="space-y-2">
            <label class="flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" :checked="hasProgram(prog.slug)" @change="toggleProgram(prog.slug, $event.target.checked)">
                All {{ prog.label }} events
            </label>

            <div v-if="prog.slug === 'mcq' && scopeOptions.mcq_exams?.length" class="ml-6 space-y-1">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Or specific Talent Search exams</p>
                <label v-for="exam in scopeOptions.mcq_exams" :key="'mcq-'+exam.id"
                       class="flex items-center gap-2 text-xs">
                    <input type="checkbox" :checked="hasScope('mcq', 'mcq_exam', exam.id)"
                           @change="toggleEvent('mcq', 'mcq_exam', exam.id, $event.target.checked)">
                    {{ exam.title }} <span class="text-slate-400">(L{{ exam.exam_level }})</span>
                </label>
            </div>

            <div v-else-if="prog.slug === 'training' && scopeOptions.training_programs?.length" class="ml-6 space-y-1">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Or specific training programs</p>
                <label v-for="tp in scopeOptions.training_programs" :key="'tr-'+tp.id"
                       class="flex items-center gap-2 text-xs">
                    <input type="checkbox" :checked="hasScope('training', 'training_program', tp.id)"
                           @change="toggleEvent('training', 'training_program', tp.id, $event.target.checked)">
                    {{ tp.title }}
                </label>
            </div>

            <div v-else-if="festEventsFor(prog.slug).length" class="ml-6 space-y-1">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Or specific events</p>
                <label v-for="ev in festEventsFor(prog.slug)" :key="'fest-'+ev.id"
                       class="flex items-center gap-2 text-xs">
                    <input type="checkbox" :checked="hasScope(prog.slug, 'fest_event', ev.id)"
                           @change="toggleEvent(prog.slug, 'fest_event', ev.id, $event.target.checked)">
                    {{ ev.title }}
                </label>
            </div>
        </div>
    </div>
</template>

<script setup>
const model = defineModel({ type: Array, default: () => [] });

const props = defineProps({
    scopeOptions: { type: Object, required: true },
    error: { type: String, default: null },
});

function festEventsFor(slug) {
    return (props.scopeOptions?.fest_events ?? []).filter(e => e.program_slug === slug);
}

function scopeKey(programSlug, scopeType, eventId) {
    return `${programSlug}:${scopeType}:${eventId ?? ''}`;
}

function hasProgram(programSlug) {
    return model.value.some(s => s.program_slug === programSlug && s.scope_type === 'program');
}

function hasScope(programSlug, scopeType, eventId) {
    return model.value.some(s =>
        s.program_slug === programSlug && s.scope_type === scopeType && s.event_id === eventId
    );
}

function toggleProgram(programSlug, checked) {
    const filtered = model.value.filter(s => s.program_slug !== programSlug);
    if (checked) {
        model.value = [...filtered, { program_slug: programSlug, scope_type: 'program', event_id: null }];
    } else {
        model.value = filtered;
    }
}

function toggleEvent(programSlug, scopeType, eventId, checked) {
    const key = scopeKey(programSlug, scopeType, eventId);
    let next = model.value.filter(s => scopeKey(s.program_slug, s.scope_type, s.event_id) !== key);
    next = next.filter(s => !(s.program_slug === programSlug && s.scope_type === 'program'));
    if (checked) {
        next.push({ program_slug: programSlug, scope_type: scopeType, event_id: eventId });
    }
    model.value = next;
}
</script>
