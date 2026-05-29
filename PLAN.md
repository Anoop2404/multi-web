# Sahodaya Platform — Full Build Plan
> Based on analysis of 5 Sahodaya + 35 CBSE school websites in Kerala  
> Current state: Phase 1 scaffold complete

---

## Architecture Decision: JSONB vs Own Table

Before phases begin, this rule governs every feature:

| Pattern | Storage | Why |
|---|---|---|
| Content configured once by superadmin (hero text, about, mission) | `site_sections.config` JSONB | Superadmin sets it, tenant admin edits text only |
| Repeated records managed by tenant admin (articles, staff, events) | Own table with `tenant_id` | CRUD operations, sortable, filterable |
| Tenant-wide settings (theme, socials, WhatsApp number) | `tenant_settings` key-value JSONB | Per-tenant config, not per-section |

---

## Phase 2 — Database Expansion + Section Engine
**Goal:** Every public-facing page renders correctly. Section render loop works for all types.

### 2A — New database tables

```
news_articles       tenant_id, title, slug, body, image, category, published_at, is_featured
events              tenant_id, title, slug, description, image, start_date, end_date, venue, is_upcoming
gallery_albums      tenant_id, title, description, cover_image, display_order
gallery_items       album_id, tenant_id, image_path, caption, display_order
staff_members       tenant_id, name, designation, department, photo, qualification, display_order, is_active
achievements        tenant_id, title, description, image, category(academic/sports/cultural/national), achieved_at, level(school/district/state/national/international)
board_results       tenant_id, class(10|12), academic_year, pass_count, total_count, pass_percent, distinctions
toppers             board_result_id, tenant_id, name, photo, percentage, subjects_json, is_perfect_scorer
testimonials        tenant_id, name, photo, designation, quote, rating, display_order
alumni              tenant_id, name, batch_year, current_role, photo, message, is_featured
admission_enquiries tenant_id, name, email, phone, class_applying, message, status, created_at
tc_requests         tenant_id, student_name, class, dob, phone, reason, status, created_at
downloads           tenant_id, title, file_path, category(booklist|calendar|circular|results|form|report|other), academic_year, display_order
job_vacancies       tenant_id, title, description, qualification, last_date, is_active
-- Sahodaya-specific --
office_bearers      tenant_id, name, photo, role, term_from, term_to, display_order
circulars           tenant_id, title, file_path, category, issued_date, academic_year
kalotsav_events     tenant_id, name, year, date, venue, description, is_active
kalotsav_categories event_id, name, description
kalotsav_results    event_id, tenant_id(school), category_id, position(1|2|3), score, notes
```

### 2B — School site sections (Blade components)
Path pattern: `resources/views/sections/{type}/{variant}.blade.php`

Each receives `$config` (JSONB array) and `$tenant`.  
Variants marked ★ are highest priority based on research frequency.

#### Hero ★
- [x] `hero/centered` — already done
- [x] `hero/split-image` — image right, text left
- [x] `hero/video-bg` — YouTube/mp4 background
- [x] `hero/minimal` — text only, no image
- [x] `hero/with-quicklinks` — 3 CTA buttons below (Admission, Gallery, Contact)

#### About the School ★
- [x] `about/text-left` — image right, text left
- [x] `about/text-right` — image left, text right  
- [x] `about/two-column` — history left, vision/mission right
- [x] `about/with-motto` — includes motto, flag, anthem player

#### Principal's Message ★
- [x] `principal_message/card-style` — photo card + message text
- [x] `principal_message/full-width` — full-width quote layout
- [x] `principal_message/with-management` — principal + chairman + director trio

#### Management / Governing Body
- [x] `management/photo-cards` — grid of leadership cards
- [x] `management/table-list` — name, designation, table format

#### Statistics / Highlights ★
- [x] `statistics/counter-cards` — animated scroll-trigger counters
- [x] `statistics/horizontal-strip` — single row colored blocks
- [x] `statistics/with-achievements` — stats + key achievement highlights

#### Facilities ★
- [x] `facilities/icon-grid` — icon + label cards
- [x] `facilities/image-cards` — photo + title cards
- [x] `facilities/with-virtual-tour` — YouTube 360 embed + facility list

#### Academic Programmes
- [x] `academic_programmes/tabs` — tabbed by stream (Science, Commerce, Humanities)
- [x] `academic_programmes/cards` — one card per stream
- [x] `academic_programmes/with-results` — stream info + result stats

