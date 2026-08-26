<template>
    <SahodayaAdminLayout title="Fest tools hub" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Fest tools hub" eyebrow="Fest & events"
                    description="Shared tools that apply across every fest program — certificates, ID cards, appeals, payments, and display screens.">
        </PageHeader>

        <div class="hub-grid">
            <HubCard v-if="menuOn('fest_appeals')" :href="links.appeals" icon="⚖️" label="Appeals queue"
                     :hint="pendingFestAppealsCount ? `${pendingFestAppealsCount} pending` : 'Review appeals raised across all fest events'" />
            <HubCard v-if="menuOn('fest_payments')" :href="links.payments" icon="💳" label="Fest payments" hint="Verify school event fee proofs" />
            <HubCard v-if="menuOn('display_screens')" :href="links.display_screens" icon="🖥️" label="Display screens" hint="Public results/announcement screens" />
            <HubCard v-if="menuOn('certificate_templates')" :href="links.certificate_templates" icon="🎓" label="Certificate templates" hint="Design templates used across events" />
            <HubCard v-if="menuOn('id_card_templates')" :href="links.id_card_templates" icon="🪪" label="ID card templates" hint="Design participant ID card layouts" />
            <HubCard :href="links.find_certificate" icon="🔍" label="Find certificate" hint="Look up a participant's issued certificate" />
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import HubCard from '@/Components/ui/HubCard.vue';
import { isNavMenuVisible } from '@/support/sahodayaAdminNav.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    pendingFestAppealsCount: { type: Number, default: 0 },
    links: Object,
});

const page = usePage();

function menuOn(key) {
    return isNavMenuVisible(page.props.navVisibility, key);
}
</script>
