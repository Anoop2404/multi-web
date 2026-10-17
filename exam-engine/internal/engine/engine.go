// Package engine is the core of the exam engine: run-context caching, sessions and the student
// operations (login, state, start, sync, resync, submit, result). The HTTP layer in package
// server is a thin adapter over it; package admin builds the internal API on the same Engine.
package engine

import (
	"context"
	"crypto/rand"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"errors"
	"hash/maphash"
	"log/slog"
	"math"
	"net/url"
	"strconv"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	"github.com/redis/go-redis/v9"

	"github.com/sahodaya/exam-engine/internal/attempt"
	"github.com/sahodaya/exam-engine/internal/config"
	"github.com/sahodaya/exam-engine/internal/entry"
	"github.com/sahodaya/exam-engine/internal/events"
	"github.com/sahodaya/exam-engine/internal/httpx"
	"github.com/sahodaya/exam-engine/internal/metrics"
	"github.com/sahodaya/exam-engine/internal/model"
	"github.com/sahodaya/exam-engine/internal/paper"
	"github.com/sahodaya/exam-engine/internal/ratelimit"
	"github.com/sahodaya/exam-engine/internal/store"
)

// Metrics the engine records.
type Metrics struct {
	Reg            *metrics.Registry
	Requests       *metrics.CounterVec
	Latency        *metrics.HistogramVec
	Logins         *metrics.CounterVec
	SyncActions    *metrics.CounterVec
	Submits        *metrics.CounterVec
	SweeperSubmits *metrics.CounterVec
	Snapshots      *metrics.CounterVec
	RedisErrors    *metrics.CounterVec
	EventsClaimed  *metrics.CounterVec
	EventsAcked    *metrics.CounterVec
	EventsTrimmed  *metrics.CounterVec
	LoginWait      *metrics.HistogramVec
}

func newMetrics() *Metrics {
	r := metrics.New()
	return &Metrics{
		Reg:            r,
		Requests:       r.Counter("exam_http_requests_total", "HTTP requests by route and status code.", "route", "code"),
		Latency:        r.Histogram("exam_http_request_duration_seconds", "HTTP request latency by route.", metrics.DefaultBuckets, "route"),
		Logins:         r.Counter("exam_logins_total", "Login attempts by method and result.", "method", "result"),
		SyncActions:    r.Counter("exam_sync_actions_total", "Sync/resync responses by action.", "action"),
		Submits:        r.Counter("exam_submits_total", "Finalised attempts by mode.", "mode"),
		SweeperSubmits: r.Counter("exam_sweeper_autosubmits_total", "Attempts auto-submitted or force-finalised by the sweeper.", "mode"),
		Snapshots:      r.Counter("exam_snapshots_total", "attempt.snapshot events emitted."),
		RedisErrors:    r.Counter("exam_redis_errors_total", "Redis errors by operation.", "op"),
		EventsClaimed:  r.Counter("exam_events_claimed_total", "Events returned by /internal/events/claim."),
		EventsAcked:    r.Counter("exam_events_acked_total", "Events acknowledged."),
		EventsTrimmed:  r.Counter("exam_events_trimmed_total", "Delivered+acknowledged events trimmed from ex:events."),
		LoginWait: r.Histogram("exam_login_queue_wait_seconds", "Time a password login waited for a bcrypt worker.",
			[]float64{0.001, 0.01, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10, 20, 30}),
	}
}

// RunCtx is a cached view of a run's meta and control state.
type RunCtx struct {
	Run           string
	K             store.Keys
	Meta          *model.Meta // nil when the run doesn't exist
	Closed        bool
	ClosedAt      time.Time
	Pace          int
	Msgs          []model.Message
	Schema        model.Schema
	BundleVersion int
	MaxExtra      int  // largest per-candidate extra minutes
	RunExtra      int  // run-level extra minutes (POST /internal/runs/{run}/extend)
	Hold          bool // POST /internal/runs/{run}/hold: no auto-submit, past-deadline students are locked
}

