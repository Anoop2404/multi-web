package main

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net"
	"net/http"
	"sort"
	"strings"
	"sync"
	"sync/atomic"
	"time"
)

// stats collects per-endpoint latencies and errors.
type stats struct {
	mu       sync.Mutex
	lat      map[string][]float64 // ms
	errs     map[string]int
	codes    map[string]map[int]int
	inflight atomic.Int64
	peak     atomic.Int64
	total    atomic.Int64
	first    time.Time
	last     time.Time
}

func newStats() *stats {
	return &stats{lat: map[string][]float64{}, errs: map[string]int{}, codes: map[string]map[int]int{}}
}

func (s *stats) record(ep string, d time.Duration, code int, isErr bool) {
	s.mu.Lock()
	defer s.mu.Unlock()
	now := time.Now()
	if s.first.IsZero() {
		s.first = now
	}
	s.last = now
	s.lat[ep] = append(s.lat[ep], float64(d.Microseconds())/1000)
	if isErr {
		s.errs[ep]++
	}
	if s.codes[ep] == nil {
		s.codes[ep] = map[int]int{}
	}
	s.codes[ep][code]++
}

func pct(sorted []float64, p float64) float64 {
	if len(sorted) == 0 {
		return 0
	}
	i := int(float64(len(sorted)-1) * p)
	return sorted[i]
}

type epReport struct {
	Endpoint           string
	Count, Errors      int
	P50, P95, P99, Max float64
	Codes              map[int]int
}

func (s *stats) report() []epReport {
	s.mu.Lock()
	defer s.mu.Unlock()
	var out []epReport
	for ep, l := range s.lat {
		c := append([]float64(nil), l...)
		sort.Float64s(c)
		out = append(out, epReport{Endpoint: ep, Count: len(c), Errors: s.errs[ep], P50: pct(c, .50), P95: pct(c, .95), P99: pct(c, .99), Max: c[len(c)-1], Codes: s.codes[ep]})
	}
	sort.Slice(out, func(i, j int) bool { return out[i].Endpoint < out[j].Endpoint })
	return out
}

// api is the HTTP client shared by every simulated student.
type api struct {
	base  string
	token string
	hc    *http.Client
	sem   chan struct{}
	st    *stats
}

func newAPI(base, token string, concurrency int, st *stats) *api {
	tr := &http.Transport{
		Proxy:               nil,
		DialContext:         (&net.Dialer{Timeout: 10 * time.Second, KeepAlive: 30 * time.Second}).DialContext,
		MaxIdleConns:        concurrency + 64,
		MaxIdleConnsPerHost: concurrency + 64,
		MaxConnsPerHost:     concurrency + 64,
		IdleConnTimeout:     120 * time.Second,
		ForceAttemptHTTP2:   false,
	}
	return &api{base: strings.TrimRight(base, "/"), token: token, hc: &http.Client{Transport: tr, Timeout: 60 * time.Second},
		sem: make(chan struct{}, concurrency), st: st}
}

type resp struct {
	Code int
	Body []byte
	Err  error
}

func (r resp) ok() bool { return r.Err == nil && r.Code >= 200 && r.Code < 300 }

func (r resp) decode(v any) error {
	if r.Err != nil {
		return r.Err
	}
	return json.Unmarshal(r.Body, v)
}

func (r resp) errCode() string {
	var e struct {
		Error string `json:"error"`
	}
	_ = json.Unmarshal(r.Body, &e)
	return e.Error
}

func (r resp) String() string {
	if r.Err != nil {
		return r.Err.Error()
	}
	b := string(r.Body)
	if len(b) > 300 {
		b = b[:300]
	}
	return fmt.Sprintf("%d %s", r.Code, b)
}

// do sends one request; ep names the endpoint for the report. expected lists non-2xx codes that
// are part of the protocol (not counted as errors).
func (a *api) do(ctx context.Context, ep, method, path string, body any, cookie string, internal bool, expected ...int) (resp, string) {
	var rd io.Reader
	if body != nil {
		b, _ := json.Marshal(body)
		rd = bytes.NewReader(b)
	}
	req, err := http.NewRequestWithContext(ctx, method, a.base+path, rd)
	if err != nil {
		return resp{Err: err}, ""
	}
	req.Header.Set("Content-Type", "application/json")
	if internal {
		req.Header.Set("Authorization", "Bearer "+a.token)
	} else {
		req.Header.Set("X-Exam-Client", "1")
		req.Header.Set("User-Agent", "Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/131.0 Mobile loadgen")
	}
	if cookie != "" {
		req.Header.Set("Cookie", "exam_session="+cookie)
	}
	select {
	case a.sem <- struct{}{}:
	case <-ctx.Done():
		return resp{Err: ctx.Err()}, ""
	}
	n := a.st.inflight.Add(1)
	for {
		p := a.st.peak.Load()
		if n <= p || a.st.peak.CompareAndSwap(p, n) {
			break
		}
	}
	start := time.Now()
	res, err := a.hc.Do(req)
	var out resp
	newCookie := ""
	if err != nil {
		out = resp{Err: err}
	} else {
		b, rerr := io.ReadAll(res.Body)
		res.Body.Close()
		out = resp{Code: res.StatusCode, Body: b, Err: rerr}
		for _, c := range res.Cookies() {
			if c.Name == "exam_session" {
				newCookie = c.Value
			}
		}
	}
	d := time.Since(start)
	a.st.inflight.Add(-1)
	<-a.sem
	a.st.total.Add(1)
	isErr := !out.ok()
	if isErr && out.Err == nil {
		for _, c := range expected {
			if out.Code == c {
				isErr = false
			}
		}
	}
	code := out.Code
	if out.Err != nil {
		code = -1
	}
	a.st.record(ep, d, code, isErr)
	return out, newCookie
}
