import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { settingsDescriptionForEvent } from '@/support/sahodayaEventCapabilities.js';

const schemeLabelMap = {
    cbse: {
        lp: 'Category I — Classes III & IV',
        up: 'Category II — Classes V–VII',
        hs: 'Category III — Classes VIII–X',
        hss: 'Category IV — Classes XI & XII',
        open: 'Open / All Categories',
    },
    sahodaya: {
        lp: 'LP — Classes I–IV',
        up: 'UP — Classes V–VII',
        hs: 'HS — Classes VIII–X',
        hss: 'HSS — Classes XI & XII',
        open: 'Open / All Classes',
    },
};

const ATHLETICS_RANK_DEFAULTS = { 1: 8, 2: 7, 3: 6, 4: 5, 5: 4, 6: 3 };

function initRankRows(source, fallbackCount = 6) {
    const rows = (source ?? []).map((row, index) => ({
        _key: `rank-${row.rank}-${index}`,
        rank: row.rank,
        points: row.points,
    }));

    if (rows.length) {
        return rows;
    }

    if (fallbackCount <= 0) {
        return [];
    }

    return Array.from({ length: fallbackCount }, (_, index) => {
        const rank = index + 1;
        return {
            _key: `default-${rank}`,
            rank,
            points: ATHLETICS_RANK_DEFAULTS[rank] ?? 0,
        };
    });
}

