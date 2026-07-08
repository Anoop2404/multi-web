<template>
    <SchoolAdminLayout title="Portal users" :school="school" :show-header-title="false">
        <PageHeader
            :title="coordinatorMode ? 'Event coordinators' : 'Portal users'"
            eyebrow="Administration"
            :description="coordinatorMode
                ? 'Assign event coordinators — they only see and manage the programs or events you select.'
                : 'Add vice principals, event coordinators (per fest / Talent Search / training), staff, and class or house admins.'"
        >
            <template #actions>
                <Link v-if="!coordinatorMode" :href="`/school-admin/${school.id}/users/profile-change-requests`" class="btn-secondary text-sm">
                    Profile change requests
                </Link>
                <Link v-else :href="`/school-admin/${school.id}/users`" class="btn-secondary text-sm">
                    All portal users
                </Link>
            </template>
        </PageHeader>

        <div v-if="newCredentials" class="notice-banner notice-banner--success mb-4 text-sm">
            Account created. Username: <strong class="font-mono">{{ newCredentials.username }}</strong>
            · Temp password: <strong class="font-mono">{{ newCredentials.password }}</strong> (shown once)
        </div>

        <section v-if="!coordinatorMode" class="card mb-6 space-y-4">
            <div>
                <h3 class="section-title">Leadership contact logins</h3>
                <p class="section-desc">
                    Principal and Vice Principal details come from the registration profile. Create or update their login and email a temporary password.
                </p>
            </div>

            <div v-if="leadershipLoginForm.errors.leadership" class="notice-banner notice-banner--warning text-sm">
                {{ leadershipLoginForm.errors.leadership }}
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div v-for="contact in leadershipLoginCards" :key="contact.key" class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ contact.label }}</p>
                            <h4 class="font-semibold text-slate-900 mt-1">{{ contact.name || 'Not added' }}</h4>
                            <p class="text-xs text-slate-500 mt-0.5">{{ contact.email || 'Email missing' }}</p>
                            <p v-if="contact.phone" class="text-xs text-slate-500">{{ contact.phone }}</p>
                        </div>
                        <span v-if="contact.loginUser" class="text-xs rounded-full bg-emerald-100 px-2 py-1 font-semibold text-emerald-800">
                            {{ contact.loginUser.username }}
                        </span>
                        <span v-else class="text-xs rounded-full bg-amber-100 px-2 py-1 font-semibold text-amber-800">
                            No login
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-secondary text-xs" @click="openLeadershipContact(contact)">
                            Edit contact
                        </button>
                        <button type="button"
                                class="btn-primary text-xs"
                                :disabled="leadershipLoginForm.processing || !contact.name || !contact.email"
                                @click="provisionLeadershipLogin(contact)">
                            {{ contact.loginUser ? 'Update login & email new password' : 'Create login & email password' }}
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="coordinatorMode" class="card mb-6 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="section-title">Registration coordinator contact</h3>
                    <p class="section-desc">
                        This is the Events Coordinator saved in the school registration profile. Create a login from this contact and email the credentials.
                    </p>
                </div>
                <span v-if="coordinatorContact?.loginUser" class="text-xs rounded-full bg-emerald-100 px-2 py-1 font-semibold text-emerald-800">
                    Login created: {{ coordinatorContact.loginUser.username }}
                </span>
                <span v-else class="text-xs rounded-full bg-amber-100 px-2 py-1 font-semibold text-amber-800">
                    No login yet
                </span>
            </div>

            <div v-if="contactLoginForm.errors.leadership" class="notice-banner notice-banner--warning text-sm">
                {{ contactLoginForm.errors.leadership }}
            </div>

            <form @submit.prevent="saveCoordinatorContact" class="grid gap-4 sm:grid-cols-3">
                <FormField label="Coordinator name" :error="contactForm.errors.name" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="contactForm.name" class="field" required placeholder="Coordinator name">
                    </template>
                </FormField>
                <FormField label="Coordinator email" :error="contactForm.errors.email" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="contactForm.email" type="email" class="field" required placeholder="email@example.com">
                    </template>
                </FormField>
                <FormField label="Phone" :error="contactForm.errors.phone">
                    <template #default="{ id }">
                        <input :id="id" v-model="contactForm.phone" class="field" placeholder="Phone">
                    </template>
                </FormField>
                <div class="sm:col-span-3 flex flex-wrap gap-2">
                    <button type="submit" class="btn-secondary text-sm" :disabled="contactForm.processing">
                        {{ contactForm.processing ? 'Saving…' : 'Save contact' }}
                    </button>
                    <Link :href="`/school-admin/${school.id}/registration/profile`" class="btn-secondary text-sm">
                        Open full registration profile
                    </Link>
                </div>
            </form>

            <div class="border-t border-slate-100 pt-4 space-y-3">
                <div>
                    <p class="form-label mb-1">Coordinator assignments</p>
                    <p class="text-xs text-slate-500">
                        These assignments control what this coordinator can see after login.
                        Creating/updating the login will email a temporary password to the coordinator email above.
                    </p>
                </div>
                <EventScopePicker
                    v-model="contactLoginForm.event_scopes"
                    :scope-options="scopeOptions"
                    :error="contactLoginForm.errors.event_scopes"
                />
                <div v-if="contactLoginForm.errors.leadership" class="text-xs text-red-600">
                    {{ contactLoginForm.errors.leadership }}
                </div>
                <button type="button" class="btn-primary text-sm" :disabled="contactLoginForm.processing" @click="provisionCoordinatorLogin">
                    {{ contactLoginForm.processing ? 'Sending…' : (coordinatorContact?.loginUser ? 'Update login & email new password' : 'Create login & email password') }}
                </button>
            </div>
        </section>

        <form @submit.prevent="createUser" class="card mb-6 space-y-4">
            <div>
                <h3 class="section-title">New user</h3>
                <p class="section-desc">Event coordinators only see the programs and events you assign below.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Full name" :error="form.errors.name" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.name" class="field" placeholder="Full name" required>
                    </template>
                </FormField>
                <FormField label="Email" :error="form.errors.email" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.email" type="email" class="field" placeholder="Email" required>
                    </template>
                </FormField>
                <FormField label="Password" :error="form.errors.password" class-extra="sm:col-span-2"
                           hint="Leave blank to auto-generate a temporary password">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.password" type="password" class="field" placeholder="Optional — auto-generated if empty" minlength="8">
                    </template>
                </FormField>
            </div>
            <div v-if="!coordinatorMode">
                <p class="form-label mb-2">Role</p>
                <div class="flex flex-wrap gap-2">
                    <label v-for="r in assignableRoles" :key="r.value"
                           class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium cursor-pointer"
                           :class="form.roles[0] === r.value ? 'border-indigo-400 bg-indigo-50' : ''">
                        <input type="radio" :value="r.value" v-model="form.roles[0]" name="create-role">
                        {{ r.label }}
                    </label>
                </div>
            </div>
            <p v-else class="text-xs text-slate-500">Role: Event coordinator (scoped to assignments below)</p>

            <EventScopePicker
                v-if="form.roles.includes('school_event_coordinator')"
                v-model="form.event_scopes"
                :scope-options="scopeOptions"
                :error="form.errors.event_scopes"
            />

            <div v-if="form.roles.includes('school_staff')">
                <p class="form-label mb-2">Staff permissions</p>
                <div class="flex flex-wrap gap-2">
                    <label v-for="p in permissions" :key="p" class="flex items-center gap-2 rounded-xl border border-slate-200 px-2 py-1 text-xs">
                        <input type="checkbox" :value="p" v-model="form.permissions">
                        {{ permissionLabels[p] || p }}
                    </label>
                </div>
            </div>
            <div v-if="form.roles.includes('group_admin')">
                <p class="form-label mb-2">Assigned classes</p>
                <div class="flex max-h-32 flex-wrap gap-2 overflow-y-auto">
                    <label v-for="cls in classes" :key="cls.id" class="flex items-center gap-2 rounded-lg border border-slate-200 px-2 py-1 text-xs">
                        <input type="checkbox" :value="cls.id" v-model="form.group_classes">
                        {{ cls.name }}
                    </label>
                </div>
            </div>
            <FormField v-if="form.roles.includes('house_admin')" label="Assigned house" required>
                <template #default="{ id }">
                    <select :id="id" v-model="form.school_house_id" class="field max-w-xs" required>
                        <option value="">Select house</option>
                        <option v-for="h in houses" :key="h.id" :value="h.id">{{ h.name }}</option>
                    </select>
                </template>
            </FormField>
            <button type="submit" class="btn-primary" :disabled="form.processing">
                {{ form.processing ? 'Creating…' : 'Create user' }}
            </button>
        </form>

        <div class="card overflow-hidden p-0">
            <EmptyState
                v-if="!visibleUsers.length"
                :title="coordinatorMode ? 'No event coordinators yet' : 'No portal users yet'"
                :description="coordinatorMode
                    ? 'Add a coordinator and assign the fest programs or specific events they should manage.'
                    : 'Add coordinators, vice principals, or staff using the form above.'"
                icon="👥"
            />
            <div v-else class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Login</th>
                        <th>Roles / assignments</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in visibleUsers" :key="u.id">
                        <td class="font-medium text-slate-900">{{ u.name }}</td>
                        <td class="text-xs text-slate-600">
                            <p>{{ u.email }}</p>
                            <p v-if="u.username" class="font-mono text-slate-400">{{ u.username }}</p>
                        </td>
                        <td class="text-xs">
                            <p>{{ u.roles.join(', ') }}</p>
                            <p v-if="u.event_scopes?.length" class="mt-1 text-indigo-700">
                                {{ formatScopes(u.event_scopes) }}
                            </p>
                            <p v-if="u.group_classes?.length" class="mt-1 text-slate-400">Classes: {{ u.group_classes.join(', ') }}</p>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" @click="openEdit(u)" class="btn-ghost text-indigo-600">Edit</button>
                            <button v-if="!isProtected(u)" type="button" @click="resetPw(u)" class="btn-ghost text-slate-600">Reset PW</button>
                            <button v-if="!isProtected(u)" type="button" @click="remove(u)" class="btn-ghost text-red-600">Remove</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="editing = null">
            <form @submit.prevent="saveEdit" class="card w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-xl space-y-4">
                <h3 class="section-title">Edit {{ editing.name }}</h3>
                <FormField label="Full name" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="editForm.name" class="field" required>
                    </template>
                </FormField>
                <FormField label="Email" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="editForm.email" type="email" class="field" required>
                    </template>
                </FormField>
                <FormField label="New password" hint="Leave blank to keep current password">
                    <template #default="{ id }">
                        <input :id="id" v-model="editForm.password" type="password" class="field">
                    </template>
                </FormField>
                <div class="flex flex-wrap gap-2">
                    <label v-for="r in assignableRoles" :key="r.value"
                           class="flex items-center gap-2 rounded-xl border border-slate-200 px-2 py-1 text-xs">
                        <input type="radio" :value="r.value" v-model="editForm.roles[0]" name="edit-role">
                        {{ r.label }}
                    </label>
                </div>
                <EventScopePicker
                    v-if="editForm.roles.includes('school_event_coordinator')"
                    v-model="editForm.event_scopes"
                    :scope-options="scopeOptions"
                />
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="editing = null" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="editForm.processing">Save</button>
                </div>
            </form>
        </div>

        <div v-if="editingLeadership" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeLeadershipContact">
            <form @submit.prevent="saveLeadershipContact" class="card w-full max-w-lg shadow-xl space-y-4">
                <div>
                    <h3 class="section-title">Edit {{ editingLeadership.label }}</h3>
                    <p class="section-desc text-xs mt-1">This updates the registration profile contact and the login source details.</p>
                </div>

                <FormField label="Name" :error="leadershipContactForm.errors.name" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="leadershipContactForm.name" class="field" required>
                    </template>
                </FormField>
                <FormField label="Email" :error="leadershipContactForm.errors.email" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="leadershipContactForm.email" type="email" class="field" required>
                    </template>
                </FormField>
                <FormField label="Phone" :error="leadershipContactForm.errors.phone">
                    <template #default="{ id }">
                        <input :id="id" v-model="leadershipContactForm.phone" class="field">
                    </template>
                </FormField>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="btn-secondary" @click="closeLeadershipContact">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="leadershipContactForm.processing">Save contact</button>
                </div>
            </form>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import EventScopePicker from '@/Components/school/EventScopePicker.vue';

