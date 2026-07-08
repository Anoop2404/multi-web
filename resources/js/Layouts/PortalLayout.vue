<template>
    <div class="min-h-screen bg-slate-50 flex flex-col">
        <header class="bg-white border-b border-slate-200 shrink-0">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <div v-if="avatarUrl" class="shrink-0">
                        <img
                            :src="avatarUrl"
                            :alt="title"
                            class="h-12 w-12 rounded-full object-cover border-2 border-indigo-100 shadow-sm"
                        >
                    </div>
                    <div v-else-if="showAvatarPlaceholder" class="shrink-0">
                        <div class="h-12 w-12 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-bold border-2 border-indigo-50">
                            {{ initials }}
                        </div>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-wider" :class="accentClass">{{ roleLabel }}</p>
                        <h1 class="text-lg font-bold text-slate-900 truncate">{{ title }}</h1>
                        <p v-if="subtitle" class="text-xs text-slate-500 truncate">{{ subtitle }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <SignOutButton class="text-xs text-red-600 hover:text-red-700 font-semibold px-2 py-1" />
                </div>
            </div>
            <nav v-if="navItems.length"
                 class="max-w-5xl mx-auto pb-2 flex gap-1 overflow-x-auto scrollbar-none -mx-4 px-4 sm:mx-0 sm:px-6">
                <Link v-for="item in navItems" :key="item.href"
                      :href="item.href"
                      class="text-xs font-semibold px-3 py-1.5 rounded-lg transition shrink-0 whitespace-nowrap"
                      :class="isActive(item)
                          ? 'bg-indigo-600 text-white shadow-sm'
                          : 'text-slate-600 hover:bg-slate-100'">
                    {{ item.label }}
                </Link>
            </nav>
        </header>
        <FlashBanner class="max-w-5xl mx-auto px-4 sm:px-6 pt-3 w-full" />
        <main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 py-6">
            <slot />
        </main>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import SignOutButton from '@/Components/SignOutButton.vue';
import FlashBanner from '@/Components/ui/FlashBanner.vue';
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    roleLabel: { type: String, required: true },
    accent: { type: String, default: 'indigo' },
    navItems: { type: Array, default: () => [] },
    avatarUrl: { type: String, default: '' },
    showAvatarPlaceholder: { type: Boolean, default: false },
});

const page = usePage();

const accentClass = computed(() => ({
    indigo: 'text-indigo-600',
    amber: 'text-amber-600',
    emerald: 'text-emerald-700',
    violet: 'text-violet-600',
}[props.accent] ?? 'text-indigo-600'));

const initials = computed(() => {
    const parts = (props.title || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
});

function isActive(item) {
    const href = item.href;
    const url = page.url.split('?')[0];
    if (item.exact) {
        return url === href || url === `${href}/`;
    }
    if (url === href || url === `${href}/`) {
        return true;
    }
    if (url.startsWith(`${href}/`)) {
        return !props.navItems.some((other) =>
            other.href !== href
            && other.href.startsWith(`${href}/`)
            && (url === other.href || url.startsWith(`${other.href}/`)),
        );
    }
    return false;
}
</script>

<style scoped>
.scrollbar-none {
    scrollbar-width: none;
}
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
</style>
