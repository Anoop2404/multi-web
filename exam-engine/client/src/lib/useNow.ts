import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue';
import { clock } from './api';

/** Server-corrected "now" (epoch ms), ticking every second while the component is mounted. */
const nowMs = ref(clock.nowMs());
let users = 0;
let timer: ReturnType<typeof setInterval> | null = null;

function tick() {
    nowMs.value = clock.nowMs();
}

export function useServerNow(): Ref<number> {
    onMounted(() => {
        users++;
        tick();
        if (!timer) {
            timer = setInterval(tick, 1000);
            document.addEventListener('visibilitychange', tick);
        }
    });
    onBeforeUnmount(() => {
        users--;
        if (users <= 0 && timer) {
            clearInterval(timer);
            document.removeEventListener('visibilitychange', tick);
            timer = null;
            users = 0;
        }
    });
    return nowMs;
}

export function refreshNow(): void {
    tick();
}
