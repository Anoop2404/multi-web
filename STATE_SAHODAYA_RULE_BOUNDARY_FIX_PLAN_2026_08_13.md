# State ↔ Sahodaya Rule-Boundary Fix Plan
**Date:** 2026-08-13 · **Policy confirmed with user:** Sahodaya runs its own level's events under its own rules (venue, schedule, fee, participation limits); once a participant/result is registered/nominated to the State level, State rules govern exclusively and Sahodaya can no longer alter that record.

This plan is split into the two sides of that boundary, per your request. Each item is marked **[CONFIRMED OK — no action]** where code already enforces it correctly, or **[GAP — fix needed]** where it doesn't yet.

---

## Set 1 — Sahodaya-level autonomy (protect their own-round rules)

The problem: Sahodaya *can* already edit these settings, but two of them get **silently reverted** the next time the State re-publishes/syncs the program — which contradicts "Sahodaya runs their level based on their rules." This isn't a permissions bug (no 403), it's a data-durability bug (a valid edit doesn't stick).

1. **[GAP]** `FestStateProgramService::syncTenantEvent()` (`app/Services/Events/FestStateProgramService.php`) unconditionally overwrites `title`, `registration_open/close`, `event_start/end`, `venue`, `fee_type`, `fee_amount`, `description`, `scoring_preset` on **every** state publish/sync — even on events the Sahodaya has already customized after initial creation.
   - **Fix:** only seed these fields on first creation (`createTenantEvent()` already does this correctly). On subsequent syncs, either skip fields entirely (Sahodaya owns them from creation onward) or track which fields the Sahodaya has actively edited (e.g. an `updated_by_user_id`/timestamp per field, or a simpler `sahodaya_customized_at` flag on the event) and only let State's push apply to fields still at their original state-seeded value.
   - **Recommended (simplest, matches how items already work):** treat sync as create-or-touch-nothing — `syncTenantEvent()` stops writing these fields on an existing event entirely; only genuinely state-owned metadata (e.g. which conduct levels exist, the link to `state_program_id`) stays synced.
   - **Effort:** S–M. **Risk:** Low — narrows current overwrite behavior, doesn't add new restrictions.

2. **[GAP]** `FestParticipationPolicyService::copyFromStateProgram()` runs on both `createTenantEvent()` and `syncTenantEvent()` — a Sahodaya's custom participation policy (saved via `FestParticipationPolicyController::store()`) gets silently reset to the State's `level_policies` on every resync.
   - **Fix:** same pattern as #1 — only apply `copyFromStateProgram()` at creation time. Once a Sahodaya has an active `FestParticipationPolicy` row for the event, sync should leave it alone.
   - **Effort:** S. **Dependencies:** none, can ship independently of #1.

3. **[GAP]** No audit/visibility when a Sahodaya's customization diverges from the State's current definition. Once #1/#2 land, a State Admin publishing an update won't know which Sahodayas already have a locally-customized value that didn't get the update.
   - **Fix:** surface a simple indicator ("customized locally, not synced from State's last update") on the Sahodaya's event-settings page and on the State Admin's propagation view. Not a blocker for #1/#2, but do it in the same pass so the new "sticky" behavior isn't invisible.
   - **Effort:** S (backend flag) + S (UI badge).

4. **[Confirm, likely fine]** `FestSchoolVerificationController::verify()` has no phase lock at all (flagged in the earlier audit, E-02). Since this is purely a Sahodaya-internal workflow step (their own document-verification bookkeeping, not a State rule), it correctly stays Sahodaya-controlled — no change needed for the State/Sahodaya boundary itself. Still worth the earlier-flagged fix (log/flag late toggles) as a separate, lower-priority item — listed here only so it isn't lost, not because it touches this boundary.

5. **[Verify]** Confirm the "enable Kalotsavam" nav toggle (`SahodayaProfile.nav_visibility` via `NavVisibilityController`) is actually settable by a Sahodaya Admin themselves, not Super-Admin-only. If it turns out to be admin-only today, that's inconsistent with "Sahodaya runs their level based on their rules" — a Sahodaya should be able to turn their own Kalotsavam section on/off (subject to the platform-level hard-override cap, which is correctly Super-Admin-only). **Effort:** Investigation S, fix S if needed.

---

## Set 2 — State-level rule enforcement (protect what's State-owned once a record crosses the boundary)

Good news first: most of this boundary is already enforced correctly. Confirmed by reading the actual guard clauses, not assumed:

1. **[CONFIRMED OK]** State catalog items (`owner_level = 'state'`) cannot be edited or deleted from the Sahodaya side — `FestEventController::updateItem()`/`destroyItem()` both `abort_if($item->isStateCatalog(), 422, ...)`.
2. **[CONFIRMED OK]** A State-linked event cannot be deleted from Sahodaya admin — `FestEventController::destroy()` `abort_if($event->isStateProgram(), 422, ...)`.
3. **[CONFIRMED OK]** Once a State-nomination batch is certified, selections are locked — `FestStateNominationService::select()` `abort_if($batch->isCertified(), 422, 'This nomination batch is already certified — withdraw/replace instead of re-selecting.')`.
4. **[CONFIRMED OK]** No `SahodayaAdmin`-namespace controller writes to `StateQualifierIntake`/`StateQualifierEntry` — review/approve of submitted qualifiers is exclusively under `StateAdmin` routes (`state.admin` middleware). Grepped the whole `app/Http/Controllers/SahodayaAdmin/` tree to confirm zero references.

Remaining gaps/unknowns on this side:

5. **[GAP — needs a quick fix]** `FestEventController::update()` excludes `event_type` from the validated field set for a State-linked event only by `unset($data['event_type'])` after validation — i.e., it's silently dropped from that one request, not actively blocked. This works for the standard web form, but hasn't been checked against other write paths (bulk edit, API, import) that might set `event_type` a different way. **Action:** grep every place that mass-assigns `FestEvent` fields (imports, API controllers, `FestCascadeService`) for a similar unguarded `event_type` write on a state-linked event, and centralize the guard (e.g. a model-level `saving` guard on `FestEvent` that refuses to change `event_type`/`state_program_id` once `state_program_id` is set) instead of relying on each controller remembering to unset it. **Effort:** S investigation, S–M fix depending on findings.

6. **[Verify — not checked this pass]** Confirm there's no Sahodaya-facing endpoint that can write to `fest_state_program_items` (the State's own master catalog) directly, as opposed to the tenant-local `FestEventItem` copies (already confirmed locked). Expected to be fine since `FestStateProgramItem` only appears wired to `Admin\StateFestProgramController`, but wasn't grepped for stray write paths this pass. **Effort:** S investigation.

