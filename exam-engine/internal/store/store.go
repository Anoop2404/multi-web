// Package store owns the Redis layout (CONTRACT §8) and the Lua scripts that make sync, submit
// and the other attempt transitions atomic. All run keys share the hash tag {run}.
package store

import (
	"context"
	"crypto/tls"
	"embed"
	"fmt"
	"strconv"
	"strings"
	"time"

	"github.com/redis/go-redis/v9"
)

//go:embed lua/*.lua
var luaFS embed.FS

// Global keys.
const (
	KeyEvents       = "ex:events"
	KeyLeader       = "ex:leader:sweeper"
	KeyRuns         = "ex:runs"
	EventsGroup     = "laravel"
	SessionPrefix   = "ex:sess:"
	JTIPrefix       = "ex:jti:"
	SahodayaPrefix  = "ex:sahodaya:"
	CloseRetention  = 7 * 24 * time.Hour
	DefaultMaxLen   = 0 // no MAXLEN: see events.Trim
	rosterScriptMax = 2000
)

// Keys builds the per-run key names.
type Keys struct {
	Run    string
	prefix string
}

// K returns the key builder for a run.
func K(run string) Keys { return Keys{Run: run, prefix: "ex:{" + run + "}:"} }

func (k Keys) Prefix() string    { return k.prefix }
func (k Keys) Meta() string      { return k.prefix + "meta" }
func (k Keys) Key() string       { return k.prefix + "key" }
func (k Keys) Bundle() string    { return k.prefix + "bundle" }
func (k Keys) Schema() string    { return k.prefix + "qs" }
func (k Keys) Roster() string    { return k.prefix + "roster" }
func (k Keys) IdxUser() string   { return k.prefix + "idx:user" }
func (k Keys) IdxTicket() string { return k.prefix + "idx:ticket" }
func (k Keys) AttPrefix() string { return k.prefix + "att:" }
func (k Keys) Att(reg int64) string {
	return k.prefix + "att:" + strconv.FormatInt(reg, 10)
}
func (k Keys) Seen() string      { return k.prefix + "seen" }
func (k Keys) Deadlines() string { return k.prefix + "dl" }
func (k Keys) Agg() string       { return k.prefix + "agg" }
func (k Keys) Msgs() string      { return k.prefix + "msgs" }
func (k Keys) Results() string   { return k.prefix + "results" }
func (k Keys) Extra() string     { return k.prefix + "extra" }
func (k Keys) Schools() string   { return k.prefix + "schools" }
func (k Keys) Ctl() string       { return k.prefix + "ctl" }
func (k Keys) Fail(id string) string {
	return k.prefix + "fail:" + id
}

// Pattern matches every key of the run (run ids are validated to a glob-safe alphabet).
func (k Keys) Pattern() string { return k.prefix + "*" }

// SahodayaKey is the set of runs armed for a Sahodaya code.
func SahodayaKey(code string) string { return SahodayaPrefix + code }

// Scripts holds the loaded Lua scripts.
type Scripts struct {
	Checkin, Start, Sync, Submit, Action, RosterUpsert, RosterDelete, Snapshot, Logout, Leader, Unleader, ISO *redis.Script
}

// Store wraps the Redis client.
type Store struct {
	R       redis.UniversalClient
	MaxLen  int64
	Scripts Scripts
}

// Options configures the Redis connection.
type Options struct {
	URL       string
	Sentinels []string
	Master    string
	PoolSize  int
}

func loadScript(name string) *redis.Script {
	prelude, err := luaFS.ReadFile("lua/prelude.lua")
	if err != nil {
		panic(err)
	}
	body, err := luaFS.ReadFile("lua/" + name + ".lua")
	if err != nil {
		panic(err)
	}
	return redis.NewScript(string(prelude) + "\n" + string(body))
}

func newScripts() Scripts {
	return Scripts{
		Checkin:      loadScript("checkin"),
		Start:        loadScript("start"),
		Sync:         loadScript("sync"),
		Submit:       loadScript("submit"),
		Action:       loadScript("action"),
		RosterUpsert: loadScript("roster_upsert"),
		RosterDelete: loadScript("roster_delete"),
		Snapshot:     loadScript("snapshot"),
		Logout:       loadScript("logout"),
		Leader:       loadScript("leader"),
		Unleader:     loadScript("unlock_leader"),
		ISO:          loadScript("iso"),
	}
}

