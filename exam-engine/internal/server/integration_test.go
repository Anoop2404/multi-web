package server

import (
	"context"
	"crypto/aes"
	"crypto/cipher"
	cryptorand "crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"io"
	"net/http"
	"strconv"
	"strings"
	"testing"
	"time"

	"github.com/sahodaya/exam-engine/internal/entry"
	"github.com/sahodaya/exam-engine/internal/events"
	"github.com/sahodaya/exam-engine/internal/model"
	"github.com/sahodaya/exam-engine/internal/ratelimit"
	"github.com/sahodaya/exam-engine/internal/syncer"
)

func num(v any) int64 {
	f, _ := v.(float64)
	return int64(f)
}

func TestLuaISOMatchesGo(t *testing.T) {
	h := newHarness(t)
	for _, ms := range []int64{0, 951782400000, 1709251199000, 1731840000123, 4102444799000, 1893456000000} {
		got, err := h.E.St.Scripts.ISO.Run(context.Background(), h.E.St.R, nil, ms).Text()
		if err != nil {
			t.Fatal(err)
		}
		if want := model.ISOms(ms); got != want {
			t.Errorf("iso(%d) = %s, want %s", ms, got, want)
		}
	}
}

func TestLoginMethods(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 3})
	s0, s1, s2 := r.Students[0], r.Students[1], r.Students[2]

	// password ($2y$ bcrypt hash), username case-insensitive
	c := h.client("Mozilla/5.0 (Linux; Android 14) Chrome/131.0 Mobile")
	code, out := c.do("POST", "/api/login", map[string]any{"sahodaya": strings.ToLower(r.Code), "method": "password", "username": strings.ToUpper(s0.Username), "password": s0.Password})
	if code != 200 {
		t.Fatalf("password login → %d %v", code, out)
	}
	st := out["state"].(map[string]any)
	if num(st["reg_id"]) != s0.RegID || st["state"] != "checked_in" || st["paper_key"] != nil {
		t.Fatalf("state %v", st)
	}
	if got := c.must("GET", "/api/state", nil); num(got["reg_id"]) != s0.RegID {
		t.Fatalf("cookie session not working: %v", got)
	}

	// passcode typed loosely (lower case, no dash); hall tickets repeat (s0 and s2 share "1")
	c2 := h.client("")
	code, out = c2.do("POST", "/api/login", map[string]any{"sahodaya": r.Code, "method": "passcode", "hall_ticket": " " + s2.Ticket, "passcode": strings.ToLower(strings.ReplaceAll(s2.Passcode, "-", " "))})
	if code != 200 || num(out["state"].(map[string]any)["reg_id"]) != s2.RegID {
		t.Fatalf("passcode login → %d %v", code, out)
	}

	// launch token, single use
	tok := entry.SignLaunch(h.priv, entry.LaunchClaims{Run: r.Run, RegID: s1.RegID, Exp: h.Now().Add(100 * time.Second).Unix(), JTI: "aaaabbbbccccddddeeeeffff00001111"})
	c3 := h.client("")
	code, out = c3.do("POST", "/api/login", map[string]any{"method": "launch", "token": tok})
	if code != 200 || num(out["state"].(map[string]any)["reg_id"]) != s1.RegID {
		t.Fatalf("launch login → %d %v", code, out)
	}
	if code, _ := h.client("").do("POST", "/api/login", map[string]any{"method": "launch", "token": tok}); code != 401 {
		t.Fatalf("replayed launch token → %d", code)
	}

	// wrong details are generic 401s
	bad := s1
	bad.Password = "nope"
	if code, out := h.client("").loginPassword(r, bad); code != 401 || out["error"] != "unauthorized" {
		t.Fatalf("wrong password → %d %v", code, out)
	}
	bad = s1
	bad.Passcode = "AAAAA-AAAAA"
	if code, _ := h.client("").loginPasscode(r, bad); code != 401 {
		t.Fatalf("wrong passcode → %d", code)
	}
	bad.Username = "nobody"
	if code, _ := h.client("").loginPassword(r, bad); code != 401 {
		t.Fatalf("unknown user → %d", code)
	}
	// CSRF header is required on POSTs
	req, _ := http.NewRequest("POST", h.srv.URL+"/api/login", strings.NewReader(`{}`))
	res, _ := http.DefaultClient.Do(req)
	if res.StatusCode != 403 {
		t.Fatalf("missing X-Exam-Client → %d", res.StatusCode)
	}
	res.Body.Close()

	// sahodaya listing
	info := h.client("").must("GET", "/api/sahodaya/"+r.Code, nil)
	runs := info["runs"].([]any)
	if len(runs) != 1 || runs[0].(map[string]any)["state"] != "checkin" || info["name"] != "Test Sahodaya" {
		t.Fatalf("sahodaya %v", info)
	}
	if code, _ := h.client("").do("GET", "/api/sahodaya/NOPE", nil); code != 404 {
		t.Fatalf("unknown sahodaya → %d", code)
	}

	evs := filterEvents(h.drainEvents(), r.Run, model.EvCheckedIn)
	if len(evs) != 3 {
		t.Fatalf("checked_in events = %d", len(evs))
	}
}

func TestEntryModeAndBlocked(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 2, entryMode: "exam_passcode"})
	if code, out := h.client("").loginPassword(r, r.Students[0]); code != 403 {
		t.Fatalf("password on passcode-only exam → %d %v", code, out)
	}
	h.mustInternal("PUT", r.path("/roster/"+itoa(r.Students[1].RegID)), map[string]any{
		"name": "Blocked", "school_id": "school-1", "hall_ticket": r.Students[1].Ticket, "allowed": false,
		"passcode_hmac": mustHMAC(r, r.Students[1]),
	})
	if code, out := h.client("").loginPasscode(r, r.Students[1]); code != 403 {
		t.Fatalf("blocked candidate → %d %v", code, out)
	}
}

func mustHMAC(r *testRun, s student) string {
	hm, _ := entry.PasscodeHMAC(r.Secret, s.RegID, entry.NormalisePasscode(s.Passcode))
	return hm
}

func itoa(n int64) string { return strconv.FormatInt(n, 10) }

