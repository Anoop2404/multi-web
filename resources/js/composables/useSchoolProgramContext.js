import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    detectSchoolProgramFromUrl,
    schoolProgramBySlug,
    schoolProgramHref,
    SCHOOL_FEST_PROGRAMS,
} from '@/support/schoolProgramNav.js';

const VALID_SLUGS = new Set(SCHOOL_FEST_PROGRAMS.map((p) => p.slug));

/**
 * Resolve fest program slug/label for school admin program pages.
 * URL is the source of truth; props are fallbacks (never trust raw UUID strings).
 */
export function useSchoolProgramContext(props = {}) {
    const page = usePage();

    const programSlug = computed(() => {
        const fromUrl = detectSchoolProgramFromUrl(page.url);
        if (fromUrl) {
            return fromUrl;
        }

        const meta = props.programMeta ?? page.props.programMeta;
        if (meta?.slug && VALID_SLUGS.has(meta.slug)) {
            return meta.slug;
        }

        const program = props.program ?? page.props.program;
        if (typeof program === 'string' && VALID_SLUGS.has(program)) {
            return program;
        }
        if (program?.slug && VALID_SLUGS.has(program.slug)) {
            return program.slug;
        }

        return 'kalotsav';
    });

    const programLabel = computed(() => {
        const meta = props.programMeta ?? page.props.programMeta;
        if (meta?.label) {
            return meta.label;
        }

        const program = props.program ?? page.props.program;
        if (typeof program === 'object' && program?.label) {
            return program.label;
        }

        return schoolProgramBySlug(programSlug.value)?.label ?? programSlug.value;
    });

    const programBase = computed(() => {
        const schoolId = props.school?.id ?? page.props.school?.id ?? '';
        return schoolProgramHref(schoolId, programSlug.value);
    });

    return { programSlug, programLabel, programBase };
}
