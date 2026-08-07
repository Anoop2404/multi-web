# Type-error / undefined-value audit — 2026-08-06

Follow-up to `CODE_AUDIT_2026_08_06.md`, focused specifically on TypeError / undefined-index / null-dereference risk. No PHP runtime is available in this environment, so this is a manual review (couldn't run PHPStan/Larastan for a systematic pass — worth doing if the user wants a truly exhaustive sweep). I sampled Models, Http/Resources, Http/Controllers, and the bulk-import Services, since those are where untyped external input meets typed PHP most often.

**Headline finding: the codebase is unusually disciplined about this already** — extensive `?->`, `??`, `whenLoaded()`, and `isset()` guards throughout the Resources, CSV importers, and model accessors I checked. Several patterns that looked risky on first grep (`->first()->prop`, `$participant->group->x`) turned out to be safely guarded by a preceding count/isset check. That said, I found one real, reproducible bug class:

## Confirmed bug: unguarded `trim()`/`strtolower()` on raw request input crashes on array input

Laravel's `$request->query('x', 'default')` / `$request->input('x')` returns whatever the client sent for that key — including an **array**, if the client sends `?x[]=1`. Passing an array into `trim()`/`strtolower()` (which require a string) is a fatal `TypeError`, not a warning. The codebase mostly avoids this via `$request->string('q')` (which safely coerces via `Stringable`, e.g. `AcademicResultsPortalController.php:25`), but four call sites bypass that safe helper:

- **`app/Http/Controllers/Public/FestPortalController.php:413`** — `trim($request->query('q', ''))` inside `search()`. This is a **public, unauthenticated** endpoint. `GET .../search?q[]=x` 500s it. Worth fixing first — it's public, and if `APP_DEBUG` is ever true in production (flagged in the earlier audit) this would also leak a stack trace to an anonymous visitor.
- `app/Http/Controllers/SahodayaAdmin/Concerns/BuildsMembershipExports.php:21` and `:35` — same pattern, but behind Sahodaya-admin auth, so lower severity (an authenticated admin would just be crashing their own page).
- `app/Http/Controllers/SahodayaAdmin/MembershipReportsController.php:27` — same pattern, also behind admin auth.

Fix is mechanical: replace `trim($request->query('q', ''))` with `trim((string) $request->query('q', ''))` or switch to `$request->string('q')->toString()` like `AcademicResultsPortalController` already does. Happy to patch these four if you want.

Checked and ruled out as a false alarm: `AuthController.php:114` has the same-looking `strtolower(trim($request->input('email')))`, but it's preceded by `$request->validate(['email' => 'required|email'])` on the line above — Laravel's `email` rule rejects non-string input before it reaches `trim()`, so that one's safe.

## Lower-severity: inconsistent null-guarding in CSV import

`app/Services/Events/FestAttendanceImportService.php:59` — `trim($row[$regNoIdx])` is missing the `?? ''` that every other column read in the same method uses (see lines 44, 60, 61). On a short/malformed CSV row (fewer columns than the header), this raises a PHP warning rather than a crash (not fatal), but it's an inconsistency in an otherwise careful function — worth aligning for cleanliness and to avoid log noise.

## Not found (checked specifically, came back clean)

- No `Model::create($request->all())`-style array-to-model shortcuts that could type-juggle unexpected fields.
- No unguarded `->first()->method()` chains where the collection could plausibly be empty (all instances found were preceded by a `count()`/`isset()` check, or were `groupBy()` groups which are never empty by construction).
- `Http/Resources` (`StudentResource`, `MembershipPaymentResource`, `RegistrationResource`, etc.) all wrap nullable-relation blocks in `whenLoaded()`.
- No raw `json_decode(...)[...]`/`->prop` chains without a decode-success check.

## Caveat

This was a targeted sample, not an exhaustive file-by-file pass — with 929 PHP files and no PHP/PHPStan available to run here, a fully mechanical sweep wasn't possible. If you want full coverage, the highest-leverage next step would be running `larastan`/`phpstan` (level 5+) locally, which would catch the rest of this bug class (and real type mismatches) automatically rather than by manual grep.
