// Package server wires the HTTP routes (Go 1.22 method patterns), middleware (metrics, in-flight
// counting for pacing, auth, CSRF header, body limits) and the student API handlers.
package server

import (
	"bytes"
	"context"
	"crypto/sha256"
	"crypto/subtle"
	"encoding/json"
	"fmt"
	"net/http"
	"runtime"
	"strconv"
	"strings"
	"time"

	"github.com/sahodaya/exam-engine/internal/admin"
	"github.com/sahodaya/exam-engine/internal/config"
	"github.com/sahodaya/exam-engine/internal/engine"
	"github.com/sahodaya/exam-engine/internal/httpx"
	"github.com/sahodaya/exam-engine/internal/model"
)

// CookieName is the student session cookie.
const CookieName = "exam_session"

// LimitStudent is the student JSON body limit.
const LimitStudent = 64 << 10

// Server is the HTTP front of the engine.
type Server struct {
	E         *engine.Engine
	A         *admin.API
	static    http.Handler
	mux       *http.ServeMux
	tokenHash [32]byte
	hasToken  bool
}

type kind int

const (
	kindPublic kind = iota
	kindStudent
	kindInternal
)

// New builds the server and its routes.
func New(e *engine.Engine, static http.Handler) *Server {
	s := &Server{E: e, A: admin.New(e), static: static, mux: http.NewServeMux()}
	if e.Cfg.InternalToken != "" {
		s.tokenHash = sha256.Sum256([]byte(e.Cfg.InternalToken))
		s.hasToken = true
	}
	s.routes()
	return s
}

// Handler returns the root handler.
func (s *Server) Handler() http.Handler { return s.mux }

// Route labels for exam_http_requests_total / exam_http_request_duration_seconds, as the ops
// dashboards expect: login, state, system_check, bundle, start, sync, resync, submit, result,
// logout, internal, health, static (plus sahodaya, metrics and other for the remaining paths).
func (s *Server) routes() {
	s.handle("GET /health", "health", kindPublic, 0, s.health)
	s.handle("GET /metrics", "metrics", kindPublic, 0, s.metrics)

	s.handle("GET /api/sahodaya/{code}", "sahodaya", kindStudent, 0, s.sahodaya)
	s.handle("POST /api/login", "login", kindStudent, LimitStudent, s.login)
	s.handle("GET /api/state", "state", kindStudent, 0, s.state)
	s.handle("POST /api/system-check", "system_check", kindStudent, LimitStudent, s.systemCheck)
	s.handle("GET /api/bundle/{run}/{version}", "bundle", kindStudent, 0, s.bundle)
	s.handle("POST /api/start", "start", kindStudent, LimitStudent, s.start)
	s.handle("POST /api/sync", "sync", kindStudent, LimitStudent, s.sync)
	s.handle("POST /api/resync", "resync", kindStudent, LimitStudent, s.resync)
	s.handle("POST /api/submit", "submit", kindStudent, LimitStudent, s.submit)
	s.handle("GET /api/result", "result", kindStudent, 0, s.result)
	s.handle("POST /api/logout", "logout", kindStudent, LimitStudent, s.logout)
	s.handle("/api/", "other", kindStudent, 0, func(w http.ResponseWriter, r *http.Request) {
		httpx.Fail(w, httpx.NotFound("No such endpoint."))
	})

	for _, rt := range s.A.Routes() {
		s.handle(rt.Pattern, "internal", kindInternal, rt.Limit, rt.Handler)
	}
	s.handle("/internal/", "internal", kindInternal, 0, func(w http.ResponseWriter, r *http.Request) {
		httpx.Fail(w, httpx.NotFound("No such endpoint."))
	})

	if s.static != nil {
		// GET/HEAD only (the handler answers 405 otherwise)
		s.handle("/", "static", kindPublic, 0, s.static.ServeHTTP)
	}
}

type statusWriter struct {
	http.ResponseWriter
	status int
}