#### Staff Directory ★
- [x] `staff/photo-grid` — photo + name + designation
- [x] `staff/table-list` — sortable by department
- [x] `staff/department-tabs` — tabbed by department

#### News & Announcements ★
- [x] `news/grid` — 3-column card grid
- [x] `news/list` — article list with date
- [x] `news/ticker` — scrolling marquee (for breaking news)
- [x] `news/featured-plus-list` — 1 featured + sidebar list

#### Events Calendar ★
- [x] `events/card-grid` — upcoming + past event cards
- [x] `events/timeline` — vertical timeline
- [x] `events/list` — simple date + title list

#### Gallery ★
- [x] `gallery/masonry-grid` — masonry layout with lightbox
- [x] `gallery/carousel` — auto-play image carousel
- [x] `gallery/album-based` — album thumbnails → album view

#### Video Gallery ★
- [x] `video_gallery/youtube-grid` — YouTube embed cards
- [x] `video_gallery/featured-embed` — single featured video + list

#### Board Results / Toppers ★ (NEW — critical)
- [x] `board_results/toppers-cards` — photo + name + % + class 10/12 cards
- [x] `board_results/stats-plus-toppers` — pass% stats + topper grid
- [x] `board_results/year-tabs` — tabbed by academic year

#### Achievements ★
- [x] `achievements/cards` — card grid, filterable by category
- [x] `achievements/timeline` — chronological timeline
- [x] `achievements/badge-wall` — certificate photo grid (award photos)

#### CBSE Mandatory Disclosure ★ (NEW — legally required)
- [x] `mandatory_disclosure/structured` — CBSE-format: General Info, Documents, Infrastructure, Staff, Results
- [x] `mandatory_disclosure/accordion` — collapsible sections

#### Admissions ★
- [x] `admissions/info-block` — procedure + eligibility + contact
- [x] `admissions/with-form` — info + embedded enquiry form
- [x] `admissions/fee-structure` — class-wise fee table

#### Downloads Library ★ (NEW — standard expectation)
- [x] `downloads/card-grid` — file type icon + title + category + download
- [x] `downloads/category-tabs` — tabbed by category (calendar, booklist, papers, etc.)

#### Alumni
- [x] `alumni/registration-form` — alumni signup form
- [x] `alumni/featured-grid` — notable alumni cards

#### House System
- [x] `house_system/color-cards` — 4 house color cards with names/captains
- [x] `house_system/with-points` — includes points tally

#### Clubs & Activities
- [x] `clubs/icon-grid` — club name + icon cards
- [x] `clubs/with-photos` — photo + description per club

#### Student / Parent Portal Links
- [x] `portals/quick-links` — CampusCare/fee portal/DIKSHA link cards

#### Testimonials ★
- [x] `testimonials/carousel` — auto-rotating quote cards
- [x] `testimonials/card-grid` — static grid of testimonials

#### Career Guidance
- [x] `career_guidance/info-block` — streams, options, counsellor details

#### School Magazine / Newsletter
- [x] `publications/download-cards` — issue cards with PDF download

#### ATL / Innovation Lab
- [x] `atl/feature-block` — ATL showcase with photos and description

#### Custom Page
- [x] `custom_page/freeform` — WYSIWYG rendered content

#### Contact & Location ★
- [x] `contact/side-by-side` — details left, map right
- [x] `contact/stacked` — details above, full-width map
- [x] `contact/with-whatsapp` — includes WhatsApp CTA

---

### 2C — Sahodaya site sections

#### Hero (Sahodaya variant)
- [x] `hero/sahodaya-centered` — with affiliated board + cluster info
- [x] `hero/event-promo` — Kalotsav/Sports Meet promotional hero with quick links

#### About the Sahodaya
- [x] `about_sahodaya/single-column` — history, objectives, jurisdiction
- [x] `about_sahodaya/with-stats` — about + member count stats

#### Office Bearers ★
- [x] `office_bearers/photo-cards` — photo + name + role + term
- [x] `office_bearers/table-list` — structured table

#### Member Schools Directory ★
- [x] `member_schools/card-grid` — logo + school name + location + type + link
- [x] `member_schools/table-list` — sortable table
- [x] `member_schools/map-view` — schools on Google Maps embed

#### News & Circulars ★
- [x] `news_circulars/grid` — card grid (news + circulars combined)
- [x] `news_circulars/list` — dated list

