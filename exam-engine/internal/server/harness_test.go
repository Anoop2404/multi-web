package server

import (
	"bytes"
	"context"
	"crypto/aes"
	"crypto/cipher"
	"crypto/ed25519"
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"log/slog"
	"net/http"
	"net/http/cookiejar"
	"net/http/httptest"
	"net/url"
	"os"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/redis/go-redis/v9"
	"golang.org/x/crypto/bcrypt"

	"github.com/sahodaya/exam-engine/internal/config"
	"github.com/sahodaya/exam-engine/internal/engine"
	"github.com/sahodaya/exam-engine/internal/entry"
	"github.com/sahodaya/exam-engine/internal/ratelimit"
	"github.com/sahodaya/exam-engine/internal/store"
	"github.com/sahodaya/exam-engine/internal/sweeper"
	"github.com/sahodaya/exam-engine/internal/syncer"
)

// Integration tests run against a real Redis, database 15 only (flushed before and after).
// Set EXAM_ENGINE_TEST_REDIS to override the URL; tests are skipped when Redis is unreachable.

var testRedis *redis.Client

func TestMain(m *testing.M) {
	u := os.Getenv("EXAM_ENGINE_TEST_REDIS")
	if u == "" {
		u = "redis://127.0.0.1:6379/15"
	}
	opt, err := redis.ParseURL(u)
	if err == nil && opt.DB != 15 {
		fmt.Fprintln(os.Stderr, "integration tests only run against Redis database 15")
		os.Exit(1)
	}
	if err == nil {
		c := redis.NewClient(opt)
		ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
		if c.Ping(ctx).Err() == nil {
			testRedis = c
			_ = c.FlushDB(context.Background()).Err()
		}
		cancel()
	}
	code := m.Run()
	if testRedis != nil {
		_ = testRedis.FlushDB(context.Background()).Err()
		_ = testRedis.Close()
	}
	os.Exit(code)
}

const testToken = "test-internal-token-0123456789abcdef"

var runSeq atomic.Int64

type harness struct {
	t     *testing.T
	srv   *httptest.Server
	E     *engine.Engine
	S     *Server
	sweep *sweeper.Sweeper
	mu    sync.Mutex
	now   time.Time
	priv  ed25519.PrivateKey
}

func newHarness(t *testing.T) *harness {
	t.Helper()
	if testRedis == nil {
		t.Skip("Redis DB 15 not reachable")
	}
	pub, priv, _ := ed25519.GenerateKey(rand.Reader)
	cfg := &config.Config{
		InternalToken: testToken, LaunchPublicKey: pub, BundleBaseURL: "/api/bundle", BcryptWorkers: 2,
		BcryptMaxWait: 5 * time.Second, BcryptMaxQueue: 100, PaceThresholds: [3]int{500, 1500, 3000},
		EventsMaxLen: 500000, RunCacheTTL: 0, SweepInterval: time.Second, SnapshotEvery: 120 * time.Second,
		SnapshotScan: 0, OfflineAfter: 60 * time.Second, SessionCacheTTL: time.Minute, NodeID: "test-node",
	}
	st := store.NewWithClient(testRedis)
	if err := st.Prepare(context.Background()); err != nil {
		t.Fatal(err)
	}
	log := slog.New(slog.NewTextHandler(io.Discard, nil))
	e := engine.New(cfg, st, log)
	e.SyncLimiter = ratelimit.New(10000, time.Second)
	e.LoginLimiter = ratelimit.New(10000, time.Second)
	h := &harness{t: t, E: e, priv: priv, now: time.Date(2030, 1, 10, 4, 0, 0, 0, time.UTC)}
	e.Now = h.Now
	h.S = New(e, nil)
	h.srv = httptest.NewServer(h.S.Handler())
	h.sweep = sweeper.New(e)
	t.Cleanup(h.srv.Close)
	return h
}

func (h *harness) Now() time.Time {
	h.mu.Lock()
	defer h.mu.Unlock()
	return h.now
}

func (h *harness) set(t time.Time) {
	h.mu.Lock()
	h.now = t
	h.mu.Unlock()
}

func (h *harness) advance(d time.Duration) { h.set(h.Now().Add(d)) }

// ---- internal API ----

func (h *harness) internal(method, path string, body any) (int, map[string]any) {
	h.t.Helper()
	var rd io.Reader
	if body != nil {
		b, _ := json.Marshal(body)
		rd = bytes.NewReader(b)
	}
	req, _ := http.NewRequest(method, h.srv.URL+path, rd)
	req.Header.Set("Authorization", "Bearer "+testToken)
	req.Header.Set("Content-Type", "application/json")
	res, err := http.DefaultClient.Do(req)
	if err != nil {
		h.t.Fatal(err)
	}
	defer res.Body.Close()
	var out map[string]any
	_ = json.NewDecoder(res.Body).Decode(&out)
	return res.StatusCode, out
}

