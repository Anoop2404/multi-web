# Online exam runbook (exam day)

How to run a live online MCQ exam on the exam engine: who does what, the timeline from T-14 days
to the day after, the support playbook, incident procedures, load tests and chaos drills, go-live gates,
and the ramp rule.

- Plan: [ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md](ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md) · Contract: `exam-engine/CONTRACT.md`
- Infrastructure and scripts: `exam-engine/deploy/README.md` · Laravel deploy: [ONLINE_EXAM_DEPLOYMENT.md](ONLINE_EXAM_DEPLOYMENT.md)
- All `scripts/...` paths are relative to `exam-engine/deploy/` on the operator machine or the ops host.

**T0** = `starts_at`. **Deadline** = T0 + duration (+ any extra time). **Grace** = 90 s after the
deadline, during which final syncs and submits are still accepted. Times below are relative. Use
IST in all messages to schools.

---

## 0. Quick reference

| Need | Where / command |
|---|---|
| Live state of an exam | Laravel live monitor (exam -> school -> student). Sahodaya: Talent Search -> exam -> Session monitor. Schools: coordinator view |
| Platform health | Grafana "Exam engine — live" · `scripts/exam-day-preflight.sh` |
| Add capacity now | `scripts/scale-out.sh N` (N = total engine nodes; new nodes serve in ~2–3 min) |
| Stop automatic scale-in | `touch .state/hold-scale-in` (remove it when the incident is closed) |
| Slow everyone's sync (shed load) | monitor -> Pacing override 30 s (or 60 s). Back to automatic after |
| Message every student | monitor -> Broadcast (shown on their next sync) |
| Extend time for everyone (platform outage) | `scripts/bulk-extend.sh --run <run> --minutes N --reason ... --actor ...` on the aaPanel server |
| Redis state | `scripts/resize-redis.sh status` |
| Engine logs | `ssh ops@<node> 'journalctl -u exam-engine -f -o cat'` |
| Consumer logs | aaPanel: `storage/logs/exam-engine-consumer.log`; `supervisorctl status exam-engine-consumer:*` |

Normal numbers while writing (sync every 10 s per student):

| | Stage A (2,000 writing) | Stage B (60,000 writing) |
|---|---|---|
| Requests/s | ~200 | ~2,000–3,000 typical, ≤ 6,000 ceiling |
| p95 sync | < 100 ms (gate < 300 ms) | < 300 ms |
| Pace (`next_sync_in`) | 10 s | 10 s; 20–30 s briefly at T0 and at the deadline |
| 5xx | ~0 | < 0.1 % |
| Offline (not seen > 60 s) | a few % (phones, home networks) | same |
| Consumer lag | < 1,000, back to 0 within 5 min of the submit rush | same |
| Redis memory | < 20 % | < 50 % (alert at 70 %) |

---

## 1. Roles and escalation

| Role | Who | Responsible for | Can do in the engine |
|---|---|---|---|
| **School MCQ coordinator** (first line) | `school_mcq_coordinator` of each school | own students: check-in chasing, passcodes, device switches, short extensions, local comms | view own school; re-issue passcode; allow device switch; unlock; extend up to the cap (10 min) |
| **Sahodaya exam controller** (second line, owns the exam) | Sahodaya admin / `exam_controller` | exam decisions: extensions beyond the cap, locks, force submits, broadcasts, pacing, close, void/re-sit decisions, comms to all schools | everything for the exam, all schools |
| **Platform on-call** (third line) | platform engineer on the roster | infrastructure: engine, Redis, LB, Cloudflare, Laravel consumer, scaling, incident lead for platform faults | scripts, internal API, DigitalOcean, Cloudflare |

- **Escalation**: student -> coordinator (school WhatsApp/phone) -> Sahodaya exam controller (exam channel)
  -> platform on-call (phone + on-call channel). Coordinators escalate after **5 minutes** without a fix.
  The controller pages on-call immediately for anything affecting more than one school.
- **Decision rights in incidents**: on-call decides *technical* actions (scale, failover, restarts). The
  Sahodaya exam controller decides *exam* outcomes (extend, pause messaging, void, re-sit), advised by on-call.
- **Channels** (set up at T-7d): one exam channel per exam (controller + all coordinators), one platform
  on-call channel (on-call + controllers of exams that day), Alertmanager -> on-call Telegram/phone.
- **Busy days** (several Sahodayas at once): one platform on-call per 2–3 concurrent exams, plus a second on-call for Stage B slots.

---

## 2. Timeline

### T-14 days: booking and readiness (Sahodaya + platform)
- [ ] Exam scheduled with the online engine, `entry_mode` chosen (`either` recommended), supervision mode
      set, and capacity **booked**. Laravel warns or refuses over capacity. Above 20,000 `portal_password`-only, switch to `either`.