// Extra is the largest total extra time any attempt can have.
func (rc *RunCtx) Extra() int { return rc.MaxExtra + rc.RunExtra }

// Exists reports whether the run is armed.
func (rc *RunCtx) Exists() bool { return rc != nil && rc.Meta != nil }

// State is the run state by clock.
func (rc *RunCtx) State(now time.Time) string {
	return attempt.RunState(rc.Meta, rc.Extra(), rc.Closed, now)
}

// GraceMs is grace_seconds in ms.
func (rc *RunCtx) GraceMs() int64 { return int64(rc.Meta.GraceSeconds) * 1000 }

type runEntry struct {
	mu  sync.Mutex
	val atomic.Pointer[cachedRun]
}

type cachedRun struct {
	rc *RunCtx
	at time.Time
}

// Session is what an exam_session cookie maps to.
type Session struct {
	Token  string `json:"-"`
	Run    string `json:"run"`
	RegID  int64  `json:"reg_id"`
	Device string `json:"device_id"`
}

type sessEntry struct {
	s   Session
	exp time.Time
}

type sessShard struct {
	mu sync.Mutex
	m  map[string]sessEntry
}

const sessShards = 64

// Engine is the shared core.
type Engine struct {
	Cfg      *config.Config
	St       *store.Store
	Log      *slog.Logger
	Now      func() time.Time
	M        *Metrics
	Pool     *entry.Pool
	Bundles  *paper.Cache
	Inflight atomic.Int64

	SyncLimiter  *ratelimit.Limiter
	LoginLimiter *ratelimit.Limiter
	PinLimiter   *ratelimit.Limiter

	runs  sync.Map // run → *runEntry
	codes sync.Map // code → *codeEntry

	streamCache  atomic.Pointer[streamSnap]
	attemptCache atomic.Pointer[attemptSnap]
	seed         maphash.Seed
	sessions     [sessShards]sessShard
}

type codeEntry struct {
	runs []string
	at   time.Time
}

// New builds the engine.
func New(cfg *config.Config, st *store.Store, log *slog.Logger) *Engine {
	st.MaxLen = cfg.EventsMaxLen
	e := &Engine{
		Cfg:          cfg,
		St:           st,
		Log:          log,
		Now:          time.Now,
		M:            newMetrics(),
		Pool:         entry.NewPool(cfg.BcryptWorkers, cfg.BcryptMaxWait, cfg.BcryptMaxQueue),
		Bundles:      paper.NewCache(512 << 20),
		SyncLimiter:  ratelimit.New(6, 10*time.Second),
		LoginLimiter: ratelimit.New(10, time.Minute),
		PinLimiter:   ratelimit.New(5, 10*time.Minute),
		seed:         maphash.MakeSeed(),
	}
	for i := range e.sessions {
		e.sessions[i].m = make(map[string]sessEntry)
	}
	e.registerGauges()
	return e
}