func (w *statusWriter) WriteHeader(code int) {
	if w.status == 0 {
		w.status = code
	}
	w.ResponseWriter.WriteHeader(code)
}

func (w *statusWriter) Write(b []byte) (int, error) {
	if w.status == 0 {
		w.status = http.StatusOK
	}
	return w.ResponseWriter.Write(b)
}

func (w *statusWriter) Unwrap() http.ResponseWriter { return w.ResponseWriter }

func (s *Server) handle(pattern, label string, k kind, limit int64, h http.HandlerFunc) {
	s.mux.HandleFunc(pattern, func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		if k == kindStudent {
			s.E.Inflight.Add(1)
			defer s.E.Inflight.Add(-1)
		}
		sw := &statusWriter{ResponseWriter: w}
		defer func() {
			if rec := recover(); rec != nil {
				buf := make([]byte, 4096)
				buf = buf[:runtime.Stack(buf, false)]
				s.E.Log.Error("panic", "route", label, "err", fmt.Sprint(rec), "stack", string(buf))
				if sw.status == 0 {
					httpx.Fail(sw, httpx.Internal())
				}
			}
			d := time.Since(start)
			code := sw.status
			if code == 0 {
				code = http.StatusOK
			}
			s.E.M.Requests.Inc(label, strconv.Itoa(code))
			s.E.M.Latency.Observe(d.Seconds(), label)
			if d > 2*time.Second && !strings.HasSuffix(r.URL.Path, "/events/claim") {
				s.E.Log.Warn("slow request", "route", label, "path", r.URL.Path, "ms", d.Milliseconds(), "status", code)
			}
		}()
		if limit > 0 && r.ContentLength > limit {
			httpx.Fail(sw, httpx.E(http.StatusRequestEntityTooLarge, httpx.CodeInvalid, "Request body is too large."))
			return
		}
		switch k {
		case kindInternal:
			if !s.authorised(r) {
				httpx.Fail(sw, httpx.Unauthorized("Missing or wrong internal token."))
				return
			}
		case kindStudent:
			sw.Header().Set("X-Content-Type-Options", "nosniff")
			if r.Method == http.MethodPost && r.Header.Get("X-Exam-Client") != "1" {
				httpx.Fail(sw, httpx.E(http.StatusForbidden, httpx.CodeForbidden, "Missing X-Exam-Client header."))
				return
			}
		}
		h(sw, r)
	})
}

func (s *Server) authorised(r *http.Request) bool {
	if !s.hasToken {
		return false
	}
	auth := r.Header.Get("Authorization")
	tok, ok := strings.CutPrefix(auth, "Bearer ")
	if !ok {
		return false
	}
	h := sha256.Sum256([]byte(strings.TrimSpace(tok)))
	return subtle.ConstantTimeCompare(h[:], s.tokenHash[:]) == 1
}

// ---- public ----

func (s *Server) health(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 2*time.Second)
	defer cancel()
	redisState := "ok"
	status := http.StatusOK
	if err := s.E.St.Ping(ctx); err != nil {
		redisState = "error"
		status = http.StatusServiceUnavailable
	}
	httpx.JSON(w, status, map[string]any{"ok": status == http.StatusOK, "redis": redisState, "version": config.Version})
}

func (s *Server) metrics(w http.ResponseWriter, r *http.Request) {
	var buf bytes.Buffer
	s.E.M.Reg.Write(&buf)
	var ms runtime.MemStats
	runtime.ReadMemStats(&ms)
	fmt.Fprintf(&buf, "# TYPE go_goroutines gauge\ngo_goroutines %d\n", runtime.NumGoroutine())
	fmt.Fprintf(&buf, "# TYPE go_memstats_heap_alloc_bytes gauge\ngo_memstats_heap_alloc_bytes %d\n", ms.HeapAlloc)
	fmt.Fprintf(&buf, "# TYPE go_gc_cycles_total counter\ngo_gc_cycles_total %d\n", ms.NumGC)
	w.Header().Set("Content-Type", "text/plain; version=0.0.4; charset=utf-8")
	w.Header().Set("Cache-Control", "no-store")
	_, _ = w.Write(buf.Bytes())
}

