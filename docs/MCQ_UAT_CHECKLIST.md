# MCQ Exam Flow — UAT Checklist

Use this checklist after deploying the MCQ completion work.

## School admin

- [ ] School with rejected or inactive membership is blocked from MCQ registration with a clear message.
- [ ] School with incomplete annual registration can still register students; downloads (hall tickets, credentials) stay locked until membership and exam fees are paid.
- [ ] Approved school can register eligible students for a published Level 1 exam.
- [ ] New portal login credentials flash once after single/bulk registration.
- [ ] School can export registered student usernames and reset a registered student's portal password.
- [ ] School uploads batch fee proof; status shows pending Sahodaya approval.
- [ ] After Sahodaya approval, hall tickets are issued and school can bulk-download PDF hall tickets once membership and exam fees are cleared.
- [ ] Level 2 registration is blocked until Level 1 results are published, promotion is locked, and student qualifies by cutoff/rank.
- [ ] Non-qualified or absent Level 1 students show a specific ineligibility reason.

## Sahodaya admin

- [ ] MCQ dashboard loads without error and shows pending payment count.
- [ ] Sahodaya can approve or reject batch school fee proofs; rejected schools can re-upload.
- [ ] Approved batch fees issue receipts and confirm registrations with hall tickets.
- [ ] Offline exam attendance can be marked present/absent; absent students cannot receive marks.
- [ ] Sahodaya can import attendance CSV by hall ticket number.
- [ ] Grade master can be created and assigned to an exam; marks receive configured grade labels including `A+`.
- [ ] Hall ticket and certificate templates can be created and assigned per exam.
- [ ] Exam coordinators (`McqExamStaff`) can access assigned exam ops without full Sahodaya admin rights.
- [ ] Results publish shows ranks/grades; exports include registration, fees, attendance, toppers, absent list, marks pending, fee pending/rejected, grade bands, session status, and Level 2 qualifier lists.

## Student portal

- [ ] Registered student sees MCQ exam on dashboard with delivery mode and lifecycle status.
- [ ] Offline exam: hall ticket download available after fee approval.
- [ ] Online exam: student can start, answer, submit; expired sessions auto-submit.
- [ ] Published results visible to student; absent students see absent status without marks.

## Finance / audit

- [ ] Approved MCQ batch fees post to the MCQ income ledger head when Sahodaya approves uploaded proof.
- [ ] Audit log entries exist for registration, fee approve/reject, mark entry, coordinator assignment, and report downloads.
