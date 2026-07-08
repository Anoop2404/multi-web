# Sahodaya Connect Software Requirements Specification

Document version: 1.0  
Document status: Draft baseline with current product completion status  
Prepared for: Sahodaya Connect / Multi Web Laravel Platform  
Prepared on: 2026-07-06  
Primary repository: `multi-web`  

---

## 1. Introduction

### 1.1 Purpose

This Software Requirements Specification (SRS) defines the expected behavior, scope, users, functional requirements, data requirements, interfaces, constraints, and quality attributes of the Sahodaya Connect platform.

The document is intended to help any stakeholder quickly identify the project and understand what the system is supposed to do. It can be used by product owners, developers, testers, deployment teams, school administrators, Sahodaya cluster administrators, auditors, and future maintainers.

### 1.2 Product Identity

Sahodaya Connect is a multi-tenant web platform for managing Sahodaya school clusters, member schools, annual membership workflows, student and teacher records, inter-school fest programs, MCQ exams, training programs, finance, public websites, and operational portals.

The system supports three major tenant contexts:

| Tenant type | Purpose |
|-------------|---------|
| Sahodaya | Cluster body that manages member schools, membership, fests, MCQ exams, training, finance, public content, and reports. |
| School | Member school that manages students, teachers, annual submissions, fest registrations, MCQ registrations, training registrations, payments, and optional school website content. |
| State | Optional state-level administration for propagated programs, state remittances, and consolidated results. |

### 1.3 Intended Audience

This SRS is intended for:

- Super administrators who manage tenants, central configuration, subscriptions, and master data.
- State administrators who manage state-level programs and remittances.
- Sahodaya administrators and staff who operate clusters, member schools, events, exams, training, and finance.
- School principals, vice principals, administrators, coordinators, teachers, students, group admins, and house admins.
- Event operations teams, judges, mark coordinators, exam controllers, and exam staff.
- Developers, testers, maintainers, support staff, and auditors.

### 1.4 Scope

The platform shall provide a single Laravel-based multi-tenant application that supports:

- Central administration of Sahodaya and school tenants.
- Tenant-specific data isolation for Sahodaya clusters and member schools.
- Role-based access for administrative, operational, school, teacher, student, and public users.
- Membership registration, school applications, annual submissions, payments, and verification.
- Student and teacher record management with verification, locks, and profile change workflows.
- Fest programs including Kalotsav, Sports Meet, Kids Fest, Teacher Fest, English Fest, and Science Fest.
- Event setup, item catalogs, registration, scheduling, attendance, mark entry, results, certificates, ID cards, reports, and finance.
- MCQ exam series, question banks, registrations, hall tickets, online/offline exam flows, attendance, marks, and results.
- Training programs and teacher/school registrations.
- Finance modules including membership fees, fest fees, MCQ fees, ledger, receivables, payables, opening balances, bank reconciliation, and state remittances.
- Public tenant websites, school application forms, fest public portals, MCQ paper archives, SEO files, and CMS content.
- JSON APIs for authenticated web/mobile clients.
- Notifications, exports, PDFs, QR codes, file uploads, and audit trails.

### 1.5 Definitions and Abbreviations

| Term | Meaning |
|------|---------|
| SRS | Software Requirements Specification. |
| Tenant | A separate Sahodaya, school, or state context managed by the platform. |
| Sahodaya | A school cluster body that manages member schools and cluster-level activities. |
| Member school | A school tenant attached to a Sahodaya tenant. |
| Portal | A focused login area for a role such as student, teacher, judge, fest ops, or exam staff. |
| Fest | A competition/event program such as Kalotsav or Sports Meet. |
| Event item | A competition item inside a fest event. |
| Item head | A grouping/category of event items, especially in sports. |
| Chest number | Sports participant identifier used on fest day. |
| MCQ | Multiple-choice-question examination module. |
| CMS | Content management system for public websites. |
| API | Application programming interface. |
| RBAC | Role-based access control. |

### 1.6 References

This SRS is based on the current repository structure and project documentation, including:

- `docs/PLATFORM_GUIDE.md`
- **`docs/erp/README.md`** — Phase-wise ERP product specification suite (23 phases, BRS through deployment)
- `FEATURE_PLAN_V2.md`
- `routes/web.php`
- `routes/tenant.php`
- `routes/api.php`
- `app/Support/TenantUserCatalog.php`
- `app/Support/ProgramRouteMap.php`
- `composer.json`
- `package.json`

### 1.7 Current Product Completion Status

This section marks what is already present in the current product based on the repository documentation, route map, controllers, models, services, middleware, and support classes. It is not a formal QA sign-off; final acceptance still requires environment setup, seed/test data, and end-to-end user testing.

| Status | Meaning |
|--------|---------|
| Completed | Feature is present in the current product with routes and supporting implementation. |
| Mostly completed | Core feature exists, but some edge cases, final QA, workflow polish, or product confirmation may still be needed. |
| Partial | Some implementation exists, but the feature should not be treated as fully complete without further work. |
| Config-dependent | Feature exists but requires environment variables, tenant settings, external credentials, or deployment configuration. |
| Not included | Feature is documented as outside the current baseline or not implemented in the current product. |

| Product area | Current status | Notes |
|--------------|----------------|-------|
| Central admin and tenant management | Completed | Tenant CRUD, Sahodaya/school creation, tenant database setup/migration, admin assignment, master data, audit, and billing routes are present. |
| State administration | Completed | State dashboard, state programs, remittances, Kalotsav result views, sports views, and winner exports are present. |
| Authentication and portal login | Completed | Admin login, portal login, school-code login, password reset, password change, email verification, and role-based redirects are present. |
| Multi-tenancy and tenant isolation | Completed | Route-tenant and host-based tenant initialization are present for admin, portal, API, and public tenant routes. |
| Role and permission control | Completed | Sahodaya roles, school roles, permission catalog, portal-only roles, event duties, and scoped middleware are present. |
| Sahodaya dashboard and setup | Completed | Dashboard, setup wizard, public content, users, nav visibility, and membership setup routes are present. |
| Member school applications | Completed | Public school registration, throttling, application review, bulk approval/rejection, and school export are present. |
| Membership settings and annual submissions | Completed | Fees, slabs, payment details, categories, classes, teaching types, submission review, payment verification, receipts, and reports are present. |
| Academic and financial years | Completed | Academic year creation, activation, closing, financial year creation, and current financial year selection are present. |
| Student management | Completed | CRUD, bulk create, import, templates, photos, ZIP photo upload, portal provisioning, verification, and reports are present. |
| Student locks and change requests | Mostly completed | Registration windows, emergency lock, per-school lock overrides, school approval, and Sahodaya approval routes/services are present; final behavior should be validated with live workflow testing. |
| Teacher management | Completed | Teacher CRUD, photos, portal provisioning, password reset, verification, rejection, portal profile, and profile change routes are present. |
| School administration | Completed | Dashboard, setup code, users, roles, houses, circular acknowledgement, notifications, payments, and school-scoped reports are present. |
| School coordinator scoping | Completed | Event/program/MCQ coordinator role support and scope middleware are present. |
| Fest program hubs | Completed | Kalotsav, Sports Meet, Kids Fest, Teacher Fest, English Fest, Science Fest, and custom program support are present. |
| Fest catalogs and taxonomy | Completed | Master catalogs, catalog sync/assignment, event items, item heads, taxonomy masters, category masters, and sports item heads are present. |
| Fest event lifecycle | Completed | Event creation, cloning, spawning, settings, lifecycle, locks, venues, stages, eligibility, registration windows, fees, numbering, volunteers, and records are present. |
| Fest registration workflow | Completed | School registration, on-behalf registration, imports, approval/rejection, cancellation, substitution, clash requests, and verification gates are present. |
| Fest finance | Completed | Event fee models, school event fees, proofs, approval/rejection, fee ledger, exports, invoices, demand PDFs, and ledger account links are present. |
| Fest scheduling and attendance | Completed | Schedule creation, auto generation, publishing, imports, item scheduling, reordering, attendance, and attendance imports are present. |
| Fest day operations | Completed | Fest ops portal, coordinator screens, registration desk, stage queue, attendance, kitchen, gate check, appeals, certificates, and admit-card desk support are present. |
| Judge and mark coordinator portals | Completed | Judge portal, fest coordinator portal, assignments, scoped mark pages, and mark storage routes are present. |
| Mark entry and result publication | Completed | Admin marks, portal marks, mark imports, auto ranking, result publish/unpublish, promotion, auto promotion, and qualification revocation are present. |
| Sports Meet | Completed | Age groups, sports catalog, item heads, chest numbers, green room, athletic records, record breaks, championship, houses, rankings, and sports composite fees are present. |
| Kalotsav and multi-level promotion | Mostly completed | Kalotsav hubs, school rounds, linked rounds, promotions, state program propagation, and winner exports are present; final event-season behavior should be verified using real event data. |
| Certificates, ID cards, hall tickets, receipts, and PDFs | Completed | Certificate templates/search/generation/ZIP/collection, ID cards, admit cards, hall tickets, receipts, invoices, DomPDF, and QR support are present. |
| MCQ exams | Completed | Series, levels, exams, eligibility, question banks, school registrations, fees, hall tickets, online exam, offline attendance, marks, supervision, results, and public archives are present. |
| Training programs | Mostly completed | Training program, school/teacher registration, eligibility, attendance, certificates, and teacher portal access are present; final attendance/certificate flows should be confirmed with test data. |
| Finance hub and ledger | Completed | Receivables, payables, opening balances, ledger, financial statements, fee waiver, bank reconciliation, account heads, and state remittances are present. |
| Public websites and CMS | Config-dependent | CMS, site builder, news, events, galleries, pages, SEO, public portal, and tenant host routes are present but depend on website enablement and domain configuration. |
| Public fest portal | Completed | Public event index, details, schedule, results, item results, scoreboards, live data, records, search, participant pages, PDFs, and posters are present. |
| API v1 | Completed | Auth, notifications, public school registration, school dashboard/students/registration/teachers/MCQ/training/fest, and Sahodaya dashboard/schools/payments/submissions/reports/events/MCQ/training endpoints are present. |
| Notifications | Config-dependent | In-app notifications and FCM token storage are present; email, SMS, and push delivery depend on environment and service configuration. |
| File storage and media | Config-dependent | Local/S3-compatible storage, media uploads, proofs, photos, PDFs, and generated files are supported but depend on disk and permission configuration. |
| Direct online payment gateway | Not included | Current product uses payment proof upload and manual verification; no direct payment gateway settlement is assumed in this SRS baseline. |
| Offline-first native mobile sync | Not included | API support exists, but offline-first native synchronization is outside the current baseline. |

