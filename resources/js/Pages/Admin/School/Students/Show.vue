<template>
    <SchoolAdminLayout :title="student.name" :school="school" :show-header-title="false">
        <div class="student-profile max-w-6xl mx-auto space-y-6 pb-10">
            <!-- Flash -->
            <div v-if="$page.props.flash?.success" class="notice-banner notice-banner--success text-sm">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.warning" class="notice-banner notice-banner--warning text-sm">
                {{ $page.props.flash.warning }}
            </div>
            <div v-if="$page.props.flash?.error" class="notice-banner notice-banner--error text-sm">
                {{ $page.props.flash.error }}
            </div>

            <Link :href="`/school-admin/${school.id}/students`" class="profile-back">
                ← Back to students
            </Link>

            <!-- Hero -->
            <section class="profile-hero">
                <div class="profile-hero__bg"></div>
                <div class="profile-hero__content">
                    <div class="profile-hero__photo-wrap">
                        <img v-if="student.photo_url" :src="student.photo_url" :alt="student.name" class="profile-hero__photo">
                        <div v-else class="profile-hero__photo profile-hero__photo--empty">{{ initials(student.name) }}</div>
                        <span class="profile-hero__status" :class="student.is_verified ? 'is-verified' : 'is-pending'">
                            {{ student.is_verified ? 'Verified' : 'Pending verification' }}
                        </span>
                    </div>

                    <div class="profile-hero__main">
                        <p class="profile-hero__eyebrow">Student profile</p>
                        <h1 class="profile-hero__name">{{ student.name }}</h1>
                        <p v-if="student.reg_no" class="profile-hero__id">{{ student.reg_no }}</p>

                        <div class="profile-hero__chips">
                            <span v-if="student.class_name" class="profile-chip">
                                Class {{ student.class_name }}
                                <template v-if="student.category_label"> · {{ student.category_label }}</template>
                            </span>
                            <span v-if="student.gender" class="profile-chip">{{ formatGender(student.gender) }}</span>
                            <span v-if="student.dob" class="profile-chip" :class="{ 'profile-chip--warn': dobStatus === 'future' }">
                                {{ dobHeroLabel }}
                            </span>
                            <span class="profile-chip" :class="student.has_portal_login ? 'profile-chip--success' : ''">
                                Portal {{ student.has_portal_login ? 'active' : 'not set' }}
                            </span>
                        </div>
                    </div>

                    <div class="profile-hero__actions">
                        <button
                            v-if="canEdit && !isEditing"
                            type="button"
                            class="btn-secondary text-sm"
                            @click="startEdit"
                        >
                            Edit profile
                        </button>
                        <button
                            v-if="isEditing"
                            type="button"
                            class="btn-ghost text-sm"
                            @click="cancelEdit"
                        >
                            Cancel
                        </button>
                        <a :href="portalLoginUrl" target="_blank" rel="noopener" class="btn-primary text-sm">
                            Participant portal ↗
                        </a>
                    </div>
                </div>
            </section>

            <!-- Lock notice -->
            <div v-if="needsChangeRequest" class="profile-lock-banner">
                <strong>Records locked.</strong>
                Edits will be submitted as change requests for Sahodaya review.
            </div>
            <div v-else-if="isLocked && canManageDirectly" class="profile-lock-banner profile-lock-banner--info">
                Edit window is closed for staff. As school admin you can still update this record directly.
            </div>

            <!-- Tabs -->
            <nav class="profile-tabs" aria-label="Profile sections">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    class="profile-tab"
                    :class="{ 'profile-tab--active': activeTab === tab.id }"
                    @click="activeTab = tab.id"
                >
                    {{ tab.label }}
                </button>
            </nav>

            <!-- Profile tab -->
            <div v-show="activeTab === 'profile'" class="space-y-5">
                <form v-if="isEditing" @submit.prevent="submitEdit" class="profile-panel space-y-5">
                    <div class="profile-panel__head">
                        <h2 class="profile-panel__title">Edit student</h2>
                        <p class="profile-panel__sub">
                            {{ needsChangeRequest
                                ? 'Submit a change request with a clear reason.'
                                : 'Changes may reset Sahodaya verification until reviewed again.' }}
                        </p>
                    </div>

                    <ProfilePhotoCropper v-model="editPhotoFile" :existing-url="student.photo_url" />

                    <div class="profile-form-grid">
                        <div class="profile-field profile-field--full">
                            <label class="form-label">Student ID</label>
                            <input :value="student.reg_no || '—'" type="text" readonly class="field bg-slate-50 text-slate-500 font-mono cursor-not-allowed">
                        </div>

                        <div class="profile-field">
                            <label class="form-label">Full name *</label>
                            <input v-model="editForm.name" type="text" required class="field">
                            <p v-if="editForm.errors.name" class="field-error">{{ editForm.errors.name }}</p>
                        </div>

                        <div class="profile-field">
                            <label class="form-label">Class *</label>
                            <select v-model="editForm.school_class_id" required class="field">
                                <option value="">Select class</option>
                                <option v-for="c in classesSorted" :key="c.id" :value="c.id">{{ formatClassOption(c) }}</option>
                            </select>
                            <p v-if="editForm.errors.school_class_id" class="field-error">{{ editForm.errors.school_class_id }}</p>
                        </div>

                        <div class="profile-field">
                            <label class="form-label">Gender *</label>
                            <select v-model="editForm.gender" required class="field">
                                <option value="">Select gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                            <p v-if="editForm.errors.gender" class="field-error">{{ editForm.errors.gender }}</p>
                        </div>

                        <div class="profile-field">
                            <label class="form-label">Date of birth</label>
                            <input v-model="editForm.dob" type="date" class="field" :max="todayIso">
                            <p v-if="editForm.errors.dob" class="field-error">{{ editForm.errors.dob }}</p>
                            <p v-else-if="editDobPreview" class="text-xs text-slate-500 mt-1">{{ editDobPreview }}</p>
                        </div>

                        <div class="profile-field profile-field--full">
                            <label class="form-label">Parent / guardian email</label>
                            <input v-model="editForm.parent_email" type="email" placeholder="optional" class="field">
                            <p v-if="editForm.errors.parent_email" class="field-error">{{ editForm.errors.parent_email }}</p>
                        </div>

                        <div v-if="needsChangeRequest" class="profile-field profile-field--full">
                            <label class="form-label">Reason for change *</label>
                            <textarea v-model="editForm.reason" rows="3" required class="field"
                                      placeholder="Explain why this update is needed…"></textarea>
                            <p v-if="editForm.errors.reason" class="field-error">{{ editForm.errors.reason }}</p>
                        </div>
                    </div>

                    <div class="profile-form-actions">
                        <button type="button" class="btn-ghost text-sm" @click="cancelEdit">Cancel</button>
                        <button type="submit" class="btn-primary text-sm" :disabled="editForm.processing">
                            {{ needsChangeRequest ? 'Submit change request' : 'Save changes' }}
                        </button>
                    </div>
                </form>

                <div v-else class="grid lg:grid-cols-2 gap-5">
                    <section class="profile-panel">
                        <div class="profile-panel__head">
                            <h2 class="profile-panel__title">Identity</h2>
                        </div>
                        <dl class="profile-dl">
                            <div v-for="field in identityFields" :key="field.label" class="profile-dl__row">
                                <dt>{{ field.label }}</dt>
                                <dd>{{ field.value }}</dd>
                            </div>
                            <div class="profile-dl__row profile-dl__row--highlight">
                                <dt>Date of birth</dt>
                                <dd>
                                    <span class="profile-dob-date">{{ dobDateLabel }}</span>
                                    <span v-if="dobAgeLabel" class="profile-dob-age">{{ dobAgeLabel }}</span>
                                    <span v-if="dobStatus === 'future'" class="profile-dob-warn">
                                        This date is in the future — please correct it when editing.
                                    </span>
                                    <span v-else-if="!student.dob" class="profile-dob-age">Not recorded</span>
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section class="profile-panel">
                        <div class="profile-panel__head">
                            <h2 class="profile-panel__title">Contact & guardian</h2>
                        </div>
                        <dl v-if="contactFields.length" class="profile-dl">
                            <div v-for="field in contactFields" :key="field.label" class="profile-dl__row">
                                <dt>{{ field.label }}</dt>
                                <dd class="break-words">{{ field.value }}</dd>
                            </div>
                        </dl>
                        <p v-else class="text-sm text-slate-500 px-5 pb-5">No contact details recorded.</p>
                    </section>

                    <section class="profile-panel lg:col-span-2">
                        <div class="profile-panel__head">
                            <h2 class="profile-panel__title">Verification</h2>
                        </div>
                        <dl class="profile-dl profile-dl--cols">
                            <div class="profile-dl__row">
                                <dt>Sahodaya status</dt>
                                <dd>{{ student.is_verified ? 'Verified' : 'Pending' }}</dd>
                            </div>
                            <div v-if="student.is_verified" class="profile-dl__row">
                                <dt>Verified on</dt>
                                <dd>{{ formatDate(student.verified_at) }}</dd>
                            </div>
                            <div v-if="student.verified_by" class="profile-dl__row">
                                <dt>Verified by</dt>
                                <dd>{{ student.verified_by }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section v-if="student.notes" class="profile-panel lg:col-span-2">
                        <div class="profile-panel__head">
                            <h2 class="profile-panel__title">Notes</h2>
                        </div>
                        <p class="text-sm text-slate-700 px-5 pb-5 whitespace-pre-wrap">{{ student.notes }}</p>
                    </section>
                </div>
            </div>

            <!-- Portal tab -->
            <div v-show="activeTab === 'portal'">
                <StudentPortalLoginCard
                    :student="student"
                    :provision-url="`/school-admin/${school.id}/students/${student.id}/portal-login`"
                    :reset-url="`/school-admin/${school.id}/students/${student.id}/reset-portal-password`"
                    :portal-login-url="portalLoginUrl"
                />
            </div>

            <!-- Sports tab -->
            <div v-show="activeTab === 'sports'">
                <StudentSportsProfileSection :sports-profile="sportsProfile" />
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ProfilePhotoCropper from '@/Components/school/ProfilePhotoCropper.vue';
import StudentPortalLoginCard from '@/Components/students/StudentPortalLoginCard.vue';
import StudentSportsProfileSection from '@/Components/students/StudentSportsProfileSection.vue';
import {
    calendarDateInputValue,
    calendarDateStatus,
    formatAgeLabel,
    formatCalendarDate,
    formatDobDetail,
} from '@/support/calendarDates.js';

const props = defineProps({
    school: Object,
    student: Object,
    classes: { type: Array, default: () => [] },
    sportsProfile: { type: Object, default: () => ({ sports_events: [], other_fest: [] }) },
    studentEditLock: { type: Object, default: () => ({ locked: false }) },
    canManageDirectly: { type: Boolean, default: false },
    portalLoginUrl: { type: String, default: '/portal/login' },
});

const activeTab = ref('profile');
const isEditing = ref(false);
const editPhotoFile = ref(null);

const tabs = [
    { id: 'profile', label: 'Profile' },
    { id: 'portal', label: 'Portal login' },
    { id: 'sports', label: 'Sports' },
];

const editForm = useForm({
    school_class_id: '',
    name: '',
    gender: '',
    dob: '',
    parent_email: '',
    reason: '',
});

const isLocked = computed(() => !!props.studentEditLock?.locked);
const needsChangeRequest = computed(() => isLocked.value && !props.canManageDirectly);
const canEdit = computed(() => props.canManageDirectly || !needsChangeRequest.value);

const classesSorted = computed(() =>
    [...props.classes].sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0) || String(a.name).localeCompare(String(b.name))),
);

