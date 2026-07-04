# Sports & Kalotsavam — Feature Comparison Report

**Reference sites analysed:** Central Travancore Sahodaya (travancoresahodaya.in, 674 pages), Malappuram Sahodaya (malappuramsahodaya.weebly.com), Kannur Sahodaya (kannursahodaya.in)  
**Our project:** Sahodaya Connect (multi-tenant Laravel 11 + Inertia.js)  
**Date:** July 2026 · **Last updated:** July 2026 (parity pass)

Legend: ✅ Implemented · ⚠️ Partial · ❌ Missing · ⏸ Deferred

---

## Implementation status (July 2026 parity pass)

| Gap | Feature | Status |
|-----|---------|--------|
| GAP-1 | Category-wise public scoreboard | ✅ |
| GAP-2 | Winner poster / shareable graphic | ✅ SVG poster at `/fest/{event}/items/{item}/winners/{mark}/poster.svg` |
| GAP-3 | March Past mandatory item | ✅ |
| GAP-4 | School substitution request form | ✅ |
| GAP-5 | Heats / time trial system | ⏸ Deferred (not required) |
| GAP-6 | Clash request form | ✅ |
| GAP-7 | Item-wise result PDF (public) | ✅ |
| GAP-8 | Excel sports registration upload | ✅ `.xls` template + CSV/XLS/XLSX upload |
| GAP-9 | Document verification day | ✅ |
| GAP-10 | Cluster-based Kids Fest | ✅ Umbrella + cluster child events + combined scoreboard |
| GAP-11 | Event manual PDF | ✅ |
| GAP-12 | Question paper archive | ✅ `/mcq/papers` public archive |
| GAP-13 | English / Science Fest types | ✅ `english_fest`, `science_fest` + catalogs |
| GAP-14 | Max items relay/march-past exclusion | ✅ |

**Also shipped:** School leadership pending banner (Principal, VP, Events Coordinator) · Login role guides on all entry pages.

---

## 1. Sports Workflow

### 1.1 Event Item Catalog

| Feature | Reference Sites | Our Project | Status |
|---|---|---|---|
| Track events (60m–5000m) by age group U8/U10/U12/U14/U17/U19 | ✅ All sites | ✅ `cksc_sports_items.php` — full catalog | ✅ |
| Field events (Long Jump, High Jump, Shot Put, Discus, Javelin, Triple Jump, Hammer, Pole Vault) | ✅ Central Travancore, Malappuram | ✅ Full catalog by age group | ✅ |
| Relay events (4×100m, 4×400m, Mixed) | ✅ Malappuram | ✅ Team type, squad 4+1, subs allowed | ✅ |
| Team games (Football, Basketball, Volleyball, Kabaddi, Cricket, etc.) | ✅ Malappuram | ✅ With squad sizes and subs | ✅ |
| Racket sports (Badminton, Tennis, Table Tennis) | Implied | ✅ Catalog section exists | ✅ |
| Board games (Chess, Carrom) | Implied | ✅ Catalog section exists | ✅ |
| Martial arts (Judo, Taekwondo, Wrestling) | Implied | ✅ Catalog section exists | ✅ |
| Aquatics / Swimming | Implied | ✅ Catalog section exists | ✅ |
| **March Past as scored competition item** | ✅ Malappuram (mandatory, scored) | ✅ Catalog + `is_mandatory` enforcement | ✅ |

---

### 1.2 Registration Workflow

| Feature | Reference Sites | Our Project | Status |
|---|---|---|---|
| Per-item online registration through school portal | ✅ All sites | ✅ `FestRegistrationController::store()` | ✅ |
| CSV bulk import for registrations | Implied | ✅ `FestRegistrationImportService` | ✅ |
| **Excel template download → fill offline → upload** | ✅ Malappuram (`.xlsx`) | ✅ Excel template (`.xls`) + CSV/XLS/XLSX upload | ✅ |
| School registration flat fee + per-candidate fee | ✅ Malappuram | ✅ `FestSportsCompositeFeeService` | ✅ |
| Max participants per item per school | ✅ Malappuram | ✅ `FestEventItem.max_per_school` | ✅ |
| **Max items per participant (relay/march past excluded)** | ✅ Malappuram | ✅ `FestParticipationLimitService` | ✅ |
| Age cutoff date enforcement | ✅ All sites | ✅ `FestEvent.sports_age_cutoff_date` | ✅ |
| Registration open/close dates per item | Implied | ✅ `FestEventItem.reg_start` / `reg_end` | ✅ |
| Document verification day workflow | ✅ Malappuram | ✅ `verification_day` + `FestSchoolVerification` | ✅ |

---

### 1.3 During the Event

| Feature | Reference Sites | Our Project | Status |
|---|---|---|---|
| Chest number assignment and reveal | ✅ All sites | ✅ `FestChestNumberController` | ✅ |
| ID cards with QR code | Implied | ✅ `FestIdCardService` | ✅ |
| Schedule management | ✅ All sites | ✅ `FestScheduleController` | ✅ |
| Schedule clash detection (admin) | Implied | ✅ `FestScheduleConflictService` | ✅ |
| **Time trial / heats system** | ✅ Malappuram | ⏸ Not implemented (deferred) | ⏸ |
| Attendance tracking | Implied | ✅ `FestAttendanceController` | ✅ |
| Athletic records tracking | ✅ Central Travancore | ✅ `FestAthleticRecord` | ✅ |
| Food coupons / catering orders | Implied | ✅ `FestFoodCouponController` | ✅ |

---

### 1.4 Results & Scoring

