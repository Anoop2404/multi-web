<template>
    <div class="space-y-1.5">
        <div v-if="label || $slots.label" class="flex items-center justify-between gap-3">
            <label :for="editorId" class="form-label">
                <slot name="label">{{ label }}</slot>
                <span v-if="required" aria-hidden="true"> *</span>
            </label>
            <span class="text-[11px] text-gray-400">{{ characterCount }} characters</span>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white transition focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100"
             :class="error ? 'border-red-300' : ''">
            <div class="flex flex-wrap items-center gap-1.5 border-b border-gray-200 bg-gray-50 p-1.5" role="toolbar" aria-label="Text formatting">
                <select v-if="!sourceMode" aria-label="Text style" class="h-9 rounded-lg border border-gray-200 bg-white px-2 text-xs font-semibold text-gray-700"
                        @mousedown="saveSelection" @change="formatBlock($event)">
                    <option value="p">Paragraph</option>
                    <option value="h2">Heading 2</option>
                    <option value="h3">Heading 3</option>
                    <option value="h4">Heading 4</option>
                </select>

                <template v-if="!sourceMode">
                    <button type="button" class="tool-button font-bold" title="Bold" aria-label="Bold" @mousedown.prevent="command('bold')">B</button>
                    <button type="button" class="tool-button italic" title="Italic" aria-label="Italic" @mousedown.prevent="command('italic')">I</button>
                    <button type="button" class="tool-button underline" title="Underline" aria-label="Underline" @mousedown.prevent="command('underline')">U</button>
                    <span class="mx-0.5 h-6 w-px bg-gray-200" aria-hidden="true"></span>
                    <button type="button" class="tool-button" title="Bulleted list" aria-label="Bulleted list" @mousedown.prevent="command('insertUnorderedList')">• List</button>
                    <button type="button" class="tool-button" title="Numbered list" aria-label="Numbered list" @mousedown.prevent="command('insertOrderedList')">1. List</button>
                    <button type="button" class="tool-button" title="Quote" aria-label="Quote" @mousedown.prevent="command('formatBlock', 'blockquote')">❝</button>
                    <span class="mx-0.5 h-6 w-px bg-gray-200" aria-hidden="true"></span>
                    <button type="button" class="tool-button" title="Add link" aria-label="Add link" @mousedown.prevent="openLinkEditor">Link</button>
                    <button type="button" class="tool-button" title="Remove link" aria-label="Remove link" @mousedown.prevent="command('unlink')">Unlink</button>
                    <button type="button" class="tool-button" title="Clear formatting" aria-label="Clear formatting" @mousedown.prevent="command('removeFormat')">Clear</button>
                </template>

                <button type="button" class="ml-auto h-9 rounded-lg px-2.5 text-xs font-semibold transition"
                        :class="sourceMode ? 'bg-sky-100 text-sky-800' : 'text-gray-500 hover:bg-white hover:text-gray-800'"
                        :aria-pressed="sourceMode" @click="toggleSource">
                    {{ sourceMode ? 'Visual editor' : 'HTML source' }}
                </button>
            </div>

            <div v-if="showLinkEditor && !sourceMode" class="flex flex-col gap-2 border-b border-gray-200 bg-sky-50/60 p-3 sm:flex-row sm:items-center">
                <label :for="`${editorId}-link`" class="shrink-0 text-xs font-semibold text-gray-700">Link address</label>
                <input :id="`${editorId}-link`" ref="linkInput" v-model="linkUrl" type="text" inputmode="url"
                       placeholder="https://example.com" class="field min-w-0 flex-1 bg-white" @keydown.enter.prevent="applyLink" @keydown.esc.prevent="closeLinkEditor">
                <div class="flex gap-2">
                    <button type="button" class="rounded-lg bg-sky-700 px-3 py-2 text-xs font-semibold text-white hover:bg-sky-800" @click="applyLink">Add link</button>
                    <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50" @click="closeLinkEditor">Cancel</button>
                </div>
            </div>

            <textarea v-if="sourceMode" :id="editorId" :value="modelValue" :required="required"
                      class="block w-full resize-y border-0 bg-white px-4 py-3 font-mono text-sm leading-6 text-gray-800 outline-none"
                      :style="{ minHeight }" spellcheck="false" @input="emitSource($event)" @blur="$emit('blur')"></textarea>
            <div v-else :id="editorId" ref="editor" contenteditable="true" role="textbox" aria-multiline="true"
                 :aria-label="label || 'Rich text editor'" :aria-required="required" :data-placeholder="placeholder"
                 class="rich-editor block w-full overflow-y-auto px-4 py-3 text-sm leading-7 text-gray-800 outline-none"
                 :style="{ minHeight }" @input="emitVisual" @focus="isFocused = true" @blur="handleBlur" @paste="pastePlainText"></div>
        </div>

        <p v-if="help && !error" class="text-xs text-gray-500">{{ help }}</p>
        <p v-if="error" class="text-xs text-red-600" role="alert">{{ error }}</p>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: '' },
    placeholder: { type: String, default: 'Start writing…' },
    help: { type: String, default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    minHeight: { type: String, default: '220px' },
});

const emit = defineEmits(['update:modelValue', 'blur']);
const editor = ref(null);
const linkInput = ref(null);
const sourceMode = ref(false);
const isFocused = ref(false);
const showLinkEditor = ref(false);
const linkUrl = ref('');
const savedRange = ref(null);
const editorId = `rich-editor-${Math.random().toString(36).slice(2, 10)}`;

const characterCount = computed(() => {
    if (typeof document === 'undefined') return props.modelValue.replace(/<[^>]*>/g, '').trim().length;
    const container = document.createElement('div');
    container.innerHTML = props.modelValue;
    return (container.textContent ?? '').trim().length;
});

