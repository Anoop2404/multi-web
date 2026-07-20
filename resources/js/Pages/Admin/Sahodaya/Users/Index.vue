<template>
    <SahodayaAdminLayout title="Portal users" :sahodaya="sahodaya" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount">
        <PageHeader
            title="Portal users"
            eyebrow="Administration"
            description="Create judges, exam staff, mark-entry coordinators, event ops, and view-only Sahodaya staff. Assign permissions and event duties below."
        />

        <div v-if="newCredentials" class="notice-banner notice-banner--success mb-4 text-sm">
            Account created. Username: <strong class="font-mono">{{ newCredentials.username }}</strong>
            · Temp password: <strong class="font-mono">{{ newCredentials.password }}</strong> (shown once — share this with the user, it won't be shown again)
        </div>

        <form @submit.prevent="createUser" class="card mb-6 form-stack">
            <div>
                <h3 class="section-title">New user</h3>
                <p class="section-desc">Password must be at least 8 characters.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Full name" :error="form.errors.name" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.name" class="field" placeholder="Full name" required>
                    </template>
                </FormField>
                <FormField label="Email (optional — leave blank to log in by username only)" :error="form.errors.email">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.email" type="email" class="field" placeholder="Email (optional)">
                    </template>
                </FormField>
                <FormField label="Username (leave blank to auto-generate from name)" :error="form.errors.username">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.username" class="field" placeholder="e.g. anoop.john">
                    </template>
                </FormField>
                <FormField label="Password" :error="form.errors.password" class-extra="sm:col-span-2" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.password" type="password" class="field" placeholder="Password (min 8)" minlength="8" required>
                    </template>
                </FormField>
            </div>
            <div>
                <ChoiceGroup label="Roles" :error="form.errors.roles">
                    <div class="w-full space-y-4">
                        <div v-for="group in groupedRoles" :key="group.name">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 mb-1.5">{{ group.name }}</p>
                            <div class="flex flex-wrap gap-2">
                                <label v-for="r in group.roles" :key="r.value"
                                       class="choice-chip"
                                       :class="{ 'choice-chip--checked': form.roles.includes(r.value) }"
                                       :title="r.description || ''">
                                    <input type="checkbox" class="choice-chip-input" :value="r.value" v-model="form.roles">
                                    <span class="choice-chip-label">{{ r.label }}</span>
                                </label>
                            </div>
                            <p v-if="group.name === 'Event roles'" class="mt-1.5 text-xs text-slate-500 space-y-0.5">
                                <span class="block"><strong class="text-slate-600">Event coordinator</strong> — full control across every event.</span>
                                <span class="block"><strong class="text-slate-600">Event operations</strong> — one duty (registration desk, stage, food…) on one event.</span>
                                <span class="block"><strong class="text-slate-600">Event admin</strong> — full control, but only for the events you tick.</span>
                            </p>
                        </div>
                    </div>
                </ChoiceGroup>
            </div>
            <div v-if="form.roles.includes('fest_ops')" class="card card--accent space-y-3">
                <p class="text-xs font-semibold text-violet-900">Event ops assignment</p>
                <FormField label="Event">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.fest_ops_event_id" class="field">
                            <option value="">Select event (optional)</option>
                            <option v-for="e in festEvents" :key="e.id" :value="e.id">{{ e.title }} ({{ e.status }})</option>
                        </select>
                    </template>
                </FormField>
                <div>
                    <p class="form-label mb-2">Duties</p>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="d in dutyOptions" :key="d.value" class="flex items-center gap-2 rounded-lg border border-violet-200 bg-white px-2 py-1 text-xs">
                            <input type="checkbox" :value="d.value" v-model="form.fest_ops_duties" :aria-label="d.label">
                            {{ d.label }}
                        </label>
                    </div>
                </div>
            </div>
            <div v-if="form.roles.includes('event_admin')" class="card card--accent space-y-3">
                <p class="text-xs font-semibold text-violet-900">Event admin — assigned events</p>
                <p class="text-xs text-slate-500">This user gets full sahodaya-admin control (items, fees, registrations, results, settings) but only for the events checked below.</p>
                <div class="flex flex-wrap gap-2">
                    <label v-for="e in festEvents" :key="e.id" class="flex items-center gap-2 rounded-lg border border-violet-200 bg-white px-2 py-1 text-xs">
                        <input type="checkbox" :value="e.id" v-model="form.event_admin_event_ids">
                        {{ e.title }} ({{ e.status }})
                    </label>
                    <p v-if="!festEvents.length" class="text-xs text-slate-400 italic">No active events yet.</p>
                </div>
            </div>
            <div v-if="hasExamRole(form.roles)" class="card card--muted space-y-3">
                <p class="text-xs font-semibold text-sky-900">Exam assignment</p>
                <FormField label="Talent Search exam">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.exam_staff_exam_id" class="field">
                            <option value="">Select exam (optional)</option>
                            <option v-for="e in mcqExams" :key="e.id" :value="e.id">{{ e.title }} ({{ e.status }})</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Exam role">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.exam_staff_role" class="field">
                            <option value="staff">Hall staff (attendance)</option>
                            <option value="controller">Exam controller (attendance + marks)</option>
                        </select>
                    </template>
                </FormField>
            </div>
            <div v-if="hasPermissionRole(form.roles)" class="card card--muted space-y-2">
                <p class="form-label mb-1">Access permissions</p>
                <p class="text-xs text-slate-500 mb-2">Role defaults are applied automatically; adjust individual permissions below.</p>
                <div class="flex flex-wrap gap-2">
                    <label v-for="p in permissions" :key="p" class="flex items-center gap-2 rounded-xl border border-slate-200 px-2 py-1 text-xs">
                        <input type="checkbox" :value="p" v-model="form.permissions">
                        {{ permissionLabels[p] || p }}
                    </label>
                </div>
            </div>
            <FormActions sticky>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Creating…' : 'Create user' }}
                </button>
            </FormActions>
        </form>

        <div class="card overflow-hidden p-0">
            <EmptyState
                v-if="!users.length"
                title="No operational users yet"
                description="Create judges, coordinators, and staff accounts using the form above."
                icon="👥"
            />
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email / Username</th>
                        <th>Roles & assignments</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in users" :key="u.id">
                        <td class="font-medium text-slate-900">{{ u.name }}</td>
                        <td class="text-slate-600">
                            {{ u.email || '—' }}
                            <div class="text-xs text-slate-400 font-mono">{{ u.username }}</div>
                        </td>
                        <td class="text-xs">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="r in u.roles" :key="r"
                                      class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700">
                                    {{ roleLabel(r) }}
                                </span>
                            </div>
                            <div v-if="u.permissions.length" class="flex flex-wrap gap-1 mt-1.5">
                                <span v-for="p in u.permissions" :key="p"
                                      class="inline-flex items-center rounded-full bg-slate-50 border border-slate-200 px-1.5 py-0.5 text-[9px] text-slate-500">
                                    {{ permissionLabels[p] || p }}
                                </span>
                            </div>
                            <div v-if="u.fest_assignments?.length" class="flex flex-wrap gap-1 mt-1.5">
                                <span v-for="(a, i) in u.fest_assignments" :key="i"
                                      class="inline-flex items-center rounded-full bg-violet-50 border border-violet-200 px-2 py-0.5 text-[10px] font-medium text-violet-700">
                                    {{ a.event_title }} · {{ dutyLabel(a.duty) }}
                                </span>
                            </div>
                            <div v-if="u.exam_assignments?.length" class="flex flex-wrap gap-1 mt-1.5">
                                <span v-for="(a, i) in u.exam_assignments" :key="i"
                                      class="inline-flex items-center rounded-full bg-sky-50 border border-sky-200 px-2 py-0.5 text-[10px] font-medium text-sky-700">
                                    {{ a.exam_title }} · {{ a.role === 'controller' ? 'Controller' : 'Hall staff' }}
                                </span>
                            </div>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" @click="openEdit(u)" class="btn-ghost text-indigo-600">Edit</button>
                            <button type="button" @click="resetPw(u)" class="btn-ghost text-slate-600">Reset PW</button>
                            <button type="button" @click="remove(u)" class="btn-ghost text-red-600">Remove</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="editing = null">
            <form @submit.prevent="saveEdit" class="card w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-xl space-y-4">
                <h3 class="section-title">Edit {{ editing.name }}</h3>
                <FormField label="Full name" :error="editForm.errors.name" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="editForm.name" class="field" required>
                    </template>
                </FormField>
                <FormField label="Email (optional)" :error="editForm.errors.email">
                    <template #default="{ id }">
                        <input :id="id" v-model="editForm.email" type="email" class="field">
                    </template>
                </FormField>
                <FormField label="Username" :error="editForm.errors.username" hint="What this person logs in with — change carefully, they'll need to be told the new one.">
                    <template #default="{ id }">
                        <input :id="id" v-model="editForm.username" class="field">
                    </template>
                </FormField>
                <FormField label="New password" hint="Leave blank to keep current password">
                    <template #default="{ id }">
                        <input :id="id" v-model="editForm.password" type="password" class="field">
                    </template>
                </FormField>
                <div class="space-y-3">
                    <p class="form-label mb-1">Roles</p>
                    <div v-for="group in groupedRoles" :key="group.name">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1">{{ group.name }}</p>
                        <div class="flex flex-wrap gap-2">
                            <label v-for="r in group.roles" :key="r.value"
                                   class="flex items-center gap-2 rounded-xl border border-slate-200 px-2 py-1 text-xs"
                                   :title="r.description || ''">
                                <input type="checkbox" :value="r.value" v-model="editForm.roles">
                                {{ r.label }}
                            </label>
                        </div>
                    </div>
                </div>
                <div v-if="editForm.roles.includes('fest_ops')" class="card card--accent space-y-3">
                    <FormField label="Event">
                        <template #default="{ id }">
                            <select :id="id" v-model="editForm.fest_ops_event_id" class="field">
                                <option value="">Select event (optional)</option>
                                <option v-for="e in festEvents" :key="e.id" :value="e.id">{{ e.title }} ({{ e.status }})</option>
                            </select>
                        </template>
                    </FormField>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="d in dutyOptions" :key="d.value" class="flex items-center gap-2 rounded-lg border border-violet-200 bg-white px-2 py-1 text-xs">
                            <input type="checkbox" :value="d.value" v-model="editForm.fest_ops_duties" :aria-label="d.label">
                            {{ d.label }}
                        </label>
                    </div>
                </div>
                <div v-if="editForm.roles.includes('event_admin')" class="card card--accent space-y-3">
                    <p class="text-xs font-semibold text-violet-900">Event admin — assigned events</p>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="e in festEvents" :key="e.id" class="flex items-center gap-2 rounded-lg border border-violet-200 bg-white px-2 py-1 text-xs">
                            <input type="checkbox" :value="e.id" v-model="editForm.event_admin_event_ids">
                            {{ e.title }} ({{ e.status }})
                        </label>
                        <p v-if="!festEvents.length" class="text-xs text-slate-400 italic">No active events yet.</p>
                    </div>
                </div>
                <div v-if="hasExamRole(editForm.roles)" class="card card--muted space-y-3">
                    <FormField label="Talent Search exam">
                        <template #default="{ id }">
                            <select :id="id" v-model="editForm.exam_staff_exam_id" class="field">
                                <option value="">Select exam (optional)</option>
                                <option v-for="e in mcqExams" :key="e.id" :value="e.id">{{ e.title }} ({{ e.status }})</option>
                            </select>
                        </template>
                    </FormField>
                    <FormField label="Exam role">
                        <template #default="{ id }">
                            <select :id="id" v-model="editForm.exam_staff_role" class="field">
                                <option value="staff">Hall staff (attendance)</option>
                                <option value="controller">Exam controller (attendance + marks)</option>
                            </select>
                        </template>
                    </FormField>
                </div>
                <div v-if="hasPermissionRole(editForm.roles)" class="card card--muted space-y-2">
                    <p class="form-label mb-1">Access permissions</p>
                    <p class="text-xs text-slate-500 mb-2">Role defaults are applied when roles change; adjust individual permissions below.</p>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="p in permissions" :key="p" class="flex items-center gap-2 rounded-xl border border-slate-200 px-2 py-1 text-xs">
                            <input type="checkbox" :value="p" v-model="editForm.permissions">
                            {{ permissionLabels[p] || p }}
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="editing = null" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="editForm.processing">Save</button>
                </div>
            </form>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    users: Array,
    assignableRoles: Array,
    permissions: Array,
    permissionLabels: Object,
    permissionRoles: { type: Array, default: () => [] },
    roleDefaultPermissions: { type: Object, default: () => ({}) },
    festEvents: Array,
    mcqExams: Array,
    dutyOptions: Array,
    newCredentials: Object,
});

