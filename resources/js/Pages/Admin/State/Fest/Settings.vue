<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <form @submit.prevent="save" class="space-y-4">
            <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-slate-900">Event</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label="Name" class="sm:col-span-2"><input v-model="form.name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></Field>
                    <Field label="Status">
                        <select v-model="form.status" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="archived">Archived</option>
                        </select>
                    </Field>
                    <Field label="Starts"><input v-model="form.starts_on" type="date" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></Field>
                    <Field label="Ends"><input v-model="form.ends_on" type="date" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></Field>
                </div>
            </section>

            <!-- The windows are the part that changes behaviour: closing qualifier submission
                 actually refuses submissions, rather than only documenting a deadline. -->
            <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <h2 class="text-sm font-bold text-slate-900">Windows</h2>
                    <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase"
                          :class="settings.qualifier_window_open ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
                        Qualifier {{ settings.qualifier_window_open ? 'open' : 'closed' }}
                    </span>
                    <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase"
                          :class="settings.scrutiny_window_open ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
                        Scrutiny {{ settings.scrutiny_window_open ? 'open' : 'closed' }}
                    </span>
                </div>
                <p class="mb-3 text-xs text-slate-500">
                    Leave a window blank to keep it open-ended. A window with no dates at all is open —
                    an event that has not been configured should not find its workflow silently shut.
                </p>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label="Qualifier opens"><input v-model="form.qualifier_opens_at" type="datetime-local" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></Field>
                    <Field label="Qualifier closes"><input v-model="form.qualifier_closes_at" type="datetime-local" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></Field>
                    <Field label="Scrutiny opens"><input v-model="form.scrutiny_opens_at" type="datetime-local" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></Field>
                    <Field label="Scrutiny closes"><input v-model="form.scrutiny_closes_at" type="datetime-local" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></Field>
                </div>
                <p v-if="settings.qualifier_window_note" class="mt-2 text-xs text-amber-700">{{ settings.qualifier_window_note }}</p>
            </section>

            <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-slate-900">Locking and publication</h2>
                <div class="grid gap-2 sm:grid-cols-2">
                    <Toggle v-model="form.registrations_locked" label="Registrations locked"
                            hint="Stops new State registrations without touching scoring." />
                    <Toggle v-model="form.scoring_locked" label="Scoring locked"
                            hint="Stops mark entry and result changes." />
                    <Toggle v-model="form.public_schedule_visible" label="Publish schedule publicly" />
                    <Toggle v-model="form.public_results_visible" label="Publish results publicly"
                            hint="Separate from internal publication, so results can be final before they are public." />
                    <Toggle v-model="form.public_ranking_visible" label="Publish Sahodaya ranking publicly" />
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-slate-900">State contact</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label="Contact name"><input v-model="form.contact_name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></Field>
                    <Field label="Phone"><input v-model="form.contact_phone" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></Field>
                    <Field label="Email"><input v-model="form.contact_email" type="email" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></Field>
                    <Field label="Venue summary" class="sm:col-span-2 lg:col-span-4">
                        <input v-model="form.venue_summary" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Shown to Sahodayas and on public pages">
                    </Field>
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" :disabled="form.processing"
                        class="rounded-xl bg-[color:var(--brand-navy)] px-5 py-2.5 text-xs font-bold text-white disabled:opacity-50">
                    Save settings
                </button>
            </div>
        </form>
    </StateEventWorkspace>
</template>

<script setup>
import { h } from 'vue';
import { useForm } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({ event: Object, events: Array, sahodayas: Array, permissions: Array, settings: Object, actionUrls: Object });

const Field = (p, { slots }) => h('div', { class: p.class }, [
    h('label', { class: 'mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500' }, p.label),
    slots.default?.(),
]);
Field.props = ['label', 'class'];

const Toggle = (p, { emit }) => h('label', { class: 'flex items-start gap-2 rounded-xl border border-slate-200 px-3 py-2' }, [
    h('input', {
        type: 'checkbox', class: 'mt-0.5 rounded border-slate-300', checked: p.modelValue,
        onChange: (e) => emit('update:modelValue', e.target.checked),
    }),
    h('span', {}, [
        h('span', { class: 'block text-xs font-semibold text-slate-700' }, p.label),
        p.hint ? h('span', { class: 'block text-[11px] text-slate-500' }, p.hint) : null,
    ]),
]);
Toggle.props = ['modelValue', 'label', 'hint'];
Toggle.emits = ['update:modelValue'];

const s = props.settings;
const form = useForm({
    name: props.event.name, status: props.event.status,
    starts_on: props.event.starts_on, ends_on: props.event.ends_on,
    contact_name: s.contact_name, contact_phone: s.contact_phone, contact_email: s.contact_email,
    venue_summary: s.venue_summary,
    qualifier_opens_at: s.qualifier_opens_at, qualifier_closes_at: s.qualifier_closes_at,
    scrutiny_opens_at: s.scrutiny_opens_at, scrutiny_closes_at: s.scrutiny_closes_at,
    public_schedule_visible: !!s.public_schedule_visible,
    public_results_visible: !!s.public_results_visible,
    public_ranking_visible: !!s.public_ranking_visible,
    registrations_locked: !!s.registrations_locked,
    scoring_locked: !!props.event.scoring_locked,
    notify_on_submission: !!s.notify_on_submission,
    notify_on_approval: !!s.notify_on_approval,
});

function save() {
    form.post(props.actionUrls.save, { preserveScroll: true });
}
</script>

