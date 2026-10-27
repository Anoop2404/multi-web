# Local online exam demo — 26 October 2026

Start with `bash scripts/local-online-exam-demo.sh` (stop any existing demo server on port 8773 first).

The launcher uses a dedicated SQLite file at `storage/app/local-online-demo/online-exam-demo.sqlite`. It does not change `.env`, the normal development database, production, or live STSE records. The seeder refuses other database paths/drivers and production environments. Re-running preserves existing attempts rather than resetting them.

- Candidate portal: http://exam-demo.localhost:8773/portal/login
- Admin login: http://exam-demo.localhost:8773/login
- Candidate tested: `candidate@demo.test`
- Spare candidate for another attempt: `practice@demo.test`
- Administrator: `exam-admin@demo.test`
- Dummy account password: `LocalExamDemo2026!`
- Five single-answer questions, 15 minutes, shuffled order, no fees.

## Verification

Browser-tested candidate login, instructions, start, running timer, answer selection, autosave, reload/resume, mark-for-review, submission confirmation, scoring, admin publishing, and student result visibility. Test attempt: three correct, one wrong, one unanswered = 3/5 (60%). The original candidate and practice accounts have now both been tested through submission.

Automated coverage: 152 tests, 1,231 assertions passed, covering paper authoring, stable question ordering, hidden answer keys, scoring, passcodes, session/draft handling, future-start gating, expiry, duplicate submissions, cross-candidate access, and exam-engine bridge/incident/monitor contract tests.

Fixed issues found during testing:

1. Student submission confirmation disclosed the score before results were published. The score is now omitted until publication.
2. An entirely unanswered attempt failed `required|array` validation, preventing timeout submission. A present empty answer array is now accepted and graded as unanswered.

3. Class-wise schedules printed on admit cards did not govern the built-in online start gate or duration. Start gating, timer expiry and displayed duration now use the candidate's class override.
4. Cancelled, rejected, malpractice and withheld registrations were not consistently rejected by the built-in session service, especially after expiry. The same eligibility check now runs on start, draft saves and submission.
5. Late requests could supply new answers after the deadline. Expired submissions now score only the server-saved draft, and expiry includes the exact deadline.
6. Returning to an expired paper only showed an error and left the attempt started. Resume now submits the saved draft and records an automatic-submission audit event.
7. Review flags were lost on reload. They now persist for the attempt in browser session storage and clear on successful submission.
8. Timer initialization briefly showed zero; rounding could submit up to a second early. Timer updates with the start response and rounds up to the true deadline. A failed auto-submit is not retried every second indefinitely.
9. Radio buttons lacked distinct accessible option names. Each now includes question number, option letter and option text. Missing student registration numbers no longer display as `null` on the exam screen.
10. Autosave failures silently cleared the save indicator. They now show a visible connection/retry warning.
11. Submissions after result publication could leave stale ranks. Published exams now recompute ranks when a new graded result is stored.

## Extended browser checks

- Completed the practice attempt with all five answers correct: **5/5, 100%, A+**.
- Cancelled a submission confirmation, continued, then submitted successfully.
- Verified answers, question order and review flags survive a page reload; the server timer did not restart.
- Tested widths **320, 390, 768, 1024 and 1440 px**: document scroll width matched viewport width at every size. Tested the mobile question drawer and navigation.
- Opened the generated achievement certificate and its public verification URL: valid certificate with the correct student and exam.
- Ran a separate one-minute blank attempt in a real browser. It automatically submitted at exactly 60 seconds with **0 marks and 5 unanswered**, showed no unpublished score, and recorded `mcq.exam.auto_submitted`.
- Automated tests also cover publishing, unpublishing, certificate access before/after publication, blocked candidates after expiry, late-answer tampering, class timing and updated published rankings.
- Production frontend build passed; shell launcher syntax and diff whitespace checks passed.

## Limits observed

 This demo uses the built-in online delivery; the separate service on port 8090 was not running, so bridge tests use fakes and are not an end-to-end engine test. This is a functional smoke test, not a concurrency/capacity test for the live STSE cohort.