func (e *Engine) registerGauges() {
	r := e.M.Reg
	e.Pool.OnWait = func(d time.Duration) { e.M.LoginWait.Observe(d.Seconds()) }
	r.GaugeFunc("exam_http_inflight_requests", "Student API requests in flight on this node (drives automatic pacing).", func() float64 { return float64(e.Inflight.Load()) })
	r.GaugeFunc("exam_pace_seconds", "Automatic next_sync_in on this node at the default 10 s interval (10/20/30/60).", func() float64 {
		return float64(e.autoPace(10))
	})
	r.GaugeFunc("exam_login_queue_depth", "Password logins waiting for a bcrypt worker.", func() float64 { w, _, _, _, _ := e.Pool.Stats(); return float64(w) })
	r.GaugeFunc("exam_bcrypt_busy", "Password checks running.", func() float64 { _, b, _, _, _ := e.Pool.Stats(); return float64(b) })
	r.GaugeFunc("exam_bcrypt_workers", "Password check workers.", func() float64 { _, _, _, _, n := e.Pool.Stats(); return float64(n) })
	r.GaugeFunc("exam_bcrypt_checks_total", "Password checks run.", func() float64 { _, _, c, _, _ := e.Pool.Stats(); return float64(c) })
	r.GaugeFunc("exam_bcrypt_busy_rejections_total", "Logins refused with 503 busy.", func() float64 { _, _, _, t, _ := e.Pool.Stats(); return float64(t) })
	r.GaugeFunc("exam_redis_pool_total_conns", "Redis pool connections.", func() float64 { return float64(e.St.R.PoolStats().TotalConns) })
	r.GaugeFunc("exam_redis_pool_idle_conns", "Redis idle connections.", func() float64 { return float64(e.St.R.PoolStats().IdleConns) })
	r.GaugeFunc("exam_redis_pool_timeouts_total", "Redis pool wait timeouts.", func() float64 { return float64(e.St.R.PoolStats().Timeouts) })
	r.GaugeFunc("exam_events_stream_length", "Entries in ex:events (delivered+acked ones are trimmed by the sweeper leader).", func() float64 {
		s := e.streamStats()
		return s.length
	})
	r.GaugeFunc("exam_events_pending", "Events delivered to Laravel but not yet acknowledged.", func() float64 {
		s := e.streamStats()
		return s.pending
	})
	r.GaugeVec("exam_attempts", "Attempts by run and state, from the Redis aggregates (identical on every node: use max, not sum).",
		[]string{"run", "state"}, func(emit func(float64, ...string)) {
			for _, row := range e.attemptGauges() {
				emit(row.v, row.run, row.state)
			}
		})
}

type streamSnap struct {
	length, pending float64
	at              time.Time
}

type attemptRow struct {
	run, state string
	v          float64
}

type attemptSnap struct {
	rows []attemptRow
	at   time.Time
}

// streamStats reads XLEN/XPENDING at most once a second (scrapes may come from several systems).
func (e *Engine) streamStats() streamSnap {
	if s := e.streamCache.Load(); s != nil && time.Since(s.at) < time.Second {
		return *s
	}
	ctx, cancel := context.WithTimeout(context.Background(), time.Second)
	defer cancel()
	l, p, err := events.Stats(ctx, e.St)
	snap := streamSnap{length: float64(l), pending: float64(p), at: time.Now()}
	if err != nil {
		snap.length, snap.pending = math.NaN(), math.NaN()
	}
	e.streamCache.Store(&snap)
	return snap
}

// attemptGauges reads the per-run aggregates of open runs (cached for 5 s).
func (e *Engine) attemptGauges() []attemptRow {
	if s := e.attemptCache.Load(); s != nil && time.Since(s.at) < 5*time.Second {
		return s.rows
	}
	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
	defer cancel()
	var rows []attemptRow
	runs, err := e.St.R.SMembers(ctx, store.KeyRuns).Result()
	if err == nil {
		now := e.Now()
		cutoff := strconv.FormatInt(now.Add(-e.Cfg.OfflineAfter).UnixMilli(), 10)
		for _, run := range runs {
			k := store.K(run)
			pipe := e.St.R.Pipeline()
			agg := pipe.HMGet(ctx, k.Agg(), "r", "ci", "st", "sub", "lk")
			off := pipe.ZCount(ctx, k.Seen(), "-inf", "("+cutoff)
			closed := pipe.HGet(ctx, k.Ctl(), "closed")
			if _, err := pipe.Exec(ctx); err != nil && !errors.Is(err, redis.Nil) {
				continue
			}
			if closed.Val() == "1" {
				continue
			}
			v := agg.Val()
			n := func(i int) float64 { f, _ := strconv.ParseFloat(asString(v[i]), 64); return f }
			r, ci, st, sub, lk := n(0), n(1), n(2), n(3), n(4)
			rows = append(rows,
				attemptRow{run, "roster", r},
				attemptRow{run, "checked_in", ci},
				attemptRow{run, "waiting", math.Max(ci-st, 0)},
				attemptRow{run, "writing", math.Max(st-sub, 0)},
				attemptRow{run, "submitted", sub},
				attemptRow{run, "locked", math.Max(lk, 0)},
				attemptRow{run, "offline", float64(off.Val())},
			)
		}
	}
	e.attemptCache.Store(&attemptSnap{rows: rows, at: time.Now()})
	return rows
}