const props = defineProps({
    school: Object,
    users: Array,
    classes: Array,
    houses: Array,
    assignableRoles: Array,
    scopeOptions: Object,
    canManageAdmins: Boolean,
    permissions: Array,
    permissionLabels: Object,
    newCredentials: Object,
    coordinatorContact: Object,
    leadershipContacts: Object,
});

const page = usePage();
const coordinatorMode = computed(() => page.url.includes('coordinators=1'));

const visibleUsers = computed(() =>
    coordinatorMode.value
        ? (props.users ?? []).filter((u) => u.roles?.includes('school_event_coordinator'))
        : (props.users ?? []),
);

const form = useForm({
    name: '', email: '', password: '', roles: ['school_event_coordinator'],
    permissions: [], group_classes: [], school_house_id: '', event_scopes: [],
});
const contactForm = useForm({
    name: props.coordinatorContact?.name ?? '',
    email: props.coordinatorContact?.email ?? '',
    phone: props.coordinatorContact?.phone ?? '',
});
const contactLoginForm = useForm({
    event_scopes: [...(props.coordinatorContact?.loginUser?.event_scopes ?? [])],
});
const editingLeadership = ref(null);
const leadershipContactForm = useForm({ name: '', email: '', phone: '' });
const leadershipLoginForm = useForm({ event_scopes: [] });

