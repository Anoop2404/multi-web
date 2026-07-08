<template>
    <PortalLayout
        role-label="Gate Check"
        title="QR Gate Check"
        :subtitle="sahodaya.name"
        accent="amber"
        :nav-items="navItems"
    >
        <div class="card space-y-4">
            <form @submit.prevent="scan" class="space-y-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Event</label>
                    <select v-model="eventId" class="field mt-1" required>
                        <option value="">Select event</option>
                        <option v-for="e in events" :key="e.id" :value="e.id">{{ e.title }}</option>
                    </select>
                </div>

                <div v-if="cameraAvailable" class="space-y-2">
                    <label class="text-xs font-semibold text-gray-600">Camera scanner</label>
                    <div class="relative rounded-lg overflow-hidden bg-black aspect-video max-w-md">
                        <video ref="videoRef" class="w-full h-full object-cover" playsinline muted />
                        <canvas ref="canvasRef" class="hidden" />
                    </div>
                    <p v-if="scanHint" class="text-xs text-amber-700">{{ scanHint }}</p>
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-600">
                        {{ cameraAvailable ? 'Manual QR payload' : 'QR payload' }}
                    </label>
                    <textarea v-model="payload" class="field mt-1 font-mono text-xs" rows="3" placeholder="Scan or paste FEST|..." required />
                </div>
                <label class="flex items-center gap-2 text-xs text-gray-600">
                    <input type="checkbox" v-model="markAttendance" class="rounded">
                    Mark participant present when valid
                </label>
                <button type="submit" class="btn-primary" :disabled="!eventId">Verify scan</button>
            </form>

            <div v-if="lastScan" class="border-t pt-4 space-y-3">
                <div class="flex items-start gap-3">
                    <img
                        v-if="lastScan.photo_url"
                        :src="lastScan.photo_url"
                        alt="Participant photo"
                        class="w-16 h-16 rounded-lg object-cover border shrink-0"
                    >
                    <div class="space-y-2 min-w-0">
                        <p class="text-sm font-semibold" :class="lastScan.valid ? 'text-green-700' : 'text-red-600'">
                            {{ lastScan.valid ? 'Valid scan' : 'Invalid scan' }}
                            <span v-if="lastScan.duplicate" class="text-amber-600 font-normal"> · Duplicate scan</span>
                        </p>
                        <dl v-if="lastScan.payload" class="text-sm grid gap-1">
                            <template v-for="(value, key) in flatPayload(lastScan.payload)" :key="key">
                                <div class="flex gap-2">
                                    <dt class="text-gray-500 capitalize min-w-24">{{ key.replace(/_/g, ' ') }}</dt>
                                    <dd class="font-medium">{{ value }}</dd>
                                </div>
                            </template>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { festOpsDashboardNav } from '@/support/festOpsPortalNav.js';

const props = defineProps({
    sahodaya: Object,
    events: Array,
    selectedEventId: Number,
    lastScan: Object,
});

const eventId = ref(props.selectedEventId || '');
const payload = ref('');
const markAttendance = ref(true);
const cameraAvailable = ref(false);
const scanHint = ref('');
const videoRef = ref(null);
const canvasRef = ref(null);

let mediaStream = null;
let scanFrame = null;
let jsQR = null;

const navItems = computed(() => festOpsDashboardNav(props.sahodaya.id));

function flatPayload(data) {
    if (! data || typeof data !== 'object') return {};
    const out = {};
    for (const [k, v] of Object.entries(data)) {
        if (Array.isArray(v)) {
            out[k] = v.map(m => m.name ?? JSON.stringify(m)).join(', ');
        } else if (v && typeof v === 'object') {
            Object.assign(out, flatPayload(v));
        } else {
            out[k] = v;
        }
    }
    return out;
}

function loadJsQR() {
    return new Promise((resolve, reject) => {
        if (window.jsQR) {
            resolve(window.jsQR);
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/jsqr@1.4.0/dist/jsQR.js';
        script.onload = () => resolve(window.jsQR);
        script.onerror = () => reject(new Error('Failed to load jsQR'));
        document.head.appendChild(script);
    });
}

async function startCamera() {
    if (! navigator.mediaDevices?.getUserMedia) {
        return;
    }

    try {
        jsQR = await loadJsQR();
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
        });
        cameraAvailable.value = true;
        await nextTick();
        if (! videoRef.value) {
            return;
        }
        videoRef.value.srcObject = mediaStream;
        await videoRef.value.play();
        scanHint.value = 'Point the camera at a fest QR code.';
        tickScan();
    } catch {
        cameraAvailable.value = false;
        stopCamera();
    }
}

function tickScan() {
    const video = videoRef.value;
    const canvas = canvasRef.value;
    if (! video || ! canvas || ! jsQR || video.readyState !== video.HAVE_ENOUGH_DATA) {
        scanFrame = requestAnimationFrame(tickScan);
        return;
    }

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(imageData.data, imageData.width, imageData.height, {
        inversionAttempts: 'dontInvert',
    });

    if (code?.data?.startsWith('FEST|') && code.data !== payload.value) {
        payload.value = code.data;
        scanHint.value = 'QR captured — tap Verify scan.';
    }

    scanFrame = requestAnimationFrame(tickScan);
}

function stopCamera() {
    if (scanFrame) {
        cancelAnimationFrame(scanFrame);
        scanFrame = null;
    }
    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => track.stop());
        mediaStream = null;
    }
}

function scan() {
    router.post(`/portal/fest-ops/${props.sahodaya.id}/events/${eventId.value}/gate-check`, {
        payload: payload.value,
        mark_attendance: markAttendance.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { payload.value = ''; },
    });
}

onMounted(() => {
    startCamera();
});

onUnmounted(() => {
    stopCamera();
});
</script>
