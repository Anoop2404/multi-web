import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ageGroupLabel, formatItemEligibility } from '../../resources/js/support/festItemEligibility.js';

test('sports registration displays configured category labels alongside gender', () => {
    const item = { age_group: 'u8', class_group: 'lp', gender: 'male' };
    assert.equal(formatItemEligibility(item, { event_type: 'sports' }, { ageGroupLabels: { u8: 'Under 8 — Mini Boys & Girls' } }), 'Under 8 — Mini Boys & Girls · Boys');
    assert.equal(formatItemEligibility({ ...item, gender: 'female' }, { event_type: 'sports' }, { ageGroupLabels: { u8: 'Mini (Under 8)' } }), 'Mini (Under 8) · Girls');
});

test('sports category fallback displays an age name rather than its key', () => {
    assert.equal(ageGroupLabel('u8'), 'Under 8');
    assert.equal(ageGroupLabel('u17'), 'Under 17');
    assert.equal(ageGroupLabel('custom', { custom: 'Junior Skaters' }), 'Junior Skaters');
    assert.equal(formatItemEligibility({ age_group: 'open', gender: 'male' }, { event_type: 'sports' }), 'Boys');
    assert.equal(formatItemEligibility({ age_group: 'open', gender: 'male' }, { event_type: 'sports' }, { ageGroupLabels: { open: 'All Ages' } }), 'All Ages · Boys');
});

test('class categories keep their configured labels for other event types', () => {
    assert.equal(formatItemEligibility({ class_group: 'lp', gender: 'female' }, { event_type: 'kalolsavam' }, { classGroupLabels: { lp: 'Category 1' } }), 'Category 1 · Girls');
});