- [ ] Platform checks the booking calendar: the slot's total concurrency fits the current stage (ramp rule, §9).
      A Stage B slot also needs the droplet limit and a scale-out rehearsal on record.
- [ ] Paper built and previewed on phone and desktop; banks marked Ready.
- [ ] Hall tickets generated (passcodes printed). Coordinators can download their passcode lists.

### T-7 days: people and comms
- [ ] On-call roster confirmed; contacts shared; channels created.
- [ ] Students/parents told: exam link `https://exam.<domain>/<sahodaya-code>`, check-in window, device
      advice (charged phone/laptop, current Chrome/Edge/Safari, stable network, no private browsing).
- [ ] Coordinators briefed on the support playbook (§4) and the monitor UI.
- [ ] Platform: no planned deploys from T-2h through close; maintenance windows moved.

### T-48 h: armed, check-in opens (automatic)
- [ ] The scheduler arms the exam (`exam-engine:arm-due`, every 5 minutes; it also refreshes armed rosters). Controller confirms the monitor shows
      the roster count and state `checkin`.
- [ ] Coordinators chase students who haven't checked in or whose system check failed (monitor filter).
      Check-in means one password check now instead of in the T0 rush, and it catches device problems early.

### T-24 h
- [ ] `scripts/exam-day-preflight.sh` clean. Fix anything red today.
- [ ] Stage B slot: confirm `plan-and-scale.sh` sees the slot (`--dry-run`), and the LB size for the slot.
- [ ] Coordinators: check-in rate by school. Under 70 %, push reminders.

### T-3 h: capacity (busy slots)
- [ ] Automatic scale-out runs (or `scripts/plan-and-scale.sh` by hand). Then:
      `scripts/exam-day-preflight.sh --expect-nodes <N>` (+ `--expect-replica` for Stage B Redis).
- [ ] All nodes healthy in the LB, same version. Redis replica in sync, Sentinel quorum OK.
      Dashboards and alerts on. Alertmanager test notification received.

### T-2 h: freeze
- [ ] **Deploy freeze** on Laravel and the engine until close. No `git pull`, no `rolling-update.sh`,
      no supervisor restarts, no Cloudflare rule edits except incident fixes the controller agrees to.
- [ ] Stop unattended upgrades on the exam droplets for the window:
      `ssh ops@<node> 'sudo systemctl stop apt-daily.timer apt-daily-upgrade.timer'` (start them again after close).

### T-65 min: final preflight
- [ ] `scripts/exam-day-preflight.sh --expect-nodes <N>` all PASS. On-call and controller on the channel.

### T-60: waiting room opens
- [ ] Watch entries by school, the login queue, login 503 rate, and p95 login.
- [ ] Coordinators handle forgotten passcodes/passwords now (§4). The client prefetches the encrypted paper in the background.

### T-15 → T0 + 5: login rush
- [ ] Expect the login queue to grow and pacing to rise briefly. Alert only if busy > 5 % for 5 minutes (I9).
- [ ] Supervised mode: invigilators have the start PIN, and reveal it at T0.

### T0 → deadline: writing
- [ ] Watch p95 sync, 5xx, offline count, pace level, consumer lag, Redis memory.
- [ ] Coordinators: passcodes, device switches, short extensions (≤ 10 min) for local power/network loss.
- [ ] Controller: escalations, broadcasts ("10 minutes left" is shown by the client; broadcast only exceptions).
- [ ] Late entry closes at T0 + `late_entry_minutes`.

### Deadline → deadline + grace
- [ ] Clients lock answers at the deadline and submit within 0–30 s. Expect a submit spike and brief pacing.
- [ ] The sweeper finalises the rest at deadline + 90 s (`auto`).

### End + 5 min: close and reconcile
- [ ] Monitor: `unfinished = 0` (everyone submitted or auto-submitted), **consumer lag 0**.
- [ ] Controller closes the exam (monitor -> Close). The engine force-finalises leftovers and refuses logins.
- [ ] Reconciliation by school: registered / checked in / entered / submitted / absent. **submitted = entered**.
- [ ] Note every extension, lock and force submit with its reason (the audit log has them).

### After (same day)
- [ ] **Export engine logs before scale-in** (burst droplets and their journals are deleted):
      ```bash
      touch .state/hold-scale-in
      for ip in $(doctl compute droplet list --tag-name exam-engine-node --format PublicIPv4 --no-header); do
        ssh ops@$ip "journalctl -u exam-engine --since '<T-60 in UTC>' -o cat" | gzip > "audit/$(date +%F)-$ip.jsonl.gz"
      done
      rm .state/hold-scale-in
      ```