func TestLockoutAfterFiveFailures(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 1})
	s := r.Students[0]
	bad := s
	bad.Password = "wrong"
	for i := 1; i <= 5; i++ {
		code, _ := h.client("").loginPassword(r, bad)
		want := 401
		if i == 5 {
			want = 423
		}
		if code != want {
			t.Fatalf("failure %d → %d, want %d", i, code, want)
		}
	}
	if code, _ := h.client("").loginPassword(r, s); code != 423 {
		t.Fatalf("correct password while locked → %d", code)
	}
	h.advance(11 * time.Minute)
	// the lock lives in Redis with a TTL; expire it as time passing would
	_ = h.E.St.R.Del(context.Background(), "ex:{"+r.Run+"}:fail:u:"+s.Username).Err()
	if code, _ := h.client("").loginPassword(r, s); code != 200 {
		t.Fatalf("after the lock → %d", code)
	}
}

func TestDeviceConflictAndSwitch(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 1})
	s := r.Students[0]
	a := h.client("device-A")
	a.login(r, s)
	b := h.client("device-B")
	if code, out := b.loginPasscode(r, s); code != 409 || out["error"] != "device_conflict" {
		t.Fatalf("second device → %d %v", code, out)
	}
	h.drainEvents()
	h.mustInternal("POST", r.path("/attempts/"+itoa(s.RegID)+"/actions"), map[string]any{"action": "allow_switch", "reason": "phone died", "actor": "user:1"})
	b.login(r, s)
	if code, _ := a.do("GET", "/api/state", nil); code != 401 {
		t.Fatalf("old device still signed in → %d", code)
	}
	evs := h.drainEvents()
	if len(filterEvents(evs, r.Run, model.EvDeviceSwitched)) != 1 || len(filterEvents(evs, r.Run, model.EvAction)) != 1 {
		t.Fatalf("events %+v", evs)
	}
	// allow_switch is one-time
	c := h.client("device-C")
	if code, _ := c.loginPasscode(r, s); code != 409 {
		t.Fatalf("third device without approval → %d", code)
	}
	// ...but after the device has been idle longer than device_idle_minutes it's allowed
	h.advance(3 * time.Minute)
	c.login(r, s)
	// logout releases the binding immediately
	c.must("POST", "/api/logout", map[string]any{})
	h.client("device-D").login(r, s)
}

func TestStartRules(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 2, lateEntry: 10})
	s0, s1 := r.Students[0], r.Students[1]
	a := h.client("")
	st := a.login(r, s0)
	if st["state"] != "checked_in" {
		t.Fatalf("state %v", st["state"])
	}
	h.set(r.Starts.Add(-10 * time.Minute))
	if got := a.must("GET", "/api/state", nil); got["state"] != "waiting" {
		t.Fatalf("waiting room state %v", got["state"])
	}
	if code, out := a.do("POST", "/api/start", map[string]any{}); code != 409 || out["error"] != "not_open_yet" {
		t.Fatalf("too early → %d %v", code, out)
	}
	h.set(r.Starts.Add(time.Second))
	st = a.must("POST", "/api/start", map[string]any{})
	if st["state"] != "writing" || st["paper_key"] == nil || st["deadline"] != iso(r.Starts.Add(time.Hour)) {
		t.Fatalf("started state %v", st)
	}
	// the key opens the bundle
	bundle := a.must("GET", st["bundle_url"].(string), nil)
	key, _ := base64.StdEncoding.DecodeString(st["paper_key"].(string))
	iv, _ := base64.StdEncoding.DecodeString(bundle["iv"].(string))
	ct, _ := base64.StdEncoding.DecodeString(bundle["ciphertext"].(string))
	blk, _ := aes.NewCipher(key)
	g, _ := cipher.NewGCM(blk)
	plain, err := g.Open(nil, iv, ct, nil)
	if err != nil || !strings.Contains(string(plain), `"id":5504`) {
		t.Fatalf("bundle does not open: %v", err)
	}
	// idempotent
	if again := a.must("POST", "/api/start", map[string]any{}); again["deadline"] != st["deadline"] {
		t.Fatal("second start changed the deadline")
	}
	// late entry closes
	b := h.client("")
	b.login(r, s1)
	h.set(r.Starts.Add(11 * time.Minute))
	if code, out := b.do("POST", "/api/start", map[string]any{}); code != 409 || out["error"] != "closed" {
		t.Fatalf("after late entry → %d %v", code, out)
	}
	if got := b.must("GET", "/api/state", nil); got["state"] != "ended" {
		t.Fatalf("missed state %v", got["state"])
	}
	evs := filterEvents(h.drainEvents(), r.Run, model.EvStarted)
	if len(evs) != 1 || evs[0].Data["deadline"] != iso(r.Starts.Add(time.Hour)) {
		t.Fatalf("started events %+v", evs)
	}
}

func TestStartPinAndIndividualTiming(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 1, supervised: true, pin: "4821", timing: "individual", duration: 30})
	a := h.client("")
	if st := a.login(r, r.Students[0]); st["start_pin_required"] != true {
		t.Fatal("pin should be required")
	}
	h.set(r.Starts.Add(5 * time.Minute))
	if code, out := a.do("POST", "/api/start", map[string]any{"pin": "1111"}); code != 403 || out["error"] != "wrong_pin" {
		t.Fatalf("wrong pin → %d %v", code, out)
	}
	st := a.must("POST", "/api/start", map[string]any{"pin": 4821})
	if st["deadline"] != iso(r.Starts.Add(35*time.Minute)) {
		t.Fatalf("individual deadline %v", st["deadline"])
	}
}

func startWriting(h *harness, r *testRun, s student) *client {
	h.t.Helper()
	c := h.client("")
	c.login(r, s)
	if h.Now().Before(r.Starts) {
		h.set(r.Starts.Add(time.Second))
	}
	c.must("POST", "/api/start", map[string]any{})
	return c
}

