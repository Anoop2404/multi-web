<div
    x-data="{
        open: false,
        images: [],
        captions: [],
        index: 0,
        openGallery(images, captions, start = 0) {
            this.images = images;
            this.captions = captions;
            this.index = start;
            this.open = true;
            document.body.style.overflow = 'hidden';
        },
        close() {
            this.open = false;
            document.body.style.overflow = '';
        },
        next() {
            if (this.images.length) this.index = (this.index + 1) % this.images.length;
        },
        prev() {
            if (this.images.length) this.index = (this.index - 1 + this.images.length) % this.images.length;
        },
    }"
    @lightbox-open.window="openGallery($event.detail.images, $event.detail.captions ?? [], $event.detail.index ?? 0)"
    @keydown.escape.window="open && close()"
    @keydown.arrow-right.window="open && next()"
    @keydown.arrow-left.window="open && prev()"
>
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4"
        role="dialog"
        aria-modal="true"
        aria-label="Image gallery"
        @click.self="close()"
        x-cloak
    >
        <button
            type="button"
            @click="close()"
            class="absolute top-4 right-4 z-10 min-w-[44px] min-h-[44px] flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition"
            aria-label="Close gallery"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>

        <template x-if="images.length > 1">
            <button
                type="button"
                @click.stop="prev()"
                class="absolute left-2 md:left-4 z-10 min-w-[44px] min-h-[44px] flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition"
                aria-label="Previous image"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
        </template>

        <template x-if="images.length > 1">
            <button
                type="button"
                @click.stop="next()"
                class="absolute right-2 md:right-4 z-10 min-w-[44px] min-h-[44px] flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition"
                aria-label="Next image"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </template>

        <div class="max-w-5xl w-full" @click.stop>
            <img
                :src="images[index]"
                :alt="captions[index] || 'Gallery image'"
                class="max-h-[80vh] w-full object-contain rounded-lg select-none touch-manipulation"
                draggable="false"
            >
            <p x-show="captions[index]" x-text="captions[index]" class="text-center text-white/80 text-sm mt-4 px-4"></p>
            <p x-show="images.length > 1" class="text-center text-white/50 text-xs mt-2" x-text="(index + 1) + ' / ' + images.length"></p>
        </div>
    </div>
</div>
