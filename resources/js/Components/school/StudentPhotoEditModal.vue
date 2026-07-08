<template>
    <Teleport to="body">
        <div v-if="modelValue && student" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="close"></div>
            <div class="relative modal-shell max-w-md w-full">
                <div class="modal-head">
                    <div>
                        <h3 class="font-bold text-[#041525]">{{ modalTitle }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ student.name }} · pick a new image below. Cancel to keep the current photo.
                        </p>
                    </div>
                    <button type="button" @click="close" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <ProfilePhotoCropper v-model="photoFile" :existing-url="student.photo_url" />
                    <p v-if="photoForm.errors.photo" class="text-xs text-red-500">{{ photoForm.errors.photo }}</p>
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="close" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                        <button type="submit" :disabled="photoForm.processing || !(photoFile instanceof File)"
                                class="btn-primary disabled:opacity-50">
                            {{ photoForm.processing ? 'Saving…' : 'Save photo' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ProfilePhotoCropper from '@/Components/school/ProfilePhotoCropper.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    student: { type: Object, default: null },
    schoolId: { type: [String, Number], required: true },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const photoFile = ref(null);
const photoForm = useForm({});

const modalTitle = computed(() => {
    if (props.student?.has_photo || props.student?.photo_url) {
        return 'Change photo';
    }
    return 'Add photo';
});

watch(() => props.modelValue, (open) => {
    if (open) {
        photoFile.value = null;
        photoForm.clearErrors();
    }
});

function close() {
    emit('update:modelValue', false);
    photoFile.value = null;
    photoForm.reset();
}

function submit() {
    if (!props.student?.id || !(photoFile.value instanceof File)) {
        return;
    }

    photoForm
        .transform(() => ({ photo: photoFile.value }))
        .post(`/school-admin/${props.schoolId}/students/${props.student.id}/photo`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                close();
                emit('saved');
            },
        });
}
</script>