func TestSyncRules(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 1, grace: 90})
	c := startWriting(h, r, r.Students[0])
	full := syncer.Answers{}

	// good diff
	full[qSingle] = "b"
	full[qMulti] = []string{"a", "c"}
	code, out := c.sync(0, full, map[string]any{qSingle: "b", qMulti: []string{"c", "a"}}, map[string]any{"flagged": []string{qTF}})
	if code != 200 || out["action"] != "none" || num(out["rev"]) != 1 || out["checksum_ok"] != true {
		t.Fatalf("sync → %d %v", code, out)
	}
	// stale base_rev → resync, not applied
	wrong := syncer.Clone(full)
	wrong[qTF] = "a"
	_, out = c.sync(0, wrong, map[string]any{qTF: "a"}, nil)
	if out["action"] != "resync" || num(out["rev"]) != 1 {
		t.Fatalf("stale rev → %v", out)
	}
	// checksum mismatch → resync, not applied
	_, out = c.sync(1, full, map[string]any{qTF: "a"}, nil)
	if out["action"] != "resync" || out["checksum_ok"] != false {
		t.Fatalf("checksum mismatch → %v", out)
	}
	st := c.must("GET", "/api/state", nil)
	if _, has := st["answers"].(map[string]any)[qTF]; has || num(st["rev"]) != 1 {
		t.Fatalf("mismatched change was applied: %v", st["answers"])
	}
	if fl := st["flagged"].([]any); len(fl) != 1 || fl[0] != qTF {
		t.Fatalf("flagged %v", st["flagged"])
	}
	// heartbeat with matching checksum, no changes
	if _, out = c.sync(1, full, map[string]any{}, nil); out["action"] != "none" || num(out["rev"]) != 1 {
		t.Fatalf("heartbeat → %v", out)
	}
	// unknown question / bad option → 400
	if code, _ := c.sync(1, full, map[string]any{"9999": "a"}, nil); code != 400 {
		t.Fatalf("unknown question → %d", code)
	}
	if code, _ := c.sync(1, full, map[string]any{qSingle: "z"}, nil); code != 400 {
		t.Fatalf("bad option → %d", code)
	}
	// resync replaces the stored map
	full = syncer.Answers{qNumeric: "12.5", qTF: "b"}
	out = c.must("POST", "/api/resync", map[string]any{"answers": full, "flagged": []string{}})
	if out["action"] != "none" || num(out["rev"]) != 2 {
		t.Fatalf("resync → %v", out)
	}
	if _, out = c.sync(2, full, map[string]any{}, nil); out["action"] != "none" {
		t.Fatalf("after resync the checksum should match: %v", out)
	}
	// proctor events are batched
	_, out = c.sync(2, full, map[string]any{}, map[string]any{"events": []map[string]any{{"type": "tab_hidden", "at": iso(h.Now())}, {"type": "bogus"}, {"type": "offline", "at": iso(h.Now()), "seconds": 40}}})
	if out["action"] != "none" {
		t.Fatalf("proctor sync → %v", out)
	}

	// after the deadline, within grace: accepted, counted as late, told to submit
	h.set(r.Starts.Add(time.Hour + 30*time.Second))
	full[qSingle] = "d"
	_, out = c.sync(2, full, map[string]any{qSingle: "d"}, nil)
	if out["action"] != "submit" || num(out["rev"]) != 3 {
		t.Fatalf("grace sync → %v", out)
	}
	// after deadline + grace: rejected
	h.set(r.Starts.Add(time.Hour + 2*time.Minute))
	late := syncer.Clone(full)
	late[qSingle] = "a"
	_, out = c.sync(3, late, map[string]any{qSingle: "a"}, nil)
	if out["action"] != "submit" || num(out["rev"]) != 3 {
		t.Fatalf("late sync → %v", out)
	}
	sub := c.must("POST", "/api/submit", map[string]any{"base_rev": 3, "changes": map[string]any{}, "checksum": syncer.Checksum(full)})
	if sub["submitted"] != true || sub["mode"] != "auto" {
		t.Fatalf("submit → %v", sub)
	}
	evs := h.drainEvents()
	subs := filterEvents(evs, r.Run, model.EvSubmitted)
	if len(subs) != 1 || num(subs[0].Data["late_changes"]) != 1 {
		t.Fatalf("submitted %+v", subs)
	}
	ans := subs[0].Data["answers"].(map[string]any)
	if ans[qSingle] != "d" || ans[qNumeric] != "12.5" || ans[qTF] != "b" || len(ans) != 3 {
		t.Fatalf("final answers %v", ans)
	}
	pb := filterEvents(evs, r.Run, model.EvProctorBatch)
	if len(pb) != 1 || len(pb[0].Data["events"].([]any)) != 2 {
		t.Fatalf("proctor batch %+v", pb)
	}
	// sync after submit → submit
	if _, out = c.sync(3, full, map[string]any{}, nil); out["action"] != "submit" {
		t.Fatalf("sync after submit → %v", out)
	}
}

func TestSubmitIdempotentAndResult(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 1})
	s := r.Students[0]
	c := startWriting(h, r, s)
	full := syncer.Answers{qMulti: []string{"b", "d"}}
	c.sync(0, full, map[string]any{qMulti: []string{"b", "d"}}, nil)
	full[qNumeric] = "-4"
	// mismatched final checksum → 409 resync
	if code, out := c.do("POST", "/api/submit", map[string]any{"base_rev": 1, "changes": map[string]any{qNumeric: "-4"}, "checksum": "bad"}); code != 409 || out["action"] != "resync" {
		t.Fatalf("bad submit → %d %v", code, out)
	}
	h.advance(5 * time.Minute)
	first := c.must("POST", "/api/submit", map[string]any{"base_rev": 1, "changes": map[string]any{qNumeric: "-4"}, "checksum": syncer.Checksum(full)})
	second := c.must("POST", "/api/submit", map[string]any{"base_rev": 1, "changes": map[string]any{qNumeric: "9"}, "checksum": "x"})
	if first["receipt"] != second["receipt"] || first["submitted_at"] != second["submitted_at"] || first["mode"] != "manual" {
		t.Fatalf("not idempotent: %v vs %v", first, second)
	}
	if !strings.HasPrefix(first["receipt"].(string), r.Code+"-") {
		t.Fatalf("receipt %v", first["receipt"])
	}
	if res := c.must("GET", "/api/result", nil); res["status"] != "pending" {
		t.Fatalf("result %v", res)
	}
	h.mustInternal("POST", r.path("/results"), map[string]any{"results": map[string]any{itoa(s.RegID): map[string]any{"score": 3.5, "max_score": 5, "percentage": 70, "grade": "B"}}})
	if res := c.must("GET", "/api/result", nil); res["status"] != "ready" || res["score"] != 3.5 {
		t.Fatalf("result %v", res)
	}
	subs := filterEvents(h.drainEvents(), r.Run, model.EvSubmitted)
	if len(subs) != 1 {
		t.Fatalf("submitted events = %d", len(subs))
	}
	ans := subs[0].Data["answers"].(map[string]any)
	if ans[qNumeric] != "-4" || len(ans[qMulti].([]any)) != 2 {
		t.Fatalf("answers %v", ans)
	}
	st := c.must("GET", "/api/state", nil)
	if st["state"] != "submitted" || st["receipt"] != first["receipt"] {
		t.Fatalf("state after submit %v", st)
	}
}

