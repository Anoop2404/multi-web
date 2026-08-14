# Public Website UI/UX Differentiation Plan

**Date:** 2026-08-13  
**Scope:** Sahodaya public websites, school public websites, microsites, and the CMS experience used to create them  
**Outcome:** Websites remain recognizably part of one reliable platform without looking or behaving like copies of one another.

## 1. Executive decision

Do not solve sameness by adding more colour presets to the current template. Build a **shared platform foundation with distinct experience families**.

- **Shared:** accessibility, performance, content models, publishing, security, ERP widgets, disclosure rules, form behaviour, and responsive standards.
- **Different:** information architecture, first-screen purpose, section composition, typography scale, spacing/density, image treatment, navigation pattern, card language, motion, and content voice.
- **Personal:** each tenant must provide a real brand story, local photography, priority audiences, flagship programmes, and contact/compliance information before launch.

The recommended first release is **4 Sahodaya families + 5 school families**, with a guided setup wizard and safe preview/publish workflow.

## 2. Research summary

### 2.1 What the current product already has

The repository is not missing components. It already has:

- 19 Sahodaya section types with 50 variants in `app/Support/SahodayaSiteBuilderCatalog.php`.
- 29 school section types with 73 variants in `app/Support/SchoolSiteBuilderCatalog.php`.
- Multiple heroes, navbars, footers, galleries, result views, admissions, events, disclosures, and other content blocks under `resources/views/sections`.
- Theme settings, section ordering, content editing, tenant domains, draft/publish/version foundations, and microsite models.
- ERP-backed public content such as events, registrations, results, member schools, downloads, and board results.

### 2.2 Why the websites can still feel the same

| Current condition | UX effect |
|---|---|
| The public layout always stacks topbar → navbar → admission banner → ticker → sections → footer | Every tenant starts with the same visual rhythm even when content differs. |
| Sahodaya has one prominent “Apply CKSC Template” action | It encourages one reference layout to become the default identity for every Sahodaya. |
| Theme controls mainly change three colours, two fonts, and radius | This changes decoration, not composition or brand character. |
| Section variants are chosen individually, without a page-level design grammar | Mixed variants can look inconsistent; repeated combinations still look cloned. |
| School builder has sections/nav/footer, but no guided school template/archetype selection | A school must assemble a site from blocks without a clear audience strategy. |
| School builder does not expose the same theme and publish/version experience as Sahodaya | School autonomy and safe experimentation are weaker. |
| Multi-site records exist, but the tenant builder initially loads all tenant sections and has no clear site/page selector | Microsites are not yet a complete authoring experience. |
| Content quality is not a launch gate | Placeholder copy, weak images, or identical defaults can survive even when the component library is broad. |

### 2.3 What live education websites show