const form = useForm({
    name: '', email: '', username: '', password: '', roles: [], permissions: [],
    fest_ops_event_id: '', fest_ops_duties: [],
    event_admin_event_ids: [],
    exam_staff_exam_id: '', exam_staff_role: 'staff',
});
const editing = ref(null);
const editForm = useForm({
    name: '', email: '', username: '', password: '', roles: [], permissions: [],
    fest_ops_event_id: '', fest_ops_duties: [],
    event_admin_event_ids: [],
    exam_staff_exam_id: '', exam_staff_role: 'staff',
});

const groupedRoles = computed(() => {
    const order = [];
    const byGroup = new Map();
    for (const r of props.assignableRoles) {
        const g = r.group || 'Other';
        if (!byGroup.has(g)) {
            byGroup.set(g, []);
            order.push(g);
        }
        byGroup.get(g).push(r);
    }
    return order.map((name) => ({ name, roles: byGroup.get(name) }));
});

function roleLabel(value) {
    return props.assignableRoles.find((r) => r.value === value)?.label ?? value;
}

function dutyLabel(value) {
    if (value === 'event_admin') return 'Event admin (full control)';
    return props.dutyOptions.find((d) => d.value === value)?.label ?? value;
}

function hasExamRole(roles) {
    return roles.includes('exam_controller') || roles.includes('exam_staff');
}