func TestSweeperAutoSubmitAndSnapshots(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 2, grace: 60})
	a := startWriting(h, r, r.Students[0])
	b := startWriting(h, r, r.Students[1])
	full := syncer.Answers{qSingle: "c"}
	a.sync(0, full, map[string]any{qSingle: "c"}, nil)
	h.drainEvents()
	// snapshots: at most every 120 s per attempt, only if changed
	h.advance(121 * time.Second)
	h.sweep.Tick(context.Background())
	snaps := filterEvents(h.drainEvents(), r.Run, model.EvSnapshot)
	if len(snaps) != 1 || snaps[0].RegID != r.Students[0].RegID {
		t.Fatalf("snapshots %+v", snaps)
	}
	h.advance(121 * time.Second)
	h.sweep.Tick(context.Background())
	if snaps := filterEvents(h.drainEvents(), r.Run, model.EvSnapshot); len(snaps) != 0 {
		t.Fatalf("unchanged attempt snapshotted again: %+v", snaps)
	}
	// extend b by 5 minutes, then move past the original deadline + grace
	h.mustInternal("POST", r.path("/attempts/"+itoa(r.Students[1].RegID)+"/actions"), map[string]any{"action": "extend", "minutes": 5, "reason": "power cut", "actor": "user:9"})
	h.set(r.Starts.Add(time.Hour + 2*time.Minute))
	h.sweep.Tick(context.Background())
	if !h.sweep.IsLeader() {
		t.Fatal("sweeper should hold the leader lock")
	}
	subs := filterEvents(h.drainEvents(), r.Run, model.EvSubmitted)
	if len(subs) != 1 || subs[0].RegID != r.Students[0].RegID || subs[0].Data["mode"] != "auto" || subs[0].Data["answers"].(map[string]any)[qSingle] != "c" {
		t.Fatalf("auto submit %+v", subs)
	}
	if _, out := a.sync(1, full, map[string]any{}, nil); out["action"] != "submit" {
		t.Fatalf("sync after auto-submit → %v", out)
	}
	if _, out := b.sync(0, syncer.Answers{}, map[string]any{}, nil); out["action"] != "none" {
		t.Fatalf("extended attempt should still be writing: %v", out)
	}
	h.set(r.Starts.Add(time.Hour + 7*time.Minute))
	h.sweep.Tick(context.Background())
	if subs := filterEvents(h.drainEvents(), r.Run, model.EvSubmitted); len(subs) != 1 || subs[0].RegID != r.Students[1].RegID {
		t.Fatalf("extended attempt auto submit %+v", subs)
	}
}

