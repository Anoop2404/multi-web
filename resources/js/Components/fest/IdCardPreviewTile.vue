<template>
    <div v-if="variant === 'pass'" class="pass-tile">
        <header class="pass-tile__head">
            <img v-if="clusterLogoUrl" :src="clusterLogoUrl" :alt="clusterName" class="pass-tile__logo">
            <div v-else class="pass-tile__logo-fallback">{{ clusterInitials }}</div>
            <div class="pass-tile__brand">
                <span class="pass-tile__org">{{ card.sahodaya_name || clusterName }}</span>
                <span v-if="card.phase_name" class="pass-tile__tagline">{{ card.phase_name }}</span>
            </div>
            <div class="pass-tile__event">
                <span class="pass-tile__event-name">Kalotsav</span>
                <span v-if="card.academic_year" class="pass-tile__event-year">{{ card.academic_year }}</span>
            </div>
        </header>

        <div class="pass-tile__body">
            <div class="pass-tile__photo-col">
                <div class="pass-tile__photo-box">
                    <img v-if="card.photo_url || card.photo_src" :src="card.photo_url || card.photo_src" :alt="card.name" class="pass-tile__photo" loading="lazy">
                    <span v-else class="pass-tile__initials">{{ card.initials }}</span>
                </div>
                <div class="pass-tile__role">{{ passLabel }}</div>
            </div>

            <div class="pass-tile__info">
                <p class="pass-tile__name">{{ card.name }}</p>
                <p class="pass-tile__school">{{ card.subtitle || card.school_name }}</p>

                <div v-if="isStaffOrVolunteer" class="pass-tile__meta">
                    <div class="pass-tile__meta-box">
                        <span class="pass-tile__meta-label">{{ card.audience === 'staff' ? 'Location' : 'Contact' }}</span>
                        <span class="pass-tile__meta-value">{{ card.detail || '—' }}</span>
                    </div>
                    <div class="pass-tile__meta-box">
                        <span class="pass-tile__meta-label">Event</span>
                        <span class="pass-tile__meta-value">{{ card.phase_name || eventTitle }}</span>
                    </div>
                    <div class="pass-tile__meta-box">
                        <span class="pass-tile__meta-label">{{ card.secondary_label || 'Info' }}</span>
                        <span class="pass-tile__meta-value pass-tile__meta-value--accent">{{ card.secondary_value || '—' }}</span>
                    </div>
                    <div class="pass-tile__meta-box">
                        <span class="pass-tile__meta-label">{{ card.id_label || 'ID' }}</span>
                        <span class="pass-tile__meta-value">{{ card.id_number || '—' }}</span>
                    </div>
                </div>

                <template v-else>
                    <div class="pass-tile__meta">
                        <div class="pass-tile__meta-box">
                            <span class="pass-tile__meta-label">Venue</span>
                            <span class="pass-tile__meta-value">{{ card.venue || '—' }}</span>
                        </div>
                        <div class="pass-tile__meta-box">
                            <span class="pass-tile__meta-label">Event Date</span>
                            <span class="pass-tile__meta-value">{{ card.event_date || '—' }}</span>
                        </div>
                        <div class="pass-tile__meta-box">
                            <span class="pass-tile__meta-label">Category</span>
                            <span class="pass-tile__meta-value pass-tile__meta-value--accent">{{ card.category || card.class_category || '—' }}</span>
                        </div>
                        <div class="pass-tile__meta-box">
                            <span class="pass-tile__meta-label">{{ card.id_label || 'ID' }}</span>
                            <span class="pass-tile__meta-value">{{ card.id_number || '—' }}</span>
                        </div>
                    </div>

                    <div class="pass-tile__items">
                        <div class="pass-tile__items-title">
                            <strong>Registered Items</strong>
                            <span class="pass-tile__items-count">{{ items.length }}</span>
                        </div>
                        <ol class="pass-tile__items-list">
                            <li v-for="item in items" :key="item">{{ item }}</li>
                        </ol>
                    </div>
                </template>
            </div>
        </div>

        <footer class="pass-tile__footer">
            <span>Official {{ passLabel }} Pass</span>
        </footer>
    </div>

    <div v-else class="id-card-tile" :class="[`id-card-tile--${card.role_class}`]">
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
                    <tr v-if="card.dob">
                        <td class="id-card-tile__meta-label">DOB</td>
                        <td class="id-card-tile__meta-sep">:</td>
                        <td class="id-card-tile__meta-val">{{ card.dob }}</td>
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

const isStaffOrVolunteer = computed(() => ['staff', 'volunteer'].includes(props.card.audience));

const passLabel = computed(() => {
    if (props.card.role_title) return props.card.role_title;
    const label = (props.card.role_label || 'Participant').toLowerCase();
    return label.charAt(0).toUpperCase() + label.slice(1);
});

const items = computed(() => {
    const card = props.card;
    let list = card.items || [];
    if (!list.length && card.members?.length) {
        list = card.members.map((m) => m.name).filter(Boolean);
    }
    if (!list.length && !isStaffOrVolunteer.value) {
        list = [card.item_label || card.detail].filter(Boolean);
    }
    return list.slice(0, 7);
});
</script>

