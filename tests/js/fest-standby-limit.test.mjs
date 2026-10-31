import test from 'node:test';
import assert from 'node:assert/strict';
import { standbyLimit } from '../../resources/js/support/festStandbyLimit.js';
test('sports defaults to one standby and respects configured zero or higher limits', () => {
    assert.equal(standbyLimit({}, 'sports'), 1);
    assert.equal(standbyLimit({criteria_json:{standbys:0}}, 'sports'), 0);
    assert.equal(standbyLimit({criteria_json:{standbys:3}}, 'sports'), 3);
    assert.equal(standbyLimit({}, 'kalolsavam'), 2);
});