func (e *Engine) autoPace(base int) int {
	t := e.Cfg.PaceThresholds
	in := e.Inflight.Load()
	switch {
	case t[2] > 0 && in > int64(t[2]):
		return max(base, 60)
	case t[1] > 0 && in > int64(t[1]):
		return max(base, 30)
	case t[0] > 0 && in > int64(t[0]):
		return max(base, 20)
	}
	return base
}

// NextSyncIn applies the pace override or the automatic level (CONTRACT §7).
func (e *Engine) NextSyncIn(rc *RunCtx) int {
	if rc.Pace > 0 {
		return rc.Pace
	}
	return e.autoPace(rc.Meta.SyncIntervalSeconds)
}

// GC drops stale cache entries and rate-limit windows; run it periodically.
func (e *Engine) GC() {
	now := time.Now()
	e.runs.Range(func(k, v any) bool {
		re := v.(*runEntry)
		if c := re.val.Load(); c != nil && now.Sub(c.at) > 5*time.Minute {
			e.runs.Delete(k)
		}
		return true
	})
	e.codes.Range(func(k, v any) bool {
		if now.Sub(v.(*codeEntry).at) > 5*time.Minute {
			e.codes.Delete(k)
		}
		return true
	})
	for i := range e.sessions {
		s := &e.sessions[i]
		s.mu.Lock()
		for k, v := range s.m {
			if now.After(v.exp) {
				delete(s.m, k)
			}
		}
		s.mu.Unlock()
	}
	e.SyncLimiter.GC(now)
	e.LoginLimiter.GC(now)
	e.PinLimiter.GC(now)
}

// StartBackground runs cache GC until ctx ends.
func (e *Engine) StartBackground(ctx context.Context) {
	go func() {
		t := time.NewTicker(time.Minute)
		defer t.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-t.C:
				e.GC()
			}
		}
	}()
}

// InvalidateRun forgets the cached run context (after an admin write on this node).
func (e *Engine) InvalidateRun(run string) {
	e.runs.Delete(run)
}

// InvalidateCode forgets the cached run list of a Sahodaya code.
func (e *Engine) InvalidateCode(code string) { e.codes.Delete(code) }

// Run returns the (cached) run context. A missing run returns a RunCtx with Meta == nil.
func (e *Engine) Run(ctx context.Context, run string) (*RunCtx, error) {
	v, _ := e.runs.LoadOrStore(run, &runEntry{})
	re := v.(*runEntry)
	ttl := e.Cfg.RunCacheTTL
	if c := re.val.Load(); c != nil && ttl > 0 && time.Since(c.at) < ttl {
		return c.rc, nil
	}
	re.mu.Lock()
	defer re.mu.Unlock()
	if c := re.val.Load(); c != nil && ttl > 0 && time.Since(c.at) < ttl {
		return c.rc, nil
	}
	rc, err := e.LoadRun(ctx, run)
	if err != nil {
		return nil, err
	}
	re.val.Store(&cachedRun{rc: rc, at: time.Now()})
	return rc, nil
}