- [ ] Scale-in (automatic 30 min after the slot, or `scripts/scale-in.sh`; `scripts/resize-redis.sh down` for Stage B).
- [ ] Start the apt timers again on the exam droplets.
- [ ] Existing results flow: ranking -> publish (pre-computed results cache) -> certificates. Stagger notifications.
- [ ] Post-exam report (Appendix B) within 2 days. Incidents get a short review.

### +7 days
- Run data (including the password-hash copies) expires from Redis automatically, 7 days after close.

---

## 3. Before you change anything during an exam
1. Say it in the on-call channel first: what, why, expected effect.
2. `touch .state/hold-scale-in` while an incident is open.
3. Prefer actions that don't restart anything: pacing, scale-out, broadcast.
4. Write down times (IST) in the incident log (Appendix C) as you go.

---

## 4. Support playbook

First line is the school MCQ coordinator. "Escalate" means to the Sahodaya exam controller, who pages platform on-call if it's systemic.

| # | Situation | What the student sees | Coordinator does | Escalate when |
|---|---|---|---|---|
| S1 | **Forgotten passcode** | can't sign in | Monitor/Hall tickets -> **Re-issue passcode** -> read the new one to the student (the old one stops working immediately) | re-issue fails, or many in one school |
| S2 | Forgotten portal password | "Details don't match" | reset the portal password (it reaches the armed exam within seconds), or have them use hall ticket + passcode if entry mode allows | reset doesn't take effect within 2 min |
| S3 | "Details don't match" | 401 | check: right Sahodaya link, hall ticket number for **their class** (numbers repeat across classes), passcode typed exactly (letters/digits, no spaces needed) | same student fails after checking |
| S4 | "Too many attempts" (5 failures) | 423 locked for 10 min | verify details while they wait; there's no override; the lock expires by itself after 10 min | — |
| S5 | **"Open on another device"** (device change) | 409 device_conflict | confirm it's really the student (call/video), then **Allow device switch** (one time). In `open` mode the old device frees itself after 2 min of silence | repeated switches (possible sharing) -> report as S10 |
| S6 | Phone died / switched device mid-exam | resumes on the new device after S5 | answers synced up to the last sync are kept; anything typed after that on the dead device arrives only if that device comes back online before deadline + grace | — |
| S7 | **Power or network loss** | "Offline. Your answers are saved on this device" | tell them to keep writing; answers sync when they're back. If they really lost time, **extend** (up to 10 min) **before their deadline** | needs > 10 min -> controller |
| S8 | "Signing you in… N ahead" | login queue | normal at T-15..T0. Wait and don't refresh. Passcode login skips the queue | > 5 minutes, or many schools |
| S9 | "Exam not open yet" / countdown | waiting room | the server owns the clock; the device clock doesn't matter. Wait for T0 | countdown wrong for everyone |
| S10 | **Suspected cheating** (shared passcode, device switching, proctor flags) | — | note the facts (time, what was seen). Supervised: invigilator acts. Flags are **never** auto-penalised | always -> controller decides: **lock** (hold), unlock, or **force submit**; decided after the exam with the flag review |
| S11 | Wrong start PIN (supervised) | 403 wrong_pin | invigilator re-checks the PIN on the controller's sheet | PIN unknown -> controller |
| S12 | Late arrival | can start until T0 + late entry | after that, the start is refused | controller may raise late entry for the whole exam (from the monitor, before it ends) |
| S13 | Attempt locked | "Your exam is on hold" | only the controller locks and unlocks | always |
| S14 | Submitted by mistake | "Submitted" + receipt | v1 has **no re-open**. Note it | controller decides after the exam (accept / re-sit) |
| S15 | Browser or system check fails | check-in warning | current Chrome/Edge/Safari, not private mode, enough storage; another device | many students on one browser type |
| S16 | Score not shown | "Result pending" / hidden | normal if the exam hides scores or grading is still running | pending > 15 min after the exam -> on-call (consumer, I6) |

Rules of thumb: always extend **before** the student's deadline (an auto-submitted attempt can't be
re-opened). Never share passcodes in group chats. Write every extension reason in the monitor.

---

## 5. Incident procedures

**Severity.** SEV1: students broadly can't sign in, sync or submit. SEV2: degraded (slow, partial
errors, redundancy lost). SEV3: back office only (consumer lag, monitoring, one node gone with
spare capacity).

**First five minutes, any incident:** acknowledge the alert -> open Grafana -> `touch .state/hold-scale-in` ->
post in the on-call channel -> tell the affected Sahodaya controller(s) -> start the incident log (Appendix C)
-> `scripts/exam-day-preflight.sh` to see what's red.

