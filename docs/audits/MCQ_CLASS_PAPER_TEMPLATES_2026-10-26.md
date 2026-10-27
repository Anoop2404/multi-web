# Class paper templates

Implemented locally, 26 October 2026. No production data changed.

## Configure

Open **Talent Search exam → Paper → Paper templates & class assignments**.

1. Create questions in the common Question Banks and mark the bank Ready.
2. Create Template 1, Template 2, etc. Add one or more bank sections. Select all, first N, random N, or manually select/reorder questions. Section arrows change section order.
3. Set optional marks and negative marks per section. Blank values retain each question's marks. Random N selects a fixed subset when that template version is saved.
4. Add class assignments. A template can serve several comma-separated classes. Numeric classes and Roman aliases are normalized (VIII = Class 8 = 8).
5. Set passing percentage, question order (template order / shuffle within sections / shuffle all), and answer-option shuffling per candidate.
6. Class total marks must equal the sum of the assigned questions. Adjust question/section marks in the template rather than silently scaling scores.
7. Choose whether unassigned classes are blocked or use the default paper. With no class assignments, the default paper remains in use.
8. Save and use **Preview saved class paper** to inspect that assignment's frozen version and answer key.

Other existing settings remain on Overview/Paper: delivery mode, publication, class date/start/duration, pass percentage, fees, default paper sections, and result publication. Student start screen shows duration, question count and total marks. Questions show marks and wrong-answer penalty.

## Version and attempt behavior

Saving an edit creates the next template version. Existing assignments retain their previous version; explicitly saving assignments selects the latest template versions. Archiving prevents new assignment and retains existing papers. Bank text, key, marks and image changes cannot rewrite saved template versions.

Starting freezes a candidate's actual paper, option order, keys, marks, marking mode, passing percentage and duration. Reload resumes the same answers and order. Class assignments lock once any candidate starts. Correct answers and private snapshots are excluded from student-facing payloads. Tenant and staff permissions protect authoring and preview.

## Current delivery boundary

Class templates support the Laravel in-app exam, which delivers single-answer and true/false questions. Multiple-correct and numeric questions still require the separate exam engine. That engine currently accepts one paper per run, so arming an exam with class assignments is explicitly rejected instead of silently serving the default paper. Per-class engine bundles remain future work.

## Local browser verification

Isolated SQLite demo at `http://exam-demo.localhost:8773`, exam 3, **LOCAL DEMO — Class paper templates**:

- Created Template 1 — Junior from the common bank: 5 questions × 2 marks.
- Assigned to VIII and IX, normalized to 8 and 9; passing percentage 40; within-section shuffle and option shuffle enabled.
- Preview showed 5 questions / 10 marks and correct answer keys.
- Candidate registration 5 started, answered all questions, reloaded, and retained all answers and shuffle order.
- Submission saved 10 marks, 100%, 5 correct; unpublished results remained hidden.
- Automated regression covers class isolation, grading, versions, archive, missing classes, duplicate aliases, marks mismatch, cross-tenant bank access, role restrictions, stable shuffle, immutable image bytes and engine rejection.

Final verification: 161 focused PHPUnit tests passed with 1,334 assertions (`Mcq|ExamEngine|LocalOnlineExamFlow`), production frontend build passed, and `git diff --check` passed. Browser authoring was also visually checked at mobile width.
