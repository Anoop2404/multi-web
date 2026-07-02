<template>
    <div class="px-3 pt-3 pb-1">
        <label class="sr-only" for="sidebar-nav-search">Search menus</label>
        <div class="relative">
            <SahodayaSvgIcon name="search"
                             class="pointer-events-none absolute left-2.5 top-1/2 w-3.5 h-3.5 -translate-y-1/2 text-white/40" />
            <input id="sidebar-nav-search"
                   ref="inputRef"
                   v-model="model"
                   type="search"
                   autocomplete="off"
                   spellcheck="false"
                   placeholder="Search menus…"
                   class="w-full rounded-lg border border-white/10 bg-white/8 py-2 pl-8 pr-8 text-sm text-white placeholder:text-white/40 transition focus:border-[#fbbf24]/40 focus:bg-white/12 focus:outline-none" />
            <button v-if="model"
                    type="button"
                    class="absolute right-1.5 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded text-white/50 transition hover:bg-white/10 hover:text-white"
                    aria-label="Clear search"
                    @click="clear">
                ×
            </button>
        </div>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import SahodayaSvgIcon from './SahodayaSvgIcon.vue';

const model = defineModel({ type: String, default: '' });

const inputRef = ref(null);

function clear() {
    model.value = '';
    inputRef.value?.focus();
}

function onKeydown(event) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        inputRef.value?.focus();
        inputRef.value?.select();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>