### I1. Engine node lost
- **Detect**: `ExamEngineNodeDown`; preflight FAIL for one node; per-node in-flight drops to 0.
- **Impact**: the LB health check removes the node within ~30 s. Its in-flight requests fail and the
  clients retry on another node. No state is lost (state lives in Redis). Remaining nodes take the load.
- **Act**:
  1. Is the remaining capacity enough? Look at CPU and pace on the other nodes. If not: `scripts/scale-out.sh <N+1>`.
     Note that the dead droplet is still tagged and counted, so first remove a dead **burst** node with
     `scripts/scale-in.sh --node <name> --force`, then `scale-out.sh N` brings the count back.
  2. Base node `exam-engine-1` dead: `doctl compute droplet-action power-cycle <id> --wait`. If it doesn't come
     back in 5 min, `scale-out.sh <N+1>` for capacity and rebuild the base node after the exam (README step 4).
  3. Look for the cause afterwards: `journalctl -u exam-engine -b -1`, DO droplet graphs, OOM kills (`dmesg`).
- **Recovered when** all nodes in the LB are healthy and p95/5xx are normal.

### I2. Redis primary lost / failover
- **Detect**: `RedisDown`, `/health` shows `redis: error`, 5xx spike everywhere; Stage B: Sentinel log `+switch-master`.
- **Stage B (Sentinel + replica)**: automatic failover within ~5–15 s. Engines follow via Sentinel.
  Writes of the last ~1 s may be lost, but clients detect the revision/checksum mismatch and **resync**
  their full answer map, so no answers are lost.
  1. `scripts/resize-redis.sh status`: one primary, Sentinel `ckquorum` OK, engines healthy.
  2. When the old primary returns, Sentinel makes it a replica. Check that its link is up.
  3. No failover happened (quorum lost)? On the replica: `sudo exam-redis-cli REPLICAOF NO ONE`, then on
     the base sentinel `redis-cli -p 26379 SENTINEL RESET exam`. Engines asking Sentinel will find the new primary
     (if not, `rolling-update.sh --env-only --force` with `EXAM_ENGINE_REDIS_URL` pointing at it).
  4. `RedisSplitBrain` (two primaries): stop `redis-server` on the one that **isn't** the Sentinel's
     master (`SENTINEL get-master-addr-by-name exam`), then `REPLICAOF <primary> 6379` on it.
- **Stage A (single Redis)**: this is a **total outage** (I7) until Redis is back.
  1. `ssh ops@redis-exam-1 'sudo systemctl status redis-server; sudo tail -50 /var/log/redis/redis-server.log'`
  2. Process died: `sudo systemctl restart redis-server`. AOF loading takes seconds to a minute; the
     engines reconnect on their own.
  3. Droplet dead: `doctl compute droplet-action power-cycle <id> --wait`.
  4. Disk/droplet lost (rare): restore the newest DO backup or `/var/backups/exam-redis/dump-*.rdb.gz` onto a
     new redis node, update `EXAM_ENGINE_REDIS_URL`, `rolling-update.sh --env-only --force`. Attempts go back
     to the snapshot time. Clients that are ahead get `resync` and re-upload their answers. Sessions created
     after the snapshot are gone, so those students sign in again (coordinators may need to allow device switches).
- Afterwards: extend time if students lost writing time (I7 "Extend time after an outage").

