import test from 'node:test';
import assert from 'node:assert/strict';
import { sportsBaseRate } from '../../resources/js/support/sportsEventFees.js';

test('sports individual base rate uses the event fee, including an explicit zero', () => {
    assert.deepEqual(sportsBaseRate({ participant_type: 'individual' }, { default_item_fee: 350, student_registration_fee: 500 }), { amount: 350, unit: 'individual entry' });
    assert.equal(sportsBaseRate({}, { default_item_fee: 0, student_registration_fee: 500 }).amount, 0);
    assert.equal(sportsBaseRate({}, { default_item_fee: '', student_registration_fee: 500 }).amount, 500);
});
test('team, pair and trio rates distinguish per-entry billing from performer fallback', () => {
    for (const participant_type of ['team', 'group', 'pair', 'trio']) {
        assert.deepEqual(sportsBaseRate({ participant_type }, { team_registration_fee: 1000, default_item_fee: 350 }), { amount: 1000, unit: 'team entry' });
        assert.deepEqual(sportsBaseRate({ participant_type }, { team_registration_fee: 0, default_item_fee: 350 }), { amount: 350, unit: 'performer' });
    }
});