const todayIso = new Date().toISOString().slice(0, 10);

const dobStatus = computed(() => calendarDateStatus(props.student.dob));

const dobDateLabel = computed(() => {
    if (props.student.dob_display) return props.student.dob_display;
    return formatCalendarDate(props.student.dob);
});

const dobAgeLabel = computed(() => {
    if (! props.student.dob) return null;
    if (props.student.age_years != null && props.student.age_years >= 0) {
        if (props.student.age_years === 0) return 'Less than 1 year old';
        if (props.student.age_years === 1) return '1 year old';
        return `${props.student.age_years} years old`;
    }
    return formatAgeLabel(props.student.dob);
});

const dobHeroLabel = computed(() => {
    if (! props.student.dob) return '';
    if (dobStatus.value === 'future') {
        return `DOB ${dobDateLabel.value} (future?)`;
    }
    const age = dobAgeLabel.value;
    return age ? `Born ${dobDateLabel.value} · ${age.replace(' old', '')}` : `DOB ${dobDateLabel.value}`;
});

const editDobPreview = computed(() => {
    if (! editForm.dob) return '';
    return formatDobDetail(editForm.dob);
});

const identityFields = computed(() => {
    const s = props.student;
    return [
        { label: 'Student ID', value: s.reg_no || '—' },
        { label: 'Roll no', value: s.roll_number || '—' },
        { label: 'Gender', value: s.gender ? formatGender(s.gender) : '—' },
        { label: 'Blood group', value: s.blood_group || '—' },
        { label: 'Class', value: s.class_name || '—' },
        { label: 'Category', value: s.category_label || '—' },
        { label: 'House', value: s.house_name || '—' },
        { label: 'Status', value: capitalize(s.status || '—') },
        { label: 'Admission date', value: s.admission_date ? formatCalendarDate(s.admission_date) : '—' },
    ].filter((f) => f.value !== '—' || ['Student ID', 'Gender', 'Class', 'Status'].includes(f.label));
});

