<template>
    <section class="space-y-5" aria-labelledby="experience-heading">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.18em] text-purple-600">Step 1</p>
                <h2 id="experience-heading" class="text-2xl font-bold text-gray-950 mt-1">Choose the website’s primary job</h2>
                <p class="text-sm text-gray-500 mt-1 max-w-2xl">Each experience changes content priority, page rhythm, navigation character and interaction—not only colours.</p>
            </div>
            <span v-if="currentKey" class="text-xs font-bold rounded-full bg-emerald-50 text-emerald-700 px-3 py-1.5">Published: {{ nameFor(currentKey) }}</span>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
            <article v-for="experience in experiences" :key="experience.key" class="overflow-hidden rounded-2xl border bg-white shadow-sm" :class="selectedKey === experience.key ? 'border-purple-500 ring-2 ring-purple-100' : 'border-gray-200'">
                <button type="button" class="w-full text-left" @click="$emit('select', experience.key)">
                    <div class="h-2" :style="{ background: `linear-gradient(90deg, ${experience.design.primary}, ${experience.design.secondary}, ${experience.design.accent_color})` }"></div>
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">{{ experience.accent }}</p>
                                <h3 class="text-lg font-bold text-gray-950 mt-1 flex items-center gap-2">
                                    {{ experience.name }}
                                    <span v-if="experience.locked" class="text-[10px] font-bold uppercase tracking-wider rounded-full bg-amber-100 text-amber-700 px-2 py-0.5">Premium</span>
                                </h3>
                            </div>
                            <span class="text-xs font-bold rounded-full px-2.5 py-1" :style="{ color: experience.design.primary, background: `${experience.design.primary}12` }">{{ experience.sections.length }} sections</span>
                        </div>
                        <p class="text-sm text-gray-600 mt-3">{{ experience.purpose }}</p>
                        <p class="text-xs text-gray-500 mt-4"><strong>Best for:</strong> {{ experience.audience }}</p>
                        <div class="flex flex-wrap gap-1.5 mt-4"><span v-for="item in experience.sections.slice(0, 5)" :key="`${item.section_type}-${item.variant}`" class="text-[10px] rounded bg-gray-100 px-2 py-1 text-gray-600">{{ label(item.section_type) }}</span></div>
                    </div>
                </button>
                <div v-if="selectedKey === experience.key && experience.locked" class="px-5 pb-5">
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <p class="font-bold">Upgrade to apply this design</p>
                        <p class="mt-0.5 text-amber-700">This premium experience isn't included in the current plan.</p>
                    </div>
                </div>
                <div v-else-if="selectedKey === experience.key" class="px-5 pb-5 flex flex-wrap gap-2">
                    <button type="button" @click="$emit('apply', experience.key, 'full')" class="px-4 py-2 rounded-xl bg-[#1e1b4b] text-white text-sm font-bold">Apply as draft</button>
                    <button type="button" @click="$emit('apply', experience.key, 'style')" class="px-4 py-2 rounded-xl border border-gray-200 text-gray-700 text-sm font-bold">Change style only</button>
                </div>
            </article>
        </div>
    </section>
</template>

<script setup>
defineProps({ experiences: { type: Array, default: () => [] }, selectedKey: String, currentKey: String });
defineEmits(['select', 'apply']);
const label = value => (value || '').replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
function nameFor(key) { return key ? key.replace(/-/g, ' ').replace(/\b\w/g, char => char.toUpperCase()) : ''; }
</script>