---

## 2. Overall Description

### 2.1 Product Perspective

Sahodaya Connect is a web application built as a multi-tenant Laravel platform. It acts as a central operating system for Sahodaya clusters and member schools. The product combines administration, school management, event operations, exam management, finance, reporting, and public-facing websites.

The platform has multiple access surfaces:

| Surface | URL pattern | Primary users |
|---------|-------------|---------------|
| Central admin | `/admin` | Super admin, state admin, central staff. |
| Sahodaya admin | `/sahodaya-admin/{sahodayaId}` | Sahodaya admin and permitted staff. |
| School admin | `/school-admin/{schoolId}` | School principal, school admin, school staff, coordinators. |
| Public tenant website | Tenant domain routes | Public visitors, schools, parents, participants. |
| Student portal | `/portal/student/{schoolId}` | Students. |
| Teacher portal | `/portal/teacher/{schoolId}` | Teachers. |
| Fest ops portal | `/portal/fest-ops/{sahodayaId}` | Assigned event operations staff. |
| Judge portal | `/portal/judge/{sahodayaId}` | Assigned judges. |
| Fest coordinator portal | `/portal/fest-coordinator/{sahodayaId}` | Mark entry coordinators. |
| Exam portal | `/portal/exam/{sahodayaId}` | Exam controller and exam staff. |
| House admin portal | `/portal/house-admin/{schoolId}` | House admins. |
| Group admin portal | `/portal/group/{schoolId}` | Class/group admins. |
| API | `/api/v1/*` | Mobile clients, external clients, or SPA clients. |

### 2.2 Product Functions

At a high level, the system shall:

- Manage platform tenants and their databases.
- Authenticate users and route them to the correct panel or portal.
- Enforce tenant, role, permission, and assignment scopes.
- Manage schools, annual membership, payments, and data submissions.
- Manage students, teachers, verification, locks, and change requests.
- Configure and operate fest programs and events.
- Register students and teachers for events and exams.
- Conduct online and offline MCQ exam workflows.
- Assign event staff, judges, exam staff, house admins, group admins, and coordinators.
- Collect, verify, post, and report financial transactions.
- Generate reports, certificates, hall tickets, ID cards, receipts, invoices, exports, and PDFs.
- Publish public websites, public fest schedules, results, scoreboards, participant pages, and archives.
- Provide API endpoints for school and Sahodaya mobile/web clients.
- Maintain auditability and operational traceability.

### 2.3 User Classes

| User class | Description |
|------------|-------------|
| Super admin | Platform-level administrator with tenant, billing, master data, and audit access. |
| State admin | State-level administrator for state programs, results, and remittances. |
| Sahodaya admin | Cluster owner with full Sahodaya administration rights. |
| Sahodaya staff | Cluster staff with custom permissions. |
| Registration coordinator | Sahodaya staff focused on fest registrations. |
| Sahodaya finance | Sahodaya staff focused on membership payments, fest payments, MCQ fees, ledger, and finance reports. |
| Certificate collector | Staff who manages generated certificates and collection tracking. |
| Data entry user | Staff who assists with event data and mark entry. |
| Event coordinator | Sahodaya staff who manages assigned events. |
| Mark entry admin | Sahodaya panel user who can manage marks. |
| Judge | Portal-only user assigned to event items for mark entry. |
| Mark entry coordinator | Portal user assigned to mark entry for one or more events. |
| Fest ops | Portal user assigned per event and duty. |
| Exam controller | Portal user who manages MCQ attendance, supervision, and marks. |
| Exam staff | Portal user focused on exam hall attendance. |
| School principal | Highest school-level user who can manage school users and school operations. |
| School vice principal | School leadership user who can manage coordinators and staff. |
| School admin | School management user for day-to-day operations. |
| School staff | School user with limited view and submission permissions. |
| School event coordinator | School user scoped to assigned events or programs. |
| School sports/kalotsavam/MCQ/training/finance coordinator | School users scoped to specific operational areas. |
| Group admin | Portal user for class/group student and fest oversight. |
| House admin | Portal user for house-based student, registration, and ranking views. |
| Teacher | Teacher portal user for profile, training, fest information, and MCQ question banks. |
| Student | Student portal user for profile, fest registrations, schedule, results, certificates, and MCQ exams. |
| Public visitor | Unauthenticated visitor to tenant websites, public fest pages, and school application pages. |

### 2.4 Operating Environment

The system shall operate in a standard Laravel web application environment:

- Backend language: PHP 8.3 or compatible version required by the repository.
- Backend framework: Laravel 13.
- Frontend delivery: Inertia.js with Vue 3 where applicable.
- Styling/build tooling: Vite and Tailwind CSS.
- Authentication APIs: Laravel Sanctum.
- Multi-tenancy: Stancl Tenancy.
- Roles and permissions: Spatie Laravel Permission.
- Activity logging: Spatie Laravel Activitylog.
- Media/file handling: Spatie Media Library, local storage, and optional S3-compatible storage.
- PDF generation: Dompdf.
- QR code generation: Endroid QR Code.
- Testing support: PHPUnit and Playwright.
- Queue support: Laravel queue workers for asynchronous jobs where configured.

### 2.5 Design and Implementation Constraints

