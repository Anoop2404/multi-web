<template>
    <div class="id-card-tile"
         :class="[
             `id-card-tile--${card.role_class}`,
             card.card_type === 'team' ? 'id-card-tile--team' : '',
             card.card_type === 'event_participant' ? 'id-card-tile--event-pass' : '',
             card.card_type === 'head_participant' ? 'id-card-tile--head-pass' : '',
             variant === 'premium' ? 'id-card-tile--premium' : '',
         ]">
        <div v-if="variant === 'premium'" class="id-card-tile__accent"></div>

        <header class="id-card-tile__head">
            <div class="id-card-tile__brand">
                <img v-if="clusterLogoUrl" :src="clusterLogoUrl" :alt="clusterName" class="id-card-tile__logo">
                <div v-else class="id-card-tile__logo-fallback">{{ clusterInitials }}</div>
                <div class="id-card-tile__head-text">
                    <span class="id-card-tile__org">{{ clusterName }}</span>
                    <span v-if="variant === 'premium' && eventTitle" class="id-card-tile__event">{{ eventTitle }}</span>
                </div>
            </div>
            <img v-if="card.qr_src" :src="card.qr_src" alt="" class="id-card-tile__qr">
        </header>

        <div v-if="discipline" class="id-card-tile__discipline">
            <span class="id-card-tile__discipline-text">{{ discipline }}</span>
        </div>

        <template v-if="card.card_type === 'team'">
            <div class="id-card-tile__body id-card-tile__body--team">
                <div>
                    <p class="id-card-tile__name">{{ card.name }}</p>
                    <p class="id-card-tile__school">{{ card.subtitle }}</p>
                    <p v-if="card.detail" class="id-card-tile__tag">{{ card.detail }}</p>
                </div>
                <ul class="id-card-tile__members">
                    <li v-for="(member, idx) in (card.members ?? []).slice(0, 5)" :key="idx" class="id-card-tile__member">
                        <span class="id-card-tile__member-name">{{ member.name }}</span>
                        <span class="id-card-tile__member-meta">{{ member.fest_id }}</span>
                    </li>
                    <li v-if="(card.member_count ?? 0) > 5" class="id-card-tile__member id-card-tile__member--more">
                        + {{ card.member_count - 5 }} more
                    </li>
                </ul>
            </div>
        </template>

        <template v-else>
            <div class="id-card-tile__body">
                <div class="id-card-tile__avatar">
                    <img v-if="card.photo_url || card.photo_src" :src="card.photo_url || card.photo_src" :alt="card.name" class="id-card-tile__photo">
                    <span v-else class="id-card-tile__initials">{{ card.initials }}</span>
                </div>
                <div class="id-card-tile__info">
                    <p class="id-card-tile__name">{{ card.name }}</p>
                    <p class="id-card-tile__school">{{ card.subtitle }}</p>
                    <p v-if="card.student_class || card.class_category" class="id-card-tile__class-meta">
                        <span v-if="card.student_class">Class: {{ card.student_class }}</span>
                        <span v-if="card.student_class && card.class_category"> · </span>
                        <span v-if="card.class_category">Category: {{ card.class_category }}</span>
                    </p>
                    <ul v-if="showItemsList" class="id-card-tile__items">
                        <li v-for="(itemTitle, idx) in card.items.slice(0, 4)" :key="idx">{{ itemTitle }}</li>
                        <li v-if="(card.item_count ?? 0) > 4" class="id-card-tile__items-more">
                            + {{ card.item_count - 4 }} more
                        </li>
                    </ul>
                    <p v-else-if="itemLine" class="id-card-tile__tag">{{ itemLine }}</p>
                </div>
                <div v-if="card.chest_number" class="id-card-tile__badge">
                    <span class="id-card-tile__badge-label">Chest</span>
                    <span class="id-card-tile__badge-value">{{ card.chest_number }}</span>
                </div>
            </div>
        </template>

        <footer class="id-card-tile__footer">
            <div class="id-card-tile__footer-id">
                <span class="id-card-tile__footer-label">{{ card.id_label }}</span>
                <span class="id-card-tile__footer-value">{{ card.id_number }}</span>
            </div>
            <span class="id-card-tile__role">{{ card.role_label }}</span>
            <span v-if="card.card_type === 'head_participant' && (card.item_count ?? 0) > 0"
                  class="id-card-tile__footer-meta">
                {{ card.item_count }} item{{ card.item_count === 1 ? '' : 's' }}
            </span>
            <span v-if="card.schedule" class="id-card-tile__footer-schedule">{{ card.schedule }}</span>
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
    variant: { type: String, default: 'standard' },
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