export function useEventSettingsForms(props) {
    const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
    const settingsDescription = computed(() => settingsDescriptionForEvent(props.event));
    const activeTab = computed(() => props.initialTab || 'lifecycle');

    const policyForm = useForm({
        preset_key: props.participationPolicy?.preset_key ?? '',
        max_onstage_per_student: props.participationPolicy?.max_onstage_per_student ?? '',
        max_offstage_per_student: props.participationPolicy?.max_offstage_per_student ?? '',
        max_group_per_student: props.participationPolicy?.max_group_per_student ?? '',
        require_fee_before_approval: props.participationPolicy?.require_fee_before_approval ?? true,
    });

    const lifecycleLinks = computed(() => ({
        items: `${base}/items`,
        registrations: `${base}/registrations`,
        school_fees: `${base}/fees`,
        state_remittance: `/sahodaya-admin/${props.sahodaya.id}/state-remittances?event_id=${props.event.id}`,
        schedule: `${base}/schedule`,
        schedule_published: `${base}/schedule`,
        ongoing: `${base}`,
        marks: `${base}/marks`,
        published: `${base}/results`,
    }));

    const settingsForm = useForm({
        scoring_locked: props.event.scoring_locked ?? false,
        appeals_open: props.event.appeals_open ?? true,
        registration_locked: props.event.registration_locked ?? false,
        certificate_collection_open: props.event.certificate_collection_open ?? false,
        require_judge_scores_before_publish: props.event.require_judge_scores_before_publish ?? false,
        require_all_marks_before_publish: props.event.require_all_marks_before_publish ?? false,
        chest_reveal_mode: props.event.chest_reveal_mode ?? 'immediate',
        appeal_fee_amount: props.event.appeal_fee_amount ?? '',
        record_tracking_enabled: props.event.record_tracking_enabled ?? false,
        default_record_prize_label: props.event.default_record_prize_label ?? 'Record Break Prize',
    });

    const venueForm = useForm({ name: '', location: '', capacity: null });
    const stageForm = useForm({ name: '', venue_id: '' });
    const comboForm = useForm({ school_id: '', class_group: '', max_arts_events: null, max_sports_events: null, max_on_stage: null, max_off_stage: null });
    const gradeForm = useForm({ item_id: '', grade: 'A', min_score: null, max_score: null });
    const pointForm = useForm({ grade: '', position: null, points: null, is_group: false });
    const rankRows = ref(initRankRows(props.rankPoints));
    const groupRankRows = ref(initRankRows(props.groupRankPoints, 0));
    const savingRanks = ref(false);
    const savingGroupRanks = ref(false);
    const seedingRanks = ref(false);
    const volunteerForm = useForm({ name: '', phone: '', duty: '', notes: '' });
    const cloneForm = useForm({ title: '' });

    const eligibilityForm = useForm({
        sports_age_cutoff_date: props.event.sports_age_cutoff_date ?? '',
    });

    const lifecycleForm = useForm({
        verification_day: props.event.verification_day ?? '',
        manual_pdf: null,
        remove_manual: false,
    });

    const registrationSettingsForm = useForm({
        require_event_registration: props.event.require_event_registration ?? false,
        event_reg_start: props.event.event_reg_start ?? '',
        event_reg_end: props.event.event_reg_end ?? '',
        allow_student_self_register: props.event.allow_student_self_register ?? false,
    });

    const numberingSettingsForm = useForm({
        event_reg_start: props.numberingSettings?.event_reg_start ?? 1,
        event_reg_prefix: props.numberingSettings?.event_reg_prefix ?? '',
        chest_no_start: props.numberingSettings?.chest_no_start ?? 1,
        chest_no_prefix: props.numberingSettings?.chest_no_prefix ?? '',
        auto_assign_on_approve: props.numberingSettings?.auto_assign_on_approve ?? true,
        auto_assign_chest_on_create: props.numberingSettings?.auto_assign_chest_on_create ?? false,
    });

    const itemNumberingForm = useForm({
        items: (props.event.items ?? [])
            .filter((i) => i.is_enabled !== false)
            .sort((a, b) => (a.title ?? '').localeCompare(b.title ?? ''))
            .map((i) => ({
                id: i.id,
                title: i.title,
                item_code: i.item_code ?? '',
                chest_no_start: i.chest_no_start ?? '',
                item_reg_id_start: i.item_reg_id_start ?? '',
            })),
    });

    const existingFeeSettings = props.event.fee_settings ?? {};
    const schedule = props.feeSchedule ?? {};
    const feeSettingsForm = useForm({
        fee_model: existingFeeSettings.fee_model
            ?? schedule.fee_model
            ?? (props.event?.event_type === 'sports' ? 'sports_composite' : 'none'),
        first_item: existingFeeSettings.first_item ?? schedule.first_item ?? '',
        additional_item: existingFeeSettings.additional_item ?? schedule.additional_item ?? '',
        charge_standbys: existingFeeSettings.charge_standbys ?? schedule.charge_standbys ?? false,
        school_registration: {
            secondary: existingFeeSettings.school_registration?.secondary ?? schedule.school_registration?.secondary ?? '',
            senior_secondary: existingFeeSettings.school_registration?.senior_secondary ?? schedule.school_registration?.senior_secondary ?? '',
        },
        flat_amount: existingFeeSettings.flat_amount ?? schedule.flat_amount ?? '',
        per_item_amount: existingFeeSettings.per_item_amount ?? schedule.per_item_amount ?? '',
        per_student_amount: existingFeeSettings.per_student_amount ?? schedule.per_student_amount ?? '',
        school_registration_flat: existingFeeSettings.school_registration_flat ?? schedule.school_registration_flat ?? '',
        included_items_per_student: existingFeeSettings.included_items_per_student ?? schedule.included_items_per_student ?? '',
        school_fee_cap: existingFeeSettings.school_fee_cap ?? schedule.school_fee_cap ?? '',
        class_group_scheme: existingFeeSettings.class_group_scheme ?? schedule.class_group_scheme ?? '',
        include_school_registration: existingFeeSettings.include_school_registration ?? schedule.include_school_registration ?? false,
        class_group_fees: {
            lp: existingFeeSettings.class_group_fees?.lp ?? schedule.class_group_fees?.lp ?? props.defaultClassGroupFees?.lp ?? '',
            up: existingFeeSettings.class_group_fees?.up ?? schedule.class_group_fees?.up ?? props.defaultClassGroupFees?.up ?? '',
            hs: existingFeeSettings.class_group_fees?.hs ?? schedule.class_group_fees?.hs ?? props.defaultClassGroupFees?.hs ?? '',
            hss: existingFeeSettings.class_group_fees?.hss ?? schedule.class_group_fees?.hss ?? props.defaultClassGroupFees?.hss ?? '',
            open: existingFeeSettings.class_group_fees?.open ?? schedule.class_group_fees?.open ?? props.defaultClassGroupFees?.open ?? '',
        },
        age_group_fees: {
            u8: existingFeeSettings.age_group_fees?.u8 ?? schedule.age_group_fees?.u8 ?? props.defaultAgeGroupFees?.u8 ?? '',
            u10: existingFeeSettings.age_group_fees?.u10 ?? schedule.age_group_fees?.u10 ?? props.defaultAgeGroupFees?.u10 ?? '',
            u11: existingFeeSettings.age_group_fees?.u11 ?? schedule.age_group_fees?.u11 ?? props.defaultAgeGroupFees?.u11 ?? '',
            u12: existingFeeSettings.age_group_fees?.u12 ?? schedule.age_group_fees?.u12 ?? props.defaultAgeGroupFees?.u12 ?? '',
            u14: existingFeeSettings.age_group_fees?.u14 ?? schedule.age_group_fees?.u14 ?? props.defaultAgeGroupFees?.u14 ?? '',
            u17: existingFeeSettings.age_group_fees?.u17 ?? schedule.age_group_fees?.u17 ?? props.defaultAgeGroupFees?.u17 ?? '',
            u19: existingFeeSettings.age_group_fees?.u19 ?? schedule.age_group_fees?.u19 ?? props.defaultAgeGroupFees?.u19 ?? '',
            open: existingFeeSettings.age_group_fees?.open ?? schedule.age_group_fees?.open ?? props.defaultAgeGroupFees?.open ?? '',
        },
        participant_type_fees: {
            group: existingFeeSettings.participant_type_fees?.group ?? schedule.participant_type_fees?.group ?? props.defaultParticipantTypeFees?.group ?? '',
            team: existingFeeSettings.participant_type_fees?.team ?? schedule.participant_type_fees?.team ?? props.defaultParticipantTypeFees?.team ?? '',
        },
        default_item_fee: existingFeeSettings.default_item_fee ?? schedule.default_item_fee ?? '',
        require_fee_before_registration: existingFeeSettings.require_fee_before_registration
            ?? schedule.require_fee_before_registration
            ?? (props.event?.event_type === 'sports'),
        require_verified_students: existingFeeSettings.require_verified_students
            ?? schedule.require_verified_students
            ?? false,
        head_fees: (props.itemHeads ?? []).map((head) => ({
            id: head.id,
            name: head.name,
            default_item_fee: head.default_item_fee ?? '',
            extra_item_fee: head.extra_item_fee ?? '',
        })),
        item_fees: (props.event.items ?? []).map((item) => ({
            id: item.id,
            title: item.title,
            fee_amount: item.fee_amount ?? '',
            age_group: item.age_group ?? null,
            class_group: item.class_group ?? null,
            participant_type: item.participant_type ?? null,
            item_code: item.item_code ?? null,
        })),
    });

    const effectiveClassGroupLabels = computed(() => {
        const scheme = feeSettingsForm.class_group_scheme || props.classGroupScheme || 'cbse';
        return schemeLabelMap[scheme] ?? props.classGroupLabels ?? schemeLabelMap.cbse;
    });

    function savePolicy() {
        policyForm.post(`${base}/participation-policy`, { preserveScroll: true });
    }

    function saveSettings() {
        settingsForm.put(`${base}/settings`, { preserveScroll: true });
    }

    function saveFeeSettings() {
        feeSettingsForm.put(`${base}/fee-settings`, { preserveScroll: true });
    }

    function saveEligibility() {
        eligibilityForm.put(`${base}/eligibility-settings`, { preserveScroll: true });
    }

    function saveLifecycle() {
        lifecycleForm.post(`${base}/lifecycle-settings`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => lifecycleForm.reset('manual_pdf'),
        });
    }

    function saveRegistrationSettings() {
        registrationSettingsForm.put(`${base}/registration-settings`, { preserveScroll: true });
    }

    function saveItemWindow(itemId, row) {
        router.patch(`${base}/items/${itemId}/windows`, {
            reg_start: row.reg_start || null,
            reg_end: row.reg_end || null,
            competition_start: row.competition_start || null,
            competition_end: row.competition_end || null,
            head_id: row.head_id || null,
        }, { preserveScroll: true });
    }

    function saveHeadWindow(headId, row) {
        router.patch(`${base}/item-heads/${headId}/windows`, {
            reg_start: row.reg_start || null,
            reg_end: row.reg_end || null,
            competition_start: row.competition_start || null,
            competition_end: row.competition_end || null,
            apply_to_items: row.apply_to_items ?? true,
        }, { preserveScroll: true });
    }

    function saveNumberingSettings() {
        numberingSettingsForm.put(`${base}/numbering-settings`, { preserveScroll: true });
    }

    function saveItemNumbering() {
        itemNumberingForm.put(`${base}/item-numbering`, { preserveScroll: true });
    }

    function backfillRegs() {
        router.post(`${base}/backfill-level-registrations`, {}, { preserveScroll: true });
    }

    function addVenue() {
        venueForm.post(`${base}/venues`, { preserveScroll: true, onSuccess: () => venueForm.reset() });
    }

    function removeVenue(id) {
        router.delete(`${base}/venues/${id}`, { preserveScroll: true });
    }

    function addStage() {
        stageForm.post(`${base}/stages`, { preserveScroll: true, onSuccess: () => stageForm.reset() });
    }

    function removeStage(id) {
        router.delete(`${base}/stages/${id}`, { preserveScroll: true });
    }

    function addComboRule() {
        comboForm.post(`${base}/combo-rules`, { preserveScroll: true, onSuccess: () => comboForm.reset() });
    }

    function removeComboRule(id) {
        router.delete(`${base}/combo-rules/${id}`, { preserveScroll: true });
    }

    function addGradeConfig() {
        gradeForm.post(`${base}/grade-configs`, { preserveScroll: true, onSuccess: () => gradeForm.reset({ grade: 'A' }) });
    }

    function removeGradeConfig(id) {
        router.delete(`${base}/grade-configs/${id}`, { preserveScroll: true });
    }

    function addPointRule() {
        pointForm.post(`${base}/point-rules`, { preserveScroll: true, onSuccess: () => pointForm.reset() });
    }

    function removePointRule(id) {
        router.delete(`${base}/point-rules/${id}`, { preserveScroll: true });
    }

    function rankLabel(rank) {
        const labels = { 1: '1st', 2: '2nd', 3: '3rd' };
        return labels[rank] ?? `#${rank}`;
    }

    function addRankRow() {
        const next = (rankRows.value.at(-1)?.rank ?? 0) + 1;
        rankRows.value.push({ _key: `new-${Date.now()}`, rank: next, points: 0 });
    }

    function removeRankRow(index) {
        rankRows.value.splice(index, 1);
    }

    function addGroupRankRow() {
        const next = (groupRankRows.value.at(-1)?.rank ?? 0) + 1;
        groupRankRows.value.push({ _key: `g-${Date.now()}`, rank: next, points: 0 });
    }

    function saveRankPoints(isGroup = false) {
        const rows = isGroup ? groupRankRows.value : rankRows.value;
        const loading = isGroup ? savingGroupRanks : savingRanks;
        loading.value = true;
        router.put(`${base}/rank-points`, {
            ranks: rows.map((row) => ({ rank: row.rank, points: row.points, is_group: isGroup })),
            is_group: isGroup,
        }, {
            preserveScroll: true,
            onFinish: () => { loading.value = false; },
        });
    }

    function seedAthletics() {
        seedingRanks.value = true;
        router.post(`${base}/rank-points/seed-athletics`, {}, {
            preserveScroll: true,
            onFinish: () => { seedingRanks.value = false; },
        });
    }

    function addVolunteer() {
        volunteerForm.post(`${base}/volunteers`, { preserveScroll: true, onSuccess: () => volunteerForm.reset() });
    }

    function removeVolunteer(id) {
        router.delete(`${base}/volunteers/${id}`, { preserveScroll: true });
    }

    function cloneEvent() {
        cloneForm.post(`${base}/clone`);
    }

    return {
        base,
        settingsDescription,
        activeTab,
        policyForm,
        lifecycleLinks,
        settingsForm,
        venueForm,
        stageForm,
        comboForm,
        gradeForm,
        pointForm,
        volunteerForm,
        cloneForm,
        eligibilityForm,
        lifecycleForm,
        registrationSettingsForm,
        numberingSettingsForm,
        itemNumberingForm,
        feeSettingsForm,
        effectiveClassGroupLabels,
        ageRuleSummary: computed(() => props.ageRuleSummary ?? ''),
        suggestedAgeCutoff: computed(() => props.suggestedAgeCutoff ?? ''),
        defaultCutoffLabel: computed(() => props.defaultCutoffLabel ?? ''),
        ageGroupHelp: computed(() => props.ageGroupHelp ?? []),
        savePolicy,
        saveSettings,
        saveFeeSettings,
        saveEligibility,
        saveLifecycle,
        saveRegistrationSettings,
        saveItemWindow,
        saveHeadWindow,
        saveNumberingSettings,
        saveItemNumbering,
        backfillRegs,
        addVenue,
        removeVenue,
        addStage,
        removeStage,
        addComboRule,
        removeComboRule,
        addGradeConfig,
        removeGradeConfig,
        addPointRule,
        removePointRule,
        rankRows,
        groupRankRows,
        saveRankPoints,
        seedAthletics,
        addRankRow,
        removeRankRow,
        addGroupRankRow,
        rankLabel,
        savingRanks,
        savingGroupRanks,
        seeding: seedingRanks,
        addVolunteer,
        removeVolunteer,
        cloneEvent,
    };
}
