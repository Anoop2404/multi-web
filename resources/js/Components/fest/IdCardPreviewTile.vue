<template>
    <div class="id-card-tile"
         :class="[
             `id-card-tile--${card.role_class}`,
             card.card_type === 'team' ? 'id-card-tile--team' : '',
             card.card_type === 'event_participant' ? 'id-card-tile--event-pass' : '',
             variant === 'premium' ? 'id-card-tile--premium' : '',
         ]">
        <div v-if="variant === 'premium'" class="id-card-tile__stripe"></div>
        <div class="id-card-tile__head">
            <div class="id-card-tile__head-text">
                <span class="id-card-tile__org">{{ clusterName }}</span>
                <span v-if="variant === 'premium' && eventTitle" class="id-card-tile__event">{{ eventTitle }}</span>
            </div>
            <span class="id-card-tile__role">{{ card.role_label }}</span>
            <img v-if="card.qr_src" :src="card.qr_src" alt="" class="id-card-tile__qr">
        </div>

        <div v-if="card.card_type === 'event_participant'" class="id-card-tile__badge id-card-tile__badge--pass">
            Event participant pass
        </div>
        <div v-else-if="card.item_label && variant === 'premium'" class="id-card-tile__badge id-card-tile__badge--item">
            {{ card.item_label }}
        </div>

        <template v-if="card.card_type === 'team'">
            <div class="id-card-tile__body id-card-tile__body--team">
                <div class="id-card-tile__info">
                    <p class="id-card-tile__name">{{ card.name }}</p>
                    <p class="id-card-tile__sub">{{ card.subtitle }}</p>
                    <p class="id-card-tile__detail">{{ card.detail }}</p>
                    <p v-if="card.schedule" class="id-card-tile__schedule">{{ card.schedule }}</p>
                    <ul class="id-card-tile__members">
                        <li v-for="(member, idx) in (card.members ?? []).slice(0, 5)" :key="idx">
                            {{ member.name }} · {{ member.fest_id }}
                            <span v-if="member.chest"> · {{ member.chest }}</span>
                        </li>
                        <li v-if="(card.member_count ?? 0) > 5" class="id-card-tile__members-more">
                            + {{ card.member_count - 5 }} more
                        </li>
                    </ul>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="id-card-tile__body">
                <div class="id-card-tile__avatar">
                    <img v-if="card.photo_url" :src="card.photo_url" :alt="card.name" class="id-card-tile__photo">
                    <span v-else class="id-card-tile__initials">{{ card.initials }}</span>
                </div>
                <div class="id-card-tile__info">
                    <p class="id-card-tile__name">{{ card.name }}</p>
                    <p class="id-card-tile__sub">{{ card.subtitle }}</p>
                    <ul v-if="card.card_type === 'event_participant' && card.items?.length" class="id-card-tile__items">
                        <li v-for="(itemTitle, idx) in card.items.slice(0, 4)" :key="idx">{{ itemTitle }}</li>
                        <li v-if="(card.item_count ?? 0) > 4" class="id-card-tile__items-more">
                            + {{ card.item_count - 4 }} more
                        </li>
                    </ul>
                    <p v-else class="id-card-tile__detail">{{ card.detail }}</p>
                    <p v-if="card.schedule" class="id-card-tile__schedule">{{ card.schedule }}</p>
                </div>
            </div>
        </template>

        <div class="id-card-tile__ids">
            <div>
                <p class="id-card-tile__id-label">{{ card.id_label }}</p>
                <p class="id-card-tile__id-value">{{ card.id_number }}</p>
            </div>
            <div>
                <p class="id-card-tile__id-label">{{ card.secondary_label }}</p>
                <p class="id-card-tile__id-value id-card-tile__id-value--sm">{{ card.secondary_value }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    card: { type: Object, required: true },
    clusterName: { type: String, default: 'Sahodaya' },
    eventTitle: { type: String, default: '' },
    variant: { type: String, default: 'standard' },
});
</script>