#### Events & Programs ★
- [x] `events_programs/cards` — upcoming events grid
- [x] `events_programs/timeline` — chronological timeline

#### Kalotsav / Sports Meet ★ (NEW — Sahodaya-specific)
- [x] `kalotsav/scoreboard` — school-wise results table per event/category
- [x] `kalotsav/results-tabs` — tabbed by year/category
- [x] `kalotsav/registration-cta` — school login + registration block

#### Circular Library ★ (NEW)
- [x] `circulars/category-filter` — filterable by category + year
- [x] `circulars/accordion` — grouped by year

#### Downloads (Sahodaya)
- [x] `downloads/sahodaya-grid` — manuals, exam papers, minutes of meetings

#### Governance / Bye-laws (NEW)
- [x] `governance/structure` — org chart + rules + bye-laws downloads

#### Organization Timeline (NEW)
- [x] `timeline/milestone` — visual year-by-year milestones

#### Testimonials / Voices
- [x] `testimonials/principal-quotes` — quotes from member school principals

---

### 2D — Global widgets (on every public page, not sections)

These are injected by the layout, not sections. Configured via `tenant_settings`.

| Widget | Blade partial | Config keys |
|---|---|---|
| Floating WhatsApp button | `partials/widgets/whatsapp.blade.php` | `widget.whatsapp_number` |
| Top bar (phone + email + socials) | `partials/widgets/topbar.blade.php` | `widget.topbar.*` |
| Admission alert banner | `partials/widgets/admission-banner.blade.php` | `widget.admission_banner.*` |
| News ticker | `partials/widgets/ticker.blade.php` | Pulls latest 5 news headlines |
| CBSE affiliation badge | `partials/widgets/cbse-badge.blade.php` | `tenant.cbse_affiliation_no` |
| Visitor counter | `partials/widgets/visitor-counter.blade.php` | Redis counter per tenant |
| Social media strip | `partials/widgets/socials.blade.php` | `widget.social_links.*` |

- [x] Floating WhatsApp button
- [x] Top bar (phone + email + socials)
- [x] Admission alert banner
- [x] News ticker
- [x] CBSE affiliation badge
- [x] Visitor counter
- [x] Social media strip

### 2E — Navbar variants
- [x] `partials/navbars/centered-below` — centered logo + menu below
- [x] `partials/navbars/sticky-transparent` — transparent over hero, goes solid on scroll

### 2F — Footer variants
- [x] `partials/footers/two-column-logo` — logo + links + contact
- [x] `partials/footers/minimal-single-row` — single row copyright

### 2G — Theme engine
- [x] Theme config keys in tenant_settings
- [x] skin_presets table
- [x] `partials/theme-vars.blade.php` — CSS custom properties injection

### 2H — Redis cache + invalidation
- [x] Cache key pattern: `site:{tenant_id}:{page_slug}`
- [x] TTL: 1 hour
- [x] Cache invalidation on content save
- [x] Cache tags for group purge

---

## Phase 3 — Superadmin Builder (Vue 3)
**Goal:** Superadmin can fully configure any tenant's site without touching DB or code.

### 3A — Tenant management pages (complete CRUD)
- [x] `Pages/Admin/Tenants/Index.vue` — full implementation
- [x] `Pages/Admin/Tenants/Create.vue` — type, name, domain, subdomain, parent_sahodaya, plan
- [x] `Pages/Admin/Tenants/Edit.vue` — same as create + activate/deactivate
- [x] `Pages/Admin/Tenants/Show.vue` — tenant overview + quick links to builder

### 3B — Site Builder
- [x] `Pages/Admin/Builder/Sections.vue` — section list, add/edit/delete/toggle/reorder
- [x] `Pages/Admin/Builder/Theme.vue` — color pickers, font dropdowns, skin presets
- [x] `Pages/Admin/Builder/Nav.vue` — layout variant, drag-to-order menu items, sub-menus
- [x] `Pages/Admin/Builder/Footer.vue` — layout variant, quick links, contact, socials, copyright
- [x] `Pages/Admin/Builder/Widgets.vue` — toggle/configure all global widgets

### 3C — Backend API for builder
- [x] Sections CRUD + reorder + toggle endpoints
- [x] Settings get/put endpoints
- [x] Nav get/save endpoints
- [x] Footer get/save endpoints
- [x] Theme get/save endpoints
- [x] Widgets get/save endpoints
- [x] Cache invalidation on all updates

