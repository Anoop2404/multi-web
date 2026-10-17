# State eligibility fixes — 16 October 2026

## Fixed

- Category and team-size findings now participate in State qualifier approval, scrutiny finalisation, materialization and item-result publication. Confirmed violations stop the action before it writes an approval or publication.
- Gender is carried from managed student records through the qualifier API and roster to State participant metadata. External school entry and State qualifier editing have gender fields. Male/female item restrictions are checked; open/mixed items remain unrestricted.
- Event Settings now supports total, individual and group item limits per student. Counts use competing members, excluding standbys and withdrawn participants, and include the entire prospective batch plus other approved entries.
- Duplicate participation in the same item is detected using student identity scoped to Sahodaya and school. Student source IDs are preferred; school roll numbers are a fallback. Source participant IDs are deliberately not used because a person receives a different participant row in each item. Names alone never establish identity.
- Missing class/category/gender, and missing identity when a student limit is configured, remain explicit audit findings. “Require verified eligibility” makes these findings blocking too. It defaults off for existing events; confirmed violations are blocking regardless of this setting.
- Roster export now selects the nominated team within a shared registration, or the nominated individual. It carries actual school class names, stable student IDs, gender and standby flags. Materialization preserves standby and leader flags instead of counting every member as a performer.
- Direct qualifier edits and additions use the approval service for approved statuses, preventing that screen from bypassing quotas and eligibility. Finalised intakes must be reopened before editing. New manual entries resolve item codes to catalog IDs instead of inventing an item UUID.
- Fixed the winner-sheet/slot-collection call to the nomination source-events method, which was inaccessible because it was private.

## Configuration and existing data

In State event → Settings, set the official total/individual/group caps. Blank means uncapped; no numeric State policy was assumed. Load the event's class categories under Categories & Eligibility, review findings, correct source data, then enable Require verified eligibility if all evidence must be complete.

Existing records without gender or stable student identity cannot be reconstructed safely from names. Correct or resubmit those records. Source student ID and roll-only records are distinct identity schemes: old roll-only records should be reconciled before relying on a complete historical duplicate audit.

These checks gate State approval and result publication. Sahodaya nomination selections can still be drafted before State approval; they do not represent an approved State entry. The separate State database or remote API must remain available for its existing approval workflow.

No schema migration is required for these changes: policy is stored in event settings and additional participant fields in metadata. Production deployment and live-data auditing are separate from local verification.

## Verification

- State feature suite: 363 passed, 1,223 assertions. One existing test in StateReportCatalogTest has no assertions and is reported as risky.
- Final payload and external school portal checks: 12 passed, 44 assertions, including the new separate-team roster regression.
- Production frontend build and whitespace checks passed.
