# School confirmation of State slots

The Sahodaya event's **State slot collection** page now has a **Copy school link** action. Its URL is `/state-slot-collection/{hubEventId}` on that Sahodaya's domain. A guest is sent to school login; after sign-in the same link routes the account to its own protected State slot choices. Existing school accounts are used. Other schools' lists and actions remain protected by school/event ownership checks.

## Candidate rules

- Only enabled, phase-correct Sahodaya items linked to the selected State program with a positive configured State quota contribute candidates.
- For published results, ranks one and two receive initial offers, limited by the configured quota (maximum two).
- Ranks three through five appear on the owning school's waiting list without response actions. An opt-out automatically offers the freed slot to the next eligible rank, stopping at rank five. A missing rank does not create an extra replacement slot.
- Unpublished items with one or two eligible competitive entries receive direct confirmation offers, within quota. Three or more require published results. A team/group is one competitive entry; standby and disqualified participants and withdrawn/rejected registrations are excluded. No marks, ranks or grades are manufactured for direct offers.
- An item with published source results does not mix an unpublished partition into its direct-entry pool.
- Unresolved ties at the available quota cutoff still need a committee choice. Later ties outside the cutoff do not block initial offers.
- Category uses the event's configured class/category labels; gender and participant type use readable labels on the admin and school pages.

Schools **Confirm registration** or opt out with a reason. Confirmations are revalidated against current eligibility; a late third registration can invalidate a previously direct offer. Final approval checks eligibility again and requires every selected offer to be accepted. Approved/certified lists cannot receive further school responses. Sending the approved list to State remains the existing winner-registration step.

No database migration is needed. Existing nomination records are preserved; records that no longer meet the rules require admin review rather than silent deletion.

Validation covers the real school-login return path, school isolation, category/gender display, State-quota filtering, direct offers at one/two versus three entries, group counting, disabled waiting ranks, promotion cutoff, missing-rank replacements, stale direct offers and approval locks.

Database isolation: school IDs are read from the central tenant registry first; the Sahodaya participant query filters registrations by those IDs. It must not embed a `tenants` subquery in the Sahodaya database. Regression coverage runs ranked and direct candidates against a separate database with no tenants table and verifies that a foreign cluster's school is excluded.

Opening performance: candidate eligibility and item quotas are loaded once inside the locked nomination transaction, then reused while inserting that operation's offers. The snapshot is cleared in a `finally` block and is never reused by later school responses or approvals. A regression fixture opening 16 slots reduces full candidate-pool loads from 33 to 1, and verifies that the next school acceptance performs a fresh load. No migration or frontend rebuild is required for this optimization.

Collection status UI: the event page shows confirmed-slot progress and clickable counts for complete items, pending school responses, unsent offers, items needing attention and unfilled items. Search covers item codes, titles, schools and participants; category/status filters and action-first sorting narrow the list. Each item has individual slot badges and separate offered-entry, waiting-list and opt-out sections. Completion requires valid school acceptances, not merely selected/offered slots. Invalid offers are marked for review and excluded from confirmed progress. Linked quota items without eligible candidates stay visible as awaiting results/entries. Opening/syncing buttons show progress and prevent duplicate clicks.
