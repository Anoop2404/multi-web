# Sports settings and reports review — 30 October 2026

Reviewed the Wayanad Athletic Meet (event 9) through the live browser. Read-only visits covered the event overview/setup, four settings groups, items, rounds, registrations, numbering, attendance, marks, mark settings, rank points, results, requests, schedule, certificates, ID cards, finance, catering, and the report hub's interactive reports and download packs. No registrations, marks, fees or published results were changed during the audit.

## Corrections

- Sports participation policies no longer fall back to an arts preset. Explicit individual, pair, team and overall caps are retained; arts stage/writing/drawing/common quotas and combination profiles are ignored for sports.
- Individual sports counts no longer depend on an item's stage type. The sports overall limit uses its explicitly configured cap rather than a synthetic sum of individual/team caps.
- Admin/school student-limit screens and their PDF/Excel exports use sports dimensions and age-category labels. Participation counts, readiness and pending-result pages/exports omit judge columns for sports.
- Sports settings omit arts presets and class-category scheme management; class schemes are no longer seeded/queried on a sports settings visit.
- Settings display one active section at a time, with section navigation above the form, wider content and correct active group styling. Automatic jumps to forms were removed. Public-display settings have their own page route, separate from their JSON configuration endpoint.
- Sports setup date cards accept date-only and timestamp inputs. Checklist descriptions wrap and completed tasks remain readable.
- Sports report packs omit Green Room, judge-sheet and area-wise arts operations. Championship report links point to the existing event championship workspace.

## Validation

- Final targeted PHP suite: 105 tests / 1,296 assertions passed (settings isolation, date/range and deletion safeguards, participation resolution/enforcement, catalog contracts, PDF rendering, policy save, absent/pending reports, bounded photo batches, photo cache isolation, ID card routes and MCQ hall-ticket regression). Four JavaScript tests also passed.
- Frontend production build passed; PHP syntax and whitespace checks passed.
- Live browser findings were recorded before deployment. These source changes are local until committed and deployed.
- The original whole-event student-limits PDF displayed a 502 response. An isolated CLI harness using the updated classes against live data now generates the complete report successfully: 1,214 student cards, 1,202 photos, 234 A4 pages, approximately 21 MB. Latest cold-cache timing: 6.2 seconds preparation + 7.9 seconds PDF rendering. All card sequence numbers 1–1,214 were verified in extracted text; first/last pages were visually checked. Public request handlers were not deployed or changed by this harness.

## Follow-up optimizations

- Batch cold private S3 photo reads with concurrency capped at eight, preferring existing thumbnails and falling back to originals. Existing local-storage/per-photo fallbacks remain available. Photo cache keys include the school and stored image path to prevent collisions and stale replacements.
- Apply search and over-limit filters before preparing photos. PDF/Excel/CSV URLs now follow the visible report's search and over-limit selection.
- Eager-load student photo timestamps and school tenancy once, instead of resolving schools once per embedded photo.
- School reports receive the event type and export sports dimensions consistently.
- Settings calculate lifecycle/checklist/verification data only in the lifecycle section, and marking progress only in Locks. Sports marking progress uses three aggregate queries rather than per-item queries.

## Full-length settings findings

The live event 9 Fees & Windows page measured 12,947 CSS pixels high. It renders billing, registration windows and participation policy together, including arts class-category controls. Navigation is repeated in the sidebar, the event navigation, group tabs, a section card and quick actions. The right column reduces the width available to dense forms. Numerous independent Save buttons make the scope of a save unclear.

| Area | Local correction / review outcome |
| --- | --- |
| Fees | Sports base rates and units match composite billing; pairs/trios are treated as team entries. Arts category-fee management is hidden. |
| Registration | Dedicated section, item search and age filter, bulk actions limited to shown items, dirty row values preserved after another row saves. Mobile actions wrap and tables scroll sideways. |
| Participation | Sports individual/pair/team/overall settings only; no arts preset fallback. Submitted-registration counting is exposed. |
| Eligibility | Correct inherited cutoff and configured age labels; explicit copy-inherited action. |
| Numbering | School ownership, duplicate school and range-bound validation; clearer labels. |
| Venues | Referenced venues/stages cannot be removed and silently lose schedule locations. |
| Records | Independent save payload, optional unit handling, visible errors. |
| Locks / operations | Partial saves preserve unrelated flags and child locks; lifecycle data loads only where needed. |
| Reports | Sports dimensions, configured category names and matching filtered exports; sports report packs omit arts operations. |

Additional safeguards cover event date ordering, used age-category deletion/reset, and age-label cache invalidation. Desktop/mobile component checks exercised asynchronous row saving, dirty-value preservation, search/category filtering and horizontal overflow.

## Remaining work / live data

- These changes are local, not deployed. The screenshot therefore still shows the older interface.
- Category-specific participation caps are not implemented: the current sports participation policy is event-wide. This needs a separate policy/data design if required.
- Event 9 currently has a saved start of 6 November 2026 and end of 7 October 2026. Confirm the intended end date before correcting live data; source validation now rejects newly submitted inverted ranges.
- The sports catalog receives `event_id` from setup, but its controller does not return an event context, so subsequent catalog navigation drops that context. This is a confirmed follow-up, not included in the corrections above.
- A further navigation simplification can reduce the repeated event-wide links and make each section's saved/inherited/unsaved state more prominent. Current local work focuses on section isolation and safe saving.
