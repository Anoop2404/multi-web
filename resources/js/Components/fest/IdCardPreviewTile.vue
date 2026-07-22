<template>
    <div class="id-card-tile" :class="[`id-card-tile--${card.role_class}`]">
        <!-- Header -->
        <header class="id-card-tile__head">
            <div class="id-card-tile__brand">
                <img v-if="clusterLogoUrl" :src="clusterLogoUrl" :alt="clusterName" class="id-card-tile__logo">
                <div v-else class="id-card-tile__logo-fallback">{{ clusterInitials }}</div>
                <div class="id-card-tile__head-text">
                    <span class="id-card-tile__org">{{ clusterName }}</span>
                    <span class="id-card-tile__event">{{ eventTitle || card.event_name }}</span>
                </div>
            </div>

            <div class="id-card-tile__pass-ribbon">
                <span>{{ card.card_type === 'event_participant' ? 'EVENT PASS' : 'ID CARD' }}</span>
            </div>
        </header>

        <!-- Wave Separator -->
        <div class="id-card-tile__wave">
            <svg viewBox="0 0 500 20" preserveAspectRatio="none">
                <path d="M0 0 C 150 18, 350 18, 500 0 L 500 20 L 0 20 Z" fill="#ffffff"/>
                <path d="M0 0 C 150 16, 350 16, 500 0" fill="none" stroke="#10b981" stroke-width="3"/>
            </svg>
        </div>

        <!-- Body -->
        <div class="id-card-tile__body">
            <div class="id-card-tile__portrait">
                <img v-if="card.photo_url || card.photo_src" :src="card.photo_url || card.photo_src" :alt="card.name" class="id-card-tile__photo" loading="lazy">
                <span v-else class="id-card-tile__initials">{{ card.initials }}</span>
            </div>

            <div class="id-card-tile__info-col">
                <p class="id-card-tile__name">{{ card.name }}</p>
                <table class="id-card-tile__meta-table">
                    <tr>
                        <td class="id-card-tile__meta-label">Role</td>
                        <td class="id-card-tile__meta-sep">:</td>
                        <td class="id-card-tile__meta-val">{{ card.role_title || 'Participant' }}</td>
                    </tr>
                    <tr>
                        <td class="id-card-tile__meta-label">Event</td>
                        <td class="id-card-tile__meta-sep">:</td>
                        <td class="id-card-tile__meta-val">{{ card.event_name || eventTitle }}</td>
                    </tr>
                    <tr>
                        <td class="id-card-tile__meta-label">Date</td>
                        <td class="id-card-tile__meta-sep">:</td>
                        <td class="id-card-tile__meta-val">{{ card.event_date || '—' }}</td>
                    </tr>
                    <tr>
                        <td class="id-card-tile__meta-label">Venue</td>
                        <td class="id-card-tile__meta-sep">:</td>
                        <td class="id-card-tile__meta-val">{{ card.venue || '—' }}</td>
                    </tr>
                    <tr>
                        <td class="id-card-tile__meta-label">Shodaya</td>
                        <td class="id-card-tile__meta-sep">:</td>
                        <td class="id-card-tile__meta-val">{{ card.sahodaya_name || clusterName }}</td>
                    </tr>
                    <tr>
                        <td class="id-card-tile__meta-label">Category</td>
                        <td class="id-card-tile__meta-sep">:</td>
                        <td class="id-card-tile__meta-val">{{ card.category || card.class_category || '—' }}</td>
                    </tr>
                </table>
            </div>

            <div class="id-card-tile__qr-col">
                <img v-if="card.qr_src" :src="card.qr_src" alt="" class="id-card-tile__qr">
                <span class="id-card-tile__qr-label">SCAN TO VERIFY</span>
            </div>
        </div>

        <!-- Footer -->
        <footer class="id-card-tile__footer">
            <div class="id-card-tile__school-pill">
                <span class="id-card-tile__school-text">{{ card.subtitle || card.school_name || '—' }}</span>
            </div>
            <div class="id-card-tile__role-pill">
                <span>{{ card.role_label || 'PARTICIPANT' }}</span>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    card: { type: Object, required: true },
    clusterName: { type: String, default: 'Sahodaya' },
    clusterLogoUrl: { type: String, default: '' },
    eventTitle: { type: String, default: '' },
    variant: { type: String, default: 'premium' },
});

const clusterInitials = computed(() =>
    props.clusterName
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join('') || 'S',
);
</script>