func TestInternalMonitorAndActions(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 4})
	s0, s1, s2 := r.Students[0], r.Students[1], r.Students[2]
	a := h.client("Mozilla/5.0 (Windows NT 10.0) Chrome/131.0.0.0 Safari/537.36")
	a.login(r, s0)
	b := h.client("")
	b.login(r, s1)
	c := h.client("")
	c.login(r, s2)
	// lock before start → start refused
	h.mustInternal("POST", r.path("/attempts/"+itoa(s2.RegID)+"/actions"), map[string]any{"action": "lock", "reason": "check id", "actor": "user:2"})
	h.set(r.Starts.Add(time.Second))
	a.must("POST", "/api/start", map[string]any{})
	b.must("POST", "/api/start", map[string]any{})
	if code, out := c.do("POST", "/api/start", map[string]any{}); code != 423 {
		t.Fatalf("locked start → %d %v", code, out)
	}
	sum := h.mustInternal("GET", r.path("/summary"), nil)
	counts := sum["counts"].(map[string]any)
	if num(counts["roster"]) != 4 || num(counts["checked_in"]) != 3 || num(counts["writing"]) != 2 || num(counts["waiting"]) != 1 || num(counts["locked"]) != 1 || sum["state"] != "live" {
		t.Fatalf("summary %v", sum)
	}
	if bs := sum["by_school"].([]any); len(bs) != 2 {
		t.Fatalf("by_school %v", bs)
	}
	// attempts list with filters
	list := h.mustInternal("GET", r.path("/attempts?state=writing"), nil)
	if num(list["total"]) != 2 {
		t.Fatalf("writing list %v", list)
	}
	list = h.mustInternal("GET", r.path("/attempts?school_id=school-0&per_page=1&page=2"), nil)
	if num(list["total"]) != 2 || len(list["data"].([]any)) != 1 {
		t.Fatalf("school list %v", list)
	}
	list = h.mustInternal("GET", r.path("/attempts?state=not_checked_in"), nil)
	if num(list["total"]) != 1 {
		t.Fatalf("absent list %v", list)
	}
	// extend moves the deadline
	row := h.mustInternal("POST", r.path("/attempts/"+itoa(s0.RegID)+"/actions"), map[string]any{"action": "extend", "minutes": 7, "reason": "slow phone", "actor": "user:3"})
	if row["deadline"] != iso(r.Starts.Add(67*time.Minute)) || num(row["extra_minutes"]) != 7 || row["device"] != "Chrome 131 / Windows" {
		t.Fatalf("extend row %v", row)
	}
	if st := a.must("GET", "/api/state", nil); st["deadline"] != iso(r.Starts.Add(67*time.Minute)) || num(st["extra_minutes"]) != 7 {
		t.Fatalf("student deadline after extend %v", st["deadline"])
	}
	// lock while writing → sync answers lock; unlock → none
	h.mustInternal("POST", r.path("/attempts/"+itoa(s1.RegID)+"/actions"), map[string]any{"action": "lock", "actor": "user:2"})
	if _, out := b.sync(0, syncer.Answers{qSingle: "a"}, map[string]any{qSingle: "a"}, nil); out["action"] != "lock" {
		t.Fatalf("locked sync → %v", out)
	}
	if st := b.must("GET", "/api/state", nil); st["state"] != "locked" {
		t.Fatalf("locked state %v", st["state"])
	}
	if code, _ := b.do("POST", "/api/submit", map[string]any{}); code != 423 {
		t.Fatalf("locked submit → %d", code)
	}
	h.mustInternal("POST", r.path("/attempts/"+itoa(s1.RegID)+"/actions"), map[string]any{"action": "unlock", "actor": "user:2"})
	if _, out := b.sync(0, syncer.Answers{qSingle: "a"}, map[string]any{qSingle: "a"}, nil); out["action"] != "none" || num(out["rev"]) != 1 {
		t.Fatalf("unlocked sync → %v", out)
	}
	// force_submit
	row = h.mustInternal("POST", r.path("/attempts/"+itoa(s1.RegID)+"/actions"), map[string]any{"action": "force_submit", "reason": "malpractice", "actor": "user:1"})
	if row["state"] != "submitted" || row["submit_mode"] != "forced" {
		t.Fatalf("force_submit row %v", row)
	}
	if code, out := h.internal("POST", r.path("/attempts/"+itoa(s1.RegID)+"/actions"), map[string]any{"action": "force_submit", "actor": "user:1"}); code != 409 || out["error"] != "already_submitted" {
		t.Fatalf("second force_submit → %d %v", code, out)
	}
	if _, out := b.sync(1, syncer.Answers{qSingle: "a"}, map[string]any{}, nil); out["action"] != "submit" {
		t.Fatalf("sync after force → %v", out)
	}
	evs := h.drainEvents()
	acts := filterEvents(evs, r.Run, model.EvAction)
	if len(acts) != 5 {
		t.Fatalf("action events = %d", len(acts))
	}
	if acts[1].Data["action"] != "extend" || num(acts[1].Data["minutes"]) != 7 || acts[1].Data["actor"] != "user:3" {
		t.Fatalf("extend event %+v", acts[1])
	}
	subs := filterEvents(evs, r.Run, model.EvSubmitted)
	if len(subs) != 1 || subs[0].Data["mode"] != "forced" || subs[0].Data["answers"].(map[string]any)[qSingle] != "a" {
		t.Fatalf("forced submitted %+v", subs)
	}
	// auth
	req, _ := http.NewRequest("GET", h.srv.URL+r.path("/summary"), nil)
	req.Header.Set("Authorization", "Bearer wrong")
	res, _ := http.DefaultClient.Do(req)
	io.Copy(io.Discard, res.Body)
	res.Body.Close()
	if res.StatusCode != 401 {
		t.Fatalf("wrong token → %d", res.StatusCode)
	}
}

func TestBroadcastAndPace(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 1})
	c := startWriting(h, r, r.Students[0])
	h.mustInternal("POST", r.path("/broadcast"), map[string]any{"message": "Five minutes extra for everyone in Hall 2", "actor": "user:1"})
	h.mustInternal("POST", r.path("/pace"), map[string]any{"seconds": 30})
	_, out := c.sync(0, syncer.Answers{}, map[string]any{}, nil)
	msgs := out["messages"].([]any)
	if len(msgs) != 1 || msgs[0].(map[string]any)["text"] != "Five minutes extra for everyone in Hall 2" || num(out["next_sync_in"]) != 30 {
		t.Fatalf("sync after broadcast %v", out)
	}
	h.mustInternal("POST", r.path("/pace"), map[string]any{"seconds": nil})
	if _, out = c.sync(0, syncer.Answers{}, map[string]any{}, nil); num(out["next_sync_in"]) != 10 {
		t.Fatalf("auto pace %v", out["next_sync_in"])
	}
	if b := filterEvents(h.drainEvents(), r.Run, model.EvBroadcast); len(b) != 1 || b[0].Data["actor"] != "user:1" {
		t.Fatalf("broadcast events %+v", b)
	}
}

func TestEventsClaimPendingFirst(t *testing.T) {
	h := newHarness(t)
	h.drainEvents()
	r := h.arm(runOpts{students: 3})
	for _, s := range r.Students {
		h.client("").login(r, s) // 3 checked_in events
	}
	claim := func(consumer string, n int) []string {
		res := h.mustInternal("POST", "/internal/events/claim", map[string]any{"consumer": consumer, "count": n, "block_ms": 100})
		var ids []string
		for _, e := range res["events"].([]any) {
			m := e.(map[string]any)
			if m["run"] != r.Run || m["type"] != "attempt.checked_in" || m["at"] == "" || m["data"].(map[string]any)["method"] != "passcode" {
				t.Fatalf("bad event %v", m)
			}
			ids = append(ids, m["id"].(string))
		}
		return ids
	}
	first := claim("laravel-1", 2)
	if len(first) != 2 {
		t.Fatalf("first claim %v", first)
	}
	again := claim("laravel-1", 2) // not acked → the same pending entries again
	if strings.Join(again, ",") != strings.Join(first, ",") {
		t.Fatalf("pending not redelivered first: %v vs %v", again, first)
	}
	other := claim("laravel-2", 10) // another consumer only gets the undelivered one
	if len(other) != 1 {
		t.Fatalf("other consumer %v", other)
	}
	ack := h.mustInternal("POST", "/internal/events/ack", map[string]any{"ids": first})
	if num(ack["acked"]) != 2 {
		t.Fatalf("ack %v", ack)
	}
	if next := claim("laravel-1", 10); len(next) != 0 {
		t.Fatalf("after ack, nothing new should remain for laravel-1: %v", next)
	}
	h.mustInternal("POST", "/internal/events/ack", map[string]any{"ids": other})
	if code, _ := h.internal("POST", "/internal/events/ack", map[string]any{"ids": []string{"x"}}); code != 400 {
		t.Fatalf("bad id → %d", code)
	}
}