<style scoped>
/* ========= Participant Pass tile ========= */
.pass-tile {
    width: 100%;
    aspect-ratio: 85.6 / 54;
    background: linear-gradient(135deg, #ffffff 0%, #ffffff 65%, #f4f9ff 100%);
    border: 1px solid #bdd0e5;
    border-radius: 0.7rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    position: relative;
    box-shadow: 0 3px 12px rgba(4, 42, 91, 0.10);
}
.pass-tile__head {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.55rem;
    border-bottom: 1px solid #dde7f1;
    background: linear-gradient(90deg, #ffffff 0%, #ffffff 72%, #eef6ff 100%);
    position: relative;
}
.pass-tile__head::before {
    content: "";
    position: absolute;
    left: 0; top: 0;
    width: 100%; height: 3px;
    background: linear-gradient(90deg, #073f82, #1767b7, #ec1470, #ff9e24);
}
.pass-tile__logo, .pass-tile__logo-fallback {
    width: 1.5rem; height: 1.5rem; border-radius: 50%; flex-shrink: 0; object-fit: contain;
}
.pass-tile__logo-fallback {
    display: flex; align-items: center; justify-content: center;
    background: #eef6ff; color: #073f82; font-size: 0.55rem; font-weight: 800;
}
.pass-tile__brand { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.pass-tile__org {
    font-size: 0.58rem; font-weight: 800; color: #073f82; text-transform: uppercase;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.pass-tile__tagline {
    display: block; margin-top: 0.03rem; font-size: 0.82rem; font-weight: 700; color: #073f82;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.pass-tile__event { flex-shrink: 0; min-width: 0; max-width: 40%; text-align: right; }
.pass-tile__event-name {
    display: block; font-size: 0.62rem; font-weight: 900; color: #ec1470; text-transform: uppercase;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.pass-tile__event-year {
    display: block; margin-top: 0.05rem; font-size: 0.4rem; font-weight: 700; color: #073f82;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.pass-tile__body { flex: 1; min-height: 0; display: flex; gap: 0.45rem; padding: 0.4rem 0.55rem 0.2rem; }
.pass-tile__photo-col { width: 22%; flex-shrink: 0; }
.pass-tile__photo-box {
    width: 100%; aspect-ratio: 19 / 24; border-radius: 0.3rem; overflow: hidden;
    background: linear-gradient(145deg, #e5edf7, #c8daed); border: 1px solid #cddaea;
}
.pass-tile__photo { width: 100%; height: 100%; object-fit: cover; display: block; }
.pass-tile__initials {
    width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; font-weight: 800; color: #073f82;
}
.pass-tile__role {
    margin-top: 0.25rem; text-align: center; padding: 0.15rem 0.1rem; border-radius: 0.2rem;
    background: #ec1470; color: #fff; font-size: 0.4rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: 0.03em;
}

.pass-tile__info { flex: 1; min-width: 0; }
.pass-tile__name {
    font-size: 0.82rem; font-weight: 800; color: #0b1e3a; line-height: 1.15;
    display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden;
}
.pass-tile__school {
    margin-top: 0.1rem; font-size: 0.53rem; font-weight: 600; color: #53667e;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.pass-tile__meta { margin-top: 0.2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.12rem 0.18rem; }
.pass-tile__meta-box { min-width: 0; padding: 0.12rem 0.2rem; background: #f2f7fc; border-radius: 0.2rem; }
.pass-tile__meta-label { display: block; font-size: 0.4rem; font-weight: 700; text-transform: uppercase; color: #8391a4; }
.pass-tile__meta-value {
    display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden;
    font-size: 0.5rem; line-height: 1.2; font-weight: 700; color: #173557;
}
.pass-tile__meta-value--accent { color: #073f82; }

.pass-tile__items { margin-top: 0.2rem; padding-top: 0.15rem; border-top: 1px solid #dce6f0; }
.pass-tile__items-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.1rem; }
.pass-tile__items-title strong { font-size: 0.5rem; color: #073f82; text-transform: uppercase; }
.pass-tile__items-count {
    padding: 0.03rem 0.22rem; border-radius: 999px; background: #e4f1ff;
    color: #073f82; font-size: 0.4rem; font-weight: 800;
}
.pass-tile__items-list {
    list-style: none; counter-reset: pass-tile-item;
    column-count: 2; column-gap: 0.4rem; margin: 0; padding: 0;
}
.pass-tile__items-list li {
    counter-increment: pass-tile-item; break-inside: avoid;
    font-size: 0.46rem; line-height: 1.35; font-weight: 600; color: #253850;
}
.pass-tile__items-list li::before { content: counter(pass-tile-item) ". "; font-weight: 800; color: #073f82; }

.pass-tile__footer {
    flex-shrink: 0; height: 0.85rem; position: relative; overflow: hidden;
    display: flex; align-items: center; justify-content: center; color: #fff;
    background: linear-gradient(90deg, #073f82, #135ea6 45%, #ec1470 82%, #ff9d20);
}
.pass-tile__footer span { font-size: 0.38rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }

/* ========= Premium-style tile (default) ========= */
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