const contactFields = computed(() => {
    const s = props.student;
    return [
        { label: 'Student email', value: s.email },
        { label: 'Parent / guardian', value: s.parent_name },
        { label: 'Parent phone', value: s.parent_phone },
        { label: 'Parent email', value: s.parent_email },
        { label: 'Address', value: s.address },
    ].filter((f) => f.value);
});

function startEdit() {
    editForm.clearErrors();
    editForm.school_class_id = props.student.school_class_id ?? '';
    editForm.name = props.student.name ?? '';
    editForm.gender = props.student.gender ?? '';
    editForm.dob = calendarDateInputValue(props.student.dob);
    editForm.parent_email = props.student.parent_email ?? '';
    editForm.reason = '';
    editPhotoFile.value = null;
    isEditing.value = true;
    activeTab.value = 'profile';
}

function cancelEdit() {
    isEditing.value = false;
    editPhotoFile.value = null;
    editForm.reset();
}

function submitEdit() {
    const url = `/school-admin/${props.school.id}/students/${props.student.id}`;

    const buildPayload = (data, { changeRequest = false } = {}) => {
        const payload = { ...data };
        if (editPhotoFile.value instanceof File) {
            payload.photo = editPhotoFile.value;
        }
        if (!changeRequest) {
            payload._method = 'put';
        }
        return payload;
    };

    if (needsChangeRequest.value) {
        editForm
            .transform((data) => buildPayload(data, { changeRequest: true }))
            .post(`${url}/change-request`, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => cancelEdit(),
            });
        return;
    }

    editForm
        .transform((data) => buildPayload(data))
        .post(url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                cancelEdit();
                router.reload({ only: ['student'], preserveScroll: true });
            },
        });
}

