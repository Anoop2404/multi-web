# Phase 20 — API Specification

Base URL pattern: `https://{tenant-domain}/api/v1`  
Auth: Laravel Sanctum / session for web; Bearer token for mobile (future).

## 1. Conventions

| Aspect | Rule |
|--------|------|
| Format | JSON |
| Errors | `{ "message": "", "errors": {} }` HTTP 422 |
| Pagination | `?page=1&per_page=25` → `meta: { total, per_page, current_page }` |
| Filtering | Query string `filter[field]=value` |
| Sorting | `sort=field` or `sort=-field` |
| Tenant | Resolved from domain / `X-Tenant-Id` (central admin) |
| School scope | Auto-applied for school roles via middleware |

---

## 2. Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/login` | email+password or login_code+password |
| POST | `/auth/logout` | Invalidate token |
| POST | `/auth/password/forgot` | Email reset link |
| POST | `/auth/password/reset` | Reset with token |
| GET | `/auth/me` | Current user + roles + school |

Student/teacher portal: `login_code` as username field `login`.

---

## 3. Masters API

| Method | Endpoint | Permission |
|--------|----------|------------|
| GET | `/masters/classes` | authenticated |
| GET | `/masters/subjects` | authenticated |
| GET | `/masters/designations` | authenticated |
| GET | `/masters/age-categories` | authenticated |
| GET | `/masters/teaching-types` | authenticated |
| POST/PUT/DELETE | `/masters/*` | sahodaya.masters.manage |

Controller ref: `MasterDataController`, Admin API routes.

---

## 4. School API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/schools` | List member schools (Sahodaya) |
| GET | `/schools/{id}` | Profile |
| PUT | `/schools/{id}` | Update profile |
| GET | `/schools/{id}/documents` | Document list |
| POST | `/schools/{id}/documents` | Upload |
| GET | `/school/profile` | Current school (school admin) |

---

## 5. Student API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/students` | Paginated list |
| POST | `/students` | Create |
| GET | `/students/{id}` | Detail |
| PUT | `/students/{id}` | Update |
| POST | `/students/import` | CSV import (queued) |
| POST | `/students/{id}/submit-verification` | School submit |
| POST | `/students/{id}/verify` | Sahodaya verify |
| POST | `/students/{id}/reject` | Sahodaya reject |

Controller ref: `StudentApiController`

---

## 6. Teacher API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/teachers` | List |
| POST | `/teachers` | Create (email required) |
| GET | `/teachers/{id}` | Detail |
| PUT | `/teachers/{id}` | Update |
| POST | `/teachers/{id}/verify` | Verify |
| GET | `/teachers/{id}/training-history` | Programs attended |

---

## 7. Payments API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/payments` | Unified payment list |
| GET | `/payments/{id}` | Detail + receipt URL |
| POST | `/payments/{id}/proof` | Upload proof |
| POST | `/payments/{id}/verify` | Finance approve |
| POST | `/payments/{id}/reject` | Finance reject |
| POST | `/payments/{id}/resend-receipt` | Resend email |
| GET | `/receipts/{id}/pdf` | Download receipt |

Controller ref: `PaymentsApiController`

---

## 8. Fest / Registration API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/fest/events` | Active events |
| GET | `/fest/events/{id}/items` | Items |
| POST | `/fest/registrations` | Create registration |
| GET | `/fest/registrations` | List |
| GET | `/fest/schedules` | Schedule |
| POST | `/fest/marks` | Mark entry (authorized) |
| GET | `/fest/results` | Published results |

---

## 9. MCQ API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/mcq/exams` | Active exams |
| POST | `/mcq/registrations` | Register student |
| GET | `/mcq/hall-tickets/{registration}` | PDF |
| POST | `/mcq/exam/start` | Start attempt |
| POST | `/mcq/exam/answer` | Save answer |
| POST | `/mcq/exam/submit` | Submit |

Controller ref: `TrainingApiController`, MCQ portal controllers.

---

## 10. Reports API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/reports` | Available reports for user |
| GET | `/reports/{id}/preview` | Paginated preview |
| POST | `/reports/{id}/export` | Start export job |
| GET | `/exports/{jobId}` | Export status + download URL |

---

## 11. Certificates API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/certificates/verify/{token}` | Public verification |
| POST | `/certificates/generate` | Bulk generate (queued) |
| GET | `/certificates/{id}/pdf` | Download |

---

## 12. Webhooks (Future)

| Endpoint | Purpose |
|----------|---------|
| POST `/webhooks/payment-gateway` | Payment confirmation (future) |

Not implemented in current release.

---

## 13. Rate Limiting

| Route group | Limit |
|-------------|-------|
| auth login | 5/min per IP |
| API general | 120/min per user |
| MCQ exam submit | 10/min per student |
| export | 10/hour per user |

---

## 14. Versioning

- Current: `v1`  
- Breaking changes → `v2` with 6-month deprecation header on v1  

---

Next: [21-UI_SPECIFICATION.md](21-UI_SPECIFICATION.md)
