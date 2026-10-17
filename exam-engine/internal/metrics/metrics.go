// Package metrics is a tiny hand-written Prometheus text-format registry (counters, gauges and
// histograms with fixed label sets). No client library, by design.
package metrics

import (
	"fmt"
	"io"
	"math"
	"sort"
	"strconv"
	"strings"
	"sync"
	"sync/atomic"
)

// Registry holds every metric family.
type Registry struct {
	mu       sync.Mutex
	families []family
	gauges   []gaugeFunc
}

type family interface {
	write(w io.Writer)
}

type gaugeFunc struct {
	name, help string
	fn         func() float64
}

// GaugeVecFunc is a labelled gauge whose series are produced at scrape time.
type GaugeVecFunc struct {
	name, help string
	labels     []string
	fn         func(emit func(v float64, values ...string))
}

func (g *GaugeVecFunc) write(w io.Writer) {
	fmt.Fprintf(w, "# HELP %s %s\n# TYPE %s gauge\n", g.name, g.help, g.name)
	g.fn(func(v float64, values ...string) {
		fmt.Fprintf(w, "%s%s %g\n", g.name, labelString(g.labels, values), v)
	})
}

// GaugeVec registers a labelled gauge collected at scrape time.
func (r *Registry) GaugeVec(name, help string, labels []string, fn func(emit func(v float64, values ...string))) {
	r.mu.Lock()
	r.families = append(r.families, &GaugeVecFunc{name: name, help: help, labels: labels, fn: fn})
	r.mu.Unlock()
}

// New creates a registry.
func New() *Registry { return &Registry{} }

func labelString(names, values []string) string {
	if len(names) == 0 {
		return ""
	}
	var b strings.Builder
	b.WriteByte('{')
	for i, n := range names {
		if i > 0 {
			b.WriteByte(',')
		}
		b.WriteString(n)
		b.WriteString(`="`)
		v := ""
		if i < len(values) {
			v = values[i]
		}
		v = strings.NewReplacer(`\`, `\\`, `"`, `\"`, "\n", `\n`).Replace(v)
		b.WriteString(v)
		b.WriteByte('"')
	}
	b.WriteByte('}')
	return b.String()
}

// CounterVec is a counter with labels.
type CounterVec struct {
	name, help string
	labels     []string
	m          sync.Map // label string → *atomic.Int64
}

// Counter registers a counter family.
func (r *Registry) Counter(name, help string, labels ...string) *CounterVec {
	c := &CounterVec{name: name, help: help, labels: labels}
	r.mu.Lock()
	r.families = append(r.families, c)
	r.mu.Unlock()
	return c
}

// Add increments the series for the given label values.
func (c *CounterVec) Add(n int64, values ...string) {
	key := labelString(c.labels, values)
	v, ok := c.m.Load(key)
	if !ok {
		v, _ = c.m.LoadOrStore(key, new(atomic.Int64))
	}
	v.(*atomic.Int64).Add(n)
}

// Inc adds one.
func (c *CounterVec) Inc(values ...string) { c.Add(1, values...) }

// Value returns the current value for label values (tests).
func (c *CounterVec) Value(values ...string) int64 {
	if v, ok := c.m.Load(labelString(c.labels, values)); ok {
		return v.(*atomic.Int64).Load()
	}
	return 0
}

func sortedKeys(m *sync.Map) []string {
	var keys []string
	m.Range(func(k, _ any) bool { keys = append(keys, k.(string)); return true })
	sort.Strings(keys)
	return keys
}

func (c *CounterVec) write(w io.Writer) {
	fmt.Fprintf(w, "# HELP %s %s\n# TYPE %s counter\n", c.name, c.help, c.name)
	for _, k := range sortedKeys(&c.m) {
		v, _ := c.m.Load(k)
		fmt.Fprintf(w, "%s%s %d\n", c.name, k, v.(*atomic.Int64).Load())
	}
}

// GaugeFunc registers a gauge read at scrape time.
func (r *Registry) GaugeFunc(name, help string, fn func() float64) {
	r.mu.Lock()
	r.gauges = append(r.gauges, gaugeFunc{name, help, fn})
	r.mu.Unlock()
}

// DefaultBuckets are latency buckets in seconds.
var DefaultBuckets = []float64{0.001, 0.0025, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10, 30}

type hist struct {
	counts []atomic.Int64 // len(buckets)+1 (last = +Inf)
	sumNs  atomic.Int64
	count  atomic.Int64
}

// HistogramVec is a histogram with labels.
type HistogramVec struct {
	name, help string
	labels     []string
	buckets    []float64
	m          sync.Map
}

// Histogram registers a histogram family.
func (r *Registry) Histogram(name, help string, buckets []float64, labels ...string) *HistogramVec {
	h := &HistogramVec{name: name, help: help, labels: labels, buckets: buckets}
	r.mu.Lock()
	r.families = append(r.families, h)
	r.mu.Unlock()
	return h
}

// Observe records a value in seconds.
func (h *HistogramVec) Observe(seconds float64, values ...string) {
	key := labelString(h.labels, values)
	v, ok := h.m.Load(key)
	if !ok {
		v, _ = h.m.LoadOrStore(key, &hist{counts: make([]atomic.Int64, len(h.buckets)+1)})
	}
	hh := v.(*hist)
	i := sort.SearchFloat64s(h.buckets, seconds)
	hh.counts[i].Add(1)
	hh.count.Add(1)
	hh.sumNs.Add(int64(seconds * 1e9))
}

func (h *HistogramVec) write(w io.Writer) {
	fmt.Fprintf(w, "# HELP %s %s\n# TYPE %s histogram\n", h.name, h.help, h.name)
	for _, k := range sortedKeys(&h.m) {
		v, _ := h.m.Load(k)
		hh := v.(*hist)
		inner := strings.TrimSuffix(strings.TrimPrefix(k, "{"), "}")
		sep := ""
		if inner != "" {
			sep = ","
		}
		var cum int64
		for i, b := range h.buckets {
			cum += hh.counts[i].Load()
			fmt.Fprintf(w, "%s_bucket{%s%sle=\"%s\"} %d\n", h.name, inner, sep, strconv.FormatFloat(b, 'g', -1, 64), cum)
		}
		cum += hh.counts[len(h.buckets)].Load()
		fmt.Fprintf(w, "%s_bucket{%s%sle=\"+Inf\"} %d\n", h.name, inner, sep, cum)
		fmt.Fprintf(w, "%s_sum%s %g\n", h.name, k, float64(hh.sumNs.Load())/1e9)
		fmt.Fprintf(w, "%s_count%s %d\n", h.name, k, hh.count.Load())
	}
}

// Write renders every metric in Prometheus text format.
func (r *Registry) Write(w io.Writer) {
	r.mu.Lock()
	fams := append([]family(nil), r.families...)
	gauges := append([]gaugeFunc(nil), r.gauges...)
	r.mu.Unlock()
	for _, f := range fams {
		f.write(w)
	}
	for _, g := range gauges {
		v := g.fn()
		if math.IsNaN(v) {
			continue
		}
		fmt.Fprintf(w, "# HELP %s %s\n# TYPE %s gauge\n%s %g\n", g.name, g.help, g.name, g.name, v)
	}
}
