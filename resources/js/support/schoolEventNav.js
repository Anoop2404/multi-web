/**
 * School admin — dedicated sidebar when working inside a single Sahodaya fest event.
 */

import { schoolEventBase, schoolProgramBase } from '@/support/eventHeadNav.js';
import { PREFIX_TO_SLUG, schoolProgramBySlug } from '@/support/schoolProgramNav.js';

/** @returns {{ programSlug: string, programPrefix: string, eventId: string }|null} */
export function detectSchoolEventFromUrl(url) {
    const full = url ?? '';
    const path = full.split('?')[0];

    const workspace = path.match(
        /\/school-admin\/[^/]+\/(kalotsav|sports|kids-fest|teacher-fest|english-fest|science-fest)\/events\/(\d+)(?:\/|$)/,
    );
    if (workspace) {
        return {
            programPrefix: workspace[1],
            programSlug: PREFIX_TO_SLUG[workspace[1]] ?? workspace[1],
            eventId: workspace[2],
        };
    }

    const reports = path.match(
        /\/school-admin\/[^/]+\/(kalotsav|sports|kids-fest|teacher-fest|english-fest|science-fest)\/reports\/(\d+)(?:\/|$)/,
    );
    if (reports) {
        return {
            programPrefix: reports[1],
            programSlug: PREFIX_TO_SLUG[reports[1]] ?? reports[1],
            eventId: reports[2],
        };
    }

    const festDay = path.match(
        /\/school-admin\/[^/]+\/(kalotsav|sports|kids-fest|teacher-fest|english-fest|science-fest)\/fest-day\/(\d+)(?:\/|$)/,
    );
    if (festDay) {
        return {
            programPrefix: festDay[1],
            programSlug: PREFIX_TO_SLUG[festDay[1]] ?? festDay[1],
            eventId: festDay[2],
        };
    }

    const requests = path.match(
        /\/school-admin\/[^/]+\/(kalotsav|sports|kids-fest|teacher-fest|english-fest|science-fest)\/events\/(\d+)\/(clash-requests|substitution-requests)(?:\/|$)/,
    );
    if (requests) {
        return {
            programPrefix: requests[1],
            programSlug: PREFIX_TO_SLUG[requests[1]] ?? requests[1],
            eventId: requests[2],
        };
    }

    const registration = path.match(
        /\/school-admin\/[^/]+\/(kalotsav|sports|kids-fest|teacher-fest|english-fest|science-fest)\/registration(?:\/|$)/,
    );
    if (registration) {
        const params = new URL(full, 'http://local').searchParams;
        const eventId = params.get('event');
        if (eventId) {
            return {
                programPrefix: registration[1],
                programSlug: PREFIX_TO_SLUG[registration[1]] ?? registration[1],
                eventId,
            };
        }
    }

    return null;
}

/** @returns {Array<{section: string, items: Array}>} */
export function schoolEventScopedNav(schoolId, programSlug, event, options = {}) {
    const {
        coordinatorMode = false,
        isSports = false,
    } = options;

    const program = schoolProgramBySlug(programSlug);
    const prefix = options.programPrefix ?? program?.prefix ?? 'sports';
    const eventId = event?.id;
    if (!eventId || !program) {
        return [];
    }

    const programBase = schoolProgramBase(schoolId, prefix);
    const eventBase = schoolEventBase(schoolId, prefix, eventId);
    const reportsBase = `${programBase}/reports/${eventId}`;

    const homeItems = coordinatorMode
        ? [{ label: '← My assignments', href: `/school-admin/${schoolId}`, icon: 'grid', exact: true }]
        : [{ label: 'Dashboard', href: `/school-admin/${schoolId}`, icon: 'grid', exact: true }];

    const workflowItems = [
        { label: 'Event overview', href: `${eventBase}/overview`, icon: 'grid', exact: true },
    ];

    if (isSports) {
        workflowItems.push(
            { label: 'Step 1 · Register students', href: `${eventBase}/registration`, icon: 'clipboard' },
            { label: 'Step 2 · Register by item head', href: `${eventBase}/items`, icon: 'layers' },
            { label: 'All reports', href: reportsBase, icon: 'file-text' },
        );
    } else {
        workflowItems.push(
            { label: 'Register students', href: `${eventBase}/registration`, icon: 'clipboard' },
            { label: 'All reports', href: reportsBase, icon: 'file-text' },
        );
    }

    workflowItems.push(
        { label: 'Clash requests', href: `${eventBase}/clash-requests`, icon: 'alert-circle' },
        { label: 'Substitutions', href: `${eventBase}/substitution-requests`, icon: 'repeat' },
        { label: 'Fest day view', href: `${programBase}/fest-day/${eventId}`, icon: 'calendar' },
    );

    const groups = [
        {
            section: coordinatorMode ? 'Assigned program' : 'School home',
            items: homeItems,
        },
        {
            section: program.label,
            items: [
                { label: `← ${program.label} hub`, href: programBase, icon: 'star' },
            ],
        },
        {
            section: event.title ?? 'This event',
            items: workflowItems,
        },
    ];

    if ((options.programEvents ?? []).length > 1) {
        groups.push({
            section: 'Switch event',
            items: options.programEvents
                .filter((ev) => String(ev.id) !== String(eventId))
                .slice(0, 5)
                .map((ev) => ({
                    label: ev.title,
                    href: `${schoolEventBase(schoolId, prefix, ev.id)}/overview`,
                    icon: 'layers',
                })),
        });
    }

    return groups;
}