func TestCloseForceFinalises(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 3})
	a := startWriting(h, r, r.Students[0])
	startWriting(h, r, r.Students[1])
	a.sync(0, syncer.Answers{qTF: "a"}, map[string]any{qTF: "a"}, nil)
	h.drainEvents()
	out := h.mustInternal("POST", r.path("/close"), nil)
	if out["ok"] != true || num(out["unfinished"]) != 0 || num(out["forced"]) != 2 {
		t.Fatalf("close %v", out)
	}
	evs := h.drainEvents()
	if subs := filterEvents(evs, r.Run, model.EvSubmitted); len(subs) != 2 || subs[0].Data["mode"] != "forced" {
		t.Fatalf("forced %+v", subs)
	}
	closed := filterEvents(evs, r.Run, model.EvClosed)
	if len(closed) != 1 || num(closed[0].Data["unfinished_forced"]) != 2 {
		t.Fatalf("run.closed %+v", closed)
	}
	if code, out := h.client("").loginPasscode(r, r.Students[2]); code != 409 || out["error"] != "closed" {
		t.Fatalf("login after close → %d %v", code, out)
	}
	if sum := h.mustInternal("GET", r.path("/summary"), nil); sum["state"] != "closed" {
		t.Fatalf("summary after close %v", sum["state"])
	}
	if ttl := testRedis.TTL(context.Background(), "ex:{"+r.Run+"}:meta").Val(); ttl < 6*24*time.Hour {
		t.Fatalf("meta TTL after close = %v", ttl)
	}
	// closing again is harmless and emits nothing
	h.mustInternal("POST", r.path("/close"), []any{})
	if c := filterEvents(h.drainEvents(), r.Run, model.EvClosed); len(c) != 0 {
		t.Fatal("second close emitted run.closed again")
	}
	if code, _ := h.client("").do("GET", "/api/sahodaya/"+r.Code, nil); code != 404 {
		t.Fatalf("closed run still listed → %d", code)
	}
}

func TestMetaLockingBundleAndDisarm(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 1})
	_, cur := h.internal("GET", r.path(""), nil)
	meta := cur["meta"].(map[string]any)
	meta["passcode_secret"] = r.Secret
	// mismatching meta.questions vs paper → bundle refused
	meta2 := map[string]any{}
	for k, v := range meta {
		meta2[k] = v
	}
	meta2["questions"] = map[string]string{qSingle: "multiple", qMulti: "multiple", qTF: "true_false", qNumeric: "numeric"}
	h.mustInternal("PUT", r.path(""), meta2)
	code, _ := h.internal("PUT", r.path("/bundle"), map[string]any{"version": 2, "iv": "AAAAAAAAAAAAAAAA", "ciphertext": "AAAA", "key": "AAAA", "sha256": ""})
	if code != 400 {
		t.Fatalf("bad bundle → %d", code)
	}
	h.mustInternal("PUT", r.path(""), meta)
	h.set(r.Starts.Add(time.Minute))
	changed := map[string]any{}
	for k, v := range meta {
		changed[k] = v
	}
	changed["title"] = "Renamed"
	if code, out := h.internal("PUT", r.path(""), changed); code != 409 || out["error"] != "locked" {
		t.Fatalf("locked meta change → %d %v", code, out)
	}
	changed["title"] = meta["title"]
	changed["late_entry_minutes"] = 30
	h.mustInternal("PUT", r.path(""), changed)
	if code, _ := h.internal("POST", r.path("/disarm"), nil); code != 409 {
		t.Fatalf("disarm after start → %d", code)
	}
	if code, _ := h.client("").do("GET", "/api/bundle/"+r.Run+"/9", nil); code != 404 {
		t.Fatalf("wrong bundle version → %d", code)
	}
	// a fresh run can be disarmed before it starts
	r2 := h.arm(runOpts{students: 1})
	h.mustInternal("POST", r2.path("/disarm"), []any{})
	if code, _ := h.internal("GET", r2.path("/summary"), nil); code != 404 {
		t.Fatalf("disarmed run still there → %d", code)
	}
	if n := testRedis.Exists(context.Background(), "ex:{"+r2.Run+"}:roster").Val(); n != 0 {
		t.Fatal("roster survived disarm")
	}
}

func TestRateLimits(t *testing.T) {
	h := newHarness(t)
	h.E.SyncLimiter = ratelimit.New(6, 10*time.Second)
	h.E.LoginLimiter = ratelimit.New(10, time.Minute)
	r := h.arm(runOpts{students: 2})
	c := startWriting(h, r, r.Students[0])
	for i := 1; i <= 7; i++ {
		code, out := c.sync(0, syncer.Answers{}, map[string]any{}, nil)
		if i <= 6 && code != 200 {
			t.Fatalf("sync %d → %d %v", i, code, out)
		}
		if i == 7 && (code != 429 || out["error"] != "rate_limited") {
			t.Fatalf("7th sync in 10 s → %d %v", code, out)
		}
	}
	// login: 10 per minute per IP + identity
	bad := r.Students[1]
	bad.Password = "wrong"
	h.E.St.R.Del(context.Background(), "ex:{"+r.Run+"}:fail:u:"+bad.Username)
	var last int
	for i := 0; i < 11; i++ {
		last, _ = h.client("").loginPassword(r, bad)
		if i == 4 {
			// keep the lockout out of the way: this test is about the rate limit
			h.E.St.R.Del(context.Background(), "ex:{"+r.Run+"}:fail:u:"+bad.Username)
		}
	}
	if last != 429 {
		t.Fatalf("11th login in a minute → %d", last)
	}
}