function formatClassOption(c) {
    const cat = c.category?.label || c.class_category?.label;
    return cat ? `${c.name} (${cat})` : c.name;
}

function capitalize(value) {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function formatGender(gender) {
    return gender ? gender.charAt(0).toUpperCase() + gender.slice(1) : '—';
}

function formatDate(value) {
    return formatCalendarDate(value);
}

function initials(name) {
    return (name || '?').split(/\s+/).slice(0, 2).map((p) => p[0]?.toUpperCase() ?? '').join('') || '?';
}

onMounted(() => {
    if (new URLSearchParams(window.location.search).get('edit') === '1' && canEdit.value) {
        startEdit();
    }
});
</script>

<style scoped>
.student-profile { font-family: 'Inter', system-ui, sans-serif; }

.profile-back {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #0f3d7a;
    text-decoration: none;
}
.profile-back:hover { color: #041525; }

.profile-hero {
    position: relative;
    border-radius: 1.25rem;
    overflow: hidden;
    border: 1px solid rgba(15, 61, 122, 0.12);
    box-shadow: 0 18px 40px rgba(4, 21, 37, 0.08);
}
.profile-hero__bg {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 100% 0%, rgba(30, 90, 168, 0.18) 0%, transparent 42%),
        linear-gradient(135deg, #041525 0%, #0a2744 45%, #0f3d7a 100%);
}
.profile-hero__content {
    position: relative;
    z-index: 1;
    display: grid;
    gap: 1.25rem;
    padding: 1.5rem;
    color: #fff;
}
@media (min-width: 768px) {
    .profile-hero__content {
        grid-template-columns: auto 1fr auto;
        align-items: center;
        padding: 1.75rem 2rem;
    }
}
.profile-hero__photo-wrap { position: relative; width: fit-content; }
.profile-hero__photo {
    width: 7.5rem;
    height: 7.5rem;
    border-radius: 1.25rem;
    object-fit: cover;
    border: 3px solid rgba(255, 255, 255, 0.25);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.25);
}
.profile-hero__photo--empty {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.12);
    font-size: 1.75rem;
    font-weight: 800;
    color: #fbbf24;
}
.profile-hero__status {
    position: absolute;
    bottom: -0.4rem;
    left: 50%;
    transform: translateX(-50%);
    white-space: nowrap;
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    border: 2px solid #0f3d7a;
}
.profile-hero__status.is-verified { background: #ecfdf5; color: #047857; }
.profile-hero__status.is-pending { background: #fff7ed; color: #c2410c; }

.profile-hero__eyebrow {
    margin: 0;
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.55);
}
.profile-hero__name {
    margin: 0.25rem 0 0;
    font-size: clamp(1.5rem, 3vw, 2rem);
    font-weight: 800;
    line-height: 1.15;
}
.profile-hero__id {
    margin: 0.35rem 0 0;
    font-family: ui-monospace, monospace;
    font-size: 0.875rem;
    color: #fbbf24;
}
.profile-hero__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.85rem;
}
.profile-chip {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.14);
    color: rgba(255, 255, 255, 0.92);
}
.profile-chip--success { background: rgba(16, 185, 129, 0.18); border-color: rgba(16, 185, 129, 0.35); }
.profile-chip--warn { background: rgba(251, 146, 60, 0.22); border-color: rgba(251, 146, 60, 0.45); color: #ffedd5; }

.profile-hero__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: flex-start;
}