// LoadRun reads a run context from Redis (no cache).
func (e *Engine) LoadRun(ctx context.Context, run string) (*RunCtx, error) {
	k := store.K(run)
	pipe := e.St.R.Pipeline()
	meta := pipe.Get(ctx, k.Meta())
	ctl := pipe.HMGet(ctx, k.Ctl(), "closed", "closed_at", "pace", "xm", "hold")
	msgs := pipe.LRange(ctx, k.Msgs(), -20, -1)
	qs := pipe.Get(ctx, k.Schema())
	agg := pipe.HMGet(ctx, k.Agg(), "bv", "mx")
	if _, err := pipe.Exec(ctx); err != nil && !errors.Is(err, redis.Nil) {
		e.M.RedisErrors.Inc("load_run")
		return nil, err
	}
	rc := &RunCtx{Run: run, K: k}
	raw, err := meta.Result()
	if errors.Is(err, redis.Nil) {
		return rc, nil
	}
	if err != nil {
		return nil, err
	}
	m, err := model.ParseMeta([]byte(raw))
	if err != nil {
		return nil, err
	}
	rc.Meta = m
	if cv, err := ctl.Result(); err == nil {
		rc.Closed = asString(cv[0]) == "1"
		if ms, _ := strconv.ParseInt(asString(cv[1]), 10, 64); ms > 0 {
			rc.ClosedAt = time.UnixMilli(ms)
		}
		rc.Pace, _ = strconv.Atoi(asString(cv[2]))
		rc.RunExtra, _ = strconv.Atoi(asString(cv[3]))
		rc.Hold = asString(cv[4]) == "1"
	}
	if ms, err := msgs.Result(); err == nil {
		for _, s := range ms {
			var mm model.Message
			if json.Unmarshal([]byte(s), &mm) == nil {
				rc.Msgs = append(rc.Msgs, mm)
			}
		}
	}
	if s, err := qs.Result(); err == nil && s != "" {
		var sc model.Schema
		if json.Unmarshal([]byte(s), &sc) == nil {
			rc.Schema = sc
		}
	}
	if rc.Schema == nil {
		rc.Schema = paper.SchemaFromTypes(m.Questions)
	}
	if av, err := agg.Result(); err == nil {
		rc.BundleVersion, _ = strconv.Atoi(asString(av[0]))
		rc.MaxExtra, _ = strconv.Atoi(asString(av[1]))
	}
	if rc.BundleVersion == 0 {
		rc.BundleVersion = m.BundleVersion
	}
	return rc, nil
}

func asString(v any) string {
	switch x := v.(type) {
	case string:
		return x
	case nil:
		return ""
	default:
		return ""
	}
}

// RunsForCode lists the runs armed for a Sahodaya code (cached like run contexts).
func (e *Engine) RunsForCode(ctx context.Context, code string) ([]string, error) {
	if v, ok := e.codes.Load(code); ok {
		ce := v.(*codeEntry)
		if e.Cfg.RunCacheTTL > 0 && time.Since(ce.at) < e.Cfg.RunCacheTTL {
			return ce.runs, nil
		}
	}
	runs, err := e.St.R.SMembers(ctx, store.SahodayaKey(code)).Result()
	if err != nil {
		e.M.RedisErrors.Inc("smembers")
		return nil, err
	}
	e.codes.Store(code, &codeEntry{runs: runs, at: time.Now()})
	return runs, nil
}

// BundleURL is where students fetch the encrypted paper.
func (e *Engine) BundleURL(run string, version int) string {
	esc := strings.ReplaceAll(url.PathEscape(run), ":", "%3A")
	return e.Cfg.BundleBaseURL + "/" + esc + "/" + strconv.Itoa(version)
}

// ---- sessions ----

// SessionTTL keeps a session until a day after the exam's last possible end.
func (e *Engine) SessionTTL(rc *RunCtx, now time.Time) time.Duration {
	end := attempt.RunEnd(rc.Meta, rc.Extra()+60).Add(24 * time.Hour)
	ttl := end.Sub(now)
	if ttl < time.Hour {
		ttl = time.Hour
	}
	return ttl
}