func TestMetaQuestionsMustMatchPaper(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 1})
	_, cur := h.internal("GET", r.path(""), nil)
	meta := cur["meta"].(map[string]any)
	meta["passcode_secret"] = r.Secret
	meta["questions"] = map[string]string{qSingle: "multiple", qMulti: "multiple", qTF: "true_false", qNumeric: "numeric"}
	h.mustInternal("PUT", r.path(""), meta)
	code, out := h.internal("PUT", r.path("/bundle"), bundleBody(t, testPaper, 2))
	if code != 400 || !strings.Contains(out["message"].(string), "5501") {
		t.Fatalf("mismatched bundle → %d %v", code, out)
	}
	// with the meta map gone, the paper alone defines the schema (types + option keys)
	delete(meta, "questions")
	h.mustInternal("PUT", r.path(""), meta)
	h.mustInternal("PUT", r.path("/bundle"), bundleBody(t, testPaper, 2))
	c := startWriting(h, r, r.Students[0])
	if code, _ := c.sync(0, syncer.Answers{qMulti: []string{"e"}}, map[string]any{qMulti: []string{"e"}}, nil); code != 400 {
		t.Fatalf("option not on the paper → %d", code)
	}
	if st := c.must("GET", "/api/state", nil); num(st["bundle_version"]) != 2 || !strings.HasSuffix(st["bundle_url"].(string), "%3A"+strings.Split(r.Run, ":")[1]+"/2") {
		t.Fatalf("bundle url %v", st["bundle_url"])
	}
}

func TestLockedBeforeStartSyncSaysLock(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 1})
	c := h.client("")
	c.login(r, r.Students[0])
	h.mustInternal("POST", r.path("/attempts/"+itoa(r.Students[0].RegID)+"/actions"), map[string]any{"action": "lock", "actor": "user:1"})
	if _, out := c.sync(0, syncer.Answers{}, map[string]any{}, nil); out["action"] != "lock" {
		t.Fatalf("locked, not started → %v", out)
	}
	if st := c.must("GET", "/api/state", nil); st["state"] != "locked" {
		t.Fatalf("state %v", st["state"])
	}
}

func bundleBody(t *testing.T, plain []byte, version int) map[string]any {
	t.Helper()
	key := make([]byte, 32)
	iv := make([]byte, 12)
	_, _ = cryptorand.Read(key)
	_, _ = cryptorand.Read(iv)
	b, _ := aes.NewCipher(key)
	g, _ := cipher.NewGCM(b)
	ct := g.Seal(nil, iv, plain, nil)
	sum := sha256.Sum256(ct)
	return map[string]any{"version": version, "iv": base64.StdEncoding.EncodeToString(iv), "ciphertext": base64.StdEncoding.EncodeToString(ct),
		"key": base64.StdEncoding.EncodeToString(key), "sha256": hex.EncodeToString(sum[:])}
}

func TestRunExtend(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 2, lateEntry: 15})
	a := startWriting(h, r, r.Students[0]) // writing, deadline starts+60
	b := h.client("")
	b.login(r, r.Students[1]) // checked in, not started
	h.drainEvents()
	out := h.mustInternal("POST", r.path("/extend"), map[string]any{"minutes": 10, "actor": "user:1 (controller)"})
	if num(out["extended_minutes"]) != 10 {
		t.Fatalf("extend → %v", out)
	}
	h.mustInternal("POST", r.path("/extend"), map[string]any{"minutes": 5, "actor": "user:1"})
	want := iso(r.Starts.Add(75 * time.Minute))
	if st := a.must("GET", "/api/state", nil); st["deadline"] != want || num(st["extra_minutes"]) != 15 {
		t.Fatalf("writing attempt after run extend: deadline %v extra %v", st["deadline"], st["extra_minutes"])
	}
	if _, out := a.sync(0, syncer.Answers{}, map[string]any{}, nil); out["deadline"] != want {
		t.Fatalf("sync deadline %v", out["deadline"])
	}
	// an attempt that starts later gets the run-level extra too
	h.advance(5 * time.Minute)
	if st := b.must("POST", "/api/start", map[string]any{}); st["deadline"] != want {
		t.Fatalf("late starter deadline %v", st["deadline"])
	}
	sum := h.mustInternal("GET", r.path("/summary"), nil)
	if num(sum["extended_minutes"]) != 15 || sum["hold"] != false {
		t.Fatalf("summary %v", sum)
	}
	row := h.mustInternal("GET", r.path("/attempts?state=writing"), nil)["data"].([]any)[0].(map[string]any)
	if row["deadline"] != want || num(row["extra_minutes"]) != 15 {
		t.Fatalf("row %v", row)
	}
	// the sweeper respects the extended deadline
	h.set(r.Starts.Add(time.Hour + 5*time.Minute))
	h.sweep.Tick(context.Background())
	if _, out := a.sync(0, syncer.Answers{}, map[string]any{}, nil); out["action"] != "none" {
		t.Fatalf("extended attempt was finalised early: %v", out)
	}
	evs := filterEvents(h.drainEvents(), r.Run, model.EvRunExtended)
	if len(evs) != 2 || evs[0].RegID != 0 || num(evs[0].Data["minutes"]) != 10 || evs[0].Data["actor"] != "user:1 (controller)" {
		t.Fatalf("run.extended events %+v", evs)
	}
	if code, _ := h.internal("POST", r.path("/extend"), map[string]any{"minutes": 0}); code != 400 {
		t.Fatalf("zero minutes → %d", code)
	}
}

