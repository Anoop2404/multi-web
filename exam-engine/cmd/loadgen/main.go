// Command loadgen is a self-contained load generator for the exam engine. It arms a synthetic
// run through the internal API (meta, roster with passcodes and optional real bcrypt hashes, an
// AES-256-GCM paper of all four question types), simulates N students end to end (Sahodaya page,
// login, system check, paper prefetch, waiting room, start, diff syncs honouring next_sync_in,
// offline gaps, resyncs, submit with jitter), then claims every event through the internal API
// and checks that each student's attempt.submitted answers equal what the student last sent.
//
//	go run ./cmd/loadgen -base http://127.0.0.1:8090 -token $EXAM_ENGINE_INTERNAL_TOKEN -students 2000
package main

import (
	"context"
	"encoding/json"
	"flag"
	"fmt"
	"os"
	"os/signal"
	"sort"
	"strings"
	"sync"
	"sync/atomic"
	"text/tabwriter"
	"time"
)

type opts struct {
	base, token, code, run      string
	students, questions         int
	duration, lead, loginSpread time.Duration
	minInterval                 time.Duration
	compress                    float64
	passwordPct                 float64
	bcryptCost                  int
	concurrency                 int
	grace                       int
	abandonPct, earlyPct        float64
	offlinePct                  float64
	seed                        uint64
	keep                        bool
	jsonOut                     string
}

func main() {
	o := &opts{}
	flag.StringVar(&o.base, "base", "http://127.0.0.1:8090", "engine base URL")
	flag.StringVar(&o.token, "token", os.Getenv("EXAM_ENGINE_INTERNAL_TOKEN"), "internal API token (default $EXAM_ENGINE_INTERNAL_TOKEN)")
	flag.IntVar(&o.students, "students", 2000, "number of simulated students")
	flag.IntVar(&o.questions, "questions", 50, "questions on the paper (all four types)")
	flag.DurationVar(&o.duration, "duration", 3*time.Minute, "exam duration (wall clock; rounded up to whole minutes)")
	flag.DurationVar(&o.lead, "lead", 40*time.Second, "time from arming to starts_at (logins happen in this window)")
	flag.DurationVar(&o.loginSpread, "login-spread", 20*time.Second, "students arrive at the login page spread over this window")
	flag.Float64Var(&o.compress, "compress", 5, "time compression for client behaviour (sync interval, jitter, think time)")
	flag.DurationVar(&o.minInterval, "min-interval", 2200*time.Millisecond, "never sync faster than this (engine allows 6 per 10 s per session)")
	flag.Float64Var(&o.passwordPct, "password-percent", 10, "percent of students who log in by portal password (bcrypt)")
	flag.IntVar(&o.bcryptCost, "bcrypt-cost", 10, "bcrypt cost for the synthetic password hashes (Laravel uses 12)")
	flag.IntVar(&o.concurrency, "concurrency", 1024, "max concurrent HTTP requests (and connections) from the generator")
	flag.IntVar(&o.grace, "grace", 45, "grace_seconds for the run")
	flag.Float64Var(&o.abandonPct, "abandon-percent", 1, "percent of students who stop without submitting (the sweeper must finalise them)")
	flag.Float64Var(&o.earlyPct, "early-percent", 20, "percent of students who submit before the deadline")
	flag.Float64Var(&o.offlinePct, "offline-percent", 5, "percent of students with an offline gap (scaled 30–120 s)")
	flag.StringVar(&o.code, "sahodaya", "LOADTEST", "Sahodaya code for the synthetic run")
	flag.StringVar(&o.run, "run", "", "run id (default loadtest-<unix>:1)")
	flag.Uint64Var(&o.seed, "seed", uint64(time.Now().UnixNano()), "random seed")
	flag.BoolVar(&o.keep, "keep", false, "don't close the run at the end")
	flag.StringVar(&o.jsonOut, "json", "", "also write the report as JSON to this file")
	flag.Parse()
	if o.token == "" {
		fmt.Fprintln(os.Stderr, "loadgen: -token (or EXAM_ENGINE_INTERNAL_TOKEN) is required")
		os.Exit(2)
	}
	if o.run == "" {
		o.run = fmt.Sprintf("loadtest-%d:1", time.Now().Unix())
	}
	if o.compress <= 0 {
		o.compress = 1
	}
	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt)
	defer stop()

	st := newStats()
	a := newAPI(o.base, o.token, o.concurrency, st)
	fmt.Printf("loadgen: %d students, %d questions, duration %s, compress ×%.1f, password %.0f%% (bcrypt %d), concurrency %d\n",
		o.students, o.questions, o.duration, o.compress, o.passwordPct, o.bcryptCost, o.concurrency)
	fmt.Printf("  arming run %s (Sahodaya %s)…\n", o.run, o.code)
	ar, err := arm(ctx, a, o)
	if err != nil {
		fmt.Fprintln(os.Stderr, "loadgen:", err)
		os.Exit(1)
	}
	// drain old events so verification only sees this run's
	fmt.Printf("  starts_at %s, deadline %s\n", ar.StartsAt.Format(time.TimeOnly), ar.Deadline.Format(time.TimeOnly))

	c := &counters{}
	sm := &sim{a: a, o: o, ar: ar, c: c}
	outs := make([]outcome, len(ar.Students))
	var wg sync.WaitGroup
	var done atomic.Int64
	simStart := time.Now()
	for i, s := range ar.Students {
		wg.Add(1)
		go func() {
			defer wg.Done()
			outs[i] = sm.run(ctx, s, i)
			done.Add(1)
		}()
	}
	ticker := time.NewTicker(10 * time.Second)
	finished := make(chan struct{})
	go func() { wg.Wait(); close(finished) }()