onMounted(() => setEditorHtml(props.modelValue));

watch(() => props.modelValue, value => {
    if (!sourceMode.value && !isFocused.value && editor.value && editor.value.innerHTML !== value) {
        setEditorHtml(value);
    }
});

function setEditorHtml(value) {
    if (!editor.value) return;
    editor.value.innerHTML = safeEditorHtml(value);
}

function safeEditorHtml(value) {
    const html = String(value ?? '');
    if (!html.includes('<')) {
        return escapeHtml(html).replace(/\r?\n/g, '<br>');
    }

    const parsed = new DOMParser().parseFromString(`<div id="editor-root">${html}</div>`, 'text/html');
    const root = parsed.getElementById('editor-root');
    const allowed = new Set(['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'a', 'h2', 'h3', 'h4', 'blockquote', 'span', 'div', 'table', 'thead', 'tbody', 'tr', 'th', 'td']);
    const dangerous = new Set(['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'link', 'meta', 'form', 'input', 'button']);

    [...root.querySelectorAll('*')].reverse().forEach(node => {
        const tag = node.tagName.toLowerCase();
        if (dangerous.has(tag)) {
            node.remove();
            return;
        }
        if (!allowed.has(tag)) {
            node.replaceWith(...node.childNodes);
            return;
        }
        [...node.attributes].forEach(attribute => {
            const name = attribute.name.toLowerCase();
            if (tag !== 'a' || !['href', 'title', 'target', 'rel'].includes(name)) node.removeAttribute(attribute.name);
        });
        if (tag === 'a') {
            const href = (node.getAttribute('href') ?? '').replace(/[\u0000-\u001F\u007F\s]+/g, '');
            if (href && !/^(https?:|mailto:|tel:|\/|#)/i.test(href)) node.removeAttribute('href');
            node.setAttribute('rel', 'noopener noreferrer');
        }
    });

    return root.innerHTML;
}

function escapeHtml(value) {
    return value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

function emitVisual() {
    emit('update:modelValue', editor.value?.innerHTML ?? '');
}

function emitSource(event) {
    emit('update:modelValue', event.target.value);
}

function handleBlur(event) {
    if (event.relatedTarget && event.currentTarget.parentElement?.contains(event.relatedTarget)) return;
    isFocused.value = false;
    emit('blur');
}

function focusEditor() {
    editor.value?.focus();
}

function command(name, value = null) {
    focusEditor();
    restoreSelection();
    document.execCommand(name, false, value);
    emitVisual();
    saveSelection();
}

function formatBlock(event) {
    command('formatBlock', event.target.value);
    event.target.value = 'p';
}

function saveSelection() {
    const selection = window.getSelection();
    if (selection?.rangeCount && editor.value?.contains(selection.anchorNode)) {
        savedRange.value = selection.getRangeAt(0).cloneRange();
    }
}

function restoreSelection() {
    if (!savedRange.value) return;
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(savedRange.value);
}

function openLinkEditor() {
    saveSelection();
    linkUrl.value = '';
    showLinkEditor.value = true;
    nextTick(() => linkInput.value?.focus());
}

function closeLinkEditor() {
    showLinkEditor.value = false;
    linkUrl.value = '';
    focusEditor();
}

function applyLink() {
    let url = linkUrl.value.trim();
    if (!url) return;
    if (!/^(https?:\/\/|mailto:|tel:|\/|#)/i.test(url)) url = `https://${url}`;
    focusEditor();
    restoreSelection();
    document.execCommand('createLink', false, url);
    const selection = window.getSelection();
    const anchor = selection?.anchorNode?.parentElement?.closest?.('a');
    if (anchor) anchor.setAttribute('rel', 'noopener noreferrer');
    emitVisual();
    showLinkEditor.value = false;
    linkUrl.value = '';
}

function toggleSource() {
    if (sourceMode.value) {
        sourceMode.value = false;
        nextTick(() => {
            setEditorHtml(props.modelValue);
            emitVisual();
        });
        return;
    }
    emitVisual();
    sourceMode.value = true;
    showLinkEditor.value = false;
}

function pastePlainText(event) {
    event.preventDefault();
    const text = event.clipboardData?.getData('text/plain') ?? '';
    document.execCommand('insertText', false, text);
    emitVisual();
}
</script>

<style scoped>
.tool-button {
    min-width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.5rem;
    padding: 0 0.55rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #4b5563;
    transition: background-color 150ms ease, color 150ms ease;
}

.tool-button:hover,
.tool-button:focus-visible {
    background: white;
    color: #111827;
    outline: none;
}

.rich-editor:empty::before {
    content: attr(data-placeholder);
    color: #9ca3af;
    pointer-events: none;
}

.rich-editor :deep(h2) { margin: 0.7rem 0 0.35rem; font-size: 1.5rem; font-weight: 700; line-height: 1.3; }
.rich-editor :deep(h3) { margin: 0.65rem 0 0.3rem; font-size: 1.25rem; font-weight: 700; line-height: 1.35; }
.rich-editor :deep(h4) { margin: 0.6rem 0 0.25rem; font-size: 1.05rem; font-weight: 700; }
.rich-editor :deep(p) { margin: 0.45rem 0; }
.rich-editor :deep(ul) { margin: 0.5rem 0; list-style: disc; padding-left: 1.5rem; }
.rich-editor :deep(ol) { margin: 0.5rem 0; list-style: decimal; padding-left: 1.5rem; }
.rich-editor :deep(blockquote) { margin: 0.65rem 0; border-left: 3px solid #0ea5e9; padding-left: 0.85rem; color: #4b5563; }
.rich-editor :deep(a) { color: #0369a1; text-decoration: underline; }
</style>
