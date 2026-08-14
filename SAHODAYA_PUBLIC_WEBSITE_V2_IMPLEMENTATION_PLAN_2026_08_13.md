# Sahodaya Public Website V2 — Implementation Plan

**Date:** 2026-08-13  
**Scope:** Sahodaya public websites and Sahodaya website-builder experience only  
**Not in this release:** School website templates, school builder redesign, state portal redesign, or unrelated ERP/admin UI  
**Strategy:** Make the shared foundation school-ready, but finish and pilot Sahodaya websites first.

## 1. Target outcome

Ship a Sahodaya website system that can produce four clearly different public experiences without forking the application or duplicating business data:

1. **Network & Directory**
2. **Events & Results Live**
3. **Academic Resources & Training**
4. **Confederation & Governance**

Each Sahodaya continues to use the same secure ERP data, publishing system, accessibility rules, and performance standards. The visible identity changes through page composition, content priority, typography, spacing, navigation, surfaces, imagery, and interaction patterns—not just colour.

## 2. Evidence and current-state diagnosis

### Existing strengths to preserve

- 19 Sahodaya section types and 50 allowed variants already exist.
- Public rendering is server-side Blade and tenant-aware.
- The builder already supports section CRUD, ordering, draft/publish, version records, media upload, nav/footer configuration, themes, domains, forms, and microsites.
- ERP-backed public data exists for member schools, events, programmes, circulars, Kalotsav, sports, results, downloads, office bearers, and related workflows.
- The current CKSC implementation provides a working migration path for existing tenants.

### Problems that must be solved before visual expansion

1. **Primary-site and microsite sections are not consistently scoped.**
   - `SiteBuilderController@index` loads every section belonging to the tenant.
   - Several mutations identify a section only by tenant and section ID.
   - Reordering updates display order without constraining all IDs to one site.
   - `CkscSiteTemplate::apply(..., replaceSections: true)` deletes tenant sections broadly and can remove microsite content.
   - The public homepage does not explicitly scope its query to the primary site plus legacy null-site rows.

2. **The builder promotes one CKSC layout as the main template action.**
   - This is the strongest direct cause of Sahodaya sites looking alike.
   - Applying it replaces sections instead of creating a safe draft.

3. **Theme changes are decorative rather than structural.**
   - The active Sahodaya editor exposes primary, secondary, accent, and fonts.
   - Public sections often hard-code their own spacing, backgrounds, radii, and card treatments.

4. **The public shell has one global rhythm.**
   - Every page can render topbar, navbar, admission banner, ticker, main content, footer, social strip, WhatsApp, CBSE badge, visitor counter, and lightbox in the same order.
   - These widgets should be context-aware and optional by experience family.

5. **Content readiness is not enforced.**
   - Generic starter copy, placeholder people, blank media, repeated testimonials, and stale links can be published.

### Research direction

