// Package attempt holds the clock rules for runs and attempts (CONTRACT §7). Everything here is
// pure: callers pass the current time, which keeps the rules unit-testable.
package attempt

import (
	"time"

	"github.com/sahodaya/exam-engine/internal/model"
)

// Run states (CONTRACT §7).
const (
	RunArmed   = "armed"
	RunCheckin = "checkin"
	RunWaiting = "waiting"
	RunLive    = "live"
	RunEnded   = "ended"
	RunClosed  = "closed"
)

// View states returned to the student (CONTRACT §4 GET /api/state).
const (
	ViewCheckedIn = "checked_in"
	ViewWaiting   = "waiting"
	ViewWriting   = "writing"
	ViewSubmitted = "submitted"
	ViewLocked    = "locked"
	ViewEnded     = "ended"
)

func minutes(n int) time.Duration { return time.Duration(n) * time.Minute }

// Duration of the exam proper.
func Duration(m *model.Meta) time.Duration { return minutes(m.DurationMinutes) }

// Grace after the deadline during which final changes are still accepted.
func Grace(m *model.Meta) time.Duration { return time.Duration(m.GraceSeconds) * time.Second }

// LateEntryUntil is the last moment /api/start is accepted.
func LateEntryUntil(m *model.Meta) time.Time { return m.StartsAt.Add(minutes(m.LateEntryMinutes)) }

// Deadline for an attempt: fixed → starts_at+duration+extra; individual → started_at+duration+extra.
func Deadline(m *model.Meta, startedAt time.Time, extraMinutes int) time.Time {
	base := m.StartsAt
	if m.Timing == "individual" {
		base = startedAt
	}
	return base.Add(Duration(m) + minutes(extraMinutes))
}

// DeadlineBase returns the base (in ms) the Lua scripts add duration+extra to; for individual
// timing the script uses the attempt's own started_at, so this returns 0.
func DeadlineBase(m *model.Meta) int64 {
	if m.Timing == "individual" {
		return 0
	}
	return m.StartsAt.UnixMilli()
}

// LastDeadline is the latest deadline any attempt can have given the largest extra time.
func LastDeadline(m *model.Meta, maxExtra int) time.Time {
	if m.Timing == "individual" {
		return LateEntryUntil(m).Add(Duration(m) + minutes(maxExtra))
	}
	return m.StartsAt.Add(Duration(m) + minutes(maxExtra))
}

// RunEnd is when the run becomes "ended": last possible deadline + grace.
func RunEnd(m *model.Meta, maxExtra int) time.Time {
	return LastDeadline(m, maxExtra).Add(Grace(m))
}

// RunState by clock (CONTRACT §7).
func RunState(m *model.Meta, maxExtra int, closed bool, now time.Time) string {
	switch {
	case closed:
		return RunClosed
	case now.Before(m.CheckinOpensAt):
		return RunArmed
	case now.Before(m.WaitingOpensAt):
		return RunCheckin
	case now.Before(m.StartsAt):
		return RunWaiting
	case !now.After(RunEnd(m, maxExtra)):
		return RunLive
	default:
		return RunEnded
	}
}

// InWindow reports whether now lies in the run's login window (checkin_opens_at … run end).
func InWindow(m *model.Meta, maxExtra int, now time.Time) bool {
	return !now.Before(m.CheckinOpensAt) && !now.After(RunEnd(m, maxExtra))
}

// Stored is the slice of the attempt hash the view needs.
type Stored struct {
	State     string // "", checked_in, writing, submitted
	Locked    bool
	StartedAt time.Time
}

// View derives the student-facing attempt state.
func View(m *model.Meta, s Stored, now time.Time) string {
	switch {
	case s.State == model.StSubmitted:
		return ViewSubmitted
	case s.Locked:
		return ViewLocked
	case s.State == model.StWriting:
		return ViewWriting
	case now.After(LateEntryUntil(m)):
		return ViewEnded
	case !now.Before(m.WaitingOpensAt):
		return ViewWaiting
	default:
		return ViewCheckedIn
	}
}

// StartCheck is the outcome of the clock rules for POST /api/start.
type StartCheck int

const (
	StartOK StartCheck = iota
	StartTooEarly
	StartTooLate
)

// CanStart applies "allowed from starts_at until late_entry_until".
func CanStart(m *model.Meta, now time.Time) StartCheck {
	if now.Before(m.StartsAt) {
		return StartTooEarly
	}
	if now.After(LateEntryUntil(m)) {
		return StartTooLate
	}
	return StartOK
}

// ChooseRun picks among candidate runs for one identity: the run whose window contains now,
// else the soonest upcoming, else the most recently ended (CONTRACT §4 login).
func ChooseRun(cands []*model.Meta, maxExtra func(*model.Meta) int, now time.Time) int {
	best := -1
	// 1. window contains now (prefer the earliest start among those)
	for i, m := range cands {
		if InWindow(m, maxExtra(m), now) {
			if best < 0 || m.StartsAt.Before(cands[best].StartsAt) {
				best = i
			}
		}
	}
	if best >= 0 {
		return best
	}
	// 2. soonest upcoming
	for i, m := range cands {
		if now.Before(m.CheckinOpensAt) {
			if best < 0 || m.StartsAt.Before(cands[best].StartsAt) {
				best = i
			}
		}
	}
	if best >= 0 {
		return best
	}
	// 3. most recently ended
	for i, m := range cands {
		if best < 0 || m.StartsAt.After(cands[best].StartsAt) {
			best = i
		}
	}
	return best
}
