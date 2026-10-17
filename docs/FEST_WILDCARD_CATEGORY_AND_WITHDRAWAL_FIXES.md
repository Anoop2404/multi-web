# Wildcard appeals: category filtering, corrections and withdrawn entries

Updated 17 October 2026.

- School and Sahodaya forms fetch eligible items after a student is selected. They check the event's actual category scheme, class or age eligibility, gender restrictions and enabled items. Open/mixed items remain available when applicable. Student and appeal-type changes clear stale item selections; aborted requests cannot overwrite a newer selection.
- Submission and final approval recheck eligibility. Wildcards waive selection/qualification; they do not waive category or gender rules. Sports age checks also run when the item has no class category.
- An approved Sahodaya wildcard whose granted registration was withdrawn or rejected no longer blocks a new request. The original appeal and registration remain in the audit history. Active requests and active competing entries still block duplicate grants. Student locks serialize grants; resolved appeals cannot be resolved twice.
- Sahodaya administrators can use **Correct item** for an existing pending or approved wildcard. A reason is required and logged. For a linked active registration, the action updates both the appeal and registration, preserves participant IDs and event registration numbers, and regenerates item/chest numbers.
- Corrections cannot silently move entries with marks, judge scores, published item results, or participant performance schedules. Already-submitted State wildcards must be corrected through State scrutiny. Withdrawn entries use a new request rather than moving their historical registration. Approved appeals without a linked registration require review.
- The appeals list shows the real origin school and category/gender/code labels so identically titled items can be distinguished.

These are application changes, not a live-data correction script. Existing incorrect entries must be reviewed using Correct item after deployment. No schema migration is required.
