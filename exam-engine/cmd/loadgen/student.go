package main

import (
	"context"
	"fmt"
	mrand "math/rand/v2"
	"net/url"
	"sort"
	"strconv"
	"sync"
	"sync/atomic"
	"time"

	"github.com/sahodaya/exam-engine/internal/syncer"
)

type stateResp struct {
	State      string `json:"state"`
	ServerTime string `json:"server_time"`
	StartsAt   string `json:"starts_at"`
	Deadline   string `json:"deadline"`
	BundleURL  string `json:"bundle_url"`
	PaperKey   string `json:"paper_key"`
	Rev        int64  `json:"rev"`
	NextSyncIn int    `json:"next_sync_in"`
	Receipt    string `json:"receipt"`
}

type syncResp struct {
	Rev        int64  `json:"rev"`
	ServerTime string `json:"server_time"`
	Deadline   string `json:"deadline"`
	NextSyncIn int    `json:"next_sync_in"`
	Action     string `json:"action"`
}

type submitResp struct {
	Submitted bool   `json:"submitted"`
	Receipt   string `json:"receipt"`
	Mode      string `json:"mode"`
}

// outcome is what one simulated student ends with.
type outcome struct {
	RegID     int64
	Expected  syncer.Answers // what the engine must hold as final answers
	Receipt   string
	Submitted bool
	Abandoned bool
	Failed    string
}

type counters struct {
	resyncs, staleRevs, offlineGaps, lockWaits, retries, busy, maxPace atomic.Int64
	actions                                                            sync.Map // action → *atomic.Int64
}

func (c *counters) action(a string) {
	v, _ := c.actions.LoadOrStore(a, new(atomic.Int64))
	v.(*atomic.Int64).Add(1)
}

type sim struct {
	a  *api
	o  *opts
	ar *armed
	c  *counters
}

func parseT(s string) time.Time {
	t, _ := time.Parse(time.RFC3339, s)
	return t
}

func sleepCtx(ctx context.Context, d time.Duration) bool {
	if d <= 0 {
		return ctx.Err() == nil
	}
	t := time.NewTimer(d)
	defer t.Stop()
	select {
	case <-ctx.Done():
		return false
	case <-t.C:
		return true
	}
}

func retryAfter(r resp) time.Duration {
	return 2 * time.Second
}

// pacer keeps one student's sync/resync/submit calls within the engine's budget (6 per 10 s
// per session, CONTRACT §4) the way a well-behaved client does.
type pacer struct{ times []time.Time }

func (p *pacer) wait(ctx context.Context) {
	now := time.Now()
	keep := p.times[:0]
	for _, t := range p.times {
		if now.Sub(t) < 10*time.Second {
			keep = append(keep, t)
		}
	}
	p.times = keep
	if len(p.times) >= 6 {
		sleepCtx(ctx, p.times[0].Add(10*time.Second+50*time.Millisecond).Sub(now))
		p.times = p.times[1:]
	}
	p.times = append(p.times, time.Now())
}

func diff(local, acked syncer.Answers) map[string]any {
	ch := map[string]any{}
	for k, v := range local {
		if w, ok := acked[k]; !ok || fmt.Sprint(w) != fmt.Sprint(v) {
			ch[k] = v
		}
	}
	for k := range acked {
		if _, ok := local[k]; !ok {
			ch[k] = nil
		}
	}
	return ch
}

func randomValue(rng *mrand.Rand, q *question) any {
	switch q.Type {
	case "single":
		return q.Options[rng.IntN(len(q.Options))].Key
	case "true_false":
		return q.Options[rng.IntN(2)].Key
	case "multiple":
		n := 1 + rng.IntN(3)
		perm := rng.Perm(len(q.Options))[:n]
		keys := make([]string, n)
		for i, p := range perm {
			keys[i] = q.Options[p].Key
		}
		sort.Strings(keys)
		return keys
	default:
		vals := []string{"12.5", "-3", "7", "0.25", "144", "3.14159"}
		return vals[rng.IntN(len(vals))]
	}
}