- The system shall preserve tenant data separation across Sahodaya and school tenants.
- Tenant resolution shall be supported by route tenant IDs and request host where applicable.
- Administrative panel access shall require authentication and password change compliance.
- Portal-only roles shall use dedicated portals instead of the full Sahodaya admin sidebar.
- School event coordinators shall be restricted by assigned program/event scopes.
- Public website routes shall respect global and tenant-level website enablement settings.
- Payment workflows shall support upload and verification of payment proof unless a direct payment gateway is introduced later.
- Current implementation shall follow Laravel conventions, Eloquent models, controllers, middleware, services, policies, validation, and route groups.

### 2.6 Assumptions and Dependencies

- Each Sahodaya can have its own tenant database.
- Schools belong to a Sahodaya by parent relationship.
- Users may have one or more roles depending on the authentication and authorization model.
- PDF, QR, image upload, and file storage features depend on server filesystem permissions and configured disks.
- Email, SMS, push notification, and storage integrations depend on valid environment configuration.
- Public websites depend on domain routing and tenant host resolution.
- Online MCQ exams depend on reliable browser sessions and network connectivity.
- Finance postings depend on verified payment records and ledger account configuration.

### 2.7 Out of Scope for This Baseline

The following items are not assumed unless separately implemented or configured:

- Direct online payment gateway settlement.
- Biometric attendance hardware integration.
- Offline-first native mobile synchronization.
- External board, CBSE, or government database integrations.
- Automated legal compliance certification.
- Real-time live streaming of events.

---

## 3. System Architecture Requirements

### 3.1 Multi-Tenant Architecture

| ID | Requirement | Priority |
|----|-------------|----------|
| AR-001 | The system shall support central, Sahodaya, school, and public tenant contexts. | Must |
| AR-002 | The system shall initialize tenant context by route tenant ID for authenticated admin and portal routes. | Must |
| AR-003 | The system shall initialize tenant context by request host for public tenant website routes. | Must |
| AR-004 | The system shall prevent public tenant access from central domains where tenant host routing is required. | Must |
| AR-005 | The system shall support separate tenant databases for Sahodaya clusters. | Must |
| AR-006 | The system shall maintain school-to-Sahodaya relationships through parent tenant linkage. | Must |
| AR-007 | The system shall allow central administrators to create, configure, migrate, activate, and deactivate tenants. | Must |

### 3.2 Authentication and Session Architecture

| ID | Requirement | Priority |
|----|-------------|----------|
| AR-008 | The system shall provide login routes for central/admin users and portal users. | Must |
| AR-009 | The system shall support password reset and password change workflows for portal users. | Must |
| AR-010 | The system shall redirect authenticated users to the correct panel or portal based on role and tenant. | Must |
| AR-011 | The system shall expose authenticated API login, logout, and current-user endpoints. | Must |
| AR-012 | The system shall use token-based authentication for API clients through Sanctum. | Must |

### 3.3 Authorization Architecture

| ID | Requirement | Priority |
|----|-------------|----------|
| AR-013 | The system shall enforce role-based access control for all protected routes. | Must |
| AR-014 | The system shall enforce granular permissions for Sahodaya staff roles where configured. | Must |
| AR-015 | The system shall enforce school leadership rules for creating and managing school users. | Must |
| AR-016 | The system shall restrict event operations users to assigned events and assigned duties. | Must |
| AR-017 | The system shall restrict judges to assigned events/items. | Must |
| AR-018 | The system shall restrict exam staff to assigned MCQ exam operations. | Must |
| AR-019 | The system shall restrict school event coordinators to assigned programs, events, or MCQ exams. | Must |

---

## 4. Functional Requirements

### 4.1 Central Administration

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-001 | The system shall provide a central admin dashboard with tenant counts and active tenant statistics. | Must |
| FR-002 | The system shall allow super admins to create Sahodaya tenants. | Must |
| FR-003 | The system shall allow super admins to create school tenants. | Must |
| FR-004 | The system shall allow super admins to view, edit, update, and delete tenant records subject to authorization. | Must |
| FR-005 | The system shall allow tenant logo upload and database configuration. | Should |
| FR-006 | The system shall allow tenant database migrations from the central admin panel. | Must |
| FR-007 | The system shall allow central admins to assign and remove Sahodaya admin users. | Must |
| FR-008 | The system shall allow central admins to assign and remove school admin users. | Must |
| FR-009 | The system shall allow central admins to reject membership for tenant records where applicable. | Should |
| FR-010 | The system shall provide central master data management for class categories, teaching types, subjects, designations, and age categories. | Must |
| FR-011 | The system shall provide audit log viewing and export for central administrative actions. | Should |
| FR-012 | The system shall support subscription and billing plan management for tenants. | Should |

### 4.2 State Administration

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-013 | The system shall provide state admin dashboard access for authorized state administrators. | Must |
| FR-014 | The system shall allow state admins to create, update, publish, and delete state-level programs. | Must |
| FR-015 | The system shall allow state admins to manage state program items. | Must |
| FR-016 | The system shall provide state remittance verification and rejection workflows. | Must |
| FR-017 | The system shall provide state-level Kalotsav and sports result views. | Should |
| FR-018 | The system shall provide state-level winner exports. | Should |

### 4.3 Sahodaya Dashboard and Setup

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-019 | The system shall provide a Sahodaya admin dashboard for cluster-level overview. | Must |
| FR-020 | The system shall provide a setup wizard for first-time Sahodaya configuration. | Should |
| FR-021 | The system shall allow Sahodaya admins to dismiss or complete setup checklist items. | Should |
| FR-022 | The system shall provide navigation visibility settings for Sahodaya menus. | Should |
| FR-023 | The system shall provide public content settings for portal and website-facing information. | Should |

### 4.4 Sahodaya User Management

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-024 | The system shall allow Sahodaya admins to create, update, and delete tenant users. | Must |
| FR-025 | The system shall support Sahodaya staff roles including staff, registration coordinator, finance, certificate collector, data entry, event coordinator, judge, mark entry admin, mark entry coordinator, exam controller, exam staff, and fest ops. | Must |
| FR-026 | The system shall support custom permissions including fest, training, finance, MCQ, membership, website, and user management permissions. | Must |
| FR-027 | The system shall identify portal-only roles and route them to dedicated portals. | Must |
| FR-028 | The system shall support assignment of event duties to fest ops users. | Must |

### 4.5 Member School and Membership Management

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-029 | The system shall allow public school membership applications from Sahodaya tenant websites. | Must |
| FR-030 | The system shall throttle public school application submissions to reduce abuse. | Must |
| FR-031 | The system shall allow Sahodaya admins to view member schools and pending school applications. | Must |
| FR-032 | The system shall allow Sahodaya admins to approve or reject school applications individually or in bulk. | Must |
| FR-033 | The system shall allow Sahodaya admins to export school data. | Should |
| FR-034 | The system shall allow Sahodaya admins to toggle whether a school can register for fests. | Must |
| FR-035 | The system shall allow Sahodaya admins to view school student records. | Must |
| FR-036 | The system shall allow Sahodaya admins to configure membership settings, fees, fee slabs, payment details, mail settings, receipt templates, classes, categories, and teaching types. | Must |
| FR-037 | The system shall allow schools to submit annual registration data including profile, account, student counts, student records, teacher records, and payment proof. | Must |
| FR-038 | The system shall allow Sahodaya admins to review, approve, or reject annual submission tracks. | Must |
| FR-039 | The system shall allow bulk approval of pending membership submissions. | Should |
| FR-040 | The system shall allow Sahodaya admins to verify membership payments and view uploaded proof. | Must |
| FR-041 | The system shall generate or display membership receipts for verified payments. | Should |
| FR-042 | The system shall provide membership reports and exports for schools, payments, dues, completed payments, and submissions. | Must |

### 4.6 Academic Years and Registration Windows

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-043 | The system shall allow Sahodaya admins to create academic year records. | Must |
| FR-044 | The system shall allow Sahodaya admins to activate and close academic years. | Must |
| FR-045 | The system shall allow Sahodaya admins to create financial years and set the current financial year. | Must |
| FR-046 | The system shall allow Sahodaya admins to configure registration windows for student additions and edits. | Must |
| FR-047 | The system shall support emergency locks that prevent student add/edit workflows. | Must |
| FR-048 | The system shall support per-school lock overrides with expiry. | Should |
| FR-049 | The system shall resolve student add/edit access using emergency lock, school override, global window, and default locked state. | Must |