const leadershipLoginCards = computed(() => [
    props.leadershipContacts?.principal,
    props.leadershipContacts?.vice_principal,
].filter(Boolean));

watch(coordinatorMode, (on) => {
    if (on) {
        form.roles[0] = 'school_event_coordinator';
    }
}, { immediate: true });
const editing = ref(null);
const editForm = useForm({
    name: '', email: '', password: '', roles: [], permissions: [],
    group_classes: [], school_house_id: '', event_scopes: [],
});

watch(() => form.roles[0], (role) => {
    if (role !== 'school_event_coordinator') {
        form.event_scopes = [];
    }
});

function formatScopes(scopes) {
    return scopes.map(s => {
        if (s.scope_type === 'program') return `All ${s.program_slug}`;
        if (s.scope_type === 'mcq_exam') {
            const exam = props.scopeOptions?.mcq_exams?.find(e => e.id === s.event_id);
            return exam ? `Talent Search: ${exam.title}` : `Talent Search #${s.event_id}`;
        }
        if (s.scope_type === 'training_program') {
            const p = props.scopeOptions?.training_programs?.find(e => e.id === s.event_id);
            return p ? `Training: ${p.title}` : `Training #${s.event_id}`;
        }
        const ev = props.scopeOptions?.fest_events?.find(e => e.id === s.event_id);
        return ev ? `${ev.program_slug}: ${ev.title}` : `${s.program_slug} #${s.event_id}`;
    }).join(' · ');
}