| Feature | Reference Sites | Our Project | Status |
|---|---|---|---|
| Measurement-based mark entry | ✅ All sites | ✅ `FestMark.measurement_value` | ✅ |
| Auto-rank by measurement | ✅ All sites | ✅ `FestSportsAutoRankService` | ✅ |
| Points per position | ✅ Central Travancore | ✅ `FestGradePointService` | ✅ |
| School scoreboard (public) | ✅ Central Travancore | ✅ `EventContext::scoreboardBySchool()` | ✅ |
| **Category-wise scoreboard (public)** | ✅ Central Travancore | ✅ `/fest/{event}/scoreboard?category=` | ✅ |
| House/colour team scoreboard | Implied | ✅ `EventContext::scoreboardByHouse()` | ✅ |
| Individual championship points | ✅ Central Travancore | ✅ `FestIndividualChampionshipPoint` | ✅ |
| **Winner poster / shareable graphic** | ✅ Central Travancore | ✅ SVG winner poster per 1st/2nd/3rd | ✅ |
| Result certificates (PDF) | Implied | ✅ `FestCertificateController` | ✅ |
| Athletic record-break certificate | ✅ Implied | ✅ `FestAthleticRecordController` | ✅ |
| Results published toggle | ✅ All sites | ✅ `FestEvent.results_published` | ✅ |
| Appeals system | Implied | ✅ `FestAppeal` | ✅ |

---

## 2. Kalotsavam Workflow

### 2.3 Registration Workflow

| Feature | Reference Sites | Our Project | Status |
|---|---|---|---|
| School registers participants per item | ✅ All sites | ✅ School portal | ✅ |
| CSV import for bulk registration | Implied | ✅ Import service | ✅ |
| Per-item registration fee | ✅ Malappuram | ✅ `FestEventItem.fee_amount` | ✅ |
| School-level event registration fee | ✅ Malappuram | ✅ `FestSchoolEventFeeService` | ✅ |
| Kalotsav manual / rules document download | ✅ Central Travancore | ✅ `manual_pdf_path` + public download | ✅ |
| **Substitution request form** | ✅ Malappuram | ✅ `FestSubstitutionRequest` workflow | ✅ |
| **Clash request form** | ✅ Malappuram | ✅ `FestClashRequest` workflow | ✅ |
| Max participants per item per school | ✅ All sites | ✅ `FestEventItem.max_per_school` | ✅ |

### 2.5 Public Portal & Scoreboards

| Feature | Reference Sites | Our Project | Status |
|---|---|---|---|
| Public event listing | ✅ All sites | ✅ `FestPortalController::index()` | ✅ |
| Public schedule | ✅ All sites | ✅ `FestPortalController::schedule()` | ✅ |
| Public item-wise results | ✅ All sites | ✅ `FestPortalController::itemResults()` | ✅ |
| Overall school scoreboard (public) | ✅ All sites | ✅ Live scoreboard | ✅ |
| **Category-wise public scoreboard** | ✅ Central Travancore | ✅ `/fest/{event}/scoreboard?category=` | ✅ |
| **Item-wise result PDFs (public)** | ✅ Malappuram | ✅ `/fest/{event}/items/{item}/results.pdf` | ✅ |
| **Winner poster per winner** | ✅ Central Travancore | ✅ `/fest/.../winners/{mark}/poster.svg` | ✅ |
| Live scoreboard during event | ✅ Central Travancore | ✅ `FestPortalController::live()` | ✅ |

---

## 3. Kids Fest

| Feature | Reference Sites | Our Project | Status |
|---|---|---|---|
| Kids Fest as event type | ✅ Malappuram, Kannur | ✅ `event_type = 'kids_fest'` | ✅ |
| Item catalog | ✅ All sites | ✅ `cksc_kids_fest_items.php` | ✅ |
| **Cluster-based Kids Fest** | ✅ Malappuram | ✅ Umbrella event + cluster child events (`cluster_key`, dates, venue) | ✅ |
| Cluster-wise result aggregation | ✅ Malappuram | ✅ Combined scoreboard rolls up all clusters | ✅ |

---

## 4. Specialty Fests

| Feature | Reference Sites | Our Project | Status |
|---|---|---|---|
| Teacher Fest | ✅ All sites | ✅ `teacher_fest` | ✅ |
| **English Fest as named event type** | ✅ Malappuram | ✅ `english_fest` + `cksc_english_fest_items.php` | ✅ |
| **Science Fest as named event type** | ✅ Malappuram | ✅ `science_fest` + `cksc_science_fest_items.php` | ✅ |
| MSAT / MCQ exams | ✅ Malappuram | ✅ `McqExam` system | ✅ |
| **Question paper download archive** | ✅ Central Travancore | ✅ `/mcq/papers` + Sahodaya upload on exam | ✅ |

---

## 5. Login & school onboarding (added)

| Feature | Our Project | Status |
|---|---|---|
| Role-specific login entry points (`/login`, `/school-login`, `/portal/login`) | ✅ With `LoginRoleGuide` on each page | ✅ |
| Principal / VP / Events Coordinator profile requirements | ✅ `SchoolContactRequirements` — pending banner on dashboard & profile | ✅ |
| 22+ custom tenant roles | ✅ Spatie + `TenantUserCatalog` | ✅ |

---

## 6. Remaining optional items

| Item | Notes |
|------|-------|
| GAP-5 Heats / time trials | Explicitly deferred — not in scope |
| Payment gateway | Reference sites also use offline payment; fee tracking only |
| PNG winner posters | SVG posters implemented; PNG conversion optional later |

---

## 7. Deploy checklist

```bash
php artisan tenants:migrate --force
npm run build
php artisan optimize:clear
```

New public URLs:
- `/fest/{event}/scoreboard?category=lp` or `?cluster=nilambur`
- `/fest/{event}/items/{item}/winners/{mark}/poster.svg`
- `/fest/{event}/items/{item}/results.pdf`
- `/fest/{event}/manual`
- `/mcq/papers`