Live Sahodaya sites confirm different primary jobs: the [Confederation of Kerala Sahodaya Complexes](https://confedsahodaya.com/) emphasizes governance, history, leadership, and state programmes; [Central Kerala Sahodaya](https://centralkeralasahodaya.com/) emphasizes notices, services, reports, member schools, and office bearers; [Chennai Sahodaya](https://www.chennaisahodaya.org/) foregrounds announcements, events, calendar, and media. V2 should encode these distinct jobs as experience families rather than one generic homepage.

## 3. Release principles

### Must do

- Keep existing published websites unchanged until a tenant explicitly opts into V2.
- Make all template application non-destructive and previewable.
- Reuse existing sections wherever they meet the experience need.
- Use ERP records as the source of truth for operational content.
- Make mobile, keyboard, low-bandwidth, and reduced-motion behaviour first-class.
- Keep builder operations tenant- and site-scoped at the backend, not only in the UI.

### Must not do

- Do not copy the CKSC page order into new families.
- Do not create one Blade tree per tenant.
- Do not permit arbitrary custom CSS in the Sahodaya admin builder.
- Do not add animation merely to create visual difference.
- Do not seed fake office bearers, testimonials, statistics, or event claims.
- Do not publish a template automatically after it is applied.

## 4. Architecture decisions

### 4.1 Template manifests are code-versioned in V2

Add `config/sahodaya_website_templates.php`. Each manifest contains:

- stable `key`, name, version, purpose, and audience;
- compatible tenant/site types;
- navigation recipe and widget policy;
- ordered section recipe;
- default design character;
- required and recommended content fields;
- preview image references;
- migration callback from the previous manifest version when required.

Do not build a database template editor in this release. Code-versioned manifests are reviewable, testable, and safe for the first rollout. Custom/admin-authored templates can follow after pilot evidence.

### 4.2 Add site-level experience metadata

Extend `website_sites` with:

- `template_key` nullable string;
- `template_version` nullable string;
- `experience_version` default `v1`;
- `homepage_mode` default `evergreen`;
- `design_json` nullable JSON;
- `draft_template_json` nullable JSON or an equivalent draft-assignment record.

Use the site record—not a tenant-wide setting—for composition metadata so future microsites can have their own experience.

### 4.3 Separate section content from section presentation

Add `layout_json` to `site_sections` for controlled presentation properties:

- width: narrow, standard, wide, full;
- spacing: compact, standard, spacious;
- surface: canvas, muted, primary, dark, image;
- heading alignment: left or centre;
- media treatment: natural, framed, editorial, edge-to-edge;
- optional visibility start/end dates.

Keep domain content in `config` and publishing snapshots in `published_config`. Add the published equivalent of layout data so draft presentation changes do not leak to the public site.

### 4.4 Expand the validated design token schema

Site-level `design_json` should support controlled tokens:

- brand colours with automatic contrast validation;
- display and body font from an allowlist;
- type scale: compact, balanced, editorial;
- density: compact, comfortable, spacious;
- surface character: flat, bordered, soft, elevated;
- corner character: square, soft, rounded;
- button character: solid, bordered, understated;
- image character: documentary, vibrant, formal, monochrome;
- motion: none, restrained, expressive;
- navigation and footer variants.

Keep backward compatibility by mapping the current theme fields into these defaults.

### 4.5 Add a shared public section frame

Create a reusable wrapper responsible for section ID, width, vertical rhythm, background, heading treatment, and accessibility landmarks. Existing section Blade files should focus on their internal content layout.

This is the mechanism that makes design characters consistent without rewriting all 50 Sahodaya variants.

## 5. Experience-family specifications

### S1 — Network & Directory

**Primary audience:** Member schools, prospective members, public visitors looking for schools  
**Primary task:** Find a member school or membership information

Default homepage recipe:

1. `hero/gradient-split` — local identity, coverage area, member search/renew CTA
2. `statistics/counter-strip` — members, regions, programmes, years of service
3. `member_schools/map-view` — searchable map/list entry point
4. `about_sahodaya/with-stats`
5. `events_programs/upcoming-cards`
6. `office_bearers/modern-grid`
7. `news_circulars/modern-feed`
8. `contact/side-by-side`

Required enhancement:

- Upgrade member directory to searchable list/map with district, school type, and location filters.
- Give every school card one clear primary action and handle missing logo/location gracefully.

Design character: civic, structured, data-forward, restrained motion.

### S2 — Events & Results Live

**Primary audience:** Coordinators, participating schools, parents/students seeking public information  
**Primary task:** Register, view schedule, follow results

Default homepage recipe:

1. `hero/event-promo` — active programme and phase-aware CTA
2. new `sahodaya_action_hub/seasonal` — deadline, registration, schedule, venue, results
3. `events_programs/upcoming-cards`
4. `kalotsav/registration-cta` or `kalotsav/results-tabs`, selected by mode
5. `sports_meet/results-highlight` when applicable
6. `news_circulars/grid`
7. `member_schools/modern-grid`
8. `contact/stacked`

Homepage modes:

- `evergreen`
- `registration_open`
- `event_live`
- `results_published`

Mode should default from ERP lifecycle state with an authorized manual override and expiry, not require rebuilding the homepage for every event phase.

Design character: energetic, status-led, high contrast, concise copy.

### S3 — Academic Resources & Training

**Primary audience:** Principals, teachers, academic coordinators  
**Primary task:** Find a circular, resource, training, or academic date

Default homepage recipe:

1. `hero/with-quicklinks` — resource search, training, calendar, login
2. new `resource_centre/search-grid` — unified circular/download search
3. `academic_quicklinks/year-tabs`
4. `events_programs/timeline` — training and academic dates
5. `programmes/service-grid`
6. `news_circulars/list`
7. `about_sahodaya/single-column`
8. `contact/side-by-side`

Required enhancement:

- Search across circulars and downloads using year, category, and keyword filters.
- Display issued/updated dates, file type, size, and clear empty states.

Design character: editorial, calm, text-first, compact but readable.

### S4 — Confederation & Governance

**Primary audience:** Sahodaya leaders, institutions, regulators, media, public  
**Primary task:** Understand governance, reach a regional body, access official documents

Default homepage recipe:

1. `hero/cksc-slider` or `hero/full-bleed` — one restrained institutional story
2. `about_sahodaya/with-timeline`
3. `statistics/horizontal-strip`
4. `governance/structure` — enable in the Sahodaya catalog and refine
5. `member_schools/table-list` or a state-level Sahodaya directory
6. `office_bearers/photo-cards`
7. `programmes/service-grid`
8. `downloads_sahodaya/sahodaya-grid`
9. `news_circulars/modern-feed`
10. `contact/stacked`

Design character: authoritative, spacious, formal photography, minimal ornament.

## 6. Builder UX specification

### 6.1 Replace “Apply CKSC Template” with an Experience step

The first builder tab becomes **Experience**:

- four large family cards with purpose, ideal use, section outline, and mobile/desktop preview;
- current family and version clearly shown;
- actions: Preview, Apply as draft, Change style only;
- explicit summary of what will change;
- no destructive replacement from the primary interface.

Keep CKSC as a legacy/Confederation Classic option available to current sites, not the universal recommendation.

### 6.2 Add site selection

The builder header shows:

- selected site name and type;
- Primary or Microsite badge;
- public URL;
- draft/published state;
- site switcher;
- preview and publish controls.

Every API request includes the selected `site_id`; the backend validates ownership and scope.

### 6.3 Reorganize the builder

Refactor the current large Vue page into focused components:

- `Website/ExperiencePicker.vue`
- `Website/SiteSwitcher.vue`
- `Website/ThemeEditor.vue`
- `Website/SectionList.vue`
- `Website/SectionEditor.vue`
- `Website/PreviewToolbar.vue`
- `Website/ReadinessPanel.vue`

Recommended tabs:

1. Experience
2. Sections
3. Navigation
4. Design
5. Footer & widgets
6. Readiness & publish

### 6.4 Section editing improvements

- Keep Move Up/Move Down controls and add drag ordering as an enhancement.
- Add duplicate section.
- Show source badge: Manual, ERP, or Mixed.
- Show layout controls separately from content fields.
- Preserve content when switching compatible variants; warn when fields cannot map.
- Show draft changes and access version restore without leaving the section.
- Add meaningful preview snippets rather than raw variant names alone.

### 6.5 Readiness panel

Block publication only for high-risk failures:

- missing organization name/logo/contact;
- placeholder/sample people or testimonials;
- broken required links;
- active hero without accessible text or usable media;
- inaccessible colour contrast;
- invalid section variant/template manifest;
- missing primary CTA target;
- unsafe or unsupported uploaded media.

Warn without blocking for recommended improvements such as thin About copy or missing optional social links.

## 7. Backend implementation sequence

### Phase 0 — Baseline and protection (2–3 days)

1. Add focused feature tests that capture current primary-site, preview, publish, and microsite behaviour.
2. Verify the `website_sites`/section-version migration state in every supported environment.
3. Introduce a per-site `experience_version` defaulting to `v1`.
4. Keep all existing tenants on V1.

Exit gate: current sites render identically and tests are green.

### Phase 1 — Site-scope correctness (3–4 days)

1. Resolve/validate selected site in the Sahodaya builder controller.
2. Require or safely resolve a site for section list/create/update/delete/toggle/reorder/publish/version operations.
3. Validate that `site_id` belongs to the authenticated Sahodaya.
4. Make reorder transactional and reject mixed-site IDs.
5. Scope public homepage to the primary site plus legacy null-site rows only.
6. Scope template application to the selected site.
7. Prevent primary-site deletion and make microsite deletion explicit about contained content.

Exit gate: no builder operation on Site A can alter Site B; primary homepage never renders microsite sections.

### Phase 2 — Template and design foundation (5–7 days)

1. Add manifest catalog and validator.
2. Add site experience/design fields and section draft/published layout fields.
3. Add the shared section frame.
4. Expand theme serialization and CSS variables.
5. Map V1 theme data into V2 defaults without modifying stored V1 data.
6. Build a non-destructive template applier that creates a draft snapshot.

Exit gate: one test template can be applied, previewed, cancelled, or published without affecting the live site.

### Phase 3 — Build S2 and S1 pilots (7–9 days)

Build in this order because they exercise the most valuable dynamic paths:

1. S2 Events & Results Live, including seasonal action hub and ERP lifecycle mapping.
2. S1 Network & Directory, including member search/list/map improvements.
3. Add preview imagery and builder descriptions.
4. Test incomplete and empty ERP states.

Exit gate: two pilots are visibly different and complete their primary visitor tasks in no more than two interactions.

### Phase 4 — Build S3 and S4 (5–7 days)

1. S3 resource centre search and academic/training recipe.
2. S4 governance recipe and catalog support for governance structure.
3. Verify navigation depth and document discovery.
4. Remove or replace all starter testimonials/sample identities from V2 recipes.

Exit gate: all four families pass responsive and content-readiness review.

### Phase 5 — Builder completion (5–7 days)

1. Componentize the Sahodaya builder.
2. Add experience picker, site switcher, responsive preview toolbar, layout editor, and readiness panel.
3. Add safe template comparison and draft publication flow.
4. Add version restore UI and clear failure/success feedback.

Exit gate: a Sahodaya admin can configure and publish a V2 site without database or developer access.

### Phase 6 — Quality, pilot, and rollout (5–7 days)

1. Browser QA at 360, 768, 1024, and 1440 px widths.
2. Keyboard, screen-reader spot checks, reduced motion, contrast, form errors, and focus visibility.
3. Performance optimization and real-user Web Vitals capture.
4. Pilot with two deliberately different Sahodayas.
5. Fix pilot issues, document content responsibilities, then enable opt-in for remaining Sahodayas.

Exit gate: launch criteria in Section 10 pass.

## 8. Expected file map

### New backend/support

- `config/sahodaya_website_templates.php`
- `app/Support/SahodayaWebsiteTemplateCatalog.php`
- `app/Support/SahodayaWebsiteTemplateValidator.php`
- `app/Services/Website/SahodayaTemplateApplier.php`
- `app/Services/Website/SahodayaHomepageModeResolver.php`
- `app/Services/Website/SahodayaContentReadiness.php`
- migration(s) for site experience/design and section layout publishing

### Backend changes

- `app/Models/WebsiteSite.php`
- `app/Models/SiteSection.php`
- `app/Http/Controllers/Public/PublicSiteController.php`
- `app/Http/Controllers/SahodayaAdmin/SiteBuilderController.php`
- `app/Http/Controllers/SahodayaAdmin/SiteBuilderApiController.php`
- `app/Http/Controllers/Admin/BuilderApiController.php`
- `app/Support/CkscSiteTemplate.php` — legacy adapter, no broad delete
- `app/Support/SahodayaSiteBuilderCatalog.php`
- `app/Support/SahodayaTenantBranding.php`

### Public UI

- `resources/views/layouts/public.blade.php`
- `resources/views/public/home.blade.php`
- `resources/views/partials/theme-vars.blade.php`
- new shared section-frame partial/component
- new `sections/sahodaya_action_hub/seasonal.blade.php`
- new `sections/resource_centre/search-grid.blade.php`
- refinements to member directory, governance, nav, footer, event, results, and circular/download sections
- `resources/css/app.css`
- `resources/js/public.js`

### Builder UI

- `resources/js/Pages/Admin/Sahodaya/SiteBuilder.vue`
- new components under `resources/js/Components/sahodaya/website/`
- `resources/js/Pages/Admin/Sahodaya/Website/Sites.vue`

## 9. Test plan

### Feature tests

- Primary site returns only primary/legacy sections.
- Microsite returns only its sections.
- Cross-site and cross-tenant section mutations are rejected.
- Mixed-site reorder is rejected and transaction rolls back.
- Applying a template creates draft content and preserves published content.
- Cancelling a draft leaves the public site unchanged.
- Publishing updates content and layout atomically and records a version.
- CKSC legacy application cannot delete microsite sections.
- Every manifest references allowed section types/variants.
- All four recipes render with minimum valid content.
- Homepage mode follows event lifecycle and respects valid override/expiry.
- Readiness detects placeholders, broken required fields, and contrast failures.

### End-to-end tests

- Admin selects each family, previews mobile/desktop, applies as draft, and publishes.
- Admin switches sites and edits the correct section list.
- Keyboard-only section ordering and publishing works.
- Public member search, circular search, event actions, and result actions work on mobile.
- Empty states render when there are no current events, results, media, or circulars.
- Existing V1 Sahodaya continues to render after migrations.

### Visual regression set

- One canonical fixture per experience family.
- Desktop and mobile snapshots of first viewport, navigation, one data-heavy section, footer, and empty state.
- Review contrast, clipping, long names, missing photos, Malayalam text, large member counts, and long circular titles.

## 10. Launch gates

### Function

- Latest circular, next deadline/event, school login, results, and member search are reachable within two interactions.
- Every public CTA has a valid target and clear state.
- Public dynamic data shows freshness and handles empty/error states.

### Differentiation

- No two pilot sites share the same family + hero + first five-section sequence + nav + design character.
- The first viewport identifies the Sahodaya, region, current priority, and primary action without scrolling.
- Real tenant content and media replace all V2 starter material before publication.

### Accessibility

- WCAG 2.2 AA target.
- Visible focus, skip link, correct landmarks/headings, keyboard menus, labelled controls, non-obscured focus.
- Pointer targets meet 24×24 minimum; primary mobile actions target 44×44.
- Sliders, tickers, and movement pause and honor `prefers-reduced-motion`.

### Performance

- 75th percentile targets: LCP ≤ 2.5 s, INP ≤ 200 ms, CLS ≤ 0.1 on mobile and desktop.
- Responsive image derivatives and explicit dimensions are used.
- Video/maps are interaction- or proximity-loaded.
- Only the real first hero image is eager/preloaded.

### Safety and rollout

- Existing V1 sites are unchanged.
- Template changes are drafts until explicit publication.
- Rollback restores content and layout together.
- Audit log records template, version, site, actor, and publish/restore action.

## 11. Recommended pilot order

1. **High-activity Sahodaya:** S2 Events & Results Live.
2. **Resource-heavy Sahodaya:** S3 Academic Resources & Training.
3. Validate feedback, then configure S1 and S4 demo tenants.
4. Open V2 as an opt-in choice for remaining Sahodayas.

Do not migrate all tenants at once. The first two pilots should run through at least one real registration/results cycle and one real circular/training cycle before V2 becomes the default for new Sahodayas.

## 12. Estimated delivery

**Total:** approximately 5–7 weeks for one full-stack engineer with regular design review, or 4–5 weeks with parallel design/frontend capacity.

The critical path is:

`site-scope safety → template/design foundation → S2/S1 pilots → S3/S4 → builder completion → QA/pilot rollout`

School website work should begin only after the shared site-scope, template, design-token, preview, and publishing foundations are stable. Those foundations can then be reused without making school sites inherit Sahodaya layouts.
