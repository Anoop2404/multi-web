<template>
<!-- Signatories: who signs changes with each host venue, so it is set per event. The
         label ties each entry to a "Signature block" on the certificate template, which
         decides where its signature image / name / designation / school are printed. -->
    <div class="card !p-3 mb-6 text-xs">
        <div class="flex items-center justify-between mb-2">
            <span class="font-semibold text-gray-700">✍️ Signatories</span>
            <div class="flex gap-2">
                <button type="button" class="btn-secondary py-1 px-3 text-xs" :disabled="signatories.length >= 12" @click="addSignatory">+ Add signatory</button>
                <button type="button" class="btn-secondary py-1 px-3 text-xs" :disabled="signatoriesSaving" @click="saveSignatories">
                    {{ signatoriesSaving ? 'Saving…' : 'Save signatories' }}
                </button>
            </div>
        </div>

        <datalist id="signatory-labels">
            <option v-for="l in labelSuggestions" :key="l" :value="l" />
        </datalist>

        <div v-for="(sg, i) in signatories" :key="sg.uid" class="flex flex-wrap items-end gap-3 py-2 border-t border-gray-100">
            <label class="block">
                <span class="block text-[10px] uppercase tracking-wide text-gray-500 mb-0.5">Label (matches template block)</span>
                <input v-model="sg.label" list="signatory-labels" type="text" maxlength="80" class="field text-xs py-1 px-2 w-48" placeholder="e.g. Venue Convenor">
            </label>
            <label class="block">
                <span class="block text-[10px] uppercase tracking-wide text-gray-500 mb-0.5">Name</span>
                <input v-model="sg.name" type="text" maxlength="120" class="field text-xs py-1 px-2 w-48" placeholder="e.g. Mr. Manoj V.M.">
            </label>
            <label class="block">
                <span class="block text-[10px] uppercase tracking-wide text-gray-500 mb-0.5">Designation (optional)</span>
                <input v-model="sg.designation" type="text" maxlength="120" class="field text-xs py-1 px-2 w-52" placeholder="blank = template's / none">
            </label>
            <label class="block">
                <span class="block text-[10px] uppercase tracking-wide text-gray-500 mb-0.5">School name</span>
                <input v-model="sg.school" type="text" maxlength="160" class="field text-xs py-1 px-2 w-56" placeholder="e.g. St. Alphonsa Public School">
            </label>
            <div class="block">
                <span class="block text-[10px] uppercase tracking-wide text-gray-500 mb-0.5">Signature image</span>
                <input type="file" accept="image/*" class="text-xs w-48" @change="onSignatureFile(sg, $event)">
            </div>
            <img v-if="sg.preview || (sg.signature_url && !sg.remove_signature)" :src="sg.preview || sg.signature_url" alt="Signature" class="h-10 border rounded bg-white p-0.5">
            <label v-if="sg.signature_url" class="flex items-center gap-1 text-gray-600 pb-1">
                <input v-model="sg.remove_signature" type="checkbox"> Remove image
            </label>
            <button type="button" class="text-rose-600 font-semibold pb-1" @click="signatories.splice(i, 1)">Remove</button>
        </div>

        <p v-if="errorMessage" class="text-rose-600 font-semibold py-1">{{ errorMessage }}</p>
        <p v-if="!signatories.length" class="text-gray-400 py-1">No signatories yet. Click "+ Add signatory", type who signs, choose the signature image, then Save.</p>
        <p class="text-gray-400 mt-2">
            Optional per-event override: whatever you fill in here replaces the default name, designation, school and signature image typed on the template's "Signature block" with the same label (Certificate templates &rarr; Signature blocks). Leave a field blank to keep the template's. Signature: PNG with a transparent background works best, max 1 MB.
        </p>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    base: { type: String, required: true },
    initialSignatories: { type: Array, default: () => [] },
    labelSuggestions: { type: Array, default: () => [] },
});

const page = usePage();
const errorMessage = computed(() => {
    const errs = page.props.errors || {};
    const key = Object.keys(errs).find((k) => k === 'signatories' || k.startsWith('signatories.'));
    if (!key) return '';
    return key.endsWith('.label') ? 'Every signatory needs a label (it links the signatory to the template block) — nothing was saved.' : errs[key];
});

let signatoryUid = 0;
const newSignatory = (src = {}) => ({
    uid: ++signatoryUid,
    label: src.label ?? '',
    name: src.name ?? '',
    designation: src.designation ?? '',
    school: src.school ?? '',
    signature_path: src.signature_path ?? null,
    signature_url: src.signature_url ?? null,
    signature: null,
    preview: null,
    remove_signature: false,
});
const signatories = ref(props.initialSignatories.map(newSignatory));
const signatoriesSaving = ref(false);

watch(() => props.initialSignatories, (rows) => { signatories.value = rows.map(newSignatory); });

function addSignatory() {
    signatories.value.push(newSignatory({ label: signatories.value.length === 0 ? 'Venue Convenor' : '' }));
}

function onSignatureFile(sg, e) {
    const file = e.target.files?.[0] ?? null;
    sg.signature = file;
    sg.remove_signature = false;
    sg.preview = file ? URL.createObjectURL(file) : null;
}

function saveSignatories() {
    signatoriesSaving.value = true;
    router.post(`${props.base}/signatories`, {
        signatories: signatories.value
            .map((sg) => ({
                label: sg.label,
                name: sg.name || null,
                designation: sg.designation || null,
                school: sg.school || null,
                signature_path: sg.signature_path,
                remove_signature: sg.remove_signature ? 1 : 0,
                ...(sg.signature ? { signature: sg.signature } : {}),
            })),
    }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => { signatoriesSaving.value = false; },
    });
}

</script>
