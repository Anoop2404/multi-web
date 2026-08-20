# Board Results — Principal / Vice Principal Verification Plan

Date: 7 August 2026  
Status: Implemented — package/report state machine, individual report signing, and the consolidated report requirement (§2, §7) are all live and enforced end-to-end. A later commit had introduced a "fast-track" that skipped the consolidated report by force-writing package status, bypassing the audit-logged transition path; that was reverted (20 Aug 2026) so this document's requirement stands as originally specified.

## 1. Decision

Add a mandatory school-leadership certification step between school data entry and Sahodaya verification.

The required flow is:

1. School staff prepare the board result and all topper categories.
2. The school sends the completed class/year result to a new **Principal Verification** workspace.
3. The system generates a separate immutable PDF for every required report/category.
4. The Principal or an authorized Vice Principal downloads each report, verifies it against the official result, signs and seals it, and uploads the signed copy against that same report.
5. The system verifies that every required report has its own signed proof.
6. The system then generates an all-types consolidated report; the Principal/Vice Principal also downloads, signs, seals, and uploads this final report.
7. The Principal/Vice Principal confirms the declaration and submits the complete signed package.
8. Only then does the package enter the Sahodaya verification queue.

Every report-level signed upload is mandatory proof of school verification. The final package contains the individually signed reports plus the signed all-types consolidated report.

## 2. Reports covered

Each school has one certification package per `academic year × class × board result version`. Within that package, each applicable report is separately generated, downloaded, signed, and uploaded.

### Class X (AISSE)

- Result summary and proof report.
- School overall toppers report.
- Subject-wise toppers report.
- Full A1 achievers report.

### Class XII (AISSCE)

- Result summary and proof report.
- One school-topper report for each configured stream.
- Subject-wise toppers report, grouped by subject and showing stream where applicable.
- One Full A1 achievers report for each configured stream.

### Consolidated certification report

After all individual reports have signed proofs, the system generates an all-types PDF combining all applicable categories for that class and year. This report must also be downloaded, signed/sealed, and uploaded. It must include:

- School name, affiliation details, academic year, class, and examination type.
- Appeared, passed, pass percentage, distinctions, first class, highest mark, and average mark.
- Overall/stream-wise topper tables.
- Subject-wise topper tables.
- Full A1 achiever tables.
- Marks and stream totals used for percentage calculation.
- Proof-document reference and topper marksheet verification summary.
- Category-review checklist with reviewer name and timestamp.
- Signed-proof checklist showing the filename, signer, upload time, and hash for every individual report.
- Package version, generated timestamp, and data hash.
- Declaration text.
- Principal/Vice Principal name and designation.
- Signature and school-seal area.
- Optional QR code for Sahodaya-side authenticity checking in a later phase.

## 3. Proposed lifecycle

```text
School Draft
    ↓
Ready for Leadership Review
    ↓
Principal Verification
    ├── Return Report → Back to School for Correction
    └── For Every Required Report
            ↓
        Generate Report PDF (report data frozen)
            ↓
        Download → Verify → Sign/Seal → Upload Signed Report
            ↓
All Individual Signed Reports Complete
            ↓
Generate All-Types Consolidated PDF
            ↓
Download → Verify → Sign/Seal → Upload Signed Consolidated PDF
            ↓
School Certified / Submitted
            ↓
Sahodaya Verification
    ├── Return to School → certification invalidated; new version required
    └── Verify → Approve → Publish
```

Recommended package statuses:

- `draft`
- `awaiting_leadership_review`
- `leadership_changes_requested`
- `awaiting_report_signatures`
- `individual_reports_signed`
- `awaiting_consolidated_signature`
- `school_certified`
- `submitted_to_sahodaya`
- `sahodaya_returned`
- `sahodaya_verified`
- `approved`
- `published`
- `superseded`

The existing `BoardResult.status` can continue to drive the broad result lifecycle. A separate certification-package status should track the school review/signature process so existing reports and publishing logic are not overloaded with category-level state.

## 4. Roles and permissions

The application already has `school_principal` and `school_vice_principal` roles. Reuse them.

### School admin / result-entry operator

- Create and edit result data while entry is open.
- Upload proof documents and topper marksheets.
- View validation/completeness errors.
- Send a completed result for leadership review.
- Correct only categories returned by leadership or Sahodaya.
- Cannot certify or submit the signed package unless the user also has an authorized leadership role.

### Principal