### 4.7 Student Management and Verification

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-050 | The system shall allow school admins to create, view, update, delete, bulk create, and import students. | Must |
| FR-051 | The system shall provide downloadable student import templates. | Must |
| FR-052 | The system shall allow school admins to upload and view student photos. | Must |
| FR-053 | The system shall support ZIP-based student photo uploads. | Should |
| FR-054 | The system shall allow school admins to provision and reset student portal logins. | Must |
| FR-055 | The system shall allow bulk provisioning of student portal accounts. | Should |
| FR-056 | The system shall allow school admins and Sahodaya admins to verify student records. | Must |
| FR-057 | The system shall support student verification reports and exports. | Should |
| FR-058 | The system shall allow Sahodaya admins to manage a central student verification queue. | Must |
| FR-059 | The system shall allow students to view and update profile information through the student portal subject to locks and approvals. | Should |
| FR-060 | The system shall support student profile change requests during locked periods. | Must |
| FR-061 | The system shall support school-level approval or rejection of student change requests. | Must |
| FR-062 | The system shall support Sahodaya-level approval or rejection of escalated student change requests. | Must |

### 4.8 Teacher Management and Verification

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-063 | The system shall allow school admins to create, view, update, and delete teacher records. | Must |
| FR-064 | The system shall allow school admins to upload and view teacher photos. | Should |
| FR-065 | The system shall allow school admins to provision and reset teacher portal logins. | Must |
| FR-066 | The system shall allow Sahodaya admins to verify or reject teacher records. | Must |
| FR-067 | The system shall provide teacher portal access for profile, password, training, fest information, results, certificates, and question banks. | Must |
| FR-068 | The system shall support teacher profile change requests. | Should |

### 4.9 School Administration

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-069 | The system shall provide a school admin dashboard. | Must |
| FR-070 | The system shall allow schools to configure setup codes. | Should |
| FR-071 | The system shall allow school principals to create school admins, vice principals, coordinators, staff, group admins, and house admins. | Must |
| FR-072 | The system shall allow school admins and vice principals to create permitted coordinator and staff roles. | Must |
| FR-073 | The system shall restrict non-leadership roles from creating leadership users. | Must |
| FR-074 | The system shall allow school admins to manage houses and assign students to houses. | Should |
| FR-075 | The system shall allow school users to view and acknowledge circulars. | Must |
| FR-076 | The system shall allow schools to view payment history and export payment data. | Should |
| FR-077 | The system shall provide school-scoped notification views. | Should |

### 4.10 Fest Program Management

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-078 | The system shall support the fest programs Kalotsav, Sports Meet, Kids Fest, Teacher Fest, English Fest, and Science Fest. | Must |
| FR-079 | The system shall map each program to a program slug, display label, and event type. | Must |
| FR-080 | The system shall provide a Sahodaya hub for each supported fest program. | Must |
| FR-081 | The system shall provide a school hub for each supported fest program. | Must |
| FR-082 | The system shall support master item catalogs per program. | Must |
| FR-083 | The system shall allow Sahodaya admins to resync, enable, configure, and assign catalog items to events. | Must |
| FR-084 | The system shall support custom fest programs where configured. | Should |
| FR-085 | The system shall support category/taxonomy masters for fest items and events. | Must |

### 4.11 Fest Event Lifecycle

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-086 | The system shall allow Sahodaya admins to create, view, update, delete, clone, and spawn fest events. | Must |
| FR-087 | The system shall support event statuses including draft, published, registration open, ongoing, and completed. | Must |
| FR-088 | The system shall support event lifecycle checklist tracking. | Should |
| FR-089 | The system shall support event setup tabs for lifecycle, locks, venues, stages, combo rules, grades, points, participation, eligibility, fees, registration, numbering, volunteers, records, and clone actions. | Must |
| FR-090 | The system shall allow event items to be imported from catalogs and managed at event level. | Must |
| FR-091 | The system shall allow item windows, item fees, per-item result publication, and item heads to be configured. | Must |
| FR-092 | The system shall allow schools to register participants for published events during valid registration windows. | Must |
| FR-093 | The system shall allow admins and assigned registration staff to approve, reject, cancel, import, or register participants on behalf of schools. | Must |
| FR-094 | The system shall support bulk approval and bulk rejection of registrations. | Should |
| FR-095 | The system shall support substitution requests and clash requests. | Must |
| FR-096 | The system shall support school document or fee verification gates before approval where configured. | Must |
| FR-097 | The system shall support school self-registration and student self-registration where enabled. | Should |

### 4.12 Fest Scheduling and Day-of Operations

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-098 | The system shall allow event schedules to be created manually. | Must |
| FR-099 | The system shall allow automatic schedule generation where supported. | Should |
| FR-100 | The system shall allow schedule publishing and unpublishing. | Must |
| FR-101 | The system shall support item scheduling, schedule imports, templates, reordering, venues, and stages. | Must |
| FR-102 | The system shall support attendance marking and attendance imports. | Must |
| FR-103 | The system shall provide stage queue tools for assigned stage managers. | Should |
| FR-104 | The system shall provide gate check tools for admit cards or participant validation. | Should |
| FR-105 | The system shall support catering orders and food coupons for applicable events. | Should |
| FR-106 | The system shall support event appeals, fee-paid marking for appeals, disqualification, and reinstatement. | Should |
| FR-107 | The system shall support event staff assignment by event and duty. | Must |

### 4.13 Fest Mark Entry, Results, and Promotion

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-108 | The system shall allow authorized users to enter and update marks. | Must |
| FR-109 | The system shall allow judges to enter marks only for assigned events/items. | Must |
| FR-110 | The system shall allow mark coordinators and fest ops users with marks duty to enter marks for assigned scopes. | Must |
| FR-111 | The system shall support mark import templates and mark imports. | Should |
| FR-112 | The system shall support automatic ranking for event items. | Should |
| FR-113 | The system shall allow results to be published and unpublished at event and item levels. | Must |
| FR-114 | The system shall support qualification and promotion to future rounds. | Should |
| FR-115 | The system shall support automatic promotion where configured. | Should |
| FR-116 | The system shall support revocation of qualifications. | Should |
| FR-117 | The system shall provide leaderboard, championship, overall ranking, house ranking, and program-specific result views. | Must |

### 4.14 Sports Meet Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-118 | The system shall support Sports Meet age groups such as U8 through U19. | Must |
| FR-119 | The system shall allow Sahodaya admins to configure, reset, update, and delete sports age groups. | Must |
| FR-120 | The system shall support sports item heads and syncing item heads from catalog to event. | Must |
| FR-121 | The system shall support chest number generation, assignment, clearing, reveal, printing, cards, and CSV export. | Must |
| FR-122 | The system shall support sports athletic records and record-break tracking. | Must |
| FR-123 | The system shall support sports championship calculation and recalculation. | Must |
| FR-124 | The system shall support sports composite fee models including school registration, student registration, included items, item fees, and extra item fees. | Must |
| FR-125 | The system shall support sports results and rankings for public, school, and admin users. | Must |

### 4.15 Kalotsav and Multi-Level Fest Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-126 | The system shall support Kalotsav-specific catalog and event workflows. | Must |
| FR-127 | The system shall support school round management for Kalotsav where configured. | Should |
| FR-128 | The system shall support school-to-Sahodaya and Sahodaya-to-state level promotion flows. | Should |
| FR-129 | The system shall support CKSC-style item templates and tiered fee behavior where configured. | Should |

### 4.16 Fest Finance

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-130 | The system shall support event fee settings and billing models including item catalog, CKSC tiered, flat school, per item, per student, sports composite, and none. | Must |
| FR-131 | The system shall calculate school event fees based on event settings and registrations. | Must |
| FR-132 | The system shall allow schools to upload event fee payment proof. | Must |
| FR-133 | The system shall allow Sahodaya finance users to approve or reject event fee payments. | Must |
| FR-134 | The system shall support event fee ledgers and exports. | Must |
| FR-135 | The system shall issue event invoices and invoice PDFs. | Should |
| FR-136 | The system shall support demand PDFs for detailed school fee demands. | Should |
| FR-137 | The system shall post verified fee transactions to ledger accounts where configured. | Must |