const showItemsList = computed(() =>
    (props.card.card_type === 'event_participant' || props.card.card_type === 'head_participant')
    && (props.card.items?.length ?? 0) > 0,
);

const discipline = computed(() => {
    if (props.card.card_type === 'event_participant') {
        return 'Event pass';
    }
    if (props.card.card_type === 'head_participant' && props.card.head_label) {
        return props.card.head_label;
    }
    if (props.card.head_label) {
        return props.card.head_label;
    }
    if (props.variant === 'premium' && props.card.item_label) {
        return props.card.item_label;
    }
    return null;
});

const itemLine = computed(() => {
    if (showItemsList.value) {
        return null;
    }
    const line = props.card.detail ?? props.card.item_label ?? null;
    if (line && discipline.value && line.toLowerCase() === discipline.value.toLowerCase()) {
        return null;
    }
    return line;
});
</script>

<style scoped>
.id-card-tile {
    border: 1px solid #cbd5e1;
    border-radius: 0.65rem;
    overflow: hidden;
    background: #fff;
    font-size: 0.8rem;
    color: #0f172a;
    aspect-ratio: 99 / 85;
    min-height: 13.5rem;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 16px rgba(10, 45, 92, 0.08);
}
.id-card-tile--premium { border-color: #94a3b8; }

.id-card-tile__accent {
    flex-shrink: 0;
    height: 0.2rem;
    background: linear-gradient(90deg, #8b6914, #c9a227, #f5e6b8, #c9a227, #8b6914);
}
.id-card-tile--event-pass.id-card-tile--premium .id-card-tile__accent {
    background: linear-gradient(90deg, #065f46, #10b981, #065f46);
}

.id-card-tile__head {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    padding: 0.38rem 0.55rem 0.32rem;
    color: #fff;
    background: linear-gradient(180deg, #0a2d5c, #0f3d7a);
}
.id-card-tile--volunteer .id-card-tile__head { background: linear-gradient(180deg, #064e3b, #047857); }
.id-card-tile--staff .id-card-tile__head { background: linear-gradient(180deg, #7c2d12, #9a3412); }

.id-card-tile__brand {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex: 1;
    min-width: 0;
}
.id-card-tile__logo,
.id-card-tile__logo-fallback {
    width: 1.9rem;
    height: 1.9rem;
    border-radius: 9999px;
    flex-shrink: 0;
    border: 1.5px solid rgba(201, 162, 39, 0.9);
    background: #fff;
    object-fit: cover;
}
.id-card-tile__logo-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0f3d7a;
    font-size: 0.52rem;
    font-weight: 700;
}
.id-card-tile__head-text { flex: 1; min-width: 0; }
.id-card-tile__org {
    display: block;
    font-size: 0.54rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    opacity: 0.88;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.id-card-tile__event {
    display: block;
    font-size: 0.72rem;
    font-weight: 800;
    margin-top: 0.04rem;
    color: #fef08a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.id-card-tile__qr {
    width: 2.2rem;
    height: 2.2rem;
    background: #fff;
    border-radius: 0.2rem;
    padding: 0.08rem;
    flex-shrink: 0;
}

.id-card-tile__discipline {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.22rem 0.55rem;
    background: #0f172a;
    border-bottom: 1px solid #334155;
}
.id-card-tile__discipline-text {
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    text-align: center;
    color: #38bdf8;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.id-card-tile--event-pass .id-card-tile__discipline-text { color: #34d399; }

.id-card-tile__body {
    flex: 1;
    min-height: 0;
    display: flex;
    align-items: stretch;
    gap: 0.45rem;
    padding: 0.45rem 0.55rem;
    overflow: hidden;
}
.id-card-tile__body--team {
    flex-direction: column;
    gap: 0.3rem;
}
.id-card-tile__avatar { flex-shrink: 0; }
.id-card-tile__photo,
.id-card-tile__initials {
    width: 3.35rem;
    height: 4.1rem;
    border-radius: 0.35rem;
    object-fit: cover;
    border: 2px solid #d97706;
    background: #f8fafc;
}
.id-card-tile__initials {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0f3d7a;
    font-weight: 700;
    font-size: 1.05rem;
    background: #e0f2fe;
}
.id-card-tile__info {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.id-card-tile__name {
    font-weight: 800;
    font-size: 0.95rem;
    line-height: 1.15;
    text-transform: uppercase;
    color: #0f172a;
}
.id-card-tile__school {
    color: #334155;
    font-weight: 700;
    line-height: 1.25;
    margin-top: 0.12rem;
    font-size: 0.65rem;
    text-transform: uppercase;
}
.id-card-tile__class-meta {
    color: #0f3d7a;
    font-weight: 800;
    font-size: 0.62rem;
    margin-top: 0.12rem;
    line-height: 1.2;
}
.id-card-tile__tag {
    display: inline-block;
    margin-top: 0.22rem;
    padding: 0.12rem 0.35rem;
    font-size: 0.58rem;
    font-weight: 700;
    color: #0f3d7a;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 0.25rem;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.id-card-tile__items {
    margin: 0.15rem 0 0 0.75rem;
    padding: 0;
    color: #1e293b;
    font-weight: 600;
    font-size: 0.62rem;
    line-height: 1.35;
}
.id-card-tile__items-more { color: #64748b; font-weight: bold; list-style: none; margin-left: -0.75rem; }

.id-card-tile__badge {
    flex-shrink: 0;
    align-self: center;
    width: 3.2rem;
    text-align: center;
    padding: 0.28rem 0.2rem;
    background: #f8fafc;
    border-radius: 0.35rem;
    border: 1.5px solid #0f3d7a;
    color: #0f3d7a;
}
.id-card-tile__badge-label {
    display: block;
    font-size: 0.48rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #475569;
}
.id-card-tile__badge-value {
    display: block;
    font-weight: 700;
    font-family: ui-monospace, monospace;
    font-size: 0.85rem;
    line-height: 1;
    margin-top: 0.06rem;
}

.id-card-tile__members {
    list-style: none;
    margin: 0;
    padding: 0.25rem 0 0;
    border-top: 1px solid #e2e8f0;
    overflow: hidden;
}
.id-card-tile__member {
    display: flex;
    justify-content: space-between;
    gap: 0.25rem;
    font-size: 0.56rem;
    line-height: 1.35;
    padding: 0.06rem 0;
}
.id-card-tile__member-name {
    font-weight: 700;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.id-card-tile__member-meta {
    color: #64748b;
    font-family: ui-monospace, monospace;
    flex-shrink: 0;
}
.id-card-tile__member--more { color: #94a3b8; font-weight: 400; }

.id-card-tile__footer {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.28rem 0.55rem;
    background: #0a2d5c;
    color: #fff;
}
.id-card-tile--volunteer .id-card-tile__footer { background: #064e3b; }
.id-card-tile--staff .id-card-tile__footer { background: #7c2d12; }
.id-card-tile__footer-id {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
}
.id-card-tile__footer-label {
    font-size: 0.48rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.75;
    flex-shrink: 0;
}
.id-card-tile__footer-value {
    font-size: 0.68rem;
    font-weight: 700;
    font-family: ui-monospace, monospace;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.id-card-tile__role {
    flex-shrink: 0;
    font-size: 0.5rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 0.12rem 0.35rem;
    background: rgba(201, 162, 39, 0.25);
    border: 1px solid rgba(201, 162, 39, 0.55);
    border-radius: 0.25rem;
    color: #f5e6b8;
}
.id-card-tile__footer-meta {
    flex-shrink: 0;
    font-size: 0.5rem;
    opacity: 0.85;
}
.id-card-tile__footer-schedule {
    flex-shrink: 0;
    font-size: 0.48rem;
    opacity: 0.8;
    max-width: 5rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