// ---- student ----

func cookieToken(r *http.Request) string {
	c, err := r.Cookie(CookieName)
	if err != nil {
		return ""
	}
	return c.Value
}

func (s *Server) setCookie(w http.ResponseWriter, r *http.Request, token string, ttl time.Duration) {
	http.SetCookie(w, &http.Cookie{
		Name: CookieName, Value: token, Path: "/", HttpOnly: true, SameSite: http.SameSiteLaxMode,
		Secure: httpx.IsHTTPS(r, s.E.Cfg.TrustProxy), MaxAge: int(ttl.Seconds()),
	})
}

func (s *Server) clearCookie(w http.ResponseWriter, r *http.Request) {
	http.SetCookie(w, &http.Cookie{
		Name: CookieName, Value: "", Path: "/", HttpOnly: true, SameSite: http.SameSiteLaxMode,
		Secure: httpx.IsHTTPS(r, s.E.Cfg.TrustProxy), MaxAge: -1,
	})
}

// authed resolves the session; on 401 the cookie is cleared.
func (s *Server) authed(w http.ResponseWriter, r *http.Request) (*engine.Session, *engine.RunCtx, bool) {
	sess, rc, err := s.E.Authed(r.Context(), cookieToken(r))
	if err != nil {
		s.fail(w, r, err)
		return nil, nil, false
	}
	return sess, rc, true
}

func (s *Server) fail(w http.ResponseWriter, r *http.Request, err *httpx.Error) {
	if err.Status == http.StatusUnauthorized && cookieToken(r) != "" {
		s.clearCookie(w, r)
	}
	httpx.Fail(w, err)
}

func (s *Server) sahodaya(w http.ResponseWriter, r *http.Request) {
	res, err := s.E.Sahodaya(r.Context(), r.PathValue("code"))
	if err != nil {
		httpx.Fail(w, err)
		return
	}
	w.Header().Set("Cache-Control", "public, max-age=5")
	httpx.JSON(w, http.StatusOK, res)
}

func (s *Server) login(w http.ResponseWriter, r *http.Request) {
	var req engine.LoginRequest
	if err := httpx.Decode(w, r, LimitStudent, &req, false); err != nil {
		httpx.Fail(w, err)
		return
	}
	req.IP = httpx.ClientIP(r, s.E.Cfg.TrustProxy)
	req.UserAgent = r.UserAgent()
	res, err := s.E.Login(r.Context(), &req)
	if err != nil {
		httpx.Fail(w, err)
		return
	}
	if old := cookieToken(r); old != "" && old != res.Session.Token {
		s.E.ForgetSession(old)
	}
	s.setCookie(w, r, res.Session.Token, res.TTL)
	httpx.JSON(w, http.StatusOK, map[string]any{"state": res.State})
}

func (s *Server) state(w http.ResponseWriter, r *http.Request) {
	sess, rc, ok := s.authed(w, r)
	if !ok {
		return
	}
	st, err := s.E.State(r.Context(), rc, sess, true)
	if err != nil {
		s.fail(w, r, err)
		return
	}
	httpx.JSON(w, http.StatusOK, st)
}

func (s *Server) systemCheck(w http.ResponseWriter, r *http.Request) {
	sess, rc, ok := s.authed(w, r)
	if !ok {
		return
	}
	var req engine.SystemCheckRequest
	if err := httpx.Decode(w, r, LimitStudent, &req, true); err != nil {
		httpx.Fail(w, err)
		return
	}
	if err := s.E.SystemCheck(r.Context(), rc, sess, &req); err != nil {
		s.fail(w, r, err)
		return
	}
	httpx.JSON(w, http.StatusOK, map[string]any{"ok": true})
}

