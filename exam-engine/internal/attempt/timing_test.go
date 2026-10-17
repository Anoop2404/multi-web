package attempt

import (
	"testing"
	"time"

	"github.com/sahodaya/exam-engine/internal/model"
)

func meta(timing string) *model.Meta {
	starts := time.Date(2026, 11, 20, 4, 30, 0, 0, time.UTC)
	return &model.Meta{
		Timing:           timing,
		CheckinOpensAt:   starts.Add(-48 * time.Hour),
		WaitingOpensAt:   starts.Add(-time.Hour),
		StartsAt:         starts,
		DurationMinutes:  60,
		LateEntryMinutes: 15,
		GraceSeconds:     90,
	}
}

func TestRunStateByClock(t *testing.T) {
	m := meta("fixed")
	s := m.StartsAt
	cases := []struct {
		at     time.Time
		extra  int
		closed bool
		want   string
	}{
		{s.Add(-49 * time.Hour), 0, false, RunArmed},
		{s.Add(-47 * time.Hour), 0, false, RunCheckin},
		{s.Add(-30 * time.Minute), 0, false, RunWaiting},
		{s, 0, false, RunLive},
		{s.Add(61 * time.Minute), 0, false, RunLive},  // within grace
		{s.Add(62 * time.Minute), 0, false, RunEnded}, // past deadline + 90 s
		{s.Add(62 * time.Minute), 10, false, RunLive}, // someone has 10 extra minutes
		{s.Add(-30 * time.Minute), 0, true, RunClosed},
	}
	for i, c := range cases {
		if got := RunState(m, c.extra, c.closed, c.at); got != c.want {
			t.Errorf("case %d: got %s want %s", i, got, c.want)
		}
	}
	// individual timing: last start is at late_entry_until, so it ends later
	mi := meta("individual")
	if got := RunState(mi, 0, false, s.Add(62*time.Minute)); got != RunLive {
		t.Errorf("individual: got %s", got)
	}
	if got := RunState(mi, 0, false, s.Add(77*time.Minute)); got != RunEnded {
		t.Errorf("individual end: got %s", got)
	}
}

func TestDeadlines(t *testing.T) {
	m := meta("fixed")
	started := m.StartsAt.Add(10 * time.Minute)
	if got := Deadline(m, started, 5); !got.Equal(m.StartsAt.Add(65 * time.Minute)) {
		t.Errorf("fixed deadline = %v", got)
	}
	mi := meta("individual")
	if got := Deadline(mi, started, 5); !got.Equal(started.Add(65 * time.Minute)) {
		t.Errorf("individual deadline = %v", got)
	}
	if DeadlineBase(m) != m.StartsAt.UnixMilli() || DeadlineBase(mi) != 0 {
		t.Error("deadline base")
	}
	if got := LateEntryUntil(m); !got.Equal(m.StartsAt.Add(15 * time.Minute)) {
		t.Errorf("late entry until = %v", got)
	}
}

func TestStartWindowAndView(t *testing.T) {
	m := meta("fixed")
	if CanStart(m, m.StartsAt.Add(-time.Second)) != StartTooEarly {
		t.Error("too early")
	}
	if CanStart(m, m.StartsAt) != StartOK || CanStart(m, m.StartsAt.Add(15*time.Minute)) != StartOK {
		t.Error("window")
	}
	if CanStart(m, m.StartsAt.Add(15*time.Minute+time.Second)) != StartTooLate {
		t.Error("too late")
	}
	cases := []struct {
		st   Stored
		at   time.Time
		want string
	}{
		{Stored{State: model.StCheckedIn}, m.StartsAt.Add(-2 * time.Hour), ViewCheckedIn},
		{Stored{State: model.StCheckedIn}, m.StartsAt.Add(-time.Minute), ViewWaiting},
		{Stored{State: model.StCheckedIn}, m.StartsAt.Add(20 * time.Minute), ViewEnded},
		{Stored{State: model.StWriting}, m.StartsAt.Add(time.Minute), ViewWriting},
		{Stored{State: model.StWriting, Locked: true}, m.StartsAt.Add(time.Minute), ViewLocked},
		{Stored{State: model.StSubmitted, Locked: true}, m.StartsAt.Add(time.Minute), ViewSubmitted},
	}
	for i, c := range cases {
		if got := View(m, c.st, c.at); got != c.want {
			t.Errorf("view case %d: got %s want %s", i, got, c.want)
		}
	}
}

func TestChooseRun(t *testing.T) {
	now := time.Date(2026, 11, 20, 5, 0, 0, 0, time.UTC)
	live := meta("fixed") // starts 04:30, window until ~05:31
	later := meta("fixed")
	later.StartsAt = now.Add(72 * time.Hour)
	later.CheckinOpensAt = now.Add(24 * time.Hour)
	later.WaitingOpensAt = later.StartsAt.Add(-time.Hour)
	soon := meta("fixed")
	soon.StartsAt = now.Add(50 * time.Hour)
	soon.CheckinOpensAt = now.Add(2 * time.Hour)
	soon.WaitingOpensAt = soon.StartsAt.Add(-time.Hour)
	zero := func(*model.Meta) int { return 0 }
	if i := ChooseRun([]*model.Meta{later, live, soon}, zero, now); i != 1 {
		t.Errorf("window run not chosen: %d", i)
	}
	if i := ChooseRun([]*model.Meta{later, soon}, zero, now); i != 1 {
		t.Errorf("soonest upcoming not chosen: %d", i)
	}
	old := meta("fixed")
	old.StartsAt = now.Add(-72 * time.Hour)
	old.CheckinOpensAt = old.StartsAt.Add(-48 * time.Hour)
	old.WaitingOpensAt = old.StartsAt.Add(-time.Hour)
	if i := ChooseRun([]*model.Meta{old}, zero, now); i != 0 {
		t.Errorf("ended run fallback: %d", i)
	}
}