.profile-lock-banner {
    padding: 0.85rem 1rem;
    border-radius: 0.85rem;
    font-size: 0.8125rem;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
}
.profile-lock-banner--info {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #1e40af;
}

.profile-tabs {
    display: flex;
    gap: 0.35rem;
    padding: 0.35rem;
    background: #f1f5f9;
    border-radius: 0.9rem;
    width: fit-content;
    max-width: 100%;
    overflow-x: auto;
}
.profile-tab {
    border: none;
    background: transparent;
    padding: 0.55rem 1rem;
    border-radius: 0.65rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    white-space: nowrap;
}
.profile-tab--active {
    background: #fff;
    color: #0f3d7a;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.profile-panel {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    overflow: hidden;
}
.profile-panel__head {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    background: #fafbfc;
}
.profile-panel__title {
    margin: 0;
    font-size: 0.9375rem;
    font-weight: 700;
    color: #0f172a;
}
.profile-panel__sub {
    margin: 0.25rem 0 0;
    font-size: 0.75rem;
    color: #64748b;
}

.profile-dl { padding: 0.25rem 0; }
.profile-dl--cols {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
    gap: 0 1rem;
    padding: 0 1.25rem 1rem;
}
.profile-dl__row {
    display: grid;
    grid-template-columns: 9rem 1fr;
    gap: 0.75rem;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid #f8fafc;
    font-size: 0.875rem;
}
.profile-dl__row:last-child { border-bottom: none; }
.profile-dl__row dt {
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #94a3b8;
}
.profile-dl__row dd {
    margin: 0;
    font-weight: 600;
    color: #0f172a;
}
.profile-dl__row--highlight {
    background: #f8fafc;
    border-radius: 0.65rem;
    margin: 0.25rem 0.75rem 0.5rem;
    border-bottom: none;
}
.profile-dob-date { display: block; font-size: 0.9375rem; }
.profile-dob-age {
    display: block;
    margin-top: 0.2rem;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #64748b;
}
.profile-dob-warn {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #c2410c;
}

.profile-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
    gap: 1rem;
    padding: 0 1.25rem;
}
.profile-field--full { grid-column: 1 / -1; }
.field-error { font-size: 0.75rem; color: #dc2626; margin-top: 0.25rem; }

.profile-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.25rem 1.25rem;
    border-top: 1px solid #f1f5f9;
    background: #fafbfc;
}
</style>
