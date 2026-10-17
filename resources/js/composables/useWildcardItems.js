import { computed, ref, watch } from 'vue';

export function useWildcardItems(form, endpoint) {
    const items = ref([]);
    const student = ref(null);
    const loading = ref(false);
    const error = ref('');
    watch(() => [form.student_id, form.appeal_type], async ([studentId, type], previous, onCleanup) => {
        form.item_id = '';
        items.value = [];
        student.value = null;
        error.value = '';
        loading.value = false;
        if (!studentId || !['sahodaya_wildcard', 'state_wildcard'].includes(type)) return;
        const controller = new AbortController();
        onCleanup(() => controller.abort());
        loading.value = true;
        try {
            const response = await fetch(endpoint + '?' + new URLSearchParams({ student_id: studentId, appeal_type: type }), {
                headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller.signal,
            });
            if (!response.ok) throw new Error('Could not load eligible items. Select the student again to retry.');
            const result = await response.json();
            if (controller.signal.aborted) return;
            items.value = result.items;
            student.value = result.student;
        } catch (failure) {
            if (!controller.signal.aborted) error.value = failure.message;
        } finally {
            if (!controller.signal.aborted) loading.value = false;
        }
    });
    const options = computed(() => items.value.map((item) => ({ value: item.id,
        label: [item.title, item.category_label, item.type_label, item.gender_label, item.item_code].filter(Boolean).join(' · '),
    })));
    return { options, student, loading, error };
}