### 3D — Section schema config (PHP)
- [x] `config/sections.php` — defines form fields for all 38+ section types/variants

---

## Phase 4 — School Admin Panel
**Goal:** School admin can manage all content independently within their site.

### 4A — School admin routes
- [x] Route prefix: `school-admin/{tenantId}`
- [x] Auth + role middleware
- [x] All CRUD routes for News, Events, Gallery, Staff, Achievements, Board Results, Downloads, Enquiries, TC Requests, Alumni, Testimonials, Vacancies, Settings, Contact

### 4B — Vue pages for school admin
- [x] `Dashboard.vue` — stats + quick actions
- [x] `News/Index.vue` — list + toggle
- [x] `News/Create.vue` — create form
- [x] `News/Edit.vue` — edit form
- [x] `Events/Index.vue` — list
- [x] `Events/Create.vue` — create form
- [x] `Events/Edit.vue` — edit form
- [x] `Gallery/Index.vue` — albums + photo upload
- [x] `Staff/Index.vue` — list with filter
- [x] `Staff/Create.vue` — create form
- [x] `Staff/Edit.vue` — edit form
- [x] `Achievements/Index.vue` — list + inline CRUD
- [x] `BoardResults/Index.vue` — results list + form
- [x] `BoardResults/Toppers.vue` — topper CRUD
- [x] `Downloads/Index.vue` — file upload + list
- [x] `Enquiries/Index.vue` — status management
- [x] `TcRequests/Index.vue` — status management
- [x] `Alumni/Index.vue` — approve/feature/delete
- [x] `Testimonials/Index.vue` — testimonial CRUD
- [x] `JobVacancies/Index.vue` — vacancy CRUD
- [x] `Settings/Index.vue` — logo, contact, social, SEO
- [x] `Contact/Edit.vue` — contact info + hours + map

### 4C — What school admin CANNOT change
- [x] Section types, variants, order, visibility (superadmin only)
- [x] Theme colors/fonts
- [x] Navbar layout/menu structure
- [x] Footer layout
- [x] Domain or subdomain

### 4D — Media uploads
- [x] S3/MinIO storage
- [x] File upload on all forms with `forceFormData`
- [x] Image sizing for staff photos, galleries, toppers

---

## Phase 5 — Sahodaya Admin Panel
**Goal:** Sahodaya admin manages cluster-level content and member school directory.

### 5A — Vue pages for sahodaya admin
- [x] `Dashboard.vue` — stats, recent circulars, active Kalotsav
- [x] `OfficeBearers/Index.vue` — combined form + list
- [x] `Circulars/Index.vue` — upload + filter by category
- [x] `Kalotsav/Index.vue` — event list + create
- [x] `Kalotsav/Show.vue` — categories, results entry, scoreboard
- [x] `Schools/Index.vue` — member school directory overview

### 5B — Kalotsav Event Management
- [x] Event create/list
- [x] Categories per event
- [x] Results entry per school per category
- [x] Live scoreboard

### 5C — What sahodaya admin CANNOT change
- [x] Section types/layout/theme (superadmin only)
- [x] Member school list additions (superadmin only)
- [x] Domain/subdomain

---

## Phase 6 — Advanced Modules
**Goal:** Features that differentiate this platform from a basic website builder.

### 6A — CBSE Mandatory Disclosure Module ★
- [x] Blade sections: `mandatory_disclosure/structured`, `mandatory_disclosure/accordion`
- [x] Config in `tenant_settings` under `cbse_disclosure.*`

### 6B — Board Results & Toppers Module ★
- [x] DB: `board_results` + `toppers` tables
- [x] Public sections: toppers-cards, stats-plus-toppers, year-tabs
- [x] School admin forms for results + toppers

### 6C — Online Admission Form Module
- [x] DB: `admission_enquiries` table
- [x] Public section: `admissions/with-form`
- [x] School admin: Enquiries list with status

### 6D — E-TC Request System
- [x] DB: `tc_requests` table
- [x] Public form: `tc_request/form`
- [x] School admin: TC Requests list with status

### 6E — Downloads Library Module ★
- [x] DB: `downloads` table
- [x] Public sections: card-grid, category-tabs
- [x] School admin: upload + list