function isProtected(user) {
    return user.roles.some(r => ['school_admin', 'school_principal', 'school_vice_principal'].includes(r));
}

function createUser() {
    form.transform(data => ({
        ...data,
        roles: [data.roles[0]].filter(Boolean),
    })).post(`/school-admin/${props.school.id}/users`, {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'email', 'password', 'event_scopes'),
    });
}

function saveCoordinatorContact() {
    contactForm.put(`/school-admin/${props.school.id}/users/coordinator-contact`, {
        preserveScroll: true,
    });
}

function provisionCoordinatorLogin() {
    contactLoginForm.post(`/school-admin/${props.school.id}/users/coordinator-login`, {
        preserveScroll: true,
    });
}

function openLeadershipContact(contact) {
    editingLeadership.value = contact;
    leadershipContactForm.clearErrors();
    leadershipContactForm.name = contact.name ?? '';
    leadershipContactForm.email = contact.email ?? '';
    leadershipContactForm.phone = contact.phone ?? '';
}

function closeLeadershipContact() {
    editingLeadership.value = null;
    leadershipContactForm.clearErrors();
}

function saveLeadershipContact() {
    leadershipContactForm.put(`/school-admin/${props.school.id}/users/leadership-contact/${editingLeadership.value.key}`, {
        preserveScroll: true,
        onSuccess: closeLeadershipContact,
    });
}

function provisionLeadershipLogin(contact) {
    leadershipLoginForm.clearErrors();
    leadershipLoginForm.event_scopes = [];
    leadershipLoginForm.post(`/school-admin/${props.school.id}/users/leadership-login/${contact.key}`, {
        preserveScroll: true,
    });
}

function openEdit(user) {
    editing.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.roles = [user.roles[0] ?? 'school_staff'];
    editForm.permissions = [...(user.permissions || [])];
    editForm.group_classes = [...(user.group_classes || [])];
    editForm.school_house_id = user.school_house_id ?? '';
    editForm.event_scopes = [...(user.event_scopes || [])];
}

function saveEdit() {
    editForm.transform(data => ({
        ...data,
        roles: [data.roles[0]].filter(Boolean),
    })).put(`/school-admin/${props.school.id}/users/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
    });
}

function resetPw(user) {
    if (!confirm(`Reset password for ${user.name}?`)) return;
    router.post(`/school-admin/${props.school.id}/users/${user.id}/reset-password`, {}, { preserveScroll: true });
}

function remove(user) {
    if (!confirm(`Remove ${user.name}?`)) return;
    router.delete(`/school-admin/${props.school.id}/users/${user.id}`, { preserveScroll: true });
}
</script>
