# Phase 14 — Certificates and ID Cards Specification

Unified via **Certificate Engine** (Phase 4).

## 1. Template Types

| Type | Used for |
|------|----------|
| participation | Fest, MCQ |
| merit / placement | Top ranks |
| membership | Active member school |
| training_completion | Teacher training |
| achievement | Student achievement |
| id_card_student | Student ID |
| id_card_teacher | Teacher / official |
| id_card_event | Chest number badge |

Templates: HTML/Blade or PDF overlay with merge fields.

---

## 2. Merge Fields

| Field | Source |
|-------|--------|
| {{name}} | Student/teacher/school |
| {{school}} | School name |
| {{item}} | Event item name |
| {{rank}} | Result |
| {{date}} | Event date |
| {{qr_url}} | Verification link |
| {{photo}} | Profile photo URL |
| {{chest_number}} | Sports |

---

## 3. QR Verification

- QR encodes signed URL: `/certificates/verify/{token}`  
- Public controller: `PublicCertificateController`  
- Token maps to issuance record (immutable snapshot)

---

## 4. Generation Modes

| Mode | When |
|------|------|
| Single | Profile action |
| Bulk filter | By school, class, item, program |
| Bulk queue | > 100 records → queued job |

Services: `FestCertificateService`, `FestIdCardService`, `BuildsFestIdCardResponses`

---

## 5. Email Delivery

- Attach PDF or link (size threshold)  
- Template per certificate type  
- Track: `issued_at`, `emailed_at`, `download_count`

---

## 6. Print & Download

- School admin: download own students  
- Sahodaya: bulk ZIP export (queued)  
- Print CSS for A4 / card stock sizes  

---

## 7. Permissions

| Action | Roles |
|--------|-------|
| Template manage | sahodaya_admin |
| Issue single | coordinators |
| Bulk issue | sahodaya coordinators |
| Verify public | anonymous |

---

## 8. Reports

| Report ID | Name |
|-----------|------|
| RPT-CERT-001 | Certificates issued log |
| RPT-CERT-002 | Pending certificate generation |
| RPT-CERT-003 | ID cards printed by school |
| RPT-CERT-004 | QR verification audit |
| RPT-CERT-005 | Email delivery status |
| RPT-CERT-006 | Template usage summary |

---

## Implementation References

- `FestIdCardController`, `FestCertificateService`  
- Concern: `BuildsFestIdCardResponses`  

Next: [15-EMAIL_NOTIFICATIONS.md](15-EMAIL_NOTIFICATIONS.md)