function hasPermissionRole(roles) {
    return roles.some((r) => props.permissionRoles.includes(r));
}

function mergedRoleDefaults(roles) {
    const out = new Set();
    for (const role of roles) {
        if (!props.permissionRoles.includes(role)) {
            continue;
        }
        for (const perm of props.roleDefaultPermissions[role] ?? []) {
            out.add(perm);
        }
    }
    return [...out];
}

function syncPermissionsFromRoles(roles, targetForm) {
    if (!hasPermissionRole(roles)) {
        targetForm.permissions = [];
        return;
    }
    const defaults = mergedRoleDefaults(roles);
    targetForm.permissions = [...new Set([...targetForm.permissions, ...defaults])];
}

watch(() => form.roles, (roles) => syncPermissionsFromRoles(roles, form), { deep: true });
watch(() => editForm.roles, (roles) => {
    if (editing.value) {
        syncPermissionsFromRoles(roles, editForm);
    }
}, { deep: true });

function createUser() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/users`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function openEdit(user) {
    editing.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.username = user.username;
    editForm.password = '';
    editForm.roles = [...user.roles];
    editForm.permissions = [...(user.permissions || [])];

    const fest = user.fest_assignments?.[0];
    editForm.fest_ops_event_id = fest?.event_id ?? '';
    editForm.fest_ops_duties = fest
        ? user.fest_assignments.filter(a => a.event_id === fest.event_id).map(a => a.duty)
        : [];

    editForm.event_admin_event_ids = (user.fest_assignments || [])
        .filter(a => a.duty === 'event_admin')
        .map(a => a.event_id);

    const exam = user.exam_assignments?.[0];
    editForm.exam_staff_exam_id = exam?.exam_id ?? '';
    editForm.exam_staff_role = exam?.role ?? 'staff';
}

function saveEdit() {
    editForm.put(`/sahodaya-admin/${props.sahodaya.id}/users/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
    });
}

function remove(user) {
    if (!confirm(`Remove ${user.name}?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/users/${user.id}`, { preserveScroll: true });
}

function resetPw(user) {
    if (!confirm(`Reset password for ${user.name}?`)) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/users/${user.id}/reset-password`, {}, { preserveScroll: true });
}
</script>
