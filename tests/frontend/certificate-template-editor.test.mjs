import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/Pages/Admin/Sahodaya/Certificates/Templates.vue', import.meta.url), 'utf8');
const layoutSource = source.slice(source.indexOf('function textFieldDefaults('), source.indexOf('const form = useForm('));
const uploadSource = source.slice(source.indexOf('function upload()'), source.indexOf('async function remove('));

test('loading a partial custom field preserves saved layout and false toggles', () => {
    const ctx = vm.createContext({ props: { defaultLayout: {} } });
    vm.runInContext(layoutSource, ctx);
    const saved = { show_photo: false, show_certificate_date: false, body: { top: 43, left: 12, width: 68, bottom: 70, font_size: 22 }, custom_fields: [{ text: '{recipient_name}', top: 40 }] };
    const loaded = ctx.layoutDefaults(saved);
    assert.equal(loaded.body.font_size, 22);
    assert.equal(loaded.body.bottom, 70);
    assert.equal(loaded.show_photo, false);
    assert.equal(loaded.show_certificate_date, false);
    assert.equal(loaded.custom_fields[0].top, 40);
    loaded.custom_fields[0].top = 60;
    assert.equal(saved.custom_fields[0].top, 40);
});

test('saving an edit reloads the saved row instead of resetting to create defaults', () => {
    let options;
    let reopened;
    const saved = { id: 23, layout_json: { body: { font_size: 22 } } };
    const form = { reset() {}, signatories: [], layout_json: { signature_blocks: [] }, transform() { return this; }, post(url, opts) { options = opts; } };
    const ctx = vm.createContext({ form, props: { sahodaya: { id: 'tenant' } }, editingId: { value: 23 }, editTemplate: row => { reopened = row; }, cancelEdit: () => { throw Error('Unexpected reset'); } });
    vm.runInContext(uploadSource, ctx);
    ctx.upload();
    options.onSuccess({ props: { templates: [saved] } });
    assert.equal(reopened, saved);
});

test('creating after editing clears the previous PUT transform', () => {
    let transform;
    const form = { transform(fn) { transform = fn; return this; }, post() {} };
    const ctx = vm.createContext({ form, props: { sahodaya: { id: 'tenant' } }, editingId: { value: null } });
    vm.runInContext(uploadSource, ctx);
    ctx.upload();
    assert.deepEqual(transform({ title: 'New template' }), { title: 'New template' });
});

test('the separate edit page loads its requested template on entry', () => {
    let opened;
    let callback;
    const row = { id: 24, body: 'Saved certificate body' };
    const start = source.indexOf('watch(() => props.editTemplateId');
    const watcherSource = source.slice(start, source.indexOf('function upload()', start));
    const ctx = vm.createContext({
        props: { editTemplateId: 24, templates: [row] },
        editingId: { value: null },
        editTemplate: template => { opened = template; },
        cancelEdit: () => { opened = null; },
        watch: (getter, handler, options) => {
            callback = handler;
            if (options.immediate) handler(getter());
        },
    });
    vm.runInContext(watcherSource, ctx);
    assert.equal(opened, row);
    ctx.editingId.value = 24;
    callback(null);
    assert.equal(opened, null);
});

test('cancel on the edit page navigates back to the template list', () => {
    let destination;
    const start = source.indexOf('function cancelEdit()');
    const cancelSource = source.slice(start, source.indexOf('watch(() => props.editTemplateId', start));
    const ctx = vm.createContext({ props: { editTemplateId: 24, sahodaya: { id: 'tenant' } }, router: { get: url => { destination = url; } } });
    vm.runInContext(cancelSource, ctx);
    ctx.cancelEdit();
    assert.equal(destination, '/sahodaya-admin/tenant/certificate-templates');
});
