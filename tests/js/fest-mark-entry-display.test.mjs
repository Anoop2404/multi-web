import test from 'node:test';
import assert from 'node:assert/strict';
import { reactive } from 'vue';
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


test('saved order numbers rearrange rows ahead of unnumbered participants', () => {
    const props = reactive({ event: { event_type: 'kalolsavam' }, registrations: [
        { item: { id: 1 }, participants: [
            { id: 11, chest_no: '500', order_no: null },
            { id: 12, chest_no: '501', order_no: null },
            { id: 13, chest_no: '537', order_no: null },
        ] },
    ] });
    const view = useFestMarkEntryDisplay(props);
    const ids = () => view.sections.value[0].rows.map(row => row.participant.id);
    assert.deepEqual(ids(), [11, 12, 13]);
    // Simulate refreshed registrations after the server accepts the last row's order.
    props.registrations[0].participants[2].order_no = 1;
    assert.deepEqual(ids(), [13, 11, 12]);
    props.registrations[0].participants[0].order_no = 2;
    assert.deepEqual(ids(), [13, 11, 12]);
    props.registrations[0].participants[2].order_no = null;
    assert.deepEqual(ids(), [11, 12, 13]);
});


test('sports relay rows show personal member chests while arts keeps a team chest', () => {
    const registration = { item: { id: 1, participant_type: 'group' }, participants: [
        { id: 1, group_id: 9, participant_role: 'performer', chest_no: 2351, group: { chest_no: 2350 } },
        { id: 2, group_id: 9, participant_role: 'performer', chest_no: 2352, group: { chest_no: 2350 } },
        { id: 3, group_id: 9, participant_role: 'standby', chest_no: null, group: { chest_no: 2350 } },
    ] };
    const sports = useFestMarkEntryDisplay({ event: { event_type: 'sports' }, registrations: [registration] });
    assert.equal(sports.sections.value[0].rows.length, 1);
    assert.equal(sports.sections.value[0].rows[0].participant.chest_no, '2351, 2352');
    const arts = useFestMarkEntryDisplay({ event: { event_type: 'kalolsavam' }, registrations: [registration] });
    assert.equal(arts.sections.value[0].rows[0].participant.chest_no, 2350);
});