loop:
	for {
		select {
		case <-finished:
			break loop
		case <-ticker.C:
			fmt.Printf("  … %s: %d/%d students done, %d requests, in-flight %d (peak %d)\n", time.Since(simStart).Round(time.Second),
				done.Load(), len(ar.Students), st.total.Load(), st.inflight.Load(), st.peak.Load())
		}
	}
	ticker.Stop()
	simEnd := time.Now()
	studentReqs := st.total.Load()

	// wait for the sweeper to finalise abandoned attempts (deadline + grace + a sweep)
	finalAt := ar.Deadline.Add(ar.Grace + 12*time.Second)
	if w := time.Until(finalAt); w > 0 {
		fmt.Printf("  waiting %s for the sweeper (deadline + grace)…\n", w.Round(time.Second))
		sleepCtx(ctx, w)
	}

	// claim every event through the internal API and verify answers
	fmt.Println("  claiming events…")
	submitted, typeCounts, modes, claimErr := claimAll(ctx, a, ar, len(ar.Students))
	rep := verify(ar, outs, submitted)
	rep.ClaimError = claimErr

	if !o.keep {
		a.do(ctx, "internal:close", "POST", runPath(o.run, "/close"), map[string]any{}, "", true)
	}
	// let one sweeper tick trim what we acknowledged, then read the engine's own gauges
	sleepCtx(ctx, 6*time.Second)
	engineMetrics := scrapeMetrics(ctx, a, "exam_events_stream_length", "exam_events_pending", "exam_events_trimmed_total",
		"exam_redis_errors_total", "exam_sweeper_autosubmits_total", "exam_snapshots_total", "go_memstats_heap_alloc_bytes")
	printReport(o, st, rep, typeCounts, modes, c, simStart, simEnd, studentReqs)
	if len(engineMetrics) > 0 {
		fmt.Println("engine /metrics after the run:")
		for _, l := range engineMetrics {
			fmt.Println("  " + l)
		}
	}
	if o.jsonOut != "" {
		writeJSON(o, st, rep, typeCounts, modes, simStart, simEnd, studentReqs)
	}
	if rep.LostAnswers > 0 || rep.MissingSubmits > 0 {
		os.Exit(1)
	}
}

type submittedEvent struct {
	Answers map[string]any
	Mode    string
	Receipt string
}

func claimAll(ctx context.Context, a *api, ar *armed, want int) (map[int64]submittedEvent, map[string]int, map[string]int, string) {
	submitted := map[int64]submittedEvent{}
	types := map[string]int{}
	modes := map[string]int{}
	consumer := "loadgen-" + strings.ReplaceAll(ar.Run, ":", "-")
	deadline := time.Now().Add(3 * time.Minute)
	empty := 0
	for time.Now().Before(deadline) && ctx.Err() == nil {
		r, _ := a.do(ctx, "internal:claim", "POST", "/internal/events/claim", map[string]any{"consumer": consumer, "count": 1000, "block_ms": 1000}, "", true)
		var res struct {
			Events []struct {
				ID    string          `json:"id"`
				Type  string          `json:"type"`
				Run   string          `json:"run"`
				RegID *int64          `json:"reg_id"`
				Data  json.RawMessage `json:"data"`
			} `json:"events"`
		}
		if !r.ok() || r.decode(&res) != nil {
			return submitted, types, modes, "claim: " + r.String()
		}
		if len(res.Events) == 0 {
			empty++
			if len(submitted) >= want || empty >= 5 {
				break
			}
			continue
		}
		empty = 0
		ids := make([]string, 0, len(res.Events))
		for _, e := range res.Events {
			ids = append(ids, e.ID)
			if e.Run != ar.Run {
				continue
			}
			types[e.Type]++
			if e.Type == "attempt.submitted" && e.RegID != nil {
				var d struct {
					Mode    string         `json:"mode"`
					Answers map[string]any `json:"answers"`
					Receipt string         `json:"receipt"`
				}
				_ = json.Unmarshal(e.Data, &d)
				if _, dup := submitted[*e.RegID]; !dup {
					modes[d.Mode]++
				}
				submitted[*e.RegID] = submittedEvent{Answers: d.Answers, Mode: d.Mode, Receipt: d.Receipt}
			}
		}
		if r, _ := a.do(ctx, "internal:ack", "POST", "/internal/events/ack", map[string]any{"ids": ids}, "", true); !r.ok() {
			return submitted, types, modes, "ack: " + r.String()
		}
	}
	return submitted, types, modes, ""
}