### 6F — Alumni Module
- [x] DB: `alumni` table
- [x] Public sections: registration-form, featured-grid
- [x] School admin: approve/feature/delete

### 6G — Job Vacancies Module
- [x] DB: `job_vacancies` table
- [x] Public section: `job_vacancies/listing`
- [x] School admin: CRUD

### 6H — Kalotsav Scoreboard (Public)
- [x] DB: kalotsav events, categories, results
- [x] Public sections: scoreboard, results-tabs, registration-cta
- [x] Sahodaya admin: event management + results entry

---

## Phase 7 — Polish, SEO & Performance
**Goal:** Production-ready. Fast, found on Google, mobile-perfect.

### 7A — SEO per tenant
- [ ] Each page: `<title>`, `<meta description>`, Open Graph tags
- [ ] SEO settings in tenant_settings (title, description, keywords, tagline)
- [ ] School admin Settings page has SEO section

### 7B — Sitemap per tenant
- [ ] Generate `/sitemap.xml` for each tenant domain

### 7C — Mobile responsive QA
- [ ] Test every section variant
- [ ] Hamburger nav on all variants
- [ ] Touch-friendly gallery lightbox

### 7D — Performance
- [ ] Cloudflare domain mapping + wildcard SSL
- [ ] Edge cache headers
- [ ] Image lazy loading
- [ ] Font preload

### 7E — Accessibility
- [ ] `lang` attribute support
- [ ] Color contrast
- [ ] Alt attributes on all images

---

## Phase 8 — Future / Post-launch

| Feature | Notes |
|---|---|
| Automated tenant onboarding | Superadmin fills a form → tenant + default sections + admin account created in one flow |
| Analytics dashboard per tenant | Page views, top pages, traffic source (via Plausible or GA4 embed) |
| Malayalam language support | `i18n` for public site, switch in tenant_settings |
| Flutter mobile app | REST API layer (already has routes/api.php) consuming tenant data |
| School login for Kalotsav registration | Separate login for member schools to register for Sahodaya events |
| Parent/student portal | External portal links panel (currently) → internal portal later |
| Notification system | Email/WhatsApp notifications for new admission enquiries, TC requests |
| Multi-location support | School with multiple campuses (branch tenant model) |
| Custom domain SSL automation | Let's Encrypt wildcard via ACME DNS challenge |

---

## Summary: What to build and in what order

```
Phase 1  ✅ Done        — Laravel scaffold, Docker, DB schema, packages
Phase 2  ✅ Complete    — All Blade sections (70+ components), widgets, cache, theme
Phase 3  ✅ Complete    — Superadmin builder Vue SPA (section manager, theme, nav, footer) + Tenant CRUD
Phase 4  ✅ Complete    — School admin panel (all content CRUD pages)
Phase 5  ✅ Complete    — Sahodaya admin panel (cluster content + Kalotsav)
Phase 6  ✅ Complete    — Advanced modules (Mandatory Disclosure, Board Results, Admissions, E-TC, Downloads, Alumni, Jobs, Kalotsav Scoreboard)
Phase 7  🔲 Next        — SEO, sitemaps, performance, mobile QA
Phase 8  🔲 Post-launch — Analytics, Flutter, onboarding automation
```

### Effort breakdown (rough)

| Phase | Components | Estimated effort |
|---|---|---|
| Phase 2 | ~70 Blade components + 7 widgets + cache | Large |
| Phase 3 | ~15 Vue pages + section schema config + API | Large |
| Phase 4 | ~25 Vue pages + controllers + media uploads | Medium-Large |
| Phase 5 | ~15 Vue pages + Kalotsav system | Medium |
| Phase 6 | ~6 specialized modules with DB + forms | Medium |
| Phase 7 | SEO + mobile QA + Cloudflare setup | Small-Medium |

### DB additions needed (beyond scaffold)

```
Migration file needed:                   Tables created:
2026_05_24_000004_content_tables.php  → news_articles, events, gallery_albums, gallery_items,
                                        staff_members, achievements, testimonials, alumni,
                                        downloads, job_vacancies
2026_05_24_000005_results_tables.php  → board_results, toppers
2026_05_24_000006_forms_tables.php    → admission_enquiries, tc_requests
2026_05_24_000007_sahodaya_tables.php → office_bearers, circulars, kalotsav_events,
                                        kalotsav_categories, kalotsav_results
2026_05_24_000008_skin_presets.php    → skin_presets