func (s *Server) bundle(w http.ResponseWriter, r *http.Request) {
	run := r.PathValue("run")
	v, err := strconv.Atoi(r.PathValue("version"))
	if !model.ValidRun(run) || err != nil || v <= 0 {
		httpx.Fail(w, httpx.NotFound("No such paper."))
		return
	}
	c, herr := s.E.Bundle(r.Context(), run, v)
	if herr != nil {
		httpx.Fail(w, herr)
		return
	}
	h := w.Header()
	h.Set("Cache-Control", "public, max-age=31536000, immutable")
	h.Set("ETag", c.ETag)
	h.Set("Content-Type", "application/json")
	h.Set("Vary", "Accept-Encoding")
	if r.Header.Get("If-None-Match") == c.ETag {
		w.WriteHeader(http.StatusNotModified)
		return
	}
	body := c.Raw
	if strings.Contains(r.Header.Get("Accept-Encoding"), "gzip") {
		h.Set("Content-Encoding", "gzip")
		body = c.Gzip
	}
	h.Set("Content-Length", strconv.Itoa(len(body)))
	w.WriteHeader(http.StatusOK)
	if r.Method != http.MethodHead {
		_, _ = w.Write(body)
	}
}

func (s *Server) start(w http.ResponseWriter, r *http.Request) {
	sess, rc, ok := s.authed(w, r)
	if !ok {
		return
	}
	var req struct {
		Pin json.RawMessage `json:"pin"`
	}
	if err := httpx.Decode(w, r, LimitStudent, &req, true); err != nil {
		httpx.Fail(w, err)
		return
	}
	pin := ""
	if len(req.Pin) > 0 {
		var fs model.FlexString
		if json.Unmarshal(req.Pin, &fs) == nil {
			pin = strings.TrimSpace(string(fs))
		}
	}
	st, err := s.E.Start(r.Context(), rc, sess, pin)
	if err != nil {
		s.fail(w, r, err)
		return
	}
	httpx.JSON(w, http.StatusOK, st)
}

func (s *Server) sync(w http.ResponseWriter, r *http.Request) {
	sess, rc, ok := s.authed(w, r)
	if !ok {
		return
	}
	var req engine.SyncRequest
	if err := httpx.Decode(w, r, LimitStudent, &req, false); err != nil {
		httpx.Fail(w, err)
		return
	}
	res, err := s.E.Sync(r.Context(), rc, sess, &req)
	if err != nil {
		s.fail(w, r, err)
		return
	}
	httpx.JSON(w, http.StatusOK, res)
}

func (s *Server) resync(w http.ResponseWriter, r *http.Request) {
	sess, rc, ok := s.authed(w, r)
	if !ok {
		return
	}
	var req engine.ResyncRequest
	if err := httpx.Decode(w, r, LimitStudent, &req, false); err != nil {
		httpx.Fail(w, err)
		return
	}
	res, err := s.E.Resync(r.Context(), rc, sess, &req)
	if err != nil {
		s.fail(w, r, err)
		return
	}
	httpx.JSON(w, http.StatusOK, res)
}

func (s *Server) submit(w http.ResponseWriter, r *http.Request) {
	sess, rc, ok := s.authed(w, r)
	if !ok {
		return
	}
	var req engine.SubmitRequest
	if err := httpx.Decode(w, r, LimitStudent, &req, true); err != nil {
		httpx.Fail(w, err)
		return
	}
	res, err := s.E.Submit(r.Context(), rc, sess, &req)
	if err != nil {
		s.fail(w, r, err)
		return
	}
	httpx.JSON(w, http.StatusOK, res)
}

func (s *Server) result(w http.ResponseWriter, r *http.Request) {
	sess, rc, ok := s.authed(w, r)
	if !ok {
		return
	}
	res, err := s.E.Result(r.Context(), rc, sess)
	if err != nil {
		s.fail(w, r, err)
		return
	}
	httpx.JSON(w, http.StatusOK, res)
}

func (s *Server) logout(w http.ResponseWriter, r *http.Request) {
	s.E.Logout(r.Context(), cookieToken(r))
	s.clearCookie(w, r)
	httpx.JSON(w, http.StatusOK, map[string]any{"ok": true})
}