func (h *harness) mustInternal(method, path string, body any) map[string]any {
	h.t.Helper()
	code, out := h.internal(method, path, body)
	if code != 200 {
		h.t.Fatalf("%s %s → %d %v", method, path, code, out)
	}
	return out
}

// ---- test run ----

type student struct {
	RegID    int64
	Username string
	Password string
	Ticket   string
	Passcode string
	School   string
}

type testRun struct {
	Run      string
	Code     string
	Secret   string
	Key      []byte
	Students []student
	Starts   time.Time
}

func (r *testRun) path(suffix string) string {
	return "/internal/runs/" + url.PathEscape(r.Run) + suffix
}

type runOpts struct {
	students   int
	timing     string
	supervised bool
	pin        string
	duration   int
	grace      int
	lateEntry  int
	idle       int
	entryMode  string
}

const (
	qSingle  = "5501"
	qMulti   = "5502"
	qTF      = "5503"
	qNumeric = "5504"
)

// arm creates a run starting one hour after the harness clock.
func (h *harness) arm(o runOpts) *testRun {
	h.t.Helper()
	if o.students == 0 {
		o.students = 3
	}
	if o.timing == "" {
		o.timing = "fixed"
	}
	if o.duration == 0 {
		o.duration = 60
	}
	if o.grace == 0 {
		o.grace = 90
	}
	if o.lateEntry == 0 {
		o.lateEntry = 15
	}
	if o.idle == 0 {
		o.idle = 2
	}
	if o.entryMode == "" {
		o.entryMode = "either"
	}
	n := runSeq.Add(1)
	r := &testRun{Run: fmt.Sprintf("tenant-%d:%d", n, 100+n), Code: fmt.Sprintf("T%d", n)}
	sec := make([]byte, 32)
	_, _ = rand.Read(sec)
	r.Secret = hex.EncodeToString(sec)
	r.Starts = h.Now().Add(time.Hour)
	meta := map[string]any{
		"run": r.Run, "tenant_id": fmt.Sprintf("tenant-%d", n), "exam_id": 100 + n,
		"sahodaya_code": r.Code, "sahodaya_name": "Test Sahodaya", "logo_url": nil, "title": "Talent Search",
		"timing": o.timing, "checkin_opens_at": iso(h.Now().Add(-time.Hour)), "waiting_opens_at": iso(r.Starts.Add(-30 * time.Minute)),
		"starts_at": iso(r.Starts), "duration_minutes": o.duration, "late_entry_minutes": o.lateEntry, "grace_seconds": o.grace,
		"entry_mode": o.entryMode, "supervision": "open", "start_pin": nil, "show_score_on_submit": true, "shuffle_questions": true,
		"device_idle_minutes": o.idle, "sync_interval_seconds": 10, "heartbeat_seconds": 30, "coordinator_extend_cap_minutes": 10,
		"passcode_secret": r.Secret, "question_count": 4, "max_score": 5, "bundle_version": 1,
		"questions": map[string]string{qSingle: "single", qMulti: "multiple", qTF: "true_false", qNumeric: "numeric"},
	}
	if o.supervised {
		meta["supervision"] = "supervised"
		meta["start_pin"] = o.pin
	}
	h.mustInternal("PUT", r.path(""), meta)
	var entries []map[string]any
	for i := 0; i < o.students; i++ {
		reg := int64(n*1000 + int64(i) + 1)
		s := student{RegID: reg, Username: fmt.Sprintf("stu/%d/%d", n, i), Password: fmt.Sprintf("pw-%d", i),
			Ticket: fmt.Sprintf("%d", i%2+1), Passcode: entry.GeneratePasscode(), School: fmt.Sprintf("school-%d", i%2)}
		hash, _ := bcrypt.GenerateFromPassword([]byte(s.Password), bcrypt.MinCost)
		hm, _ := entry.PasscodeHMAC(r.Secret, reg, entry.NormalisePasscode(s.Passcode))
		entries = append(entries, map[string]any{
			"reg_id": reg, "name": fmt.Sprintf("Student %d", i), "school_id": s.School, "school_name": "School " + s.School,
			"class_name": "Class 8", "hall_ticket": s.Ticket, "username": s.Username, "email": nil,
			"password_hash": "$2y$" + string(hash[4:]), "passcode_hmac": hm, "extra_minutes": 0, "allowed": true,
		})
		r.Students = append(r.Students, s)
	}
	h.mustInternal("POST", r.path("/roster"), map[string]any{"entries": entries})
	r.Key = h.putBundle(r, 1)
	return r
}

var testPaper = []byte(`{"version":1,"questions":[
 {"id":5501,"type":"single","section":"A","body":"Q1","image":null,"options":[{"key":"a","label":"1","image":null},{"key":"b","label":"2","image":null},{"key":"c","label":"3","image":null},{"key":"d","label":"4","image":null}],"show_count":null,"marks":1,"negative_mark":0},
 {"id":5502,"type":"multiple","section":"A","body":"Q2","image":null,"options":[{"key":"a","label":"1","image":null},{"key":"b","label":"2","image":null},{"key":"c","label":"3","image":null},{"key":"d","label":"4","image":null}],"show_count":2,"marks":2,"negative_mark":0.5},
 {"id":5503,"type":"true_false","section":"B","body":"Q3","image":null,"options":[{"key":"a","label":"True","image":null},{"key":"b","label":"False","image":null}],"show_count":null,"marks":1,"negative_mark":0},
 {"id":5504,"type":"numeric","section":"B","body":"Q4","image":null,"options":[],"show_count":null,"marks":1,"negative_mark":0}]}`)