### I3. Load balancer problem
- **Detect**: public `/health` fails (preflight "public path"), but every node's local `/health` is OK. DO status page.
- **Act**:
  1. `doctl compute load-balancer get $LB_ID`: status, `droplet_ids`, health check, forwarding rules, allow-list.
     A partial `doctl ... update` wipes the settings, so restore the full config (lb/README.md).
  2. LB saturated (5xx without the engine's JSON body, engines idle): raise the size units (full update command).
  3. LB down for good (Stage A emergency): turn `exam-engine-1` into the origin. Install nginx with
     `nginx/exam-site.conf` (keep the origin cert on the ops machine for this), open 80/443 to Cloudflare in
     its firewall, and point the Cloudflare `exam` record at its public IP. About 10 minutes, and a single node's capacity only.
- Afterwards: extend time (I7) if students lost time.

### I4. Cloudflare problem
- **Detect**: errors with Cloudflare's own pages (5xx with `cf-ray`, 52x), www.cloudflarestatus.com, user
  reports across ISPs, while the LB answers fine from inside DO.
- **Self-inflicted first**: Security -> Events. A WAF or rate-limit rule blocking students (403/429 HTML
  pages)? Disable that rule. Bot Fight Mode or "Under Attack" switched on? Turn it off.
- **Cloudflare outage**: usually short and regional. Keep students writing offline (the client saves locally),
  extend afterwards. Switch the record to **DNS only** only if a publicly trusted cert is installed on the
  LB (cloudflare/README.md §8). Without it, browsers will refuse the origin certificate.
- Afterwards: extend time (I7) if needed.

### I5. Laravel unreachable (engine keeps running)
- **Detect**: `ExamConsumerStalled`; preflight Laravel section red; the monitor UI shows "engine unreachable"; aaPanel server down.
- **Impact on students: none.** Laravel isn't in the exam hot path. Exceptions: the portal "Enter exam"
  button needs Laravel, so students use the exam login page directly. Coordinators lose the monitor and its
  actions (extend, allow switch, re-issue). Password resets and passcode re-issues can't reach the engine.
- **Events queue up** in Redis (`ex:events`). Capped at ~500,000 entries: about **8 hours** of headroom at
  2,000 writing, about **15–20 minutes** at 60,000 writing. Past the cap, the oldest unconsumed events are
  trimmed (final answers still sit in the engine's attempt state for 7 days, but the automatic grading
  path loses them). Watch `ExamEventStreamNearCap`.
- **Act**:
  1. Fix the aaPanel side: server up? PHP-FPM? `supervisorctl status exam-engine-consumer:*`? DB reachable?
  2. Link problem? From the aaPanel server: `curl -s https://exam.<domain>/health`. A 403 on `/internal/*`
     means the WAF rule lost the server's IP. Fix rule 1.
  3. Coordinators need actions while Laravel is down: the controller collects requests; on-call can
     temporarily add their own IP to WAF rule 1 and call the internal API with the token (Appendix A).
     Every action carries an `actor`, so it is audit-logged when the consumer catches up.
- **Recovered when** the consumer lag reaches 0 and the monitor works. Reconcile before close.

### I6. Consumer backlog
- **Detect**: `ExamConsumerLagHigh`, results not appearing for show-score exams.
- **Act**:
  1. Consumers running? `supervisorctl status exam-engine-consumer:*`. Errors in `storage/logs/exam-engine-consumer.log`
     and `laravel.log` (tenant DB errors, a failing event retried over and over)?
  2. Throughput: raise `numprocs` to 4–8 in the aaPanel Supervisor profile, then `supervisorctl reread && supervisorctl update`.
     Watch Postgres connections: each process holds central + tenant connections.
  3. Postgres slow? Check the DB server load. Grading is batched; the exam itself is unaffected.
- The lag must be **0 before close and reconciliation**.

### I7. Total engine outage
- **Detect**: public `/health` fails, every node unhealthy (or Redis down in Stage A), students can't sync or sign in.
- **What students experience**: already-writing students keep answering offline (saved on the device). The
  on-screen timer keeps running from the last known deadline. New logins fail.
- **Act (on-call)**: find the broken layer fast: Cloudflare (I4) -> LB (I3) -> engine nodes (I1) -> Redis (I2).
  Post status every 5 minutes in the channel.
- **Message to schools** (controller): "Technical problem on the exam server. Students: keep the exam page
  open and keep answering; your answers are saved on your device and will be sent when the server is
  back. Don't close or refresh the page. Lost time will be added."

**Fallback decision tree** (controller decides, on-call advises):
```
Outage started BEFORE T0 (check-in / waiting room)?
 ├─ fixed before T0 ..................... continue; nothing to do
 └─ not fixed by T0 ..................... controller sets a new start time (meta can change until starts_at;
                                           Laravel re-arms) and announces it; late entry window as normal
Outage DURING writing:
 ├─ restored within 10 min .............. extend every writing attempt by (outage + 2 min) right away
 │                                         (bulk-extend.sh), broadcast "N minutes added"
 ├─ restored within 10–30 min ........... controller: extend (if the exam's purpose allows) or stop;
 │                                         a ranked/competitive exam usually stops -> re-sit
 └─ > 30 min, recurring, or Redis data lost
                                          -> stop: close the run when possible, void the sitting,
                                             schedule a re-sit (new exam/date). Small single-correct papers
                                             can re-sit on the in-app Laravel path (settings engine = laravel)
Outage spans the DEADLINE:
 └─ attempts past deadline + 90 s are auto-submitted with their last synced answers as soon as the engine
    is back (the sweeper runs every 5 s, so you can't extend first). There's no re-open in v1:
    controller decides per affected student/school: accept, or re-sit.
```

**Extend time after an outage** (on the aaPanel server, where the internal API accepts calls):
```bash
cd /path/to/exam-engine/deploy     # or copy scripts/bulk-extend.sh + lib.sh to the aaPanel server
scripts/bulk-extend.sh --dry-run --run '<tenantId>:<examId>' --minutes 12 \
  --reason "platform outage 10:42-10:51 IST" --actor "platform-oncall:<name> (incident <date>)"
scripts/bulk-extend.sh           --run '<tenantId>:<examId>' --minutes 12 \
  --reason "platform outage 10:42-10:51 IST" --actor "platform-oncall:<name> (incident <date>)"
```
It extends `writing` and `locked` attempts (`--state` to change that). It is **not idempotent**: run it
once per decision. Then broadcast the new end time.

### I8. Slow or overloaded engine
- **Detect**: `ExamSyncLatencyHigh`, `ExamApi5xxRate`, `ExamPacingRaised` sustained, high in-flight, CPU > 85 %.
- **Act**, cheapest first:
  1. **Pacing override 30 s** (controller, monitor). This cuts sync traffic about 3x and costs nothing; answers still save locally.
  2. **Scale out** `scripts/scale-out.sh <N+1 or +2>`. New nodes serve in about 2–3 minutes.
  3. Redis the bottleneck (`RedisSlowEval`, Redis CPU): `sudo exam-redis-cli SLOWLOG GET 20`,
     `sudo exam-redis-cli --latency`. Stage B: is the big node the primary? (`resize-redis.sh status`)
  4. LB the bottleneck: I3 step 2.
- Return pacing to automatic once p95 is back to normal for 10 minutes.

### I9. Login queue backlog
- **Detect**: `ExamLoginBusyRate`, `ExamLoginQueueDeep`, "Signing you in… N ahead" reports.
- **Cause**: bcrypt password checks (~0.25 s CPU each) for students who didn't check in early.
  Already checked-in devices aren't affected.
- **Act**: scale out (each node adds cores to the bcrypt pool). Coordinators steer students to **hall ticket +
  passcode** (microseconds per check). The controller may extend late entry. Afterwards: push check-in harder
  for the next exam, and use `entry_mode = either`.

### I10. Redis memory high
- **Detect**: `RedisMemoryHigh` (70 %) / `RedisMemoryCritical` (85 %). With `noeviction`, at 100 % every write
  fails (syncs return errors).
- **Act**:
  1. Why? `sudo exam-redis-cli INFO memory`, `XLEN ex:events` (consumer backlog makes the stream grow, see I6),
     number of armed runs this week (run data stays 7 days after close).
  2. Immediate room: raise maxmemory up to ~75 % of RAM:
     `sudo exam-redis-cli CONFIG SET maxmemory <MB>mb && sudo exam-redis-cli CONFIG REWRITE`.
  3. Stage B: `scripts/resize-redis.sh up` (8 GB node becomes primary). Stage A: an offline resize is only possible
     with nothing live. Plan it for after the exam.
  4. `RedisAofWriteFailing`: disk full? `df -h`; delete old `/var/backups/exam-redis/*.gz`; `journalctl --vacuum-size=500M`.

---

## 6. Load-test procedure

The engine ships a load generator, `exam-engine/cmd/loadgen` (the `loadgen` binary in every release
tarball). Its main flags are **`-students`**, **`-questions`**, **`-duration`**, **`-compress`** (run the
exam timeline faster than real time) and **`-password-percent`** (share of students who sign in with a
portal password instead of a passcode). Targeting and auth flags (engine URL, internal token, test run)
are documented in the engine README. Each virtual student does the real flow: login -> waiting room ->
start -> diff syncs with occasional proctor events -> final submit, and checks its answers came back intact.

**Ground rules**
- Use a **load-test Sahodaya and exam in Laravel** (e.g. code `LOADTEST`) so the consumer grades the
  synthetic attempts and the "consumer lag 0" gate means something. Never point the load generator at a real exam.
- Run on the production infrastructure only in a window with **no real runs** in check-in, waiting or live
  state (check the booking calendar), with the freeze rules in force.
- Generators run in **BLR1** to stress the origin (Cloudflare's capacity isn't in question). Add their IPs to
  the LB allow-list and Cloudflare WAF rule 2 (skip rate limits); one IP carrying thousands of students would
  otherwise trip the per-IP limits. Also do one smaller run through Cloudflare from outside DO to check the CDN path.
- Generator droplets need many sockets: `ulimit -n 200000` and
  `sysctl -w net.ipv4.ip_local_port_range="10240 65535"` (or create them from `cloud-init/engine-node.yaml` without
  starting the engine).
- Afterwards: close the test run, remove the allow-list/WAF entries, delete the generator droplets
  (`doctl compute droplet delete --tag-name exam-loadgen --force`), and write down the results (Appendix B table).

### Stage A: 3,000 virtual students from one machine
1. One `c-4` droplet in BLR1 tagged `exam-loadgen`; copy `loadgen` from the release tarball.
2. Fleet as for a real exam: preflight clean, monitoring on.
3. Run 1 (real time, the gate run):
   `./loadgen -students 3000 -questions 60 -duration 60m -compress 1 -password-percent 50 <target/auth flags>`
   The plan gate asks for **1,500 password logins in the last 15 minutes** before T0. Configure the login
   ramp accordingly (engine README).
4. Run 2 (repeatability): same with `-compress 4`.
5. **Pass** (plan §10): p95 sync < 300 ms · errors < 0.1 % · **0 lost answers** (loadgen report) ·
   login queue wait p95 < 60 s · consumer lag back to 0 within 5 min of the submit rush.
6. Run the four chaos drills (§7) during one of the runs.

### Stage B: 90,000 virtual students across ~10 generators
1. Create a **real booking** in Laravel for the test slot (90,000, the test exam). Let `plan-and-scale.sh`
   scale out at T-3h and add the Redis replica. The gate requires that scale-out was triggered by a real booking.
2. Ten `c-4` generator droplets in BLR1 (tag `exam-loadgen`), 9,000 students each. Each generator must use
   a **distinct slice of the roster** (loadgen sharding per the engine README). If it can't shard, arm
   ten test runs of 9,000, which is also a realistic multi-Sahodaya mix.
3. Start all generators within a minute (a `for` loop over `ssh -f`), with `-password-percent` matching the expected mix.
4. **Pass**: the Stage A criteria at 90k, plus pacing kicks in cleanly (20–30 s at peaks, no 5xx storm),
   Redis latency < 10 ms (`RedisSlowEval` quiet, dashboard "Avg Lua call latency"), LB not saturated,
   and scale-in afterwards removes everything it added.
5. Run the chaos drills (§7) under this load.
6. Calibrate: record the nodes needed per 20,000 students and per password-only share, and update the starting rule
   in `exam-engine:plan-capacity` if needed.

---

## 7. Chaos drills (run during a load test; repeat before each stage goes live)

| # | Drill | How | Pass |
|---|---|---|---|
| D1 | **Kill an engine node** | mid-writing: `ssh ops@<burst-node> 'sudo systemctl kill -s KILL exam-engine'` (or `doctl compute droplet-action power-off <id>`) | LB drops it within ~30 s; brief error blip; p95 back to normal within 2 min; **0 lost answers**; `scale-out.sh N` brings capacity back |
| D2 | **Fail over the Redis primary** | Stage B: `redis-cli -p 26379 SENTINEL FAILOVER exam`, then a hard one: `sudo systemctl kill -s KILL redis-server` on the primary. Stage A: `sudo systemctl restart redis-server` and time the recovery | Stage B: new primary within 15 s, engines recover on their own, resyncs visible, **0 lost answers**. Stage A: recovery time recorded for I2 |
| D3 | **Drop 5,000 clients for 5 minutes** | a separate loadgen process with `-students 5000`: `kill -STOP <pid>`, wait 5 min, `kill -CONT <pid>` | offline count rises by ~5,000 and falls back; those clients sync or resync; **0 lost answers**; no 5xx storm on return |
| D4 | **Cut Laravel <-> engine for 10 minutes** | remove the aaPanel IP from Cloudflare WAF rule 1 (or add a block rule for `/internal/*`) for 10 min. Simpler variant: `supervisorctl stop exam-engine-consumer:*` | students unaffected; monitor shows "unreachable"; `ex:events` grows; after restore, lag 0 within 5 min; reconciliation exact; no duplicate marks |

Write down for each drill: start/end time, what you saw, and the gate result. Any lost answer is a release blocker.

---

## 8. Go-live gates (plan §10)

### Stage A (2,000 platform-wide, always-on footprint)
- [ ] Engine unit tests (clock, state machine, seed, diff/checksum, pacing, login queue, answer shapes): all transitions covered
- [ ] Contract: OpenAPI + pack schema validate the same fixtures in Go, vitest and PHPUnit
- [ ] Grader: single / multiple (both marking modes) / numeric (tolerance) match the hand-worked cases; in-app and engine agree on single
- [ ] Laravel feature tests: arm, passcodes, password sync, bookings, consume
- [ ] Client e2e (Playwright): check-in, 5 min offline, refresh, device switch with approval, auto-submit, resync, phone viewport. No answer lost, order stable
- [ ] **Load test Stage A** (§6) passed: p95 sync < 300 ms, errors < 0.1 %, 0 lost answers, login wait p95 < 60 s, lag 0 within 5 min
- [ ] **Chaos D1–D4** passed (§7)
- [ ] **Dry run** with ≥ 200 real students, ≥ 3 schools, school + home, phones + computers: reconciliation exact
- [ ] Monitoring and alerts live; Alertmanager reaches on-call; DO uptime check on `/health`
- [ ] Redis backup **restore tested** (restore a daily RDB on a scratch droplet and read the keys)
- [ ] `exam-day-preflight.sh` clean; Cloudflare checklist done (cloudflare/README.md §9)
- [ ] Runbook walked through with the first Sahodaya's controller and coordinators; on-call roster set
- [ ] Laravel `EXAM_ENGINE_CAPACITY=2000`

### Stage B (60,000 platform-wide)
- [ ] Everything in Stage A, still green
- [ ] **Load test Stage B** (§6): 90,000 virtual students; scale-out triggered by a real booking; pacing clean; Redis latency < 10 ms; scale-in clean
- [ ] **Chaos D1–D4 under 90k load**, including a hard Redis primary kill with Sentinel
- [ ] Sentinel set up (`resize-redis.sh sentinel-setup`), engines on Sentinel; `resize-redis.sh up/down` rehearsed
- [ ] `plan-and-scale.sh` cron on the ops host for ≥ 2 weeks with correct decisions in its log
- [ ] Droplet limit raised; LB size units decided for busy slots
- [ ] Consumer `numprocs` plan for > 10k slots (4–8) tested against Postgres connection limits
- [ ] `ex:events` headroom accepted, or the cap raised/configurable (deploy README, assumption 6)
- [ ] Second on-call for Stage B slots
- [ ] Laravel `EXAM_ENGINE_CAPACITY` raised only per the ramp rule (§9)

---

## 9. Ramp rule

Live exams grow platform-wide concurrency in three steps: **about 2k -> 10–15k -> 60k**.
- Each step must pass its gates **and** leave **no unresolved incidents** from the previous step's real exams.
- Raise `EXAM_ENGINE_CAPACITY` in Laravel only when the next step is approved. Bookings above it are refused,
  which enforces the ramp across Sahodayas.
- First exam at each new step: one well-prepared Sahodaya, with a platform engineer watching live, and a
  post-exam review before the next step.

---

## Appendix A. Calling the internal API by hand (on-call)

Prefer the Laravel monitor (it records who did what). Use this only when the monitor is down. It works from
the aaPanel server (or from an IP temporarily added to Cloudflare WAF rule 1):
```bash
cd /www/wwwroot/sahodaya.truecampus.in
URL=$(grep -m1 '^EXAM_ENGINE_INTERNAL_URL=' .env | cut -d= -f2- | tr -d '"')
TOK=$(grep -m1 '^EXAM_ENGINE_INTERNAL_TOKEN=' .env | cut -d= -f2- | tr -d '"')
RUN='<tenantId>:<examId>'     # engines accept the raw ":" or %3A
api() { printf 'header = "Authorization: Bearer %s"\n' "$TOK" | curl -sS -K - -H 'Content-Type: application/json' "$@"; }
api "$URL/internal/runs/$RUN/summary" | jq .
api -d '{"seconds":30}' "$URL/internal/runs/$RUN/pace"                                   # pacing override (null = auto)
api -d '{"message":"5 minutes have been added.","actor":"oncall:<name>"}' "$URL/internal/runs/$RUN/broadcast"
api -d '{"action":"allow_switch","reason":"phone died","actor":"oncall:<name>"}' "$URL/internal/runs/$RUN/attempts/<reg_id>/actions"
```

## Appendix B. Post-exam report (template)

| Item | Value |
|---|---|
| Exam / Sahodaya / date / T0 (IST) | |
| Registered / checked in / entered / submitted / absent | |
| Peak writing, peak requests/s, peak p95 sync, max pace | |
| 5xx %, login 503 %, login queue wait p95 | |
| Engine nodes (base + burst), Redis replica used? | |
| Consumer lag peak; time to 0 after the deadline | |
| Extensions (count, total minutes, reasons), locks, force submits | |
| Incidents (ref to log), student impact | |
| Cost of burst capacity | |
| What to change before the next exam | |

## Appendix C. Incident log (template)

```
Incident: <short title>          Severity: SEV1/2/3        Exam(s): <run ids>
Lead (on-call): <name>           Exam controller(s): <names>
Timeline (IST):
  hh:mm  detected by <alert/report>
  hh:mm  action ...
  hh:mm  recovered (what showed it)
Student impact: <who, how many, how long>
Decisions: <extend N min / re-sit / none> by <controller>
Follow-ups: <owner, due date>
```