type verifyReport struct {
	Students        int
	Failed          int
	FailedReasons   map[string]int
	Abandoned       int
	SubmittedByUs   int
	SubmittedEvents int
	MissingSubmits  int
	LostAnswers     int
	ExtraAnswers    int
	ReceiptMismatch int
	AnswersChecked  int
	ClaimError      string
	Examples        []string
}

func canon(v any) string {
	switch x := v.(type) {
	case []string:
		return strings.Join(x, ",")
	case []any:
		s := make([]string, len(x))
		for i, e := range x {
			s[i] = fmt.Sprint(e)
		}
		return strings.Join(s, ",")
	case nil:
		return ""
	default:
		return fmt.Sprint(x)
	}
}

func verify(ar *armed, outs []outcome, submitted map[int64]submittedEvent) verifyReport {
	rep := verifyReport{Students: len(outs), FailedReasons: map[string]int{}, SubmittedEvents: len(submitted)}
	for _, o := range outs {
		if o.Failed != "" && o.Expected == nil {
			rep.Failed++
			reason := o.Failed
			if i := strings.IndexAny(reason, "{"); i > 0 {
				reason = reason[:i]
			}
			if len(reason) > 80 {
				reason = reason[:80]
			}
			rep.FailedReasons[reason]++
			continue
		}
		if o.Abandoned {
			rep.Abandoned++
		}
		if o.Submitted {
			rep.SubmittedByUs++
		}
		ev, ok := submitted[o.RegID]
		if !ok {
			rep.MissingSubmits++
			rep.LostAnswers += len(o.Expected)
			if len(rep.Examples) < 5 {
				rep.Examples = append(rep.Examples, fmt.Sprintf("reg %d: no attempt.submitted event (failed=%q)", o.RegID, o.Failed))
			}
			continue
		}
		if o.Receipt != "" && ev.Receipt != o.Receipt {
			rep.ReceiptMismatch++
		}
		for q, v := range o.Expected {
			rep.AnswersChecked++
			if canon(ev.Answers[q]) != canon(v) {
				rep.LostAnswers++
				if len(rep.Examples) < 5 {
					rep.Examples = append(rep.Examples, fmt.Sprintf("reg %d q%s: sent %q, engine has %q", o.RegID, q, canon(v), canon(ev.Answers[q])))
				}
			}
		}
		for q := range ev.Answers {
			if _, ok := o.Expected[q]; !ok {
				rep.ExtraAnswers++
				if len(rep.Examples) < 5 {
					rep.Examples = append(rep.Examples, fmt.Sprintf("reg %d q%s: engine has %q the student cleared/never sent", o.RegID, q, canon(ev.Answers[q])))
				}
			}
		}
	}
	rep.LostAnswers += rep.ExtraAnswers
	return rep
}