<style scoped>
.id-card-tile {
    border: 1px solid #cbd5e1;
    border-radius: 0.75rem;
    overflow: hidden;
    background: #fff;
    font-size: 0.65rem;
    color: #0f172a;
}
.id-card-tile--premium {
    border-color: #94a3b8;
    box-shadow: 0 4px 14px rgba(15, 61, 122, 0.08);
}
.id-card-tile__stripe {
    height: 0.2rem;
    background: linear-gradient(90deg, #c9a227, #f4e4a6, #c9a227);
}
.id-card-tile--event-pass.id-card-tile--premium .id-card-tile__stripe {
    background: linear-gradient(90deg, #047857, #6ee7b7, #047857);
}
.id-card-tile__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.5rem;
    color: #fff;
    font-weight: 700;
    letter-spacing: 0.04em;
}
.id-card-tile--student .id-card-tile__head { background: #0f3d7a; }
.id-card-tile--volunteer .id-card-tile__head { background: #047857; }
.id-card-tile--staff .id-card-tile__head { background: #7c2d12; }
.id-card-tile--premium.id-card-tile--student .id-card-tile__head {
    background: linear-gradient(135deg, #0f3d7a, #1e5aa8);
}
.id-card-tile__head-text { flex: 1; min-width: 0; }
.id-card-tile__org {
    display: block;
    font-size: 0.55rem;
    text-transform: uppercase;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.id-card-tile__event {
    display: block;
    font-size: 0.5rem;
    font-weight: 600;
    opacity: 0.85;
    margin-top: 0.05rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.id-card-tile__role { font-size: 0.6rem; flex-shrink: 0; }
.id-card-tile__qr { width: 1.75rem; height: 1.75rem; flex-shrink: 0; background: #fff; border-radius: 0.15rem; }
.id-card-tile__badge {
    text-align: center;
    font-size: 0.5rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 0.2rem 0.5rem;
}
.id-card-tile__badge--pass { background: #ecfdf5; color: #047857; border-bottom: 1px solid #a7f3d0; }
.id-card-tile__badge--item { background: #eff6ff; color: #1e40af; border-bottom: 1px solid #bfdbfe; }
.id-card-tile__body {
    display: flex;
    gap: 0.5rem;
    padding: 0.5rem;
}
.id-card-tile__body--team { display: block; }
.id-card-tile__avatar {
    width: 2.5rem;
    height: 2.5rem;
    flex-shrink: 0;
}
.id-card-tile__photo,
.id-card-tile__initials {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 9999px;
    object-fit: cover;
}
.id-card-tile--premium .id-card-tile__photo,
.id-card-tile--premium .id-card-tile__initials {
    border: 2px solid #c9a227;
}
.id-card-tile__initials {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e2e8f0;
    color: #0f3d7a;
    font-weight: 700;
    font-size: 0.85rem;
}
.id-card-tile__name {
    font-weight: 700;
    font-size: 0.75rem;
    line-height: 1.2;
}
.id-card-tile__sub,
.id-card-tile__detail {
    color: #475569;
    line-height: 1.25;
    margin-top: 0.1rem;
}
.id-card-tile__schedule {
    color: #64748b;
    margin-top: 0.15rem;
    font-size: 0.58rem;
}
.id-card-tile__items {
    margin: 0.25rem 0 0 0.85rem;
    padding: 0;
    color: #334155;
    font-size: 0.55rem;
    line-height: 1.35;
}
.id-card-tile__items-more { color: #94a3b8; list-style: none; margin-left: -0.85rem; }
.id-card-tile__members {
    margin-top: 0.35rem;
    padding-left: 0.9rem;
    color: #334155;
    font-size: 0.58rem;
    line-height: 1.35;
}
.id-card-tile__members-more { color: #94a3b8; list-style: none; margin-left: -0.9rem; }
.id-card-tile--team { min-height: 9rem; }
.id-card-tile__ids {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.25rem;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
    padding: 0.35rem 0.5rem;
}
.id-card-tile--premium .id-card-tile__ids {
    background: linear-gradient(180deg, #f8fafc, #f1f5f9);
}
.id-card-tile__id-label {
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #94a3b8;
    font-size: 0.5rem;
}
.id-card-tile__id-value {
    font-weight: 700;
    font-family: ui-monospace, monospace;
    color: #0f3d7a;
    font-size: 0.7rem;
}
.id-card-tile__id-value--sm { font-size: 0.62rem; }
</style>