### 4.17 Certificates, ID Cards, Admit Cards, and Documents

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-138 | The system shall generate event certificates. | Must |
| FR-139 | The system shall support certificate templates and certificate search. | Should |
| FR-140 | The system shall support certificate ZIP downloads. | Should |
| FR-141 | The system shall support certificate collection tracking. | Should |
| FR-142 | The system shall generate participant ID cards and PDFs by item, head, or whole event. | Should |
| FR-143 | The system shall generate student and teacher admit cards where configured. | Should |
| FR-144 | The system shall generate MCQ hall tickets. | Must |
| FR-145 | The system shall include QR codes where needed for verification or identification. | Should |

### 4.18 Fest Reports and Exports

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-146 | The system shall provide event report packs for before, during, and after event phases. | Must |
| FR-147 | The system shall provide registration, results, attendance, fee, school detailed, overall ranking, house detailed, participation count, mark status, clash, schedule, item count, discipline registration, head-wise participant, age group matrix, fee collection, numbering, pending approval, student-wise, and item-wise reports where applicable. | Must |
| FR-148 | The system shall provide school-scoped fest reports. | Must |
| FR-149 | The system shall support CSV, spreadsheet, PDF, or ZIP exports where the corresponding report requires download. | Should |

### 4.19 Public Fest Portal

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-150 | The system shall expose a public fest index for Sahodaya tenants. | Must |
| FR-151 | The system shall expose public event detail, schedule, item schedule, results, item results, scoreboards, manuals, live views, records, search, and participant pages. | Must |
| FR-152 | The system shall expose item result PDFs and winner posters where supported. | Should |
| FR-153 | The system shall only show public schedules and results when publication flags permit. | Must |

### 4.20 MCQ Exam Management

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-154 | The system shall allow Sahodaya admins to create and manage MCQ exam series. | Must |
| FR-155 | The system shall support multi-level MCQ series with parent exam promotion rules. | Should |
| FR-156 | The system shall allow Sahodaya admins to create and manage MCQ exams. | Must |
| FR-157 | The system shall allow Sahodaya admins to configure eligibility, class, gender, verification requirements, schedule, fees, and publication status. | Must |
| FR-158 | The system shall allow question banks and questions to be attached to exams. | Must |
| FR-159 | The system shall allow teachers to create and manage question banks through the teacher portal. | Should |
| FR-160 | The system shall allow schools to register students for MCQ exams. | Must |
| FR-161 | The system shall support MCQ fee upload and verification. | Must |
| FR-162 | The system shall generate hall tickets after required approvals. | Must |
| FR-163 | The system shall support online MCQ exam start, answer saving, submission, and auto-grading where configured. | Must |
| FR-164 | The system shall support offline attendance and manual mark entry. | Must |
| FR-165 | The system shall provide exam controller supervision views. | Should |
| FR-166 | The system shall publish MCQ results when finalized. | Must |
| FR-167 | The system shall expose public MCQ paper archives and downloads where configured. | Should |

### 4.21 Training Management

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-168 | The system shall allow Sahodaya admins to create and manage training programs. | Must |
| FR-169 | The system shall allow schools or teachers to register for training programs. | Must |
| FR-170 | The system shall support training eligibility rules where configured. | Should |
| FR-171 | The system shall support training attendance and certificates where configured. | Should |
| FR-172 | The system shall expose teacher training information in the teacher portal. | Must |

### 4.22 Finance, Ledger, and Accounting

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-173 | The system shall provide a Sahodaya finance hub. | Must |
| FR-174 | The system shall show receivables across membership, fest, MCQ, and other fee layers. | Must |
| FR-175 | The system shall support payables creation, payment marking, and cancellation. | Should |
| FR-176 | The system shall support opening balances. | Should |
| FR-177 | The system shall support ledger accounts and ledger transactions. | Must |
| FR-178 | The system shall support double-entry-style reporting for verified fee transactions where configured. | Should |
| FR-179 | The system shall support financial statements. | Should |
| FR-180 | The system shall support fee waivers. | Should |
| FR-181 | The system shall support bank reconciliation for ledger transactions. | Should |
| FR-182 | The system shall support state remittance tracking and verification. | Must |

### 4.23 Website and CMS

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-183 | The system shall support optional public websites for Sahodaya and school tenants. | Should |
| FR-184 | The system shall enable or disable website features by global and tenant settings. | Must |
| FR-185 | The system shall provide site-builder sections, navigation, footer, theme, widgets, and public website settings. | Should |
| FR-186 | The system shall support news, events, gallery, staff pages, achievements, downloads, board results, alumni, testimonials, job vacancies, contact details, and enquiries for school websites. | Should |
| FR-187 | The system shall support Sahodaya public pages such as about, executive, contact, downloads, gallery, MOA pages, office bearers, circulars, and notification templates. | Should |
| FR-188 | The system shall support SEO routes including sitemap and robots files for public tenant websites. | Should |
| FR-189 | The system shall apply public cache headers to public tenant routes where appropriate. | Should |

### 4.24 Student Portal

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-190 | The system shall provide a student portal dashboard. | Must |
| FR-191 | The system shall allow students to access profile management. | Must |
| FR-192 | The system shall allow students to view MCQ registrations, hall tickets, and online exam pages. | Must |
| FR-193 | The system shall allow students to view fest schedules, results, sports results, certificates, and admit cards. | Must |
| FR-194 | The system shall allow students to submit fest appeals where enabled. | Should |
| FR-195 | The system shall allow students to register for fest items where self-registration is enabled. | Should |

### 4.25 Teacher Portal

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-196 | The system shall provide a teacher portal dashboard. | Must |
| FR-197 | The system shall allow teachers to view training programs, fest schedules, results, certificates, and admit cards. | Must |
| FR-198 | The system shall allow teachers to submit fest appeals where enabled. | Should |
| FR-199 | The system shall allow teachers to create MCQ question banks and questions. | Should |
| FR-200 | The system shall allow teachers to manage profile and password information. | Must |

### 4.26 Operational Portals

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-201 | The system shall provide a fest ops portal scoped to assigned events and duties. | Must |
| FR-202 | The fest ops portal shall support coordinator views, registrations, marks, admit cards, appeals, certificates, stage queue, attendance, kitchen, and gate check according to duty. | Must |
| FR-203 | The system shall provide a judge portal for assigned mark entry. | Must |
| FR-204 | The system shall provide a fest coordinator portal for assigned mark entry. | Must |
| FR-205 | The system shall provide an exam portal for attendance, mark entry, and supervision. | Must |
| FR-206 | The system shall provide a house admin portal for students, registrations, and rankings. | Should |
| FR-207 | The system shall provide a group admin portal for students, fest registrations, schedule, clashes, and admit cards. | Should |

### 4.27 API Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-208 | The system shall expose API documentation at `/api/v1/docs`. | Should |
| FR-209 | The system shall expose public API endpoints for login branding and school application forms. | Must |
| FR-210 | The system shall expose authenticated API endpoints for logout, current user, notifications, and FCM token registration. | Must |
| FR-211 | The system shall expose school admin API endpoints for dashboard, setup code, students, annual registration, profile, teachers, MCQ, training, circulars, and fest registration. | Must |
| FR-212 | The system shall expose Sahodaya admin API endpoints for dashboard, schools, payments, submissions, reports, settings, events, MCQ exams, and training. | Must |
| FR-213 | API endpoints shall enforce authentication and tenant-specific middleware. | Must |
| FR-214 | API endpoints shall return validation errors in a client-consumable format. | Must |

### 4.28 Notifications

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-215 | The system shall provide in-app notification lists. | Should |
| FR-216 | The system shall allow notifications to be marked as read. | Should |
| FR-217 | The system shall store FCM tokens for push notifications. | Should |
| FR-218 | The system shall support notification templates where configured. | Should |
| FR-219 | The system shall support email or SMS notification channels when valid service configuration exists. | Could |

---

## 5. Data Requirements

### 5.1 Core Data Entities

