// Package sweeper is the leader-elected background loop (CONTRACT §7): it auto-submits attempts
// past deadline + grace (unless the run is on hold), force-finalises stragglers of closed runs,
// emits answer snapshots (EXAM_ENGINE_SNAPSHOT_SECONDS per changed attempt), trims events that
// Laravel has acknowledged, and expires runs nobody closed.
package sweeper

import (
	"context"
	"strconv"
	"sync/atomic"
	"time"

	"github.com/redis/go-redis/v9"

	"github.com/sahodaya/exam-engine/internal/admin"
	"github.com/sahodaya/exam-engine/internal/attempt"
	"github.com/sahodaya/exam-engine/internal/engine"
	"github.com/sahodaya/exam-engine/internal/events"
	"github.com/sahodaya/exam-engine/internal/store"
)

// LeaderTTL is the leader lock lifetime (renewed every sweep).
const LeaderTTL = 10 * time.Second

// Sweeper runs the loop.
type Sweeper struct {
	E        *engine.Engine
	leader   atomic.Bool
	lastSnap time.Time
}

// New creates a sweeper.
func New(e *engine.Engine) *Sweeper {
	s := &Sweeper{E: e}
	e.M.Reg.GaugeFunc("exam_sweeper_leader", "1 when this node holds the sweeper lock.", func() float64 {
		if s.leader.Load() {
			return 1
		}
		return 0
	})
	return s
}

// IsLeader reports whether this node is the sweeper leader.
func (s *Sweeper) IsLeader() bool { return s.leader.Load() }

// Run loops until ctx is done, then releases the lock.
func (s *Sweeper) Run(ctx context.Context) {
	t := time.NewTicker(s.E.Cfg.SweepInterval)
	defer t.Stop()
	for {
		s.Tick(ctx)
		select {
		case <-ctx.Done():
			if s.leader.Load() {
				_ = s.E.St.Scripts.Unleader.Run(context.Background(), s.E.St.R, []string{store.KeyLeader}, s.E.Cfg.NodeID).Err()
			}
			return
		case <-t.C:
		}
	}
}

// Tick does one sweep if this node is (or becomes) the leader.
func (s *Sweeper) Tick(ctx context.Context) {
	ok, err := s.E.St.Scripts.Leader.Run(ctx, s.E.St.R, []string{store.KeyLeader}, s.E.Cfg.NodeID, LeaderTTL.Milliseconds()).Int()
	if err != nil {
		s.E.M.RedisErrors.Inc("leader")
		s.leader.Store(false)
		return
	}
	s.leader.Store(ok == 1)
	if ok != 1 {
		return
	}
	snap := time.Since(s.lastSnap) >= s.E.Cfg.SnapshotScan
	if snap {
		s.lastSnap = time.Now()
	}
	if n, err := events.Trim(ctx, s.E.St); err != nil {
		s.E.Log.Warn("event trim failed", "err", err)
	} else if n > 0 {
		s.E.M.EventsTrimmed.Add(n)
	}
	runs, err := s.E.St.R.SMembers(ctx, store.KeyRuns).Result()
	if err != nil {
		return
	}
	for _, run := range runs {
		if ctx.Err() != nil {
			return
		}
		if err := s.sweepRun(ctx, run, snap); err != nil {
			s.E.Log.Warn("sweep failed", "run", run, "err", err)
		}
	}
}

func (s *Sweeper) sweepRun(ctx context.Context, run string, snap bool) error {
	rc, err := s.E.LoadRun(ctx, run)
	if err != nil {
		return err
	}
	if !rc.Exists() {
		return s.E.St.R.SRem(ctx, store.KeyRuns, run).Err()
	}
	now := s.E.Now()
	if rc.Closed {
		// stragglers that started after close was issued (cached contexts on other nodes)
		regs, err := s.E.St.R.ZRange(ctx, rc.K.Deadlines(), 0, 999).Result()
		if err != nil || len(regs) == 0 {
			return err
		}
		n, err := admin.Finalise(ctx, s.E, rc, regs, "forced", false)
		s.E.M.SweeperSubmits.Add(int64(n), "forced")
		return err
	}
	end := attempt.RunEnd(rc.Meta, rc.Extra())
	if now.After(end.Add(store.CloseRetention)) {
		// never closed: expire it like a close would, so password-hash copies don't linger
		s.E.Log.Info("expiring run that was never closed", "run", run)
		if err := s.E.St.ExpireRun(ctx, run, 24*time.Hour); err != nil {
			return err
		}
		return s.E.St.R.SRem(ctx, store.KeyRuns, run).Err()
	}
	if now.After(end.Add(24 * time.Hour)) {
		return nil // left alone until close/expiry
	}
	// auto-submit past deadline + grace (stored deadlines exclude the run-level extra); a run on
	// hold is left alone, and submit.lua re-checks both atomically
	due := now.UnixMilli() - rc.GraceMs() - int64(rc.RunExtra)*60000
	var regs []string
	if !rc.Hold {
		regs, err = s.E.St.R.ZRangeByScore(ctx, rc.K.Deadlines(), &redis.ZRangeBy{Min: "-inf", Max: "(" + strconv.FormatInt(due, 10), Count: 2000}).Result()
		if err != nil {
			return err
		}
	}
	if len(regs) > 0 {
		n, err := admin.Finalise(ctx, s.E, rc, regs, "auto", true)
		s.E.M.SweeperSubmits.Add(int64(n), "auto")
		if err != nil {
			return err
		}
	}
	if snap {
		return s.snapshots(ctx, rc, now)
	}
	return nil
}

func (s *Sweeper) snapshots(ctx context.Context, rc *engine.RunCtx, now time.Time) error {
	regs, err := s.E.St.R.ZRange(ctx, rc.K.Seen(), 0, -1).Result()
	if err != nil {
		return err
	}
	for i := 0; i < len(regs); i += 500 {
		end := min(i+500, len(regs))
		args := make([]any, 0, 5+end-i)
		args = append(args, now.UnixMilli(), s.E.Cfg.SnapshotEvery.Milliseconds(), rc.Run, s.E.St.MaxLen, rc.K.AttPrefix())
		for _, r := range regs[i:end] {
			args = append(args, r)
		}
		n, err := s.E.St.Scripts.Snapshot.Run(ctx, s.E.St.R, []string{store.KeyEvents}, args...).Int64()
		if err != nil {
			return err
		}
		s.E.M.Snapshots.Add(n)
	}
	return nil
}