7. **[Related structural gap, from the earlier full audit]** The dual State Admin routing/architecture (`admin.state.*` in `routes/web.php` live today vs `state.portal.*` in `routes/state.php`, currently inert, plus two near-duplicate Vue page trees) is the same "what's State's vs what's Sahodaya's" boundary problem, just at the routing/ownership layer rather than the data layer. Worth resolving as part of this same effort so the boundary is consistent end-to-end, not just field-by-field. **Effort:** M, needs your decision on which routing path is canonical (flagged as F-01 in the full audit).

8. **[Related, lower priority]** No DB-level unique constraint on `fest_participants` (E-05, full audit) — tangential to this boundary, but relevant here too: `FestStateNominationService::candidatePool()` builds its State-nomination candidate list directly from tenant-side marks/participants, so a duplicate/double-registered participant would also corrupt what gets nominated to State. Worth fixing in the same window since the root cause (`fest_participants` schema) is shared.

---

## Suggested build order

1. Set 1, items 1–2 (stop the silent overwrite — this is the one actively contradicting the policy you just confirmed).
2. Set 2, item 5 (centralize the `event_type`/`state_program_id` immutability guard at the model level — cheap, closes a possible backdoor).
3. Set 1, item 3 (customization-visible indicator) + Set 2, item 6 (verify no stray write path) together — both are quick investigation/UI passes.
4. Set 2, item 8 (fest_participants unique constraint) — do this with its own migration + dedup pass, independent of the rest.
5. Set 2, item 7 / Set 1 item 5 — larger, decision-gated items (dual routing architecture, nav-visibility ownership) — schedule once 1–4 are done.

Want me to start on #1 (Set 1, items 1–2 — stopping the sync from clobbering Sahodaya's customized event fields and participation policy)? That's the most concrete, highest-value fix and doesn't depend on anything else.
