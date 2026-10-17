// Package ratelimit is an in-process fixed-window limiter, sharded to keep lock contention low.
// Limits are per engine node (CONTRACT §4 rate limits); the load balancer and Cloudflare add
// per-IP limits in front.
package ratelimit

import (
	"hash/maphash"
	"sync"
	"time"
)

const shards = 64

type window struct {
	start time.Time
	n     int
}

type shard struct {
	mu sync.Mutex
	m  map[string]*window
}

// Limiter allows `limit` events per `per` for each key.
type Limiter struct {
	limit  int
	per    time.Duration
	seed   maphash.Seed
	shards [shards]shard
}

// New creates a limiter.
func New(limit int, per time.Duration) *Limiter {
	l := &Limiter{limit: limit, per: per, seed: maphash.MakeSeed()}
	for i := range l.shards {
		l.shards[i].m = make(map[string]*window)
	}
	return l
}

// Allow records one event for key; when over the limit it returns false and how long to wait.
func (l *Limiter) Allow(key string, now time.Time) (bool, time.Duration) {
	if l == nil || l.limit <= 0 {
		return true, 0
	}
	s := &l.shards[maphash.String(l.seed, key)%shards]
	s.mu.Lock()
	defer s.mu.Unlock()
	w := s.m[key]
	if w == nil || now.Sub(w.start) >= l.per {
		if w == nil {
			w = &window{}
			s.m[key] = w
		}
		w.start, w.n = now, 0
	}
	if w.n >= l.limit {
		return false, l.per - now.Sub(w.start)
	}
	w.n++
	return true, 0
}

// GC drops expired windows.
func (l *Limiter) GC(now time.Time) {
	for i := range l.shards {
		s := &l.shards[i]
		s.mu.Lock()
		for k, w := range s.m {
			if now.Sub(w.start) >= l.per {
				delete(s.m, k)
			}
		}
		s.mu.Unlock()
	}
}