// NewToken returns a random 32-byte base64url token.
func NewToken() string {
	var b [32]byte
	_, _ = rand.Read(b[:])
	return base64.RawURLEncoding.EncodeToString(b[:])
}

// NewDeviceID returns a random device id.
func NewDeviceID() string {
	var b [12]byte
	_, _ = rand.Read(b[:])
	return hex.EncodeToString(b[:])
}

func validToken(t string) bool {
	if len(t) != 43 {
		return false
	}
	for i := 0; i < len(t); i++ {
		c := t[i]
		if !(c >= 'A' && c <= 'Z' || c >= 'a' && c <= 'z' || c >= '0' && c <= '9' || c == '-' || c == '_') {
			return false
		}
	}
	return true
}

func (e *Engine) shard(token string) *sessShard {
	return &e.sessions[maphash.String(e.seed, token)%sessShards]
}

// CacheSession remembers a session locally.
func (e *Engine) CacheSession(s Session) {
	sh := e.shard(s.Token)
	sh.mu.Lock()
	sh.m[s.Token] = sessEntry{s: s, exp: time.Now().Add(e.Cfg.SessionCacheTTL)}
	sh.mu.Unlock()
}

// ForgetSession drops a session from the local cache.
func (e *Engine) ForgetSession(token string) {
	sh := e.shard(token)
	sh.mu.Lock()
	delete(sh.m, token)
	sh.mu.Unlock()
}

// LookupSession resolves a cookie token. The device binding is re-checked against the attempt
// by every operation, so a locally cached session can't outlive a device switch.
func (e *Engine) LookupSession(ctx context.Context, token string) (*Session, error) {
	if !validToken(token) {
		return nil, nil
	}
	sh := e.shard(token)
	sh.mu.Lock()
	if v, ok := sh.m[token]; ok && time.Now().Before(v.exp) {
		sh.mu.Unlock()
		s := v.s
		return &s, nil
	}
	sh.mu.Unlock()
	raw, err := e.St.R.Get(ctx, store.SessionPrefix+token).Result()
	if errors.Is(err, redis.Nil) {
		return nil, nil
	}
	if err != nil {
		e.M.RedisErrors.Inc("session")
		return nil, err
	}
	var s Session
	if err := json.Unmarshal([]byte(raw), &s); err != nil {
		return nil, nil
	}
	s.Token = token
	e.CacheSession(s)
	return &s, nil
}

// Authed resolves the session and its run, or fails with 401.
func (e *Engine) Authed(ctx context.Context, token string) (*Session, *RunCtx, *httpx.Error) {
	if token == "" {
		return nil, nil, httpx.Unauthorized("Please sign in.")
	}
	s, err := e.LookupSession(ctx, token)
	if err != nil {
		return nil, nil, httpx.Internal()
	}
	if s == nil {
		return nil, nil, httpx.Unauthorized("Your session has ended. Please sign in again.")
	}
	rc, err := e.Run(ctx, s.Run)
	if err != nil {
		return nil, nil, httpx.Internal()
	}
	if !rc.Exists() {
		return nil, nil, httpx.Unauthorized("This exam is no longer available. Please sign in again.")
	}
	return s, rc, nil
}

// RevokeSession deletes a session everywhere it can.
func (e *Engine) RevokeSession(ctx context.Context, token string) {
	if token == "" {
		return
	}
	e.ForgetSession(token)
	_ = e.St.R.Del(ctx, store.SessionPrefix+token).Err()
}

// Receipt builds "{CODE}-{exam}-{reg}-{4 hex}".
func Receipt(m *model.Meta, regID int64) string {
	var b [2]byte
	_, _ = rand.Read(b[:])
	return m.SahodayaCode + "-" + strconv.FormatInt(m.ExamID, 10) + "-" + strconv.FormatInt(regID, 10) + "-" + strings.ToUpper(hex.EncodeToString(b[:]))
}