<style scoped>
.id-card-tile {
    width: 100%;
    aspect-ratio: 98 / 62;
    background: #ffffff;
    border: 1.5px solid #042a5b;
    border-radius: 0.6rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    position: relative;
    box-shadow: 0 3px 12px rgba(4, 42, 91, 0.12);
}

/* Header */
.id-card-tile__head {
    flex-shrink: 0;
    height: 30%;
    background: linear-gradient(135deg, #042a5b 0%, #0a3d7a 100%);
    color: #ffffff;
    padding: 0.3rem 0.5rem 0.15rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
}
.id-card-tile__brand {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex: 1;
    min-width: 0;
}
.id-card-tile__logo,
.id-card-tile__logo-fallback {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 9999px;
    border: 1.5px solid rgba(16, 185, 129, 0.6);
    background: #ffffff;
    object-fit: cover;
    flex-shrink: 0;
}
.id-card-tile__logo-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #042a5b;
    font-size: 0.6rem;
    font-weight: 800;
}
.id-card-tile__head-text { min-width: 0; flex: 1; }
.id-card-tile__org {
    display: block;
    font-size: 0.48rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.9);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.id-card-tile__event {
    display: block;
    font-size: 0.88rem;
    font-weight: 800;
    color: #ffffff;
    margin-top: 0.04rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.id-card-tile__pass-ribbon {
    position: absolute;
    top: 0;
    right: 0.6rem;
    background: #059669;
    color: #ffffff;
    font-size: 0.48rem;
    font-weight: 800;
    padding: 0.1rem 0.42rem 0.15rem;
    border-bottom-left-radius: 0.25rem;
    border-bottom-right-radius: 0.25rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

/* Wave */
.id-card-tile__wave {
    height: 0.5rem;
    margin-top: -0.5rem;
    position: relative;
    z-index: 2;
}
.id-card-tile__wave svg { width: 100%; height: 100%; display: block; }

/* Body */
.id-card-tile__body {
    flex: 1;
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    padding: 0.2rem 0.5rem 0.15rem;
    background: #ffffff;
}
.id-card-tile__portrait {
    width: 22%;
    aspect-ratio: 4/5;
    border-radius: 0.25rem;
    border: 1.5px solid #0d9488;
    overflow: hidden;
    background: #f0fdf4;
    flex-shrink: 0;
}
.id-card-tile__photo { width: 100%; height: 100%; object-fit: cover; display: block; }
.id-card-tile__initials {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 800;
    color: #042a5b;
    background: #e0f2fe;
}
.id-card-tile__info-col {
    flex: 1;
    min-width: 0;
}
.id-card-tile__name {
    font-size: 0.82rem;
    font-weight: 800;
    color: #042a5b;
    text-transform: uppercase;
    line-height: 1.15;
    margin-bottom: 0.12rem;
    word-wrap: break-word;
}

/* Meta table */
.id-card-tile__meta-table { width: 100%; border-collapse: collapse; }
.id-card-tile__meta-table td {
    font-size: 0.5rem;
    line-height: 1.35;
    padding: 0.025rem 0;
    vertical-align: middle;
}
.id-card-tile__meta-label { color: #475569; font-weight: 600; width: 2.6rem; }
.id-card-tile__meta-sep { color: #64748b; width: 0.3rem; text-align: center; }
.id-card-tile__meta-val { color: #0f172a; font-weight: 700; }

/* QR column */
.id-card-tile__qr-col {
    width: 18%;
    text-align: center;
    flex-shrink: 0;
    align-self: center;
}
.id-card-tile__qr {
    width: 100%;
    aspect-ratio: 1;
    background: #ffffff;
    border-radius: 0.15rem;
    border: 1px solid #d1d5db;
    padding: 0.06rem;
    display: block;
    margin: 0 auto;
}
.id-card-tile__qr-label {
    display: block;
    font-size: 0.32rem;
    font-weight: 800;
    color: #10b981;
    letter-spacing: 0.06em;
    margin-top: 0.05rem;
    text-transform: uppercase;
}

/* Footer */
.id-card-tile__footer {
    flex-shrink: 0;
    height: 11%;
    background: #042a5b;
    padding: 0 0.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.id-card-tile__school-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 9999px;
    padding: 0.08rem 0.35rem;
    max-width: 68%;
}
.id-card-tile__school-text {
    font-size: 0.45rem;
    font-weight: 800;
    color: #ffffff;
    text-transform: uppercase;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.id-card-tile__role-pill {
    background: #059669;
    color: #ffffff;
    font-size: 0.45rem;
    font-weight: 800;
    padding: 0.08rem 0.42rem;
    border-radius: 9999px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
</style>