func (h *harness) putBundle(r *testRun, version int) []byte {
	key := make([]byte, 32)
	iv := make([]byte, 12)
	_, _ = rand.Read(key)
	_, _ = rand.Read(iv)
	b, _ := aes.NewCipher(key)
	g, _ := cipher.NewGCM(b)
	ct := g.Seal(nil, iv, testPaper, nil)
	sum := sha256.Sum256(ct)
	h.mustInternal("PUT", r.path("/bundle"), map[string]any{
		"version": version, "iv": base64.StdEncoding.EncodeToString(iv), "ciphertext": base64.StdEncoding.EncodeToString(ct),
		"key": base64.StdEncoding.EncodeToString(key), "sha256": hex.EncodeToString(sum[:]),
	})
	return key
}

func iso(t time.Time) string { return t.UTC().Format("2006-01-02T15:04:05Z") }

// ---- student client ----

type client struct {
	h    *harness
	http *http.Client
	ua   string
}

func (h *harness) client(ua string) *client {
	jar, _ := cookiejar.New(nil)
	return &client{h: h, http: &http.Client{Jar: jar}, ua: ua}
}

func (c *client) do(method, path string, body any) (int, map[string]any) {
	c.h.t.Helper()
	var rd io.Reader
	if body != nil {
		b, _ := json.Marshal(body)
		rd = bytes.NewReader(b)
	}
	req, _ := http.NewRequest(method, c.h.srv.URL+path, rd)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Exam-Client", "1")
	if c.ua != "" {
		req.Header.Set("User-Agent", c.ua)
	}
	res, err := c.http.Do(req)
	if err != nil {
		c.h.t.Fatal(err)
	}
	defer res.Body.Close()
	var out map[string]any
	_ = json.NewDecoder(res.Body).Decode(&out)
	return res.StatusCode, out
}

func (c *client) must(method, path string, body any) map[string]any {
	c.h.t.Helper()
	code, out := c.do(method, path, body)
	if code != 200 {
		c.h.t.Fatalf("%s %s → %d %v", method, path, code, out)
	}
	return out
}

func (c *client) loginPassword(r *testRun, s student) (int, map[string]any) {
	return c.do("POST", "/api/login", map[string]any{"sahodaya": r.Code, "method": "password", "username": s.Username, "password": s.Password})
}

func (c *client) loginPasscode(r *testRun, s student) (int, map[string]any) {
	return c.do("POST", "/api/login", map[string]any{"sahodaya": r.Code, "method": "passcode", "hall_ticket": s.Ticket, "passcode": s.Passcode})
}

func (c *client) login(r *testRun, s student) map[string]any {
	c.h.t.Helper()
	code, out := c.loginPasscode(r, s)
	if code != 200 {
		c.h.t.Fatalf("login → %d %v", code, out)
	}
	return out["state"].(map[string]any)
}

// sync sends a diff computed against the client's own full map.
func (c *client) sync(rev int64, full syncer.Answers, changes map[string]any, extra map[string]any) (int, map[string]any) {
	body := map[string]any{"base_rev": rev, "changes": changes, "checksum": syncer.Checksum(full), "events": []any{}}
	for k, v := range extra {
		body[k] = v
	}
	return c.do("POST", "/api/sync", body)
}

// ---- events ----

type evt struct {
	ID    string
	Type  string
	Run   string
	RegID int64
	Data  map[string]any
}

// drainEvents claims and acks everything currently in the stream, returning the events.
func (h *harness) drainEvents() []evt {
	h.t.Helper()
	var out []evt
	for {
		res := h.mustInternal("POST", "/internal/events/claim", map[string]any{"consumer": "test-drain", "count": 1000, "block_ms": 0})
		list, _ := res["events"].([]any)
		if len(list) == 0 {
			return out
		}
		var ids []string
		for _, it := range list {
			m := it.(map[string]any)
			e := evt{ID: m["id"].(string), Type: m["type"].(string), Run: m["run"].(string)}
			if r, ok := m["reg_id"].(float64); ok {
				e.RegID = int64(r)
			}
			e.Data, _ = m["data"].(map[string]any)
			out = append(out, e)
			ids = append(ids, e.ID)
		}
		h.mustInternal("POST", "/internal/events/ack", map[string]any{"ids": ids})
	}
}

func filterEvents(evs []evt, run, typ string) []evt {
	var out []evt
	for _, e := range evs {
		if e.Run == run && e.Type == typ {
			out = append(out, e)
		}
	}
	return out
}
