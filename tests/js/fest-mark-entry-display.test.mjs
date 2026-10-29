import test from 'node:test';
import assert from 'node:assert/strict';
import { useFestMarkEntryDisplay } from '../../resources/js/composables/useFestMarkEntryDisplay.js';

const display = event => useFestMarkEntryDisplay({ event, registrations: [] });

test('ordinary non-sports items retain manual rank entry', () => {
    assert.equal(display({ event_type: 'kalolsavam' }).showRankColumn({ result_method: 'marks' }), true);
});

test('rank-only items never show measurement fields, including record-tracked events', () => {
    for (const event_type of ['sports', 'kalolsavam']) {
        assert.equal(display({ event_type, record_tracking_enabled: true }).showMeasurement({ result_method: 'rank', category: 'sports' }), false);
    }
});

test('rank-only save does not resend stale scores or measurements', () => {
    const payload = display({ event_type: 'kalolsavam' }).buildMarkPayload({ id: 1 }, { id: 2, result_method: 'rank' }, {
        1: { position: 2, score: 95, grade: 'A', measurement_value: '4.2', measurement_unit: 'm' },
    });
    assert.deepEqual(payload, { participant_id: 1, item_id: 2, position: 2, score: null, grade: null, measurement_value: null, measurement_unit: null });
});

test('time and distance show the default winning direction without an explicit override', () => {
    const view = display({ event_type: 'sports' });
    assert.equal(view.directionLabel({ result_method: 'time' }), 'lower is better');
    assert.equal(view.directionLabel({ result_method: 'distance' }), 'higher is better');
    assert.equal(view.directionLabel({ result_method: 'time', ranking_direction: 'desc' }), 'higher is better');
});

test('participants with separate registrations for one item share one section', () => {
    const view = useFestMarkEntryDisplay({ event: { event_type: 'sports' }, registrations: [
        { item: { id: 1 }, participants: [{ id: 11, participant_role: 'performer' }] },
        { item: { id: 1 }, participants: [{ id: 12, participant_role: 'performer' }] },
    ] });
    assert.equal(view.sections.value.length, 1);
    assert.equal(view.sections.value[0].rows.length, 2);
});


test('mark entry can keep chest order stable while assigning performance order', () => {
    const props = { event: { event_type: 'kalolsavam' }, registrations: [
        { item: { id: 1 }, participants: [
            { id: 11, chest_no: '500', order_no: 2 },
            { id: 12, chest_no: '501', order_no: 1 },
        ] },
    ] };
    const stable = useFestMarkEntryDisplay(props, null, { sortByOrder: false });
    assert.deepEqual(stable.sections.value[0].rows.map(row => row.participant.id), [11, 12]);
    const ordered = useFestMarkEntryDisplay(props);
    assert.deepEqual(ordered.sections.value[0].rows.map(row => row.participant.id), [12, 11]);
});
