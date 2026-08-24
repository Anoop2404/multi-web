# Path Breaks — Fest/Event Flow Audit (2026-08-23)

Starting from the Malappuram Sahodaya tenant homepage (`http://malappuramsahodaya.test:8000/`), this document maps the **Fest/Event module specifically** — public discovery → School Admin registration → Sahodaya Admin event management/mark entry/publish → back out to the public scoreboard — and flags every place that flow breaks. **No application code was changed while producing this document.**

Per your direction this pass is scoped to the Event/Fest module only (not the whole platform) and covers: Public visitor, School Admin, Sahodaya Admin (full access), and a Scoped Event Admin — skipping the ~15 granular Fest staff roles (judge, mark-entry coordinator, certificate collector, etc.).

---

## Read this first — relationship to prior audits

This repo already has a very thorough platform-wide audit from four days ago, now preserved at [`Documents/Path_breaks.2026-08-18-platform-audit.md`](Path_breaks.2026-08-18-platform-audit.md) (this file used to hold that content — it's backed up, not deleted). That audit covered navigation/permission correctness across the *entire* platform, mostly via static code analysis for the Sahodaya/School Admin roles because live browser testing broke partway through that session. Its findings were subsequently implemented and verified.

This document is different in two ways:
1. **Scope**: Fest/Event flows only, per your instruction this session.
2. **Method**: primarily **live, in-browser testing** — actually clicking through as each role — supplemented with static code/DB cross-reference to pin down exact root causes. This catches a different, deeper class of bug than a nav-link audit: things that only break when real data flows through the system (a save that silently fails, a roster that shows the wrong participants), not just "does this link 404."

**One more thing worth knowing**: partway through this session, a concurrent Claude session was actively committing fixes to `FestPortalController.php` (public results-visibility gating — 5 commits landed same-day, timestamped minutes before I checked them). Those fixes are verified as **already in place** below, not reported as new findings.

---

## Coverage

| Area | Method | Status |
|---|---|---|
| Public homepage → Fest portal discovery | Live | ✅ Done |
| Public Fest event pages (schedule/live/records/manual) | Live | ✅ Done (one representative in-progress event) |
| Public results/scoreboard visibility gating | Static (code read) | ✅ Done — traced every method in `FestPortalController` |
| Public results/scoreboard rendering (published event) | Live | ✅ Done |
| School Admin: dashboard, registration flow (Steps 1–2) | Live | ✅ Done |
| Sahodaya Admin: event workspace, mark entry, publish pipeline | Live + static | ✅ Done |
| Scoped Event Admin: permission boundaries | Live | ✅ Done for Kalotsav, Sports Meet, Kids Fest; not checked for Teacher/Science Fest/Custom Events |
| Granular Fest staff roles (judge, mark-entry coordinator, etc.) | — | Out of scope this pass, per your direction |
| Non-Fest modules (billing, MCQ, training, CMS, tenant/state admin) | — | Out of scope this pass — see the Aug-18 doc |

---

## Findings

### 1. [CRITICAL] Mark entry shows "Saved ✓" while silently discarding the mark — and the cause affects every region-partitioned event's default view

**Where:** Sahodaya Admin → any Fest program's event workspace → **Marks** tab, viewed at the hub level with no region selected (i.e. the default "All Regions" view you land on first).

**What happens:** I entered a real mark (Rank: 1st, Score: 95, auto-graded A+) for the one registered participant shown under "Recitation-Malayalam," clicked Save, and got a clean "Saved ✓" confirmation. Checking the Results/Publish tab for the exact same item immediately after: **"No marks entered yet."** Checking the database directly: zero `FestMark` rows exist for that item. The save never happened — the success message was false.

**Root cause:**
- The Marks page (`FestMarkEntryController::index()`) builds its participant roster by aggregating registrations across **every child/region event** under the hub (`reportableEventIds()`/`reportableItemIds()`) — by design, since region-partitioned events like "Kerala State Kalotsavam 2026" register schools into region-specific child events (Digi Fest, District Kalotsav, Off Stage × 2 regions, Sargadhara × 2 regions), not the hub directly.
- The Save action (`FestMarkSaveService::save()`) validates the mark against the literal event in the URL — the hub itself — and correctly rejects any participant whose real registration lives on a child event (`abort_if($participant->registration->event_id !== $event->id, 403)`).
- So the page you land on by default shows you a roster it structurally cannot save marks against, for exactly the participants who are region-assigned — which, for a partitioned event, is normal, not an edge case.
- The frontend does not surface this rejection to the user — it displays success regardless.

**Effect:** A Sahodaya admin doing routine mark entry from the default hub view believes marks are being recorded. Nothing is actually saved. This would likely only be discovered at publish time — or when results never appear on the public scoreboard — days or weeks later, far from when the mistake was made.

**Confidence:** Confirmed live (reproduced once cleanly) + confirmed via direct database check + root cause traced to specific code (`app/Http/Controllers/SahodayaAdmin/FestMarkEntryController.php:38,71-80`, `app/Http/Controllers/SahodayaAdmin/Concerns/ResolvesRegionAwareReportEvent.php:17-26`, `app/Services/Events/FestMarkSaveService.php:26-29`). The exact frontend code that swallows the server rejection was not traced to a specific line — the effect is confirmed, the precise Vue-side cause is not.

---

### 2. [CRITICAL] A Sahodaya admin scoped to one program has full access to other programs they are not assigned to

**Where:** Sahodaya Admin login as a scoped role (tested with `event_admin`, assigned only to English Fest).

**What happens:** The sidebar correctly narrows to show only "English Fest." But navigating directly to another program's URL:
- `/sahodaya-admin/{tenant}/kalotsav` → correctly blocked, "Access denied — You don't have permission to access this page." Scoping works here.
- `/sahodaya-admin/{tenant}/sports` → **loads the complete, real Sports Meet admin workspace** — 1 real event, 13 real athletes, a working "+ Create event" button, full access to Item catalog, Age categories, Athletic records, Cluster results, School rankings, House championship.
- `/sahodaya-admin/{tenant}/kids-fest` → **also fully open** — Item Catalog (71 items), Category masters, the full 4-school participation directory, "+ Create event."

Confirmed reproducible via direct URL navigation (not an accidental click) — the "Scoped access" badge and "English Fest Admin" label stay visible throughout, i.e. the app knows exactly who's logged in and still lets them in.

**Pattern:** Kalotsav is the only program with real scope enforcement for this role. Sports Meet and Kids Fest are both wide open. Teacher Fest, Science Fest, and Custom Events weren't checked in this pass due to time, but given the pattern, they're likely affected too.

**Effect:** A role that exists specifically to restrict staff to one assigned program (`event_admin`/`region_admin`) can view real student/athlete data and, via visibly-enabled controls, very likely create or modify events in programs they have no assignment to. This is a real authorization boundary failure, not a cosmetic one. (I stopped at confirming read access and the presence of enabled write controls — I did not actually create or modify anything in an out-of-scope program, to stay within the audit's remit.)

**Minor related bug, same area:** clicking their own *in-scope* "English Fest" card on the "All events" hub page produces "You are not assigned to this event" — the one program this admin should be able to open the easy way is itself broken; direct sidebar navigation still works.

**Confidence:** Confirmed live, reproduced cleanly via direct URL navigation for both Sports Meet and Kids Fest.

---

### 3. [HIGH] Duplicate fee rows make the School Admin dashboard show padded, indistinguishable "pending actions"

**Where:** School Admin dashboard → "Action required" widget.

**What happens:** The widget showed "10 pending" actions, but 9 of the 10 rows were exact duplicates of just two messages: "Event fee — Kerala State Kalotsavam 2026 fees awaiting upload" appeared **6 times**, and "Event fee — Kalotsav 2026-27 fees awaiting upload" appeared **3 times** — with no way to tell whether that's a real backlog of six separate obligations or a glitch.

**Root cause:** Six separate `FestSchoolEventFee` database rows exist for the same (school, event) pair — confirmed these are not legitimate per-head or per-phase billing rows (both explicitly checked off), just plain duplicates with varying amounts, created in clusters at identical timestamps. This points to the fee-recalculation job (`FestSchoolEventFeeService::recalculate()`, triggered by `RecalculateEventSchoolFeesJob`) inserting a new row each time it runs instead of updating the school's existing pending one. The dashboard widget (`ProgramHubDataService::schoolPendingActions()`) then faithfully lists every row as a separate action, with no de-duplication and no way to distinguish one from another.

**Effect:** A school admin can't tell if this is real (owe six separate amounts) or broken, likely to be ignored either way. Not yet checked whether the same un-deduplicated table inflates a balance total anywhere else (the dashboard's headline "Fees due" figure did *not* match a naive sum of these rows, so that particular number looks unaffected, but Sahodaya-side finance/ledger views weren't checked).

**Confidence:** Confirmed via direct database inspection (`app/Services/Events/ProgramHubDataService.php:540+`).

---

### 4. [MEDIUM] The homepage's most fest-labeled element leads nowhere

**Where:** Homepage → "Quick Portals" section → **"Kalotsav & Sports Portal"** card ("Event schedules, online registrations, item rules, and live competition results").

**What happens:** This is the single most prominent, most explicitly-labeled promise of "this leads to fest stuff" on the entire homepage. Clicking it scrolls to an on-page section instead of navigating to the real Fest portal (`/fest`). That section's own heading and description render **completely invisible** — white text on a transparent background, a CSS bug in the "primary" surface style used by the site's CMS block system (verified via computed styles; a sibling section using the "dark" surface variant renders correctly, so the pattern itself works, just not this one variant). Even if the color were fixed, the section has zero links or buttons in it — a dead end regardless.

**What still works:** The header's "Academic" dropdown → "Fest Schedule & Results" correctly links to `/fest` and works — but it's a small dropdown item, far less prominent than the broken card.

**Confidence:** Confirmed live + confirmed via computed CSS values.

---

### 5. [MEDIUM] Homepage "Upcoming events" cards look clickable and aren't

**Where:** Homepage → "Upcoming Programmes & Events" section (both currently-listed cards: "Sports Meet 2026-27" and "Kerala State Kalotsavam 2026").

**What happens:** Each card ends in "Programme Details →", styled with a hover arrow animation and primary-brand coloring — every visual signal of a link. It isn't one. No `<a>` tag, no click handler anywhere in the card.

**Root cause:** `resources/views/sections/events_programs/upcoming-cards.blade.php:58` — a plain `<span>`, no href. The underlying data pipeline (`SahodayaPublicData::upcomingEvents()`) doesn't even pass the event's ID/slug to the view, so there isn't yet a target to link to even if the markup were fixed.

**Confidence:** Confirmed live + confirmed via source read (both the view and its data source).

---

### 6. [LOW] Session-expiry mid-task loses form progress with a generic message

Not a bug exactly — `SESSION_LIFETIME=120` (minutes) is a reasonable value, and this only surfaced because of how long this audit session ran. Noting it because the failure mode is worth being aware of: a school admin deep in a multi-step registration who gets logged out mid-flow sees a generic "Your session has expired" and has to start that step over, with no auto-save of in-progress work.

---

### 7. [INFO] Not a bug, but worth a sanity check: duplicate publish-item-results code path

`FestResultsController::publishItem()` (route: `/results/items/{item}/publish`) and `FestEventSettingsController::publishItemResults()` (route: `/items/{item}/publish-results`) do the exact same thing — both call the same underlying service, both work correctly — reached from two different UI components (`Events/Results.vue` and the shared `FestItemOpsPanel.vue`). Not user-facing (both buttons work fine), just two implementations of one action that could drift out of sync in a future change. Purely a maintenance note.

---

## What's confirmed working well

Worth stating explicitly, since a findings-only list undersells how much of this actually works:

- **Public results-visibility gating** — the same-day commits closing gaps in `/tv` (previously ungated entirely) and `/school-results` (previously had a weaker leak-prone check) are both confirmed solid on read. A separate inconsistency I initially suspected (`$event->results_published` used directly in a few methods instead of the shared `$selectedScope`) turned out to be mathematically identical after tracing `directScope()` — a style inconsistency, not a bug.
- **Public scoreboard and results pages**, tested live against a real completed & published event (REGIONAL ENGLISH FEST 2026-27 — Region 1): fully functional — live standings, leading schools with points, winner photos, full toppers/school-wise/category-wise/item-wise/individual/championship breakdowns, clear "Results published on [date]" provenance. This is a well-built, complete feature.
- **School Admin registration flow** (Step 1: Event Registration, Step 2: Item Registration with student picker) — mechanically solid, confirmed via live testing including the actual save round-trip.
- **The mark-entry form itself** — rank/score entry, auto-grade-from-score derivation, per-judge score capping — careful, well-commented implementation. The bug in Finding 1 is about which participants get offered to it, not the entry mechanism itself.
- **Kalotsav-specific event-level scoping** — correctly blocks an out-of-scope admin with a proper 403. (The gap is that this check wasn't generalized to the other programs — see Finding 2.)
- **Item-level publish gating on the public side** (item schedule/results/winner posters) — correctly hidden when unpublished, via `EventLifecycleGate` and a deliberately fine-grained per-item visibility service, not just one blanket event-level flag.

---

## Summary matrix

| Area | Issue | Severity | Status |
|---|---|---|---|
| Sahodaya Admin — Mark Entry | Default hub view offers a roster it can't actually save marks for; false "Saved" message | 🔴 Critical | Confirmed |
| Sahodaya Admin — Scoped roles | `event_admin` has full access to unassigned programs (Sports, Kids Fest confirmed open; only Kalotsav enforces scope) | 🔴 Critical | Confirmed |
| School Admin — Dashboard | Duplicate `FestSchoolEventFee` rows produce repeated, indistinguishable action items | 🟠 High | Confirmed |
| Public homepage | "Kalotsav & Sports Portal" card leads to an invisible, linkless dead end instead of `/fest` | 🟡 Medium | Confirmed |
| Public homepage | "Upcoming events" cards' "Programme Details" isn't a link | 🟡 Medium | Confirmed |
| Sahodaya Admin — Scoped roles | Own in-scope program's card gives "not assigned" error | ⚪ Low | Confirmed |
| — | Session expiry loses in-progress form work, generic message | ⚪ Low | Noted |
| Sahodaya Admin — code | Two controllers implement identical publish-item logic | ⚪ Info | Noted, not user-facing |
| Public homepage | Fest nav link ("Fest Schedule & Results") works correctly | ✅ Working | Confirmed |
| Public portal | Item cards correctly gate on publish state with clear "not yet published" styling | ✅ Working | Confirmed |
| Public portal | Results-visibility gating (scoreboard/results/tv/school-results/live) | ✅ Working | Confirmed (recent fix verified) |
| Public portal | Full results/scoreboard rendering for a published event | ✅ Working | Confirmed |
| School Admin | Registration flow Steps 1–2 (event + item registration) | ✅ Working | Confirmed |
| Sahodaya Admin | Mark entry form itself (rank/score/auto-grade) | ✅ Working | Confirmed |
| Sahodaya Admin | Kalotsav-specific scope enforcement | ✅ Working | Confirmed |

---

## Fix status (implementation pass after this audit)

Every item you approved was implemented and re-verified live in this same session, except the one you explicitly said to leave alone (session expiry) and the one info-only code note (now also done, since you asked for it alongside the others).

| Finding | Resolution |
|---|---|
| 1. Mark entry false "Saved" / region roster mismatch | **Fixed, live-verified both ways.** The default "All Regions" view on a region-partitioned event now shows a clear "Select your region to begin mark entry" prompt instead of an unsaveable roster (`FestMarkEntryController::index()`, keyed off the *original* route-bound event so a genuine child selection isn't mistaken for the unselected hub). Separately, and as a general safety net: a rejected save now shows "Not saved — see message above" on the row instead of a false "Saved ✓", by checking the flash-error the app's own exception handler already sets (`MarkEntry.vue`). Verified both the failure path (an unapproved registration correctly shows "Not saved") and the success path (an approved one correctly still shows "Saved ✓") against real data. |
| 2. Scoped admin has full access to unassigned programs | **Fixed, live-verified.** Added a shared `assertProgramAccess()` check (base `SahodayaAdminController`) used by `FestEventController::programIndex()` (covers every program's dashboard) and `SportsProgramController`'s championship/results/rankings pages. Re-tested as the English-Fest-scoped admin: Sports Meet and Kids Fest now correctly 403; English Fest (their own program) still opens normally; re-tested as the full Sahodaya Admin to confirm no regression — unrestricted access unchanged. |
| 3. Duplicate `FestSchoolEventFee` rows | **Fixed, live-verified.** Root cause was a race condition in `FestRegistrationBatchFeeService::syncRollup()` — a check-then-write with no row lock, unlike its sibling `recalculateBatch()` which already used one. Wrapped in a transaction with `lockForUpdate()`. Cleaned up the 2 confirmed stale duplicate rows for AMU/Kalotsav and re-ran recalculation to regenerate a single correct one. Also added event-level de-duplication to the dashboard widget itself (`ProgramHubDataService::schoolPendingActions()`) as the safety net you asked for. AMU's dashboard went from "10 pending" (7 of them duplicate text) to a clean "3 pending". |
| 4. Homepage "Kalotsav & Sports Portal" card dead end | **Fixed, live-verified.** The invisible-text bug was the `site-section-surface-primary` CSS class's `linear-gradient()` background not rendering in practice; replaced with an explicit `background-color` fallback alongside the gradient (`theme-vars.blade.php`), and the same fix applied to the specific section template the card scrolls to (`sections/kalotsav/registration-cta.blade.php`), which also got a new always-present "View Fest Schedule & Results" button linking straight to `/fest` — not dependent on any tenant's CMS content being filled in. |
| 5. "Programme Details" not a real link | **Fixed, live-verified.** `SahodayaPublicData::upcomingEvents()` now includes the event id; `upcoming-cards.blade.php`'s card is now a real `<a href="/fest/{id}">` wrapping the whole card. Confirmed via raw HTML that both current cards (Sports Meet, Kerala State Kalotsavam) now carry correct hrefs. |
| 6. Own in-scope program card gives "not assigned" | **Not independently re-verified as its own repro** — it was folded into the scope-leak fix per your direction, and the underlying `resolveScopedLandingEvent()` fallback code already exists for exactly this case, but I did not specifically reproduce the original "click the All-Events hub card" path again after the fix (direct URL/sidebar navigation to the same program was re-tested and works correctly). Worth a quick manual check if you hit it again. |
| 7. Session expiry loses form progress | **Deliberately left as-is**, per your choice. |
| 8. Duplicate publish-item-results code path | **Fixed.** `FestEventSettingsController::publishItemResults()` now delegates to `FestResultsController::publishItem()` instead of duplicating the logic — one implementation, two routes/buttons still both work. Verified the route still resolves correctly (`route:list`) and the delegation's method signature matches exactly; did not re-click through the UI for this one given it's byte-identical behavior to before, just no longer duplicated in two places.

**Not done / no action needed:** nothing from the approved list was skipped outside of the two items above (session expiry, and the minor own-card issue not independently re-confirmed).

---

## Test accounts used

| Role | Email | Password |
|---|---|---|
| School Admin (AMU Residential School) | admin@amu-school.test | password |
| Sahodaya Admin (full access) | sahodaya@malappuram.test | password |
| Scoped Event Admin (English Fest only) | english.admin@malappuram.test | 123@Admin |

Public pages required no login. Events used: Kerala State Kalotsavam 2026 (id 30, hub) and its District Kalotsav child (id 39) for in-progress/registration testing; REGIONAL ENGLISH FEST 2026-27 — Region 1 (id 4, completed & published) for public-results verification.