func TestRunHold(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 2, grace: 60})
	a := startWriting(h, r, r.Students[0])
	b := startWriting(h, r, r.Students[1])
	full := syncer.Answers{qSingle: "a"}
	a.sync(0, full, map[string]any{qSingle: "a"}, nil)
	h.drainEvents()
	h.mustInternal("POST", r.path("/hold"), map[string]any{"on": true, "actor": "user:1"})
	// before the deadline: business as usual
	if _, out := a.sync(1, full, map[string]any{}, nil); out["action"] != "none" {
		t.Fatalf("held, before deadline → %v", out)
	}
	// past deadline + grace: lock with a message, no changes, no auto-submit
	h.set(r.Starts.Add(time.Hour + 3*time.Minute))
	late := syncer.Clone(full)
	late[qTF] = "b"
	_, out := a.sync(1, late, map[string]any{qTF: "b"}, nil)
	msgs := out["messages"].([]any)
	if out["action"] != "lock" || num(out["rev"]) != 1 || len(msgs) == 0 || num(msgs[len(msgs)-1].(map[string]any)["id"]) != -1 {
		t.Fatalf("held sync → %v", out)
	}
	h.sweep.Tick(context.Background())
	early := h.drainEvents()
	if subs := filterEvents(early, r.Run, model.EvSubmitted); len(subs) != 0 {
		t.Fatalf("sweeper submitted a held run: %+v", subs)
	}
	if code, out := b.do("POST", "/api/submit", map[string]any{}); code != 423 || out["action"] != "lock" {
		t.Fatalf("submit while held → %d %v", code, out)
	}
	if st := a.must("GET", "/api/state", nil); st["state"] != "locked" {
		t.Fatalf("held state %v", st["state"])
	}
	sum := h.mustInternal("GET", r.path("/summary"), nil)
	if sum["hold"] != true || num(sum["counts"].(map[string]any)["writing"]) != 2 {
		t.Fatalf("summary while held %v", sum)
	}
	// typical incident: extend, then release the hold → students continue
	h.mustInternal("POST", r.path("/extend"), map[string]any{"minutes": 10, "actor": "user:1"})
	h.mustInternal("POST", r.path("/hold"), map[string]any{"on": false, "actor": "user:1"})
	if _, out := a.sync(1, late, map[string]any{qTF: "b"}, nil); out["action"] != "none" || num(out["rev"]) != 2 {
		t.Fatalf("after release → %v", out)
	}
	// release without extension: the sweeper finalises once due
	h.set(r.Starts.Add(time.Hour + 12*time.Minute))
	h.sweep.Tick(context.Background())
	evs := append(early, h.drainEvents()...)
	if subs := filterEvents(evs, r.Run, model.EvSubmitted); len(subs) != 2 || subs[0].Data["mode"] != "auto" {
		t.Fatalf("auto-submit after release %+v", subs)
	}
	holds := filterEvents(evs, r.Run, model.EvRunHold)
	if len(holds) != 2 || holds[0].Data["on"] != true || holds[1].Data["on"] != false || holds[0].RegID != 0 {
		t.Fatalf("run.hold events %+v", holds)
	}
}

func TestEventTrimKeepsUnacknowledged(t *testing.T) {
	h := newHarness(t)
	h.drainEvents()
	ctx := context.Background()
	for i := 0; i < 450; i++ {
		if _, err := events.Emit(ctx, h.E.St, model.EvBroadcast, "trim:1", 0, h.Now(), map[string]any{"i": i}); err != nil {
			t.Fatal(err)
		}
	}
	claim := func(n int) []string {
		res := h.mustInternal("POST", "/internal/events/claim", map[string]any{"consumer": "trim-test", "count": n, "block_ms": 0})
		var ids []string
		for _, e := range res["events"].([]any) {
			ids = append(ids, e.(map[string]any)["id"].(string))
		}
		return ids
	}
	got := claim(400) // the last 50 stay undelivered
	if len(got) != 400 {
		t.Fatalf("claimed %d", len(got))
	}
	h.mustInternal("POST", "/internal/events/ack", map[string]any{"ids": got[:300]}) // 100 stay pending
	before := testRedis.XLen(ctx, "ex:events").Val()
	n, err := events.Trim(ctx, h.E.St)
	if err != nil {
		t.Fatal(err)
	}
	after := testRedis.XLen(ctx, "ex:events").Val()
	if n == 0 || after >= before {
		t.Fatalf("nothing trimmed: %d → %d", before, after)
	}
	// every pending and undelivered entry is still there
	if left := testRedis.XRange(ctx, "ex:events", got[300], "+").Val(); len(left) != 150 {
		t.Fatalf("pending+undelivered entries after trim = %d, want 150", len(left))
	}
	// the gauges report it
	body := h.metricsText()
	if !strings.Contains(body, "exam_events_pending 100") || !strings.Contains(body, "exam_events_stream_length ") {
		t.Fatalf("stream gauges missing:\n%s", body)
	}
	h.mustInternal("POST", "/internal/events/ack", map[string]any{"ids": got[300:]})
	h.drainEvents()
}

func (h *harness) metricsText() string {
	res, err := http.Get(h.srv.URL + "/metrics")
	if err != nil {
		h.t.Fatal(err)
	}
	defer res.Body.Close()
	b, _ := io.ReadAll(res.Body)
	return string(b)
}

func TestMetricsHealthAndUnknownRun(t *testing.T) {
	h := newHarness(t)
	r := h.arm(runOpts{students: 1})
	c := startWriting(h, r, r.Students[0])
	c.sync(0, syncer.Answers{}, map[string]any{}, nil)
	c.must("POST", "/api/submit", map[string]any{})
	body := h.metricsText()
	for _, want := range []string{
		`exam_http_requests_total{route="sync",code="200"}`,
		`exam_http_requests_total{route="login",code="200"}`,
		`exam_http_requests_total{route="internal",code="200"}`,
		`exam_http_request_duration_seconds_bucket{route="submit",le="0.005"}`,
		"exam_http_inflight_requests ",
		"exam_login_queue_depth ",
		"# TYPE exam_login_queue_wait_seconds histogram",
		"exam_pace_seconds 10",
		`exam_attempts{run="` + r.Run + `",state="submitted"} 1`,
		`exam_submits_total{mode="manual"}`,
	} {
		if !strings.Contains(body, want) {
			t.Errorf("metrics missing %q", want)
		}
	}
	code, out := h.client("").do("GET", "/health", nil)
	if code != 200 || out["ok"] != true || out["redis"] != "ok" || out["version"] == nil || len(out) != 3 {
		t.Fatalf("health → %d %v", code, out)
	}
	for _, path := range []string{"/internal/runs/nope%3A999/summary", "/internal/runs/not-a-run/summary"} {
		if code, out := h.internal("GET", path, nil); code != 404 || out["error"] != "not_found" {
			t.Fatalf("%s → %d %v", path, code, out)
		}
	}
}
