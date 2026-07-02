#!/usr/bin/env node
/**
 * Bulk-migrate admin Vue pages to the premium design system tokens.
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pagesDir = path.join(__dirname, '../resources/js/Pages/Admin');

function walk(dir, files = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full, files);
        else if (entry.name.endsWith('.vue')) files.push(full);
    }
    return files;
}

const skipFiles = ['/Auth/Login.vue', '/Auth/SuperadminLogin.vue'];

const regexReplacements = [
    // Buttons — static classes
    [/class="([^"]*\b)?bg-indigo-600[^"]*"/g, (m) => m.replace(/\bbg-indigo-600\b/g, '').replace(/\bhover:bg-indigo-700\b/g, '').replace(/\s+/g, ' ').replace(/class="\s+/, 'class="btn-primary ').replace(/class="btn-primary\s+"/, 'class="btn-primary"')],
    [/class="([^"]*\b)?bg-blue-600[^"]*text-white[^"]*"/g, (m) => {
        let c = m.slice(7, -1);
        c = c.replace(/\bbg-blue-600\b/g, '').replace(/\bhover:bg-blue-700\b/g, '').replace(/\btext-white\b/g, '').replace(/\s+/g, ' ').trim();
        return c ? `class="btn-primary ${c}"` : 'class="btn-primary"';
    }],
    [/class="([^"]*\b)?bg-purple-600[^"]*text-white[^"]*"/g, (m) => {
        let c = m.slice(7, -1);
        c = c.replace(/\bbg-purple-600\b/g, '').replace(/\bhover:bg-purple-700\b/g, '').replace(/\btext-white\b/g, '').replace(/\s+/g, ' ').trim();
        return c ? `class="btn-primary ${c}"` : 'class="btn-primary"';
    }],
    [/class="([^"]*\b)?bg-green-600[^"]*text-white[^"]*"/g, (m) => {
        let c = m.slice(7, -1);
        c = c.replace(/\bbg-green-600\b/g, '').replace(/\bhover:bg-green-700\b/g, '').replace(/\btext-white\b/g, '').replace(/\s+/g, ' ').trim();
        return c ? `class="btn-primary ${c}"` : 'class="btn-primary"';
    }],
    [/class="([^"]*\b)?bg-emerald-600[^"]*text-white[^"]*"/g, (m) => {
        let c = m.slice(7, -1);
        c = c.replace(/\bbg-emerald-600\b/g, '').replace(/\btext-white\b/g, '').replace(/\s+/g, ' ').trim();
        return c ? `class="btn-primary ${c}"` : 'class="btn-primary"';
    }],
    [/class="([^"]*\b)?bg-gray-900[^"]*text-white[^"]*"/g, (m) => {
        let c = m.slice(7, -1);
        c = c.replace(/\bbg-gray-900\b/g, '').replace(/\btext-white\b/g, '').replace(/\s+/g, ' ').trim();
        return c ? `class="btn-primary ${c}"` : 'class="btn-primary"';
    }],
    [/class="([^"]*\b)?bg-\[#0f3d7a\][^"]*text-white[^"]*"/g, (m) => {
        let c = m.slice(7, -1);
        c = c.replace(/\bbg-\[#0f3d7a\]\b/g, '').replace(/\bhover:bg-\[#1a4f8c\]\b/g, '').replace(/\btext-white\b/g, '').replace(/\s+/g, ' ').trim();
        return c ? `class="btn-primary ${c}"` : 'class="btn-primary"';
    }],

    // Dynamic :class tab/filter patterns (safe — only full ternary strings)
    [/activeTab === tab\.id \? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'/g,
        "activeTab === tab.id ? 'tab-btn--active' : 'tab-btn'"],
    [/modal\.editMode === 'form' \? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600'/g,
        "modal.editMode === 'form' ? 'tab-btn--active' : 'tab-btn'"],
    [/modal\.editMode === 'json' \? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600'/g,
        "modal.editMode === 'json' ? 'tab-btn--active' : 'tab-btn'"],
    [/link\.active \? 'bg-\[#0f3d7a\] text-white' : 'text-gray-600 hover:bg-gray-100'/g,
        "link.active ? 'pagination-link--active' : 'pagination-link'"],
    [/school\.fest_registration_closed \? 'bg-emerald-600 text-white' : 'bg-red-50 text-red-700 border border-red-200'/g,
        "school.fest_registration_closed ? 'btn-primary' : 'btn-secondary text-red-700 border-red-200 bg-red-50'"],

    // Cards
    [/class="bg-white border rounded-xl divide-y"/g, 'class="card-list"'],
    [/class="bg-white border rounded-xl overflow-hidden"/g, 'class="card card--flush"'],
    [/class="bg-white border rounded-xl p-4 flex gap-3"/g, 'class="card flex gap-3"'],
    [/class="bg-white border rounded-xl p-4 mb-4 flex flex-wrap gap-2"/g, 'class="card mb-4 flex flex-wrap gap-2"'],
    [/class="bg-white border rounded-xl p-4 mb-4 flex flex-wrap gap-2 items-end"/g, 'class="card mb-4 flex flex-wrap gap-2 items-end"'],
    [/class="bg-white border rounded-xl p-4 mb-4 grid sm:grid-cols-2 gap-2"/g, 'class="card mb-4 grid sm:grid-cols-2 gap-2"'],
    [/class="bg-white border rounded-xl p-4 mb-4 grid sm:grid-cols-3 gap-2"/g, 'class="card mb-4 grid sm:grid-cols-3 gap-2"'],
    [/class="bg-white border rounded-xl p-4 mb-4 grid sm:grid-cols-4 gap-2"/g, 'class="card mb-4 grid sm:grid-cols-4 gap-2"'],
    [/class="bg-white border rounded-xl p-4 mb-4 space-y-3"/g, 'class="card mb-4 space-y-3"'],
    [/class="bg-white border rounded-xl p-4 mb-4"/g, 'class="card mb-4"'],
    [/class="bg-white border rounded-xl p-4 space-y-3"/g, 'class="card space-y-3"'],
    [/class="bg-white border rounded-xl p-4 space-y-4"/g, 'class="card space-y-4"'],
    [/class="bg-white border rounded-xl p-4 max-w-xl space-y-3"/g, 'class="card max-w-xl space-y-3"'],
    [/class="bg-white border rounded-xl p-4 max-w-2xl space-y-4"/g, 'class="card max-w-2xl space-y-4"'],
    [/class="bg-white border rounded-xl p-4"/g, 'class="card"'],
    [/class="bg-white border rounded-xl p-6 space-y-4"/g, 'class="card space-y-4"'],
    [/class="bg-white border rounded-xl p-6"/g, 'class="card"'],
    [/class="bg-white border rounded-xl p-4 mb-4 space-y-3"/g, 'class="card mb-4 space-y-3"'],
    [/class="bg-white border rounded-xl p-4 mb-4 flex flex-wrap gap-2 items-end mb-4"/g, 'class="card mb-4 flex flex-wrap gap-2 items-end"'],
    [/class="bg-white border rounded-xl p-4 mb-4 flex flex-wrap gap-2 items-end mb-4" @submit/g, 'class="card mb-4 flex flex-wrap gap-2 items-end" @submit'],
    [/class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5"/g, 'class="card space-y-5"'],
    [/class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4"/g, 'class="card space-y-4"'],
    [/class="bg-white rounded-xl shadow-sm border border-gray-100 p-6"/g, 'class="card"'],
    [/class="bg-white rounded-xl shadow-sm border border-gray-100 p-8"/g, 'class="card"'],
    [/class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"/g, 'class="card card--flush"'],
    [/class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-start justify-between gap-4"/g, 'class="card-list-row justify-between"'],
    [/class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center justify-between gap-4"/g, 'class="card-list-row justify-between"'],
    [/class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center gap-4 hover:shadow-md transition"/g, 'class="card-list-row hover:shadow-md"'],
    [/class="bg-white rounded-xl border border-dashed border-gray-200 p-12 text-center text-gray-400"/g, 'class="card card--dashed p-12 text-center text-slate-400"'],
    [/class="bg-white rounded-xl border border-dashed border-gray-200 p-10 text-center text-gray-400"/g, 'class="card card--dashed p-10 text-center text-slate-400"'],
    [/class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 flex items-center gap-4"/g, 'class="card flex items-center gap-4"'],
    [/class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 space-y-6"/g, 'class="card space-y-6"'],
    [/class="bg-white rounded-xl p-6 shadow-sm border border-gray-100"/g, 'class="card"'],
    [/class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 sticky top-4"/g, 'class="card sticky top-4"'],
    [/class="bg-white rounded-xl p-10 text-center text-gray-400 border border-dashed border-gray-200"/g, 'class="card card--dashed p-10 text-center text-slate-400"'],
    [/class="bg-white rounded-xl border border-gray-100 p-5 text-sm"/g, 'class="card text-sm"'],
    [/class="bg-white border rounded-lg p-3 space-y-2"/g, 'class="card p-3 space-y-2"'],
    [/class="bg-white border rounded-xl p-4 text-center"/g, 'class="card text-center"'],
    [/class="bg-white border rounded-xl p-4 text-sm"/g, 'class="card text-sm"'],
    [/class="bg-white border rounded-xl p-4 flex flex-wrap items-start justify-between gap-3"/g, 'class="card flex flex-wrap items-start justify-between gap-3"'],
    [/class="bg-white border rounded-xl p-4 flex gap-3 transition"/g, 'class="card flex gap-3 transition"'],
    [/class="bg-white border rounded-xl p-4 mb-4"/g, 'class="card mb-4"'],
    [/class="bg-white border rounded-xl p-4 mb-4 grid sm:grid-cols-2 gap-2"/g, 'class="card mb-4 grid sm:grid-cols-2 gap-2"'],
    [/class="bg-white border rounded-xl p-4 mb-4 space-y-3"/g, 'class="card mb-4 space-y-3"'],
    [/class="bg-white border rounded-xl p-4 mb-4 flex flex-wrap gap-2 items-end mb-4"/g, 'class="card mb-4 flex flex-wrap gap-2 items-end"'],

    // Toggle switches → brand navy
    [/peer-checked:bg-purple-600/g, 'peer-checked:bg-[#041525]'],
    [/peer-checked:bg-blue-600/g, 'peer-checked:bg-[#041525]'],
    [/peer-focus:ring-purple-300/g, 'peer-focus:ring-[#041525]/20'],

    // Tab bar base classes
    [/class="\['rounded-xl px-3 py-2 text-sm font-semibold transition',\s*\n\s*activeTab === tab\.id \? 'tab-btn--active' : 'tab-btn'\]/g,
        "class=\"['tab-btn', activeTab === tab.id ? 'tab-btn--active' : '']\""],

    // Chip filter base
    [/class="px-3 py-1\.5 rounded-full text-xs font-medium transition"\s*\n\s*:class="typeFilter === t \? 'chip-tab--active' : 'chip-tab'"/g,
        'class="chip-tab" :class="{ \'chip-tab--active\': typeFilter === t }"'],
    [/class="px-3 py-1\.5 rounded-full text-xs font-medium transition"\s*\n\s*:class="activeTab === tab\.value \? 'chip-tab--active' : 'chip-tab'"/g,
        'class="chip-tab" :class="{ \'chip-tab--active\': activeTab === tab.value }"'],

    // sa-btn leftover
    [/sa-btn-primary/g, 'btn-primary'],

    // Cleanup double btn-primary
    [/class="btn-primary btn-primary"/g, 'class="btn-primary"'],
    [/class="btn-primary  /g, 'class="btn-primary '],
];

const scopedFieldBlock =
    /<style scoped>\s*(?:@reference[^;]+;\s*)?\.field\s*\{[^}]+\}\s*<\/style>\s*\n?/g;

let stats = { files: 0, changed: 0, fieldBlocks: 0, replacements: 0 };

for (const file of walk(pagesDir)) {
    if (skipFiles.some((s) => file.includes(s))) continue;

    let content = fs.readFileSync(file, 'utf8');
    const original = content;

    if (scopedFieldBlock.test(content)) {
        content = content.replace(scopedFieldBlock, '');
        stats.fieldBlocks++;
    }

    for (const [pattern, replacement] of regexReplacements) {
        const before = content;
        content = typeof replacement === 'function'
            ? content.replace(pattern, replacement)
            : content.replace(pattern, replacement);
        if (content !== before) stats.replacements++;
    }

    // Normalize legacy inputs
    content = content.replace(
        /(<(?:input|select|textarea)[^>]*?)class="([^"]*w-full border border-gray-200 rounded-lg[^"]*)"/g,
        (_, tag, cls) => {
            const extras = cls
                .replace(/w-full\s*/g, '')
                .replace(/border-gray-200\s*/g, '')
                .replace(/border\s+border-gray-200\s*/g, '')
                .replace(/rounded-lg\s*/g, '')
                .replace(/rounded-xl\s*/g, '')
                .replace(/px-[234]\s*/g, '')
                .replace(/py-[12](?:\.5)?\s*/g, '')
                .replace(/text-sm\s*/g, '')
                .replace(/text-xs\s*/g, '')
                .replace(/bg-white\s*/g, '')
                .replace(/focus:outline-none\s*/g, '')
                .replace(/focus:ring-2\s*/g, '')
                .replace(/focus:ring-\[[^\]]+\]\/?\d*\s*/g, '')
                .replace(/focus:ring-sky-200\s*/g, '')
                .replace(/focus:ring-purple-200\s*/g, '')
                .replace(/focus:ring-indigo-300\s*/g, '')
                .replace(/resize-none\s*/g, 'resize-none ')
                .trim();
            const merged = extras ? `field ${extras}`.replace(/\s+/g, ' ').trim() : 'field';
            return `${tag}class="${merged}"`;
        },
    );

    if (content !== original) {
        fs.writeFileSync(file, content);
        stats.changed++;
    }
    stats.files++;
}

console.log(JSON.stringify(stats, null, 2));