func printReport(o *opts, st *stats, rep verifyReport, types, modes map[string]int, c *counters, simStart, simEnd time.Time, studentReqs int64) {
	eps := st.report()
	fmt.Println()
	fmt.Printf("=== exam engine load test: %d students, %d questions, %s exam, compress ×%.1f ===\n", o.students, o.questions, o.duration, o.compress)
	w := tabwriter.NewWriter(os.Stdout, 0, 0, 2, ' ', tabwriter.AlignRight)
	fmt.Fprintln(w, "endpoint\tcount\terrors\tp50 ms\tp95 ms\tp99 ms\tmax ms\tstatus codes\t")
	var total, errs int
	for _, e := range eps {
		codes := make([]string, 0, len(e.Codes))
		for k, v := range e.Codes {
			codes = append(codes, fmt.Sprintf("%d×%d", k, v))
		}
		sort.Strings(codes)
		fmt.Fprintf(w, "%s\t%d\t%d\t%.1f\t%.1f\t%.1f\t%.1f\t%s\t\n", e.Endpoint, e.Count, e.Errors, e.P50, e.P95, e.P99, e.Max, strings.Join(codes, " "))
		if !strings.HasPrefix(e.Endpoint, "internal:") {
			total += e.Count
			errs += e.Errors
		}
	}
	w.Flush()
	wall := simEnd.Sub(simStart)
	fmt.Println()
	fmt.Printf("student requests: %d in %s → %.0f req/s average; peak in-flight %d\n", total, wall.Round(time.Second), float64(studentReqs)/wall.Seconds(), st.peak.Load())
	fmt.Printf("error rate: %d / %d = %.3f%%\n", errs, total, 100*float64(errs)/float64(max(total, 1)))
	acts := []string{}
	c.actions.Range(func(k, v any) bool {
		acts = append(acts, fmt.Sprintf("%s=%d", k, v.(*atomic.Int64).Load()))
		return true
	})
	sort.Strings(acts)
	fmt.Printf("sync actions: %s; resyncs %d (stale-rev probes %d); offline gaps %d; retries %d; 503 busy %d; max next_sync_in %ds\n",
		strings.Join(acts, " "), c.resyncs.Load(), c.staleRevs.Load(), c.offlineGaps.Load(), c.retries.Load(), c.busy.Load(), c.maxPace.Load())
	tl := []string{}
	for k, v := range types {
		tl = append(tl, fmt.Sprintf("%s=%d", k, v))
	}
	sort.Strings(tl)
	fmt.Printf("events claimed for this run: %s\n", strings.Join(tl, " "))
	ml := []string{}
	for k, v := range modes {
		ml = append(ml, fmt.Sprintf("%s=%d", k, v))
	}
	sort.Strings(ml)
	fmt.Printf("submit modes: %s\n", strings.Join(ml, " "))
	fmt.Printf("verification: %d students, %d failed before writing, %d abandoned (sweeper), %d submitted by client, %d attempt.submitted events\n",
		rep.Students, rep.Failed, rep.Abandoned, rep.SubmittedByUs, rep.SubmittedEvents)
	for k, v := range rep.FailedReasons {
		fmt.Printf("  failed: %d × %s\n", v, k)
	}
	fmt.Printf("answers checked: %d; LOST ANSWERS: %d (missing submits %d, extra %d, receipt mismatches %d)\n",
		rep.AnswersChecked, rep.LostAnswers, rep.MissingSubmits, rep.ExtraAnswers, rep.ReceiptMismatch)
	for _, ex := range rep.Examples {
		fmt.Println("  e.g.", ex)
	}
	if rep.ClaimError != "" {
		fmt.Println("claim error:", rep.ClaimError)
	}
	var sync epReport
	for _, e := range eps {
		if e.Endpoint == "POST /api/sync" {
			sync = e
		}
	}
	pass := sync.P95 < 300 && 100*float64(errs)/float64(max(total, 1)) < 0.1 && rep.LostAnswers == 0 && rep.MissingSubmits == 0
	fmt.Printf("targets (p95 sync < 300 ms, errors < 0.1%%, 0 lost answers): p95 sync %.1f ms → %s\n", sync.P95, map[bool]string{true: "PASS", false: "FAIL"}[pass])
}

func writeJSON(o *opts, st *stats, rep verifyReport, types, modes map[string]int, simStart, simEnd time.Time, studentReqs int64) {
	b, _ := json.MarshalIndent(map[string]any{
		"students": o.students, "questions": o.questions, "duration": o.duration.String(), "compress": o.compress,
		"endpoints": st.report(), "peak_inflight": st.peak.Load(), "student_requests": studentReqs,
		"wall_seconds": simEnd.Sub(simStart).Seconds(), "verification": rep, "event_types": types, "submit_modes": modes,
	}, "", "  ")
	_ = os.WriteFile(o.jsonOut, b, 0o644)
}

// scrapeMetrics returns the /metrics lines whose names start with one of the prefixes.
func scrapeMetrics(ctx context.Context, a *api, prefixes ...string) []string {
	r, _ := a.do(ctx, "internal:metrics", "GET", "/metrics", nil, "", false)
	if !r.ok() {
		return nil
	}
	var out []string
	for _, line := range strings.Split(string(r.Body), "\n") {
		if strings.HasPrefix(line, "#") {
			continue
		}
		for _, p := range prefixes {
			if strings.HasPrefix(line, p) {
				out = append(out, line)
				break
			}
		}
	}
	return out
}