The system shall maintain the following major entity groups.

| Entity group | Representative entities |
|--------------|-------------------------|
| Tenancy and users | Tenant, tenant settings, user, roles, permissions, user scopes, FCM tokens. |
| Master data | Class categories, master classes, subjects, designations, teaching types, age categories, taxonomy masters. |
| Membership | Sahodaya profile, membership settings, registration windows, fee slabs, membership payments, annual submissions, submitted students, submitted teachers, lock overrides. |
| School records | Students, teachers, school classes, houses, house assignments, student/teacher verification, change requests, profile change requests. |
| Fest catalog | Fest catalog items, event items, item heads, taxonomy masters, participation rules, participation policies, combination rules, sports age groups. |
| Fest event | Fest events, registrations, participants, level registrations, clashes, substitutions, schedules, venues, stages, attendance, marks, results, grades, points, qualifications. |
| Sports | Chest numbers, sports age group configs, athletic records, record breaks, houses, championships, rankings. |
| Event operations | Judges, event staff assignments, catering orders, food coupons, appeals, volunteers, admit cards, ID cards, certificates. |
| MCQ | Exam series, exams, question banks, questions, registrations, school fees, marks, exam staff, attendance. |
| Training | Training programs, sessions, registrations, school fees, attendance, certificates. |
| Finance | Account heads, ledger transactions, opening balances, fee receipts, invoices, payables, bank accounts, state remittances. |
| CMS and public content | Site sections, news articles, events, galleries, downloads, office bearers, circulars, achievements, board results, alumni, testimonials, vacancies, enquiries. |
| Audit and support | Activity logs, data change logs, uploaded file backups, screen settings, notification templates. |

### 5.2 Data Integrity Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| DR-001 | Every tenant-scoped record shall be associated with the correct tenant context. | Must |
| DR-002 | School records shall not be visible or mutable from unrelated school tenants. | Must |
| DR-003 | Sahodaya admins shall only manage member schools belonging to their Sahodaya unless central override is used. | Must |
| DR-004 | Registration records shall reference valid event, item, school, and participant records. | Must |
| DR-005 | Mark records shall reference valid registrations or participants and valid event items. | Must |
| DR-006 | Published result records shall be derived from completed mark entry and ranking rules. | Must |
| DR-007 | Payment verification shall preserve uploaded proof, verification status, verifier identity, and timestamps. | Must |
| DR-008 | Ledger postings shall be traceable to source payment, invoice, fee, or manual transaction records. | Must |
| DR-009 | Change requests shall retain old values, requested values, approval status, reviewer, and timestamps. | Must |
| DR-010 | Certificates, receipts, hall tickets, and ID cards shall be reproducible from stored data. | Should |

### 5.3 Data Retention and Auditability

| ID | Requirement | Priority |
|----|-------------|----------|
| DR-011 | The system shall retain audit logs for security-sensitive and administrative operations where logging is configured. | Should |
| DR-012 | The system shall retain payment proof files as long as required for financial verification and audit. | Must |
| DR-013 | The system shall retain event results and certificate records after event completion. | Must |
| DR-014 | The system shall avoid deleting records that are required for financial, result, or certificate traceability unless a controlled archival process exists. | Must |
| DR-015 | The system shall support exports for audit, finance, membership, event, and school reporting. | Must |

---

## 6. External Interface Requirements

### 6.1 User Interfaces

| Interface | Requirement |
|-----------|-------------|
| Central admin UI | Shall provide dashboards and management pages for tenants, master data, billing, state users, state programs, remittances, audit logs, and builder settings. |
| Sahodaya admin UI | Shall provide module hubs for membership, schools, students, teachers, fests, MCQ, training, finance, reports, users, settings, website, and public content. |
| School admin UI | Shall provide student, teacher, annual registration, payment, circular, fest, MCQ, training, house, user, and optional website management. |
| Operational portals | Shall provide focused task screens for event operations, judges, mark coordinators, exam staff, house admins, and group admins. |
| Student portal | Shall provide student dashboard, profile, MCQ, fest, schedule, results, certificates, admit card, and appeals screens. |
| Teacher portal | Shall provide teacher dashboard, profile, training, fest, question bank, results, and certificates screens. |
| Public website | Shall provide public content, school applications, public fest pages, MCQ archives, SEO files, and CMS pages. |

### 6.2 Software Interfaces

| Interface | Requirement |
|-----------|-------------|
| Database | Shall use Laravel-supported relational database connections, including central and tenant databases. |
| File storage | Shall support local and S3-compatible storage for uploads and generated files. |
| Mail | Shall support Laravel mail configuration for password reset, notifications, membership communication, and test mail workflows. |
| SMS | Shall support SMS notification behavior where configured. |
| Push notifications | Shall store FCM tokens and support push notification delivery where configured. |
| PDF generation | Shall generate receipts, certificates, invoices, hall tickets, admit cards, and reports through PDF tooling. |
| QR code generation | Shall generate QR codes for verification and identification use cases where required. |
| API clients | Shall support token-authenticated clients through `/api/v1`. |

### 6.3 Communication Interfaces

| ID | Requirement | Priority |
|----|-------------|----------|
| IR-001 | The system shall serve browser-based HTML/Inertia pages over HTTPS in production. | Must |
| IR-002 | The system shall expose JSON API responses for mobile/web API clients. | Must |
| IR-003 | The system shall support file uploads for photos, payment proofs, media, and imports. | Must |
| IR-004 | The system shall support file downloads for templates, proofs, receipts, certificates, PDFs, and exports. | Must |
| IR-005 | Public routes shall be accessible without authentication only where explicitly intended. | Must |

---

## 7. Security Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| SR-001 | All administrative and portal routes shall require authentication. | Must |
| SR-002 | Protected routes shall enforce password change requirements where configured. | Must |
| SR-003 | The system shall enforce tenant isolation for all tenant-scoped operations. | Must |
| SR-004 | The system shall enforce role-based authorization through middleware, roles, permissions, and assignment checks. | Must |
| SR-005 | The system shall prevent users from accessing records outside their tenant or assignment scope. | Must |
| SR-006 | The system shall validate all user input before persistence. | Must |
| SR-007 | The system shall throttle public school registration submissions. | Must |
| SR-008 | Uploaded files shall be validated by type, size, and intended purpose. | Must |
| SR-009 | Payment proof, student photos, teacher photos, and private documents shall not be publicly exposed except through authorized routes. | Must |
| SR-010 | API routes shall require Sanctum authentication unless intentionally public. | Must |
| SR-011 | The system shall protect against CSRF on web form submissions through Laravel web middleware. | Must |
| SR-012 | The system shall log security-sensitive administrative operations where audit logging is configured. | Should |
| SR-013 | The system shall avoid exposing sensitive credentials or tenant database configuration to non-superadmin users. | Must |
| SR-014 | Public website routes shall not expose private membership, student, teacher, payment, or internal event operation data. | Must |
| SR-015 | The system shall support secure password reset flows. | Must |

---

## 8. Non-Functional Requirements

### 8.1 Performance

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-001 | Common dashboard and list pages should load within acceptable interactive time under normal tenant load. | 2-5 seconds typical target, excluding very large exports. |
| NFR-002 | Large exports, PDFs, ZIP generation, and imports should be queued or optimized when synchronous processing becomes slow. | No request timeout under expected production data sizes. |
| NFR-003 | Public schedule and result pages should be cache-friendly. | Use cache headers where appropriate. |
| NFR-004 | Search and report pages should support filtering and pagination for large datasets. | Required for student, school, registration, payment, and report lists. |

### 8.2 Scalability

| ID | Requirement |
|----|-------------|
| NFR-005 | The system shall scale across multiple Sahodaya tenants and many school tenants. |
| NFR-006 | Tenant databases and storage paths shall be designed to prevent data collision. |
| NFR-007 | Event registration, mark entry, and result publication workflows shall support large inter-school event volumes. |
| NFR-008 | API endpoints shall support mobile or external clients without bypassing tenant and role scopes. |

### 8.3 Availability and Reliability

