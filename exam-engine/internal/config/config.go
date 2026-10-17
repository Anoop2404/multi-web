// Package config reads the engine configuration from the environment (CONTRACT §9).
package config

import (
	"crypto/ed25519"
	"fmt"
	"os"
	"runtime"
	"strconv"
	"strings"
	"time"

	"github.com/sahodaya/exam-engine/internal/entry"
)

// Version is reported by /health. cmd/engine sets it from main.version, which the build stamps
// with -ldflags "-X main.version=…".
var Version = "dev"

// Config is the engine configuration.
type Config struct {
	Addr            string
	RedisURL        string
	RedisSentinels  []string
	RedisMaster     string
	RedisPoolSize   int
	InternalToken   string
	LaunchPublicKey ed25519.PublicKey
	StaticDir       string
	BundleBaseURL   string
	TrustProxy      bool
	BcryptWorkers   int
	BcryptMaxWait   time.Duration
	BcryptMaxQueue  int
	PaceThresholds  [3]int
	EventsMaxLen    int64 // EXAM_ENGINE_EVENTS_HARD_CAP (0 = no cap)
	RunCacheTTL     time.Duration
	SweepInterval   time.Duration
	SnapshotEvery   time.Duration
	SnapshotScan    time.Duration
	OfflineAfter    time.Duration
	SessionCacheTTL time.Duration
	LogLevel        string
	NodeID          string
}

func env(k, def string) string {
	if v, ok := os.LookupEnv(k); ok && strings.TrimSpace(v) != "" {
		return strings.TrimSpace(v)
	}
	return def
}

func envInt(k string, def int) (int, error) {
	v := env(k, "")
	if v == "" {
		return def, nil
	}
	n, err := strconv.Atoi(v)
	if err != nil {
		return 0, fmt.Errorf("%s: %w", k, err)
	}
	return n, nil
}

func envBool(k string) bool {
	switch strings.ToLower(env(k, "")) {
	case "1", "true", "yes", "on":
		return true
	}
	return false
}

// FromEnv builds the configuration.
func FromEnv() (*Config, error) {
	c := &Config{
		Addr:          env("EXAM_ENGINE_ADDR", ":8090"),
		RedisURL:      env("EXAM_ENGINE_REDIS_URL", "redis://127.0.0.1:6379/0"),
		RedisMaster:   env("EXAM_ENGINE_REDIS_MASTER", ""),
		InternalToken: env("EXAM_ENGINE_INTERNAL_TOKEN", ""),
		StaticDir:     env("EXAM_ENGINE_STATIC_DIR", ""),
		BundleBaseURL: strings.TrimRight(env("EXAM_ENGINE_BUNDLE_BASE_URL", "/api/bundle"), "/"),
		TrustProxy:    envBool("EXAM_ENGINE_TRUST_PROXY"),
		LogLevel:      env("EXAM_ENGINE_LOG_LEVEL", "info"),
	}
	if s := env("EXAM_ENGINE_REDIS_SENTINELS", ""); s != "" {
		for _, p := range strings.Split(s, ",") {
			if p = strings.TrimSpace(p); p != "" {
				c.RedisSentinels = append(c.RedisSentinels, p)
			}
		}
	}
	if k := env("EXAM_ENGINE_LAUNCH_PUBLIC_KEY", ""); k != "" {
		pk, err := entry.ParsePublicKey(k)
		if err != nil {
			return nil, fmt.Errorf("EXAM_ENGINE_LAUNCH_PUBLIC_KEY: %w", err)
		}
		c.LaunchPublicKey = pk
	}
	var err error
	if c.BcryptWorkers, err = envInt("EXAM_ENGINE_BCRYPT_WORKERS", runtime.NumCPU()); err != nil {
		return nil, err
	}
	wait, err := envInt("EXAM_ENGINE_BCRYPT_MAX_WAIT_SECONDS", 20)
	if err != nil {
		return nil, err
	}
	c.BcryptMaxWait = time.Duration(wait) * time.Second
	if c.BcryptMaxQueue, err = envInt("EXAM_ENGINE_BCRYPT_MAX_QUEUE", 5000); err != nil {
		return nil, err
	}
	if c.RedisPoolSize, err = envInt("EXAM_ENGINE_REDIS_POOL_SIZE", 32*runtime.NumCPU()); err != nil {
		return nil, err
	}
	c.PaceThresholds = [3]int{500, 1500, 3000}
	if s := env("EXAM_ENGINE_PACE_THRESHOLDS", ""); s != "" {
		parts := strings.Split(s, ",")
		if len(parts) != 3 {
			return nil, fmt.Errorf("EXAM_ENGINE_PACE_THRESHOLDS must be three numbers, e.g. 500,1500,3000")
		}
		for i, p := range parts {
			n, err := strconv.Atoi(strings.TrimSpace(p))
			if err != nil {
				return nil, fmt.Errorf("EXAM_ENGINE_PACE_THRESHOLDS: %w", err)
			}
			c.PaceThresholds[i] = n
		}
	}
	// Hard safety cap on ex:events (XADD MAXLEN ~). 0 = off: events are only trimmed once
	// delivered and acknowledged by the `laravel` group (sweeper leader, XTRIM MINID).
	hc, err := envInt("EXAM_ENGINE_EVENTS_HARD_CAP", 0)
	if err != nil {
		return nil, err
	}
	c.EventsMaxLen = int64(max(hc, 0))
	ms, err := envInt("EXAM_ENGINE_RUN_CACHE_MS", 1000)
	if err != nil {
		return nil, err
	}
	c.RunCacheTTL = time.Duration(ms) * time.Millisecond
	c.SweepInterval = 5 * time.Second
	snap, err := envInt("EXAM_ENGINE_SNAPSHOT_SECONDS", 300)
	if err != nil {
		return nil, err
	}
	c.SnapshotEvery = time.Duration(max(snap, 120)) * time.Second
	c.SnapshotScan = 30 * time.Second
	c.OfflineAfter = 60 * time.Second
	c.SessionCacheTTL = 60 * time.Second
	host, _ := os.Hostname()
	c.NodeID = fmt.Sprintf("%s-%d", host, os.Getpid())
	return c, nil
}