- Sahodaya sites are operational hubs. The [Confederation of Kerala Sahodaya Complexes](https://confedsahodaya.com/) emphasizes institutional history, programmes, leadership, gallery, and state-level news. [Central Kerala Sahodaya](https://centralkeralasahodaya.com/) prioritizes notices, reports, member schools, services, and office bearers. [Chennai Sahodaya](https://www.chennaisahodaya.org/) foregrounds announcements, events, a calendar, and a media library. This supports multiple Sahodaya experience families instead of one universal homepage.
- School sites serve several audiences at once. [JAMA-ATH Residential Public School](https://jamaathpublicschool.org/) gives prominence to admissions, board results, facilities, gallery, and an enquiry form. [Chavara Public School](https://cps.ac.in/) uses deeper navigation for academics, student life, facilities, activities, results, and disclosure. This supports audience-led school families rather than a Sahodaya layout with school content inserted.
- CBSE requires prescribed documents and teacher qualification details under Mandatory Public Disclosure; CBSE also directs schools to make the disclosure link prominent on the homepage. This is a product requirement, not an optional visual block: [CBSE Circular 01/2025 / SARAS guidance](https://www.cbse.gov.in/cbsenew/documents/Circular_Aff_SARAS_6_0_11032025.pdf) and [CBSE disclosure reminder](https://www.cbse.gov.in/cbsenew/documents/Circular_Merged_01_08012025.pdf).
- Accessibility should target WCAG 2.2 AA, including keyboard-visible focus, focus not being obscured, and minimum target sizing: [W3C WCAG 2.2](https://www.w3.org/TR/WCAG22/).
- Performance gates should use field measurements for LCP, INP, and CLS. Recommended “good” thresholds are LCP ≤ 2.5 s, INP ≤ 200 ms, and CLS ≤ 0.1 at the 75th percentile: [Web Vitals](https://web.dev/articles/vitals), [LCP](https://web.dev/articles/lcp), [INP](https://web.dev/articles/inp), and [CLS](https://web.dev/articles/cls).

## 3. Product model: identity, composition, and content

Each site should be generated from three independent choices:

1. **Experience family** — determines audience priority, navigation, homepage recipe, and default modules.
2. **Visual character** — determines design tokens such as typography, density, surfaces, corners, imagery, and motion.
3. **Content profile** — tenant-specific story, programmes, photos, outcomes, calls to action, and seasonal state.

This permits two Sahodayas to use the same underlying components without producing the same experience.

### 3.1 Expand theme tokens beyond colour

Add controlled tokens for:

- display/body font pairing and heading scale;
- content width, section spacing, and compact/comfortable/editorial density;
- surface style: flat, bordered, soft, elevated, or photographic;
- corner language: square, soft, rounded, or pill only for controls;
- button style and icon family;
- image ratio, crop position, overlay strength, and monochrome/duotone option;
- background rhythm and section separators;
- motion level: none, restrained, or expressive, always honoring `prefers-reduced-motion`;
- navbar behaviour and first-viewport treatment;
- light/dark section policy, not a global uncontrolled dark-mode toggle.

### 3.2 Add page recipes

A recipe is a versioned, curated sequence of sections with rules, not a hard-coded page. It can be applied as:

- **Structure only:** keeps existing content and theme.
- **Style only:** keeps sections/content and changes visual character.
- **Full starter:** creates sections with tenant-aware content prompts.

Applying a recipe must create a draft and preserve the currently published version. The existing destructive “replace sections” action should be retired from the primary workflow.

## 4. Sahodaya experience families

### S1. Network & Directory

For clusters whose main value is their member-school network.

- First screen: member count, district/region coverage, member search, and “Join/Renew Membership.”
- Page rhythm: map/list split, regional stats, office bearers, network stories, contact.
- Visual character: civic, structured, data-forward, restrained motion.
- Signature interaction: searchable member directory with list/map toggle and filters.

### S2. Events & Results Live

For Sahodayas where Kalotsav, sports, quizzes, and examinations dominate traffic.

- First screen: current event status, next deadline, registration, schedule, live results.
- Page rhythm: event command centre, timeline, programme tiles, latest results, venue and help.
- Visual character: energetic, high contrast, bolder type, status colour used carefully.
- Signature interaction: seasonal homepage modes—registration, event live, results published, and archive.

### S3. Academic Resources & Training

For clusters focused on teacher development, circulars, model exams, and shared resources.

- First screen: latest circulars, training registration, downloads search, and upcoming academic dates.
- Page rhythm: resource search, category chips, training calendar, featured publication, impact stats.
- Visual character: editorial, calm, text-first, compact information density.
- Signature interaction: unified searchable resource centre with year/category filters.

### S4. Confederation & Governance

For state/apex organizations and large umbrella bodies.

- First screen: mission, geographic scope, major state programme, and Sahodaya directory.
- Page rhythm: governance, timeline, district coverage, flagship programmes, reports, public documents.
- Visual character: authoritative, spacious, institutional photography, minimal decoration.
- Signature interaction: hierarchy/directory navigation from state → Sahodaya → member schools.

### Sahodaya evergreen requirements

Every family still includes member schools, office bearers/governance, events/programmes, circulars/downloads, results where applicable, contact, and portal entry. The difference is priority and interaction—not missing essential information.

## 5. School experience families

School sites must have their own system. They should not inherit Sahodaya page order or visual language.

### C1. Admissions & Parent Discovery

- For schools actively growing enrolment.
- First screen: clear value proposition, admission year/status, apply/enquire, grades offered, visit campus.
- Follow with learning approach, facilities, transport/location, outcomes, parent questions, enquiry form.
- Signature interaction: short enquiry flow with saved progress and response expectation.

### C2. Academic Excellence

- For schools known for board results, competitive exams, or strong academic programmes.
- First screen: academic promise, programmes/streams, verified outcomes, admissions CTA.
- Follow with board results, toppers, faculty strength, laboratories, academic calendar, student support.
- Signature interaction: result explorer by year/class without turning the homepage into a trophy wall.

### C3. Campus Life & Community

- For schools whose strongest differentiator is student experience.
- First screen: authentic campus-life story or editorial photo sequence.
- Follow with activities, clubs, sports, arts, houses, facilities, calendar, gallery, student voices.
- Signature interaction: filterable “Life at school” story feed rather than repeated image-card grids.

### C4. Heritage & Values

- For established or mission-led institutions.
- First screen: heritage statement, founding story, values, and present-day promise.
- Follow with timeline, leadership, alumni, traditions, service, academics, admissions.
- Signature interaction: visual history/alumni story path with restrained, editorial styling.

### C5. Innovation & Future Skills

- For STEM, ATL, AI, makerspace, or internationally oriented schools.
- First screen: student work, innovation proposition, programmes, and admissions.
- Follow with labs, projects, competitions, partnerships, faculty, career guidance, outcomes.
- Signature interaction: project showcase with evidence, student role, skills, and outcome—not generic facility claims.

### School evergreen requirements

All school families must include:

- a **prominent homepage Mandatory Public Disclosure entry** and a complete structured disclosure page;
- affiliation number, school code where applicable, address, and contact details;
- admissions status and next action;
- parent/student portal access that is visually separate from public admissions;
- academics, faculty, facilities, calendar/notices, downloads, safety/compliance, and contact;
- no public exposure of sensitive student data; results/toppers require approved publication rules.

## 6. Preventing “template clone” output

Add a launch-time differentiation check. It should warn—not arbitrarily block—when a new site duplicates another active tenant on too many dimensions.

Recommended fingerprint:

`experience family + visual character + hero composition + first 5 sections + navbar + footer + density + image treatment`

Launch targets:

- No two pilot sites use the same complete fingerprint.
- At least three of the first five homepage sections differ in type, variant, or composition from the platform default.
- Logo, real hero media, main headline, primary CTA, and organization description are tenant-specific.
- Placeholder names, stock testimonials, blank cards, broken media, and duplicated default paragraphs produce blocking content-readiness errors.

This creates meaningful difference while still allowing tenants to choose the same accessible component when it is the best fit.

## 7. CMS and builder UX changes

### 7.1 Guided setup

Replace the blank-builder start with a short wizard:

1. Choose website type: Sahodaya, school, or event microsite.
2. Choose primary audience and top three visitor tasks.
3. Select an experience family using real mobile/desktop previews.
4. Add logo, colours, typography preference, contact, and real imagery.
5. Review generated navigation and homepage recipe.
6. Complete readiness checklist and publish.

### 7.2 Builder workspace

- Add a clear **site → page → section** hierarchy and site selector.
- Add page management instead of storing most non-home pages in one `cms_pages` setting blob.
- Show mobile, tablet, and desktop preview in the builder.
- Add drag-and-drop ordering while retaining current Move Up/Move Down buttons for keyboard and WCAG compliance.
- Give sections layout controls: width, spacing, background treatment, media side, content density, and visibility dates.
- Add reusable global content references so a phone number, affiliation number, or admission year is updated once.
- Show draft/published status and version history consistently in both Sahodaya and school builders.
- Add “copy section,” “save as pattern,” “schedule,” and “restore” actions.
- Add pre-publish checks for accessibility, required content, broken links, image dimensions/alt text, SEO, and compliance.

## 8. Technical workstreams

### A. Template and page architecture

- Add versioned `website_templates`/template manifests with tenant type, family, recipe, compatible sections, preview images, and defaults.
- Add `website_pages` with `site_id`, slug, page type, status, SEO, navigation visibility, and publish metadata.
- Scope `site_sections` to a page (and therefore a site) while keeping legacy primary-site rows compatible.
- Make the builder load and reorder sections within the selected page only.
- Keep current CKSC output as a versioned “Confederation Classic” legacy template; never silently migrate live sites.

### B. Design token engine

- Replace the small theme array with a validated token schema.
- Map existing tenants into backward-compatible defaults.
- Make every navbar, footer, section wrapper, button, form, and card consume tokens consistently.
- Add automated contrast checks for saved colour combinations.

### C. Section composition

- Keep the existing 123 variants; do not rewrite them wholesale.
- Add a shared section frame for width, spacing, background, heading alignment, and decorative treatment.
- Create only the missing signature components: directory search/map, seasonal event hub, resource centre, audience/task gateway, results explorer, and project stories.
- Reduce duplicate “three cards in a grid” treatment by offering editorial lists, split layouts, data views, timelines, and immersive media only where content supports them.

### D. Content and ERP integration

- Prefer live ERP data for events, deadlines, results, circulars, member schools, training, downloads, board results, and compliance expiries.
- Define empty, loading, error, archived, and no-upcoming-content states for every dynamic widget.
- Add freshness labels such as “Updated 2 hours ago” for operational data.
- Allow editorial override of headings/introduction while preserving a single source of truth for records.

### E. Quality foundation

- WCAG 2.2 AA audits: keyboard, focus, landmark/headings, contrast, target size, labels, error recovery, reduced motion, and language metadata.
- Performance: responsive image derivatives, explicit image dimensions, hero preload only when needed, lazy loading below the fold, reduced third-party embeds, font subsets, and real-user Web Vitals collection.
- SEO: per-page metadata, canonical URLs, sitemap inclusion, organization/school schema, breadcrumbs, and valid public-event schema only where eligible.
- Privacy/security: consent-aware analytics, form spam controls, upload rules, and publication approval for student names/photos.

## 9. Delivery phases

| Phase | Duration* | Deliverable | Exit gate |
|---|---:|---|---|
| 0. Baseline and pilots | 3–5 days | Inventory active sites/content, select 2 Sahodayas + 3 schools, capture current UX/performance | Pilot owners and top visitor tasks confirmed |
| 1. Foundation | 1.5–2 weeks | Page model, template manifests, expanded tokens, backward compatibility | Existing live sites render unchanged |
| 2. Builder UX | 1.5–2 weeks | Site/page selector, guided setup, previews, safe recipe apply, consistent publish/version UI | A nontechnical admin can create a draft site without developer help |
| 3. Sahodaya families | 2 weeks | S1–S4 recipes and missing signature components | Four visibly/task-wise distinct pilots pass review |
| 4. School families | 2.5–3 weeks | C1–C5 recipes, school theme control, disclosure/admissions guardrails | Five distinct school demos meet compliance and audience tasks |
| 5. Quality and rollout | 1.5–2 weeks | A11y, responsive, performance, content readiness, analytics, documentation | All launch gates below pass |

\*Indicative elapsed time for one designer + one full-stack engineer; parallel implementation can shorten elapsed time after Phase 1.

## 10. Launch acceptance criteria

### Visitor tasks

- Sahodaya: latest circular, next event/deadline, registration/login, results, and member-school search are each reachable in no more than two interactions.
- School: admissions, Mandatory Public Disclosure, parent/student portal, calendar/notices, and contact are each reachable in no more than two interactions.
- Mobile navigation does not require horizontal scrolling or ambiguous icon-only actions.

### Accessibility

- WCAG 2.2 AA automated checks plus keyboard and screen-reader spot checks.
- Visible focus, no focus hidden under sticky UI, skip link, correct headings/landmarks, labelled forms, useful error messages.
- Minimum 24×24 CSS pixel pointer targets; prefer 44×44 for primary/mobile controls.
- Autoplay media/tickers pauseable; motion respects reduced-motion preferences.

### Performance

- At the 75th percentile: LCP ≤ 2.5 s, INP ≤ 200 ms, CLS ≤ 0.1 on both mobile and desktop.
- No homepage ships an unoptimized original hero/gallery image.
- Embedded video/maps load on interaction or below-the-fold proximity.

### Content and compliance

- No placeholder or duplicated starter content.
- School disclosure checklist is complete, homepage link is prominent, files are current, and expiry dates are monitored.
- Broken-link, missing-alt, missing-contact, missing-SEO, and stale-document checks pass.
- Tenant approves public student data and photography before publishing.

### Differentiation

- Pilot homepages pass the fingerprint rules in Section 6.
- Each pilot's first screen communicates organization type, local identity, current priority, and one primary action without scrolling.
- Sahodaya and school sites use separate navigation/content recipes even if their brand colours are similar.

## 11. Prioritized backlog

### P0 — Do first

1. Fix site/page scoping and add a real site/page selector.
2. Introduce template manifests and non-destructive recipe application.
3. Expand tokens and centralize the public section frame.
4. Deliver one Sahodaya pilot (S2 Events & Results) and one school pilot (C1 Admissions).
5. Add school Mandatory Public Disclosure launch gate.
6. Bring school preview/publish/version/theme controls to parity.

### P1 — Complete differentiation

1. Deliver the remaining Sahodaya and school families.
2. Add seasonal Sahodaya homepage modes.
3. Add content-readiness and fingerprint checks.
4. Add audience/task analytics and real-user Web Vitals.
5. Add Malayalam-ready per-page/section content with correct language metadata; do not machine-translate compliance text without review.

### P2 — After pilot evidence

1. AI-assisted copy/image suggestions with mandatory human approval.
2. Saved tenant patterns and approved custom pattern library.
3. A/B testing for admissions CTAs where traffic is sufficient.
4. More expressive motion or campaign microsite themes only after accessibility/performance budgets are proven.

## 12. Recommended first pilot set

- **One high-activity Sahodaya:** S2 Events & Results Live.
- **One resource-heavy Sahodaya:** S3 Academic Resources & Training.
- **One admissions-led school:** C1 Admissions & Parent Discovery.
- **One established school:** C4 Heritage & Values.
- **One innovation-focused school:** C5 Innovation & Future Skills.

Using deliberately different pilots will validate that the platform can create distinct identities before the template library is rolled out to every tenant.
