import { computed, reactive } from 'vue';

const GROUP_PARTICIPANT_TYPES = ['team', 'group', 'pair', 'trio'];

export function useFestMarkEntryDisplay(props, isSportsParam = null) {
    const isSports = isSportsParam ?? computed(() => props.event?.event_type === 'sports');
    const bulkRank = reactive({});

    // Team/group items: the mark applies to the whole squad, so show one
    // row per team (not one per member) — saving it fans out to every
    // member on the backend (see FestMarkEntryController::expandToTeam()).
    function markableParticipants(reg) {
        const list = reg.participants ?? [];
        const performers = list.filter((p) => p.participant_role !== 'standby');
        const source = performers.length ? performers : list;

        const isGroupItem = GROUP_PARTICIPANT_TYPES.includes(reg.item?.participant_type);
        if (!isGroupItem) {
            return source;
        }

        const byKey = new Map();
        for (const p of source) {
            const key = p.group_id ?? `solo-${p.id}`;
            const existing = byKey.get(key);
            if (existing) {
                existing._member_count += 1;
                continue;
            }
            byKey.set(key, {
                ...p,
                chest_no: isSports.value ? source.filter(m => m.group_id === p.group_id).map(m => m.chest_no).filter(n => n != null).filter((n, i, a) => a.indexOf(n) === i).join(', ') || null : (p.group?.chest_no ?? p.chest_no),
                order_no: p.group?.order_no ?? p.order_no,
                _is_team: Boolean(p.group_id),
                _member_count: 1,
                _team_name: p.group?.team_name || 'Team',
            });
        }

        return [...byKey.values()];
    }

    const sections = computed(() => {
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
                    rows: [],
                    bulkKey: itemId,
                });
            }

            for (const participant of markableParticipants(reg)) {
                byItem.get(itemId).rows.push({
                    participant,
                    item: reg.item,
                    schoolName: reg.school?.name ?? '—',
                });
            }
        }

        const sectionList = [...byItem.values()];
        for (const sec of sectionList) {
            sec.rows.sort((a, b) => {
                // Order No (set from this page, unique per item) takes priority over
                // chest No whenever both rows have one — that's the whole point of
                // assigning it. Falls through to the existing chest-no ordering when
                // either side has no order yet, so an item with only some rows numbered
                // still shows a sane order instead of shuffling unrelated rows around.
                const orderA = a.participant?.order_no;
                const orderB = b.participant?.order_no;
                const hasOrderA = orderA !== null && orderA !== undefined;
                const hasOrderB = orderB !== null && orderB !== undefined;

                if (hasOrderA && hasOrderB && orderA !== orderB) return orderA - orderB;
                if (hasOrderA && !hasOrderB) return -1;
                if (!hasOrderA && hasOrderB) return 1;

                const chestAStr = String((isSports.value ? a.participant?.chest_no : (a.participant?.group?.chest_no ?? a.participant?.chest_no)) ?? '');
                const chestBStr = String((isSports.value ? b.participant?.chest_no : (b.participant?.group?.chest_no ?? b.participant?.chest_no)) ?? '');
                const numA = parseInt(chestAStr.replace(/\D/g, ''), 10);
                const numB = parseInt(chestBStr.replace(/\D/g, ''), 10);
                const validA = !isNaN(numA);
                const validB = !isNaN(numB);

                if (validA && validB) return numA - numB;
                if (validA) return -1;
                if (validB) return 1;
                return (a.participant?.id ?? 0) - (b.participant?.id ?? 0);
            });
        }

        return sectionList;
    });

    function attendanceKey(participant, item) {
        return `${item.id}-${participant.id}`;
    }

    function attendanceStatus(participant, item) {
        // Unmarked shows as "Present" by default — most participants who show up never
        // get an explicit attendance click at all (only the exceptional absentee does),
        // so defaulting the display to present matches the common case instead of
        // leaving every un-clicked row looking unmarked. This is display-only: it
        // doesn't write an attendance record — markAttendance() (triggered by actually
        // changing the dropdown) is still the only thing that persists one.
        return props.attendance?.[attendanceKey(participant, item)]?.status ?? 'present';
    }

    function isAbsent(participant, item) {
        return attendanceStatus(participant, item) === 'absent';
    }

    // Driven by the item's own result_method (rank / time / distance / ...), not just
    // "is this a sports event". Falls back to the old behavior (measurement column for
    // any sports item) only when the item has no result_method set yet.
    function resultMethod(item) {
        return (item?.result_method || '').toLowerCase();
    }

    function showMeasurement(item) {
        const method = resultMethod(item);

        if (method === 'rank') return false;

        if (method === 'time' || method === 'distance') {
            return true;
        }

        if (isSports.value) {
            return !method || method === 'marks' || method === 'points';
        }

        return props.event.record_tracking_enabled
            && (item?.category === 'sports' || item?.sport_discipline);
    }

    // Items whose result is fixed at the table, not by measurement — the rank column is
    // the only input that matters there, and it can't be auto-ranked from a time/distance.
    function rankEntryOnly(item) {
        return resultMethod(item) === 'rank';
    }

    function showRankColumn(item) {
        return true;
    }

    function directionLabel(item) {
        const method = resultMethod(item);
        const dir = (item?.ranking_direction || '').toLowerCase();

        if (['time', 'distance'].includes(method) && (dir === 'asc' || (!dir && method === 'time'))) {
            return 'lower is better';
        }
        if (['time', 'distance'].includes(method) && (dir === 'desc' || (!dir && method === 'distance'))) {
            return 'higher is better';
        }
        if (method === 'rank') {
            return 'rank only — enter rank directly, no auto-rank';
        }

        return '';
    }

    function measurementUnitHint(item) {
        switch (resultMethod(item)) {
            case 'time': return 's';
            case 'distance': return 'm';
            default: return 's/m';
        }
    }

    function pointsForRank(rank, item) {
        if (!rank || rank < 1) {
            return null;
        }

        // rankPointsByType is pre-resolved server-side per participant_type (including
        // configured individual-template inheritance) — see
        // FestRankPointService::rowsForAllTypes(). No fallback logic needed here.
        const rows = props.rankPointsByType?.[item?.participant_type] ?? [];
        const row = rows.find((r) => r.rank === rank);

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

        const rows = props.rankPointsByType?.[item?.participant_type] ?? [];

        const ranks = [...new Set([1, 2, 3, ...rows.map(row => row.rank)])];
        return ranks.sort((a, b) => a - b).map(rank => ({
            rank,
            label: rankLabel(rank),
            points: rows.find(row => row.rank === rank)?.points ?? null,
        }));
    }

    function setRank(participantId, item, markForms, rawValue) {
        const form = markForms[participantId];
        if (!form) {
            return;
        }

        form.position = rawValue === '' || rawValue == null ? null : Number(rawValue);
    }

    function displayTeamPts(participantId, item, markForms) {
        const form = markForms[participantId];
        if (!form?.position) {
            return null;
        }

        return pointsForRank(form.position, item);
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
            }
        }
    }

    function buildMarkPayload(participant, item, markForms) {
        const form = markForms[participant.id];

        const payload = {
            participant_id: participant.id,
            item_id: item.id,
            position: form.position ?? null,
            measurement_value: rankEntryOnly(item) ? null : form.measurement_value || null,
            measurement_unit: rankEntryOnly(item) ? null : form.measurement_unit || null,
        };

        if (!isSports.value) {
            payload.grade = rankEntryOnly(item) ? null : form.grade || null;
            payload.score = rankEntryOnly(item) ? null : form.score ?? null;
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
        rankEntryOnly,
        showRankColumn,
        directionLabel,
        measurementUnitHint,
        resultMethod,
        rankLabel,
        rankOptionsForItem,
        setRank,
        pointsForRank,
        displayTeamPts,
        applyBulkRank,
        buildMarkPayload,
        iterSaveRows,
    };
}
