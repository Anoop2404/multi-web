import { computed, reactive } from 'vue';

export function useFestMarkEntryDisplay(props, isSports) {
    const bulkRank = reactive({});

    function markableParticipants(reg) {
        const list = reg.participants ?? [];
        const performers = list.filter((p) => p.participant_role !== 'standby');

        return performers.length ? performers : list;
    }

    const sections = computed(() => {
        if (!isSports.value) {
            return (props.registrations ?? []).map((reg) => ({
                key: `reg-${reg.id}`,
                item: reg.item,
                schoolName: reg.school?.name ?? null,
                rows: markableParticipants(reg).map((participant) => ({
                    participant,
                    item: reg.item,
                })),
                bulkKey: reg.id,
            }));
        }

        const byItem = new Map();

        for (const reg of props.registrations ?? []) {
            const itemId = reg.item?.id;
            if (!itemId) {
                continue;
            }

            if (!byItem.has(itemId)) {
                byItem.set(itemId, {
                    key: `item-${itemId}`,
                    item: reg.item,
                    schoolName: null,
                    rows: [],
                    bulkKey: itemId,
                });
            }

            for (const participant of markableParticipants(reg)) {
                byItem.get(itemId).rows.push({
                    participant,
                    item: reg.item,
                });
            }
        }

        return [...byItem.values()];
    });

    function attendanceKey(participant, item) {
        return `${item.id}-${participant.id}`;
    }

    function attendanceStatus(participant, item) {
        return props.attendance?.[attendanceKey(participant, item)]?.status ?? '';
    }

    function isAbsent(participant, item) {
        return attendanceStatus(participant, item) === 'absent';
    }

    function showMeasurement(item) {
        if (isSports.value) {
            return true;
        }

        return props.event.record_tracking_enabled
            && (item?.category === 'sports' || item?.sport_discipline);
    }

    function pointsForRank(rank, item) {
        if (!rank || rank < 1) {
            return null;
        }

        const isGroup = ['group', 'team'].includes(item?.participant_type);
        const row = props.rankPoints.find((r) => r.rank === rank && Boolean(r.is_group) === isGroup)
            ?? props.rankPoints.find((r) => r.rank === rank && !r.is_group);

        return row?.points ?? null;
    }

    function rankLabel(rank) {
        const labels = { 1: '1st', 2: '2nd', 3: '3rd' };
        return labels[rank] ?? `#${rank}`;
    }

    /** @returns {Array<{ rank: number, label: string, points: number }>} */
    function rankOptionsForItem(item) {
        if (!isSports.value) {
            return [];
        }

        const isGroup = ['group', 'team'].includes(item?.participant_type);
        let rows = props.rankPoints.filter((r) => Boolean(r.is_group) === isGroup);

        if (!rows.length) {
            rows = props.rankPoints.filter((r) => !r.is_group);
        }

        return rows
            .slice()
            .sort((a, b) => a.rank - b.rank)
            .map((r) => ({
                rank: r.rank,
                label: rankLabel(r.rank),
                points: r.points,
            }));
    }

    function setRank(participantId, item, markForms, rawValue) {
        const form = markForms[participantId];
        if (!form) {
            return;
        }

        form.position = rawValue === '' || rawValue == null ? null : Number(rawValue);
        applyRankPoints(participantId, item, markForms);
    }

    function displayTeamPts(participantId, item, markForms) {
        const form = markForms[participantId];
        if (!form?.position) {
            return null;
        }

        return pointsForRank(form.position, item);
    }

    function applyRankPoints(participantId, item, markForms) {
        if (!isSports.value) {
            return;
        }

        const form = markForms[participantId];
        if (!form) {
            return;
        }

        const pts = pointsForRank(form.position, item);
        if (pts != null) {
            form.score = pts;
        }
    }

    function applyBulkRank(section, markForms) {
        const rank = bulkRank[section.bulkKey];
        if (!rank || rank < 1) {
            return;
        }

        for (const { participant, item } of section.rows) {
            if (isAbsent(participant, item)) {
                continue;
            }

            if (markForms[participant.id]) {
                markForms[participant.id].position = rank;
                applyRankPoints(participant.id, item, markForms);
            }
        }
    }

    function buildMarkPayload(participant, item, markForms) {
        const form = markForms[participant.id];

        const payload = {
            participant_id: participant.id,
            item_id: item.id,
            position: form.position ?? null,
            measurement_value: form.measurement_value || null,
            measurement_unit: form.measurement_unit || null,
        };

        if (!isSports.value) {
            payload.grade = form.grade || null;
            payload.score = form.score ?? null;
        }

        return payload;
    }

    function iterSaveRows() {
        const pairs = [];

        for (const section of sections.value) {
            for (const { participant, item } of section.rows) {
                if (isSports.value && isAbsent(participant, item)) {
                    continue;
                }

                if (item?.id) {
                    pairs.push({ participant, item });
                }
            }
        }

        return pairs;
    }

    return {
        bulkRank,
        sections,
        markableParticipants,
        attendanceKey,
        attendanceStatus,
        isAbsent,
        showMeasurement,
        rankLabel,
        rankOptionsForItem,
        setRank,
        pointsForRank,
        displayTeamPts,
        applyRankPoints,
        applyBulkRank,
        buildMarkPayload,
        iterSaveRows,
    };
}
