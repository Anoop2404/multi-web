import test from 'node:test';
import assert from 'node:assert/strict';
import { mergeWindowRows } from '../../resources/js/support/eventWindowSettings.js';

test('saving one row preserves unrelated date edits and accepts server changes for untouched fields', () => {
    const baseline = [{ id: 1, reg_start: '', reg_end: '' }, { id: 2, reg_start: '', reg_end: '' }];
    const current = [{ id: 1, reg_start: '2026-11-01', reg_end: '' }, { id: 2, reg_start: '2026-11-02', reg_end: '' }];
    const incoming = [{ id: 1, reg_start: '2026-11-01', reg_end: '2026-11-05' }, { id: 2, reg_start: '', reg_end: '' }];
    const result = mergeWindowRows(current, baseline, incoming);
    assert.equal(result[0].reg_start, '2026-11-01');
    assert.equal(result[0].reg_end, '2026-11-05');
    assert.equal(result[1].reg_start, '2026-11-02');
});

test('a saved value becomes clean on the next refresh and removed rows are not retained', () => {
    const previous = [{ id: 1, reg_start: '2026-11-01' }, { id: 2, reg_start: '' }];
    const result = mergeWindowRows(previous, previous, [{ id: 1, reg_start: '2026-11-03' }, { id: 3, reg_start: '' }]);
    assert.deepEqual(result.map(row => row.id), [1, 3]);
    assert.equal(result[0].reg_start, '2026-11-03');
});