// New connects to Redis (a URL, or Sentinel when sentinels + master are given).
func New(o Options) (*Store, error) {
	opt, err := redis.ParseURL(o.URL)
	if err != nil {
		return nil, fmt.Errorf("EXAM_ENGINE_REDIS_URL: %w", err)
	}
	pool := o.PoolSize
	if pool <= 0 {
		pool = 64
	}
	var c redis.UniversalClient
	if len(o.Sentinels) > 0 && o.Master != "" {
		fo := &redis.FailoverOptions{
			MasterName:            o.Master,
			SentinelAddrs:         o.Sentinels,
			Username:              opt.Username,
			Password:              opt.Password,
			DB:                    opt.DB,
			PoolSize:              pool,
			TLSConfig:             opt.TLSConfig,
			ContextTimeoutEnabled: true,
			ReadTimeout:           5 * time.Second,
			WriteTimeout:          5 * time.Second,
		}
		c = redis.NewFailoverClient(fo)
	} else {
		opt.PoolSize = pool
		opt.MinIdleConns = pool / 8
		opt.PipelinePoolSize = pool / 2
		opt.ContextTimeoutEnabled = true
		opt.ReadTimeout = 5 * time.Second
		opt.WriteTimeout = 5 * time.Second
		if strings.HasPrefix(o.URL, "rediss://") && opt.TLSConfig == nil {
			opt.TLSConfig = &tls.Config{MinVersion: tls.VersionTLS12}
		}
		c = redis.NewClient(opt)
	}
	return &Store{R: c, MaxLen: DefaultMaxLen, Scripts: newScripts()}, nil
}

// NewWithClient wraps an existing client (tests).
func NewWithClient(c redis.UniversalClient) *Store {
	return &Store{R: c, MaxLen: DefaultMaxLen, Scripts: newScripts()}
}

// Prepare loads the scripts and makes sure the event consumer group exists.
func (s *Store) Prepare(ctx context.Context) error {
	for _, sc := range []*redis.Script{s.Scripts.Checkin, s.Scripts.Start, s.Scripts.Sync, s.Scripts.Submit, s.Scripts.Action,
		s.Scripts.RosterUpsert, s.Scripts.RosterDelete, s.Scripts.Snapshot, s.Scripts.Logout, s.Scripts.Leader, s.Scripts.Unleader} {
		if err := sc.Load(ctx, s.R).Err(); err != nil {
			return fmt.Errorf("loading Lua script: %w", err)
		}
	}
	return s.EnsureGroup(ctx)
}

// EnsureGroup creates the `laravel` consumer group on ex:events (from the start of the stream).
func (s *Store) EnsureGroup(ctx context.Context) error {
	err := s.R.XGroupCreateMkStream(ctx, KeyEvents, EventsGroup, "0").Err()
	if err != nil && !strings.Contains(err.Error(), "BUSYGROUP") {
		return err
	}
	return nil
}

// Ping checks connectivity.
func (s *Store) Ping(ctx context.Context) error { return s.R.Ping(ctx).Err() }

// Close closes the client.
func (s *Store) Close() error { return s.R.Close() }

// ---- result parsing helpers ----

// Str returns element i of a script reply as a string.
func Str(vals []any, i int) string {
	if i >= len(vals) {
		return ""
	}
	switch v := vals[i].(type) {
	case string:
		return v
	case int64:
		return strconv.FormatInt(v, 10)
	case nil:
		return ""
	default:
		return fmt.Sprint(v)
	}
}

// Int returns element i of a script reply as an int64.
func Int(vals []any, i int) int64 {
	if i >= len(vals) {
		return 0
	}
	switch v := vals[i].(type) {
	case int64:
		return v
	case string:
		n, _ := strconv.ParseInt(v, 10, 64)
		return n
	default:
		return 0
	}
}

// Slice converts a script reply to []any.
func Slice(v any, err error) ([]any, error) {
	if err != nil {
		return nil, err
	}
	s, ok := v.([]any)
	if !ok {
		return nil, fmt.Errorf("unexpected script reply %T", v)
	}
	return s, nil
}

// ScanRunKeys returns every key of a run (used by close/disarm; not on hot paths).
func (s *Store) ScanRunKeys(ctx context.Context, run string) ([]string, error) {
	var keys []string
	var cursor uint64
	pat := K(run).Pattern()
	for {
		ks, next, err := s.R.Scan(ctx, cursor, pat, 1000).Result()
		if err != nil {
			return nil, err
		}
		keys = append(keys, ks...)
		cursor = next
		if cursor == 0 {
			break
		}
	}
	return keys, nil
}

// ExpireRun sets a TTL on every key of the run.
func (s *Store) ExpireRun(ctx context.Context, run string, ttl time.Duration) error {
	keys, err := s.ScanRunKeys(ctx, run)
	if err != nil {
		return err
	}
	for i := 0; i < len(keys); i += 500 {
		end := min(i+500, len(keys))
		pipe := s.R.Pipeline()
		for _, k := range keys[i:end] {
			pipe.Expire(ctx, k, ttl)
		}
		if _, err := pipe.Exec(ctx); err != nil {
			return err
		}
	}
	return nil
}

// DeleteRun removes every key of the run.
func (s *Store) DeleteRun(ctx context.Context, run string) error {
	keys, err := s.ScanRunKeys(ctx, run)
	if err != nil {
		return err
	}
	for i := 0; i < len(keys); i += 500 {
		end := min(i+500, len(keys))
		if err := s.R.Unlink(ctx, keys[i:end]...).Err(); err != nil {
			return err
		}
	}
	return nil
}