- View the Principal Verification menu.
- Review every report category and its evidence.
- Generate, download, verify, sign/seal, and upload every required individual report.
- Return individual reports/categories with comments when data is incorrect.
- Generate and sign the consolidated certification report after every individual signed proof is present.
- Submit the certified package to Sahodaya.

### Vice Principal

- Same review capability as the Principal.
- Final signing/submission should be controlled by a Sahodaya setting:
  - `principal_only`; or
  - `principal_or_vice_principal`.
- Recommended default: `principal_or_vice_principal`, because the requested operational flow explicitly allows either role, while recording exactly who signed.

### Sahodaya admin

- See only packages that completed school certification.
- Preview the generated and signed version of every individual report plus the consolidated report.
- Compare package version/hash, per-report hashes, and signed-proof status.
- Verify, return with reason, approve, and publish.
- Download school-wise or consolidated certification-status reports.

## 5. New school menu and screens

Add **Board Results → Principal Verification** to the school navigation. Show it to Principal/Vice Principal users and optionally read-only to school admins.

### 5.1 Verification dashboard

Filters:

- Academic year.
- Class X / XII.
- Status.
- Pending action.

Cards/table columns:

- Class and examination.
- Summary/proof signed-report status.
- Overall/stream topper signed-report status.
- Subject-topper signed-report status.
- Full-A1 signed-report status.
- Consolidated signed-report status.
- Signed reports completed (`x of y`).
- Current package version.
- Last prepared/reviewed/submitted dates.
- Primary action: Review, Continue Review, Upload Signed Copy, or View Submission.

### 5.2 Package review screen

**Superseded (20 Aug 2026):** implemented as separate pages per report rather than tabs/expandable sections. `Review.vue` is an index/checklist (one row per required report + the consolidated report, each showing a status pill and a "Review →" link); each report's generate/print/sign/upload/accept flow lives on its own page (`ReportReview.vue`, route `.../principal-verification/reports/{report}`), and the consolidated report has its own page too (`ConsolidatedReview.vue`, route `.../principal-verification/consolidated`, locked until every individual report is accepted). Class/year context is preserved via the board result the whole route tree is scoped to, not via same-page tab state.

Each report item has:

- Data table and totals.
- Links to supporting proof/marksheets.
- Completeness and validation warnings.
- `Generate report PDF` action.
- `Download unsigned report` action.
- Signed/sealed PDF upload.
- `Verify and accept signed report` action, completed by uploading the signed proof and confirming the report declaration.
- `Return for correction` action with mandatory reason.
- Reviewer/signer identity, role, and timestamp.
- Generated-file hash and uploaded signed-file hash.

Class XII topper and Full A1 reports must be generated separately for each configured stream. An empty configured stream must produce either a signed `Nil declaration` report or be explicitly marked not applicable under Sahodaya policy; it must not silently disappear.

### 5.3 Final certification screen

- Shows the individual-report checklist with generated/downloaded/signed/uploaded status.
- Generates the consolidated PDF only after every required individual signed proof is uploaded and accepted.
- Provides `Download unsigned certification PDF`.
- Provides a separate signed PDF upload for the consolidated report (`pdf` only, recommended maximum 20 MB).
- Requires declarations:
  - “I have checked the figures against the official board result.”
  - “The topper, subject-wise, stream-wise, and Full A1 details are correct.”
  - “The uploaded document bears the authorized signature and school seal.”
- Shows the signer name/role from the authenticated account; it must not be typed freely.
- Provides the final `Sign-off and submit all signed reports to Sahodaya` action.

## 6. Data model

### 6.1 `board_result_certification_packages`

Suggested columns:

- `id`
- `board_result_id`
- `tenant_id`
- `academic_year`
- `class`
- `version`
- `status`
- `data_snapshot` JSON
- `data_hash`
- `generated_pdf_path`
- `generated_pdf_disk`
- `generated_at`
- `signed_pdf_path`
- `signed_pdf_disk`
- `signed_pdf_hash`
- `signed_by_user_id`
- `signer_role`
- `signed_at`
- `submitted_by_user_id`
- `submitted_at`
- `returned_by_user_id`
- `returned_at`
- `return_reason`
- `superseded_at`
- timestamps

Unique constraint: `board_result_id + version`.

The package-level generated/signed file columns hold the all-types consolidated report. Individual report files belong to the report records below.

### 6.2 `board_result_certification_reports`

Suggested columns:

- `id`
- `certification_package_id`
- `report_type`: `summary`, `overall_toppers`, `subject_toppers`, `full_a1`
- `stream_id` nullable
- `status`: `pending`, `generated`, `signed_uploaded`, `accepted`, `changes_requested`, `superseded`
- `row_count`
- `data_snapshot` JSON
- `data_hash`
- `generated_pdf_path`
- `generated_pdf_disk`
- `generated_at`
- `signed_pdf_path`
- `signed_pdf_disk`
- `signed_pdf_hash`
- `signed_by_user_id`
- `signer_role`
- `signed_at`
- `accepted_at`
- `review_notes`
- timestamps

Unique constraint: `certification_package_id + report_type + stream_id`.

### 6.3 Audit log

Record every event:

- Review requested.
- Individual report generated/downloaded.
- Individual signed report uploaded/replaced/accepted.
- Individual report returned.
- Consolidated report generated/downloaded.
- Consolidated signed document uploaded/replaced.
- School declaration accepted.
- Submitted to Sahodaya.
- Returned, verified, approved, and published by Sahodaya.

Audit entries should include actor, role, school, IP address, package version, old/new state, and reason where applicable.

## 7. Snapshot and document integrity

This is the most important technical rule.

- Generating each individual report must store its own JSON snapshot and SHA-256 data hash.
- Print a short report reference, package version, report type, stream (when applicable), and hash in every generated PDF.
- The uploaded signed proof must belong to the same report record and data hash.
- Once the first report is generated, the underlying result and topper rows are locked for leadership verification.
- Any authorized correction invalidates all affected generated/signed reports and the consolidated report, increments the package version, and requires fresh signatures.
- Never silently carry a signed report from one data version or stream to another.
- Replacing a signed report before final submission is allowed and audited.
- Replacing any signed report after Sahodaya submission is blocked unless Sahodaya returns the package.

## 8. Validation and completeness rules

Before `Send for Principal Verification`:

- Result summary and proof document are present.
- `pass_count <= total_appeared`.
- Pass percentage is recomputed server-side from appeared/passed counts.
- Distinction and first-class totals are internally valid.
- Every overall topper has required identity, gender, marks, configured total, and stream for Class XII.
- Percentages and ranks are server-computed.
- Subject marks are 0–100 and subjects are valid for the configured class/stream.
- Full A1 entries have every required subject and every mark is 91–100.
- Required marksheets are present according to Sahodaya policy.
- Every required individual report has a signed/sealed PDF uploaded by an authorized leadership user.
- The signed all-types consolidated report is uploaded.
- Duplicate roll numbers are rejected within each entry type.
- The academic-year entry window and leadership-review deadline are open.

Sahodaya settings should control whether a category is:

- Required.
- Optional.
- Required with an explicit `No entries` declaration.
- Subject to mandatory marksheet evidence.

## 9. Changes to existing workflow

- School `submit` must no longer send a raw `BoardResult` directly to Sahodaya.
- Replace it with `Send for Principal Verification`.
- Only the certification service may transition the result to Sahodaya `submitted` status.
- The Sahodaya queue should reject results unless every required individual signed report and the signed consolidated report are present on the active package version.
- Existing proof preview remains available, but add every individual signed report and the signed consolidated report as primary verification evidence.
- Fix the existing marksheet upload lock gap: no marksheet may change after leadership review starts unless the package is returned for correction.
- Sahodaya `verify-all` must enforce valid state transitions and must not bypass school certification.
- Publishing must fail visibly or enter a retryable `publish_failed` state if rankings/awards/certificates fail.

## 10. Sahodaya reports

Add a **School Certification Status Report** with one row per school/class/year:

- School.
- Class/examination.
- Entry completeness.
- Leadership review status.
- Verified categories.
- Principal/Vice Principal signer.
- Individual reports required/signed count.
- Missing signed reports.
- Consolidated signed document present.
- School submission date.
- Sahodaya verification status/date.
- Return reason.
- Approval/publication status.

Exports:

- PDF status report.
- Excel status matrix.
- ZIP download of every signed individual and consolidated school report for a selected class/year.
- Individual consolidated certification PDF.
- Individual signed certification PDF.

## 11. Notifications

- Notify Principal and Vice Principal when school staff request review.
- Notify school staff when a category is returned.
- Remind leadership about pending verification/signature before the deadline.
- Notify Sahodaya when a signed package is submitted.
- Notify the signer and school admin when Sahodaya returns, verifies, approves, or publishes it.
- Deduplicate scheduled reminders using the existing reminder guard pattern.

## 12. Routes and controllers

Suggested school routes:

```text
GET  /board-results/principal-verification
GET  /board-results/{boardResult}/principal-verification
POST /board-results/{boardResult}/request-leadership-review
POST /board-results/{boardResult}/certification/reports/{report}/generate
GET  /board-results/{boardResult}/certification/reports/{report}/pdf
POST /board-results/{boardResult}/certification/reports/{report}/signed-pdf
POST /board-results/{boardResult}/certification/reports/{report}/return
POST /board-results/{boardResult}/certification/consolidated/generate
GET  /board-results/{boardResult}/certification/consolidated/pdf
POST /board-results/{boardResult}/certification/consolidated/signed-pdf
POST /board-results/{boardResult}/certification/submit
```

Suggested Sahodaya routes:

```text
GET  /board-results/certifications
GET  /board-results/certifications/{package}
GET  /board-results/certifications/{package}/reports/{report}/generated-pdf
GET  /board-results/certifications/{package}/reports/{report}/signed-pdf
GET  /board-results/certifications/{package}/consolidated/generated-pdf
GET  /board-results/certifications/{package}/consolidated/signed-pdf
POST /board-results/certifications/{package}/verify
POST /board-results/certifications/{package}/return
GET  /board-results/certifications/report
GET  /board-results/certifications/report/export
GET  /board-results/certifications/signed-documents.zip
```

Keep certification workflow logic in a dedicated service rather than expanding the already large `BoardResultController`.

Recommended classes:

- `BoardResultCertificationController`
- `BoardResultLeadershipReviewController`
- `BoardResultCertificationService`
- `BoardResultCertificationPdfService`
- `BoardResultCertificationPolicy`
- `BoardResultCertificationNotifier`

## 13. Rollout plan

### Phase 1 — Foundation

- Add certification package/report tables and models.
- Add permissions and policies for Principal/Vice Principal.
- Implement snapshot, hash, versioning, and transition service.
- Add unit tests for the state machine.

### Phase 2 — School leadership workflow

- Add Principal Verification menu and dashboard.
- Add per-report generation, download, signed upload, acceptance, and return actions.
- Add completeness validation.
- Lock data while under leadership review.

### Phase 3 — Individual reports, consolidated PDF, and signatures

- Build each official individual report PDF, including Class XII stream-specific reports.
- Add individual download and signed-proof upload.
- Build the all-types consolidated PDF after all individual reports are signed.
- Add consolidated download, signed upload, declaration, and final submission.
- Add per-report file hashes and audit events.

### Phase 4 — Sahodaya verification

- Add certification queue/detail screen.
- Integrate generated/signed PDF previews for every individual report and the consolidated report.
- Add return, verify, approve, and publish gates.
- Add certification status report and exports.

### Phase 5 — Notifications and migration

- Add reminders and lifecycle notifications.
- For historical results, create read-only `legacy_without_school_certification` records or exempt years before the configured go-live year.
- Enable mandatory certification per academic year only after Principal/Vice Principal accounts are verified.

### Phase 6 — Hardening

- Permission and cross-school access tests.
- Concurrent review/submission tests.
- PDF snapshot/hash tests.
- Individual and consolidated signed-file replacement/invalidation tests.
- Full Class X/Class XII/stream/subject/Full-A1 browser tests.
- Accessibility and mobile review of the leadership pages.

## 14. Acceptance criteria

- A school admin cannot submit directly to Sahodaya.
- Only a verified Principal or authorized Vice Principal can certify a package.
- Every required report has its own generated PDF and uploaded signed/sealed proof.
- Every configured category is signed or has an authorized signed Nil declaration.
- Class XII overall and Full A1 sections are grouped by stream.
- Class XII stream-wise reports are independently downloadable and independently signed.
- The consolidated PDF and every individual report exactly match their immutable stored snapshots.
- Any data change invalidates prior verification and signature.
- Sahodaya cannot verify a package with any missing, unsigned, mismatched, or superseded report.
- Every action is attributable to a named user and role.
- Returned packages preserve history and create a new version after correction.
- Report exports point to the correct dataset.
- Historical data is not unexpectedly blocked during rollout.

## 15. Recommended product wording

Use **Principal Verification** as the school menu label. It is clearer to users than “Leadership Certification,” while the page can state: “The Principal or an authorized Vice Principal may review and submit.”

Use **School-Certified Result Packages** as the Sahodaya menu label, because Sahodaya is reviewing the complete set of individually signed reports plus the signed consolidated declaration rather than an isolated result row.