func (sm *sim) run(ctx context.Context, s *simStudent, idx int) outcome {
	rng := mrand.New(mrand.NewPCG(sm.o.seed, uint64(idx)+1))
	out := outcome{RegID: s.RegID}
	o := sm.o
	scale := func(d time.Duration) time.Duration { return time.Duration(float64(d) / o.compress) }

	// 1. arrive at the login page (spread over the login window)
	if !sleepCtx(ctx, time.Duration(rng.Float64()*float64(o.loginSpread))) {
		out.Failed = "cancelled"
		return out
	}
	sm.a.do(ctx, "GET /api/sahodaya", "GET", "/api/sahodaya/"+url.PathEscape(sm.ar.Code), nil, "", false)

	// 2. log in (bounded retries on busy / rate limits / network)
	var cookie string
	var body map[string]any
	if s.ByPass {
		body = map[string]any{"sahodaya": sm.ar.Code, "method": "password", "username": s.Username, "password": s.Password}
	} else {
		body = map[string]any{"sahodaya": sm.ar.Code, "method": "passcode", "hall_ticket": s.Ticket, "passcode": s.Passcode}
	}
	ep := "POST /api/login (passcode)"
	if s.ByPass {
		ep = "POST /api/login (password)"
	}
	for i := 0; ; i++ {
		r, ck := sm.a.do(ctx, ep, "POST", "/api/login", body, "", false)
		if r.ok() {
			cookie = ck
			break
		}
		if r.Code == 503 {
			sm.c.busy.Add(1)
		}
		if i >= 10 || r.Code == 401 || r.Code == 403 || r.Code == 409 || ctx.Err() != nil {
			out.Failed = "login: " + r.String()
			return out
		}
		sm.c.retries.Add(1)
		sleepCtx(ctx, retryAfter(r)+time.Duration(rng.IntN(1000))*time.Millisecond)
	}

	// 3. system check, state, paper prefetch
	sm.a.do(ctx, "POST /api/system-check", "POST", "/api/system-check", map[string]any{
		"user_agent": "loadgen", "screen": "390x844", "online": true, "latency_ms": 40, "storage_ok": true, "crypto_ok": true}, cookie, false)
	var st stateResp
	r, _ := sm.a.do(ctx, "GET /api/state", "GET", "/api/state", nil, cookie, false)
	if err := r.decode(&st); err != nil || !r.ok() {
		out.Failed = "state: " + r.String()
		return out
	}
	offset := parseT(st.ServerTime).Sub(time.Now()) // server − local
	var bundle struct {
		IV, Ciphertext string
	}
	for i := 0; i < 5; i++ {
		r, _ = sm.a.do(ctx, "GET /api/bundle", "GET", st.BundleURL, nil, "", false)
		if r.ok() && r.decode(&bundle) == nil {
			break
		}
		sleepCtx(ctx, time.Second)
	}
	if bundle.IV == "" {
		out.Failed = "bundle: " + r.String()
		return out
	}

	// 4. waiting room: poll state until the start, then start with 0–5 s jitter (scaled)
	startsLocal := parseT(st.StartsAt).Add(-offset)
	for time.Until(startsLocal) > 0 {
		wait := min(time.Until(startsLocal), scale(10*time.Second))
		if !sleepCtx(ctx, wait) {
			out.Failed = "cancelled"
			return out
		}
		if time.Until(startsLocal) > 0 {
			sm.a.do(ctx, "GET /api/state", "GET", "/api/state", nil, cookie, false)
		}
	}
	sleepCtx(ctx, time.Duration(rng.Float64()*float64(scale(5*time.Second))))
	for i := 0; ; i++ {
		r, _ = sm.a.do(ctx, "POST /api/start", "POST", "/api/start", map[string]any{}, cookie, false, 409)
		if r.ok() && r.decode(&st) == nil && st.PaperKey != "" {
			break
		}
		if i >= 15 || ctx.Err() != nil {
			out.Failed = "start: " + r.String()
			return out
		}
		sm.c.retries.Add(1)
		sleepCtx(ctx, 500*time.Millisecond+time.Duration(rng.IntN(500))*time.Millisecond)
	}
	paper, err := decryptPaper(st.PaperKey, bundle.IV, bundle.Ciphertext)
	if err != nil || len(paper.Questions) != o.questions {
		out.Failed = fmt.Sprintf("paper: %v", err)
		return out
	}
	deadlineLocal := parseT(st.Deadline).Add(-offset)
	startedLocal := time.Now()
	dur := deadlineLocal.Sub(startedLocal)

	// behaviour plan
	abandon := rng.Float64()*100 < o.abandonPct
	early := !abandon && rng.Float64()*100 < o.earlyPct
	var submitAt, abandonAt time.Time
	switch {
	case abandon:
		abandonAt = startedLocal.Add(time.Duration((0.3 + 0.6*rng.Float64()) * float64(dur)))
	case early:
		submitAt = startedLocal.Add(time.Duration((0.5 + 0.45*rng.Float64()) * float64(dur)))
	default:
		submitAt = deadlineLocal.Add(time.Duration(rng.Float64() * float64(scale(30*time.Second))))
	}
	var gapFrom, gapTo time.Time
	if rng.Float64()*100 < o.offlinePct {
		gapFrom = startedLocal.Add(time.Duration((0.2 + 0.5*rng.Float64()) * float64(dur)))
		gapTo = gapFrom.Add(scale(time.Duration(30+rng.IntN(90)) * time.Second))
		sm.c.offlineGaps.Add(1)
	}

	local := syncer.Answers{}
	acked := syncer.Answers{}
	rev := st.Rev
	next := st.NextSyncIn
	var pendingEvents []map[string]any
	wasOffline := false
	var pace pacer

	resync := func() bool {
		sm.c.resyncs.Add(1)
		pace.wait(ctx)
		r, _ := sm.a.do(ctx, "POST /api/resync", "POST", "/api/resync", map[string]any{"answers": local, "flagged": []string{}}, cookie, false)
		var sr syncResp
		if r.ok() && r.decode(&sr) == nil && (sr.Action == "none" || sr.Action == "submit") {
			acked = syncer.Clone(local)
			rev = sr.Rev
			return true
		}
		return false
	}

	// 5. writing
	for {
		if abandon && time.Now().After(abandonAt) {
			out.Abandoned = true
			out.Expected = syncer.Clone(acked)
			return out
		}
		if !submitAt.IsZero() && time.Now().After(submitAt) {
			break
		}
		interval := scale(time.Duration(next)*time.Second + time.Duration(rng.Float64()*0.2*float64(time.Duration(next)*time.Second)))
		interval = max(interval, o.minInterval)
		wake := time.Now().Add(interval)
		if !submitAt.IsZero() && wake.After(submitAt) {
			wake = submitAt
		}
		if !sleepCtx(ctx, time.Until(wake)) {
			out.Failed = "cancelled"
			return out
		}
		now := time.Now()
		if now.Before(deadlineLocal) {
			// answer something (the UI locks at the deadline)
			k := 0
			switch p := rng.Float64(); {
			case p < 0.45:
				k = 1
			case p < 0.60:
				k = 2
			case p < 0.65:
				k = 3
			}
			for j := 0; j < k; j++ {
				q := &paper.Questions[rng.IntN(len(paper.Questions))]
				id := strconv.FormatInt(q.ID, 10)
				if _, has := local[id]; has && rng.Float64() < 0.08 {
					delete(local, id)
				} else {
					local[id] = randomValue(rng, q)
				}
			}
			if rng.Float64() < 0.01 {
				pendingEvents = append(pendingEvents, map[string]any{"type": "tab_hidden", "at": now.Add(offset).UTC().Format(time.RFC3339)})
			}
		}
		if !gapFrom.IsZero() && now.After(gapFrom) && now.Before(gapTo) {
			wasOffline = true
			continue // offline: answers pile up locally
		}
		if wasOffline {
			wasOffline = false
			pendingEvents = append(pendingEvents, map[string]any{"type": "offline", "at": gapFrom.Add(offset).UTC().Format(time.RFC3339), "seconds": int(gapTo.Sub(gapFrom).Seconds())})
		}
		if !submitAt.IsZero() && time.Now().After(submitAt) {
			break
		}
		if rng.Float64() < 0.003 {
			if resync() {
				continue
			}
		}
		base := rev
		if rev > 0 && rng.Float64() < 0.005 {
			base = rev - 1 // a stale client: the engine must answer resync
			sm.c.staleRevs.Add(1)
		}
		changes := diff(local, acked)
		pace.wait(ctx)
		r, _ := sm.a.do(ctx, "POST /api/sync", "POST", "/api/sync", map[string]any{
			"base_rev": base, "changes": changes, "checksum": syncer.Checksum(local), "events": pendingEvents}, cookie, false)
		var sr syncResp
		if !r.ok() || r.decode(&sr) != nil {
			if r.Code == 429 {
				sm.c.retries.Add(1)
			}
			continue // keep the diff; retry next tick
		}
		pendingEvents = nil
		sm.c.action(sr.Action)
		if sr.NextSyncIn > 0 {
			next = sr.NextSyncIn
			for {
				m := sm.c.maxPace.Load()
				if int64(next) <= m || sm.c.maxPace.CompareAndSwap(m, int64(next)) {
					break
				}
			}
		}
		switch sr.Action {
		case "none", "submit":
			if len(changes) == 0 || sr.Rev != rev {
				acked = syncer.Clone(local)
			}
			rev = sr.Rev
			if sr.Action == "submit" && submitAt.IsZero() {
				submitAt = time.Now()
			}
		case "resync":
			resync()
		case "lock":
			sm.c.lockWaits.Add(1)
		}
	}

	// 6. submit (retry; on 409 resync first)
	for i := 0; i < 10; i++ {
		changes := diff(local, acked)
		pace.wait(ctx)
		r, _ := sm.a.do(ctx, "POST /api/submit", "POST", "/api/submit", map[string]any{
			"base_rev": rev, "changes": changes, "checksum": syncer.Checksum(local)}, cookie, false, 409)
		var sr submitResp
		if r.ok() && r.decode(&sr) == nil && sr.Submitted {
			out.Submitted = true
			out.Receipt = sr.Receipt
			out.Expected = syncer.Clone(local)
			break
		}
		if r.Code == 409 && r.errCode() == "invalid" {
			resync()
			continue
		}
		sm.c.retries.Add(1)
		sleepCtx(ctx, time.Second)
	}
	if !out.Submitted {
		out.Failed = "submit failed"
		out.Expected = syncer.Clone(acked)
		return out
	}
	sm.a.do(ctx, "GET /api/result", "GET", "/api/result", nil, cookie, false)
	return out
}