| ID | Requirement |
|----|-------------|
| NFR-009 | The system should remain available during school registration periods, exam periods, and fest result publication periods. |
| NFR-010 | The system shall avoid data loss during imports by validating input and reporting row-level errors where practical. |
| NFR-011 | Financial verification and ledger posting shall be transactionally safe where multiple records are updated. |
| NFR-012 | Critical generated documents shall be reproducible from stored system data where possible. |

### 8.4 Usability

| ID | Requirement |
|----|-------------|
| NFR-013 | Role-specific users shall see only the navigation and actions relevant to their permissions and assignments. |
| NFR-014 | Event workflows shall be grouped into clear setup, registration, marks, results, finance, and report phases. |
| NFR-015 | School users shall be able to complete annual registration, fest registration, MCQ registration, and payment uploads without central assistance under normal conditions. |
| NFR-016 | Public pages shall be understandable to schools, parents, students, and visitors without requiring login. |

### 8.5 Maintainability

| ID | Requirement |
|----|-------------|
| NFR-017 | The system shall follow Laravel conventions for routes, controllers, models, middleware, services, validation, and views/components. |
| NFR-018 | Business logic shared across controllers should live in services or support classes. |
| NFR-019 | Feature additions shall preserve tenant isolation and role scope patterns. |
| NFR-020 | Tests shall cover high-risk workflows such as auth, tenant access, registrations, payments, mark entry, results, and APIs. |

### 8.6 Compatibility

| ID | Requirement |
|----|-------------|
| NFR-021 | The system shall be compatible with supported PHP and Laravel versions defined in project dependencies. |
| NFR-022 | The frontend build shall be compatible with the Vite, Vue, Inertia, Tailwind, and related versions defined in project dependencies. |
| NFR-023 | The system shall support modern browsers used by schools and administrators. |

### 8.7 Accessibility

| ID | Requirement |
|----|-------------|
| NFR-024 | Administrative and portal screens should use semantic labels, readable contrast, keyboard-friendly controls, and clear validation messages. |
| NFR-025 | Public pages should be usable on desktop and mobile devices. |
| NFR-026 | Generated PDFs should use clear typography and print-friendly layouts. |

### 8.8 Privacy

| ID | Requirement |
|----|-------------|
| NFR-027 | Student and teacher personal data shall only be visible to authorized users. |
| NFR-028 | Payment proofs and identity-related documents shall be treated as private files. |
| NFR-029 | Public pages shall not expose private student or teacher details beyond approved public event result/participant information. |
| NFR-030 | Exports containing personal or financial data shall require authorized access. |

---

## 9. Workflow Requirements

### 9.1 New Sahodaya Tenant Workflow

1. Super admin creates a Sahodaya tenant.
2. Super admin configures tenant database and runs migrations.
3. Super admin assigns Sahodaya admin user.
4. Sahodaya admin logs in and completes setup wizard.
5. Sahodaya admin configures membership settings, fee slabs, categories, academic years, and payment details.
6. Sahodaya admin enables public portal or website content as required.

### 9.2 New Member School Workflow

1. School applies through the Sahodaya public school registration form, or the school is created by an admin.
2. Sahodaya admin reviews the application.
3. Sahodaya admin approves or rejects the school.
4. School admin/principal receives or uses login credentials.
5. School completes profile, account details, annual registration data, students, teachers, and payment proof.
6. Sahodaya verifies submission tracks and membership payment.
7. Sahodaya enables school access to fest registrations where applicable.

### 9.3 Student Add/Edit and Change Request Workflow

1. Sahodaya configures academic year registration and edit windows.
2. School creates or edits students during open windows.
3. If locked, school or portal user submits a change request.
4. School principal or school admin reviews the change request.
5. During unlocked periods, approved school-level changes may apply directly where configured.
6. During locked periods, school-approved requests escalate to Sahodaya.
7. Sahodaya approves or rejects the request.
8. Approved changes are applied and audit details are retained.

### 9.4 Fest Event Workflow

1. Sahodaya selects a fest program hub.
2. Sahodaya configures master catalog and event items.
3. Sahodaya creates an event.
4. Sahodaya configures dates, registration windows, fees, venues, stages, eligibility, numbering, grades, points, locks, and lifecycle settings.
5. Schools register eligible participants.
6. Sahodaya or assigned staff approve registrations.
7. Schools upload required event fee proof.
8. Finance users verify payment proof.
9. Sahodaya builds and publishes schedule.
10. Event operations users manage attendance, stage queue, gate check, catering, appeals, and day-of workflows.
11. Judges, mark coordinators, or authorized admins enter marks.
12. Results are ranked, reviewed, published, and optionally promoted to next rounds.
13. Certificates, ID cards, reports, exports, and public result pages are generated.

### 9.5 Sports Meet Workflow

1. Sahodaya configures sports age groups and catalog.
2. Sahodaya creates a sports event and imports catalog items.
3. Sahodaya syncs item heads and configures sports composite fees.
4. Schools register students for events.
5. Fees are calculated using school, student, included item, item, and extra item rules.
6. Finance verifies payment proof.
7. Chest numbers are generated and printed.
8. Attendance, marks, records, rankings, and championships are managed.
9. Results, certificates, record-break certificates, and reports are published.

### 9.6 MCQ Exam Workflow

1. Sahodaya creates an MCQ series and levels where required.
2. Sahodaya creates an exam and configures eligibility, fees, schedule, and publication.
3. Question banks are created or attached.
4. Schools register eligible students.
5. Schools upload fee proof.
6. Sahodaya verifies fees and registrations.
7. Hall tickets are issued.
8. Online students take exams through the student portal, or offline exams are managed through attendance and manual marks.
9. Exam staff mark attendance and exam controllers monitor or enter marks.
10. Results are published and promoted to later levels where configured.

### 9.7 Finance Workflow

1. Membership, fest, MCQ, or training fee obligations are created from configuration and registrations.
2. Schools upload payment proof.
3. Authorized finance users review proof.
4. Payments are verified or rejected.
5. Verified payments create receipts and ledger postings where configured.
6. Finance users review receivables, payables, opening balances, financial statements, bank reconciliation, and state remittances.

---

## 10. Reporting Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| RR-001 | The system shall provide dashboards for central admin, Sahodaya admin, school admin, student portal, teacher portal, and operational portals. | Must |
| RR-002 | The system shall provide membership reports and exports. | Must |
| RR-003 | The system shall provide school reports for student, teacher, verification, payment, and fest participation data. | Must |
| RR-004 | The system shall provide event reports grouped by before, during, and after phases. | Must |
| RR-005 | The system shall provide result and ranking reports for event, item, school, student, house, and overall views. | Must |
| RR-006 | The system shall provide finance reports, ledgers, statements, fee collection reports, pending dues, receipts, and invoices. | Must |
| RR-007 | The system shall provide MCQ reports for registrations, attendance, marks, hall tickets, and results. | Must |
| RR-008 | The system shall provide exportable data for operational and audit needs. | Should |

---

## 11. Acceptance Criteria

The system shall be considered functionally acceptable for this SRS baseline when:

1. A super admin can create and configure Sahodaya and school tenants.
2. A Sahodaya admin can configure membership, approve schools, verify annual submissions, and verify payments.
3. A school admin can complete annual registration, manage students and teachers, upload payment proof, and view circulars.
4. Student add/edit locks and change requests protect data outside configured windows.
5. A Sahodaya admin can create a fest event, import items, configure fees, open registration, approve participants, schedule items, enter marks, publish results, and generate certificates/reports.
6. A school can register students for fest events and view school-scoped results/reports.
7. Assigned fest ops, judges, and mark coordinators can only access assigned event operations.
8. A Sports Meet can be configured with age groups, item heads, composite fees, chest numbers, marks, rankings, and certificates.
9. An MCQ exam can be created, published, registered for, paid, assigned hall tickets, conducted online or offline, marked, and published.
10. Finance users can verify payments, view receivables, manage ledger outputs, and review reports.
11. Public tenant pages can show permitted content, school application forms, fest schedules, results, and MCQ archives.
12. API clients can authenticate and access school or Sahodaya endpoints only within authorized tenant scopes.
13. Unauthorized users cannot access tenant data, portal duties, private files, or administrative actions outside their role.

---

## 12. Testing Requirements

| Area | Required tests |
|------|----------------|
| Authentication | Login, logout, password reset, password change, portal routing, API token auth. |
| Authorization | Role middleware, permission checks, tenant isolation, portal-only restrictions, event duty scopes, coordinator scopes. |
| Membership | School application, approval/rejection, annual submission, payment proof upload, payment verification, receipt generation. |
| Student/teacher | CRUD, import, photo upload, verification, lock windows, change requests, portal provisioning. |
| Fest events | Event creation, catalog import, registration, approval, fees, scheduling, attendance, marks, results, certificates, reports. |
| Sports | Age groups, item heads, chest numbers, composite fees, athletic records, championships. |
| MCQ | Exam setup, question banks, registration, fees, hall tickets, online exam, offline marks, result publication. |
| Finance | Ledger posting, receivables, payables, opening balances, bank reconciliation, state remittances. |
| Public website | Tenant host routing, public website enablement, school application, fest public pages, SEO routes. |
| API | Public and authenticated API validation, tenant middleware, school endpoints, Sahodaya endpoints, notifications. |
| Regression | Playwright or equivalent browser tests for critical user journeys. |

---

## 13. Deployment and Operations Requirements

| ID | Requirement |
|----|-------------|
| OR-001 | The system shall provide environment configuration for database, cache, queue, mail, filesystem, and third-party services. |
| OR-002 | The system shall support Laravel migrations for central and tenant databases. |
| OR-003 | The system shall support production asset builds through Vite. |
| OR-004 | The system shall support queue workers for background jobs where configured. |
| OR-005 | The system shall support scheduled commands for recurring operations where required. |
| OR-006 | The system shall provide backup strategy for central database, tenant databases, uploaded files, and generated documents. |
| OR-007 | The system shall provide log monitoring for application errors, queue failures, and security-sensitive failures. |
| OR-008 | The system shall protect `.env`, credentials, tenant database details, and private storage paths from public access. |

---

## 14. Risks and Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Tenant data leakage | High | Strict tenant middleware, route scoping, model scoping, authorization tests. |
| Event-day traffic spike | High | Cache public pages, optimize queries, use queues for heavy generation, monitor performance. |
| Incorrect mark entry or result publication | High | Scope mark entry, audit changes, require review before publication, support unpublish/recalculate. |
| Payment proof fraud or mismatch | High | Require verifier identity, proof retention, bank reconciliation, audit logs. |
| Large imports causing failures | Medium | Validate templates, provide row-level errors, process large imports safely. |
| Public exposure of private files | High | Serve files through authorized controller routes, avoid direct public storage for sensitive files. |
| Complex role matrix causing confusion | Medium | Keep role labels, permissions, portal routing, and navigation visibility consistent. |
| Long-term data volume growth | Medium | Use pagination, indexes, archival policies, tenant-level scaling, and optimized reporting. |

---

## 15. Traceability Matrix

| Business capability | Requirement IDs |
|---------------------|-----------------|
| Tenant administration | FR-001 to FR-012, AR-001 to AR-007 |
| State administration | FR-013 to FR-018 |
| Sahodaya setup and users | FR-019 to FR-028 |
| Membership and schools | FR-029 to FR-049 |
| Student and teacher records | FR-050 to FR-068 |
| School administration | FR-069 to FR-077 |
| Fest programs and events | FR-078 to FR-117 |
| Sports meet | FR-118 to FR-125 |
| Kalotsav and level promotion | FR-126 to FR-129 |
| Fest finance and documents | FR-130 to FR-149 |
| Public fest portal | FR-150 to FR-153 |
| MCQ exams | FR-154 to FR-167 |
| Training | FR-168 to FR-172 |
| Finance and ledger | FR-173 to FR-182 |
| Website and CMS | FR-183 to FR-189 |
| Student and teacher portals | FR-190 to FR-200 |
| Operational portals | FR-201 to FR-207 |
| APIs and notifications | FR-208 to FR-219 |
| Data governance | DR-001 to DR-015 |
| Security | SR-001 to SR-015 |
| Quality attributes | NFR-001 to NFR-030 |

---

## 16. Open Questions

The following items should be confirmed by product ownership before final sign-off:

1. ~~Should direct online payment gateway integration be added, or should payment proof upload remain the official workflow?~~ **Resolved:** Offline proof upload is the official workflow for the current release; gateway is future scope.
2. What retention period is required for student photos, payment proof files, generated PDFs, and audit logs?
3. Which reports must be legally/audit certified versus operational-only?
4. ~~Which notification channels are mandatory: email, SMS, push, WhatsApp, or only in-app notifications?~~ **Resolved:** Email for external notifications; in-app for portal users; SMS/WhatsApp future scope.
5. Are there official SLAs for event-day uptime and public result publication?
6. Should the mobile/API feature set fully match the web panels or remain a focused subset?
7. Should public participant details be anonymized or limited for privacy-sensitive events?
8. What data archival policy should be used after academic years and event seasons are closed?

---

## 17. Glossary

| Term | Definition |
|------|------------|
| Annual registration | Yearly school submission of profile, student, teacher, count, and payment data. |
| Assignment scope | A restriction that allows a user to access only assigned programs, events, items, exams, houses, or groups. |
| Certificate collector | User who manages certificate collection workflows. |
| Composite fee | Sports fee model combining school fee, student fee, included item quota, item fee, and extra item fee. |
| Conduct level | The level of a competition such as school, Sahodaya, or state. |
| Event staff | Users assigned to specific event duties. |
| Fest ops | Event operations role used for registration desk, stage, attendance, food, appeals, certificates, marks, discipline, and admit cards. |
| Hall ticket | MCQ exam admission document for registered students. |
| Item catalog | Master list of items available for a program before assignment to an event. |
| Level promotion | Movement of winners or qualifiers from one competition level to another. |
| Member school | School attached to and managed by a Sahodaya cluster. |
| Portal-only role | A role that uses a dedicated portal instead of the full admin panel. |
| Registration window | Time period during which add/edit or event registration actions are allowed. |
| School override | Per-school exception to global registration or edit locks. |
| Tenant isolation | Technical guarantee that one tenant cannot access another tenant's data. |

---

## 18. Document Control

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| 1.0 | 2026-07-06 | Cursor AI coding agent | Initial SRS baseline generated from project docs, routes, support classes, and dependency files. |
| 1.1 | 2026-07-06 | Cursor AI coding agent | Added current product completion status matrix marking completed, mostly completed, config-dependent, and not-included areas. |
| 1.2 | 2026-07-06 | Cursor AI coding agent | Linked full ERP phase specification under `docs/erp/`; confirmed scale baseline (150 schools, 105k students), offline-only payments, email-only external notifications. |

---

## 19. ERP Product Specification Suite

Detailed phase-wise specifications live in **`docs/erp/`** (do not edit plan files in `.cursor/plans/`). The suite covers:

| Phases | Topics |
|--------|--------|
| 1–4 | Product foundation, common masters, RBAC/STU/T credentials, common engines |
| 5–8 | Organization, school, student, teacher, membership/offline payments |
| 9–13 | Fee/accounts, sports, kalotsavam, MCQ, teacher training |
| 14–18 | Certificates/ID, email notifications, report engine (232 reports), dashboards, audit/config |
| 19–23 | Database design, API, UI framework, QA/UAT, deployment/ops |

### 19.1 Scale Baseline

- **150** member schools per Sahodaya tenant  
- **105,000** active students (~700 per school)  
- Pagination, indexed search, queued exports, and cached dashboards are mandatory  

### 19.2 Confirmed Product Decisions

| Decision | Current release |
|----------|-----------------|
| Payments | Offline proof → verify → receipt → email → ledger |
| External notifications | Email only |
| Student portal login | `STU` + 6-digit code (e.g. `STU000001`) |
| Teacher portal login | `T` + 6-digit code; **email mandatory** |
| Legacy routes/reports | Retained; consolidate later |
| Online payment gateway | Future scope only |

See [docs/erp/README.md](erp/README.md) for the full document index.

