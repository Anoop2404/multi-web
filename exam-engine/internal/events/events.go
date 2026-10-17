// Package events moves engine events to Laravel through the Redis stream ex:events and its
// consumer group `laravel` (CONTRACT §3 claim/ack, §6). Most events are written atomically by the
// Lua scripts; Emit covers the few written from Go (broadcast, run.closed).
package events

import (
	"context"
	"encoding/json"
	"errors"
	"strconv"
	"strings"
	"time"

	"github.com/redis/go-redis/v9"

	"github.com/sahodaya/exam-engine/internal/model"
	"github.com/sahodaya/exam-engine/internal/store"
)

// Event is one event as Laravel receives it.
type Event struct {
	ID    string          `json:"id"`
	Type  string          `json:"type"`
	Run   string          `json:"run"`
	RegID *int64          `json:"reg_id"`
	At    string          `json:"at"`
	Data  json.RawMessage `json:"data"`
}

// Emit appends an event from Go.
func Emit(ctx context.Context, s *store.Store, typ, run string, regID int64, at time.Time, data any) (string, error) {
	b, err := json.Marshal(data)
	if err != nil {
		return "", err
	}
	reg := ""
	if regID > 0 {
		reg = strconv.FormatInt(regID, 10)
	}
	args := &redis.XAddArgs{
		Stream: store.KeyEvents,
		Values: []any{"type", typ, "run", run, "reg_id", reg, "at", model.ISO(at), "data", string(b)},
	}
	if s.MaxLen > 0 {
		args.MaxLen, args.Approx = s.MaxLen, true
	}
	return s.R.XAdd(ctx, args).Result()
}

type streamID struct{ ms, seq uint64 }

func parseID(s string) (streamID, bool) {
	a, b, ok := strings.Cut(s, "-")
	if !ok {
		return streamID{}, false
	}
	ms, e1 := strconv.ParseUint(a, 10, 64)
	seq, e2 := strconv.ParseUint(b, 10, 64)
	return streamID{ms, seq}, e1 == nil && e2 == nil
}

func (x streamID) less(y streamID) bool { return x.ms < y.ms || (x.ms == y.ms && x.seq < y.seq) }

// TrimPoint is the id below which every entry has been delivered to and acknowledged by the
// `laravel` group: the oldest of the group's last-delivered-id and its oldest pending entry.
// ok=false means nothing may be trimmed (no group, or nothing delivered yet).
func TrimPoint(ctx context.Context, s *store.Store) (string, bool, error) {
	groups, err := s.R.XInfoGroups(ctx, store.KeyEvents).Result()
	if err != nil {
		if strings.Contains(err.Error(), "no such key") {
			return "", false, nil
		}
		return "", false, err
	}
	var last string
	for _, g := range groups {
		if g.Name == store.EventsGroup {
			last = g.LastDeliveredID
		}
	}
	lastID, ok := parseID(last)
	if !ok || (lastID == streamID{}) {
		return "", false, nil
	}
	min := last
	p, err := s.R.XPending(ctx, store.KeyEvents, store.EventsGroup).Result()
	if err != nil && !errors.Is(err, redis.Nil) {
		return "", false, err
	}
	if p != nil && p.Count > 0 {
		if lo, ok := parseID(p.Lower); ok && lo.less(lastID) {
			min = p.Lower
		}
	}
	return min, true, nil
}

// Trim removes delivered+acknowledged events (approximate, whole stream nodes only, so it never
// removes an entry at or after the trim point). Returns how many entries were removed.
func Trim(ctx context.Context, s *store.Store) (int64, error) {
	id, ok, err := TrimPoint(ctx, s)
	if err != nil || !ok {
		return 0, err
	}
	return s.R.XTrimMinIDApprox(ctx, store.KeyEvents, id, 0).Result()
}

// Stats returns the stream length and the group's pending (delivered, unacknowledged) count.
func Stats(ctx context.Context, s *store.Store) (length, pending int64, err error) {
	pipe := s.R.Pipeline()
	l := pipe.XLen(ctx, store.KeyEvents)
	p := pipe.XPending(ctx, store.KeyEvents, store.EventsGroup)
	_, _ = pipe.Exec(ctx)
	length, err = l.Result()
	if err != nil && !errors.Is(err, redis.Nil) {
		return 0, 0, err
	}
	if pv, perr := p.Result(); perr == nil && pv != nil {
		pending = pv.Count
	}
	return length, pending, nil
}

func str(v any) string {
	switch x := v.(type) {
	case string:
		return x
	case nil:
		return ""
	default:
		b, _ := json.Marshal(x)
		return string(b)
	}
}

func convert(m redis.XMessage) (Event, bool) {
	if len(m.Values) == 0 {
		return Event{ID: m.ID}, false // trimmed from the stream while pending
	}
	e := Event{ID: m.ID, Type: str(m.Values["type"]), Run: str(m.Values["run"]), At: str(m.Values["at"])}
	if r := str(m.Values["reg_id"]); r != "" {
		if n, err := strconv.ParseInt(r, 10, 64); err == nil {
			e.RegID = &n
		}
	}
	d := str(m.Values["data"])
	if d == "" || !json.Valid([]byte(d)) {
		d = "{}"
	}
	e.Data = json.RawMessage(d)
	return e, true
}

// StaleClaimAfter: pending entries of other consumers idle this long are taken over.
const StaleClaimAfter = 5 * time.Minute

// Claim returns this consumer's pending entries first; if there are none, entries abandoned by
// other consumers (idle > 5 min); then new entries, blocking up to block.
func Claim(ctx context.Context, s *store.Store, consumer string, count int64, block time.Duration) ([]Event, error) {
	out := make([]Event, 0, count)
	var dead []string
	collect := func(msgs []redis.XMessage) {
		for _, m := range msgs {
			if e, ok := convert(m); ok {
				out = append(out, e)
			} else {
				dead = append(dead, m.ID)
			}
		}
	}
	defer func() {
		if len(dead) > 0 {
			_ = s.R.XAck(context.Background(), store.KeyEvents, store.EventsGroup, dead...).Err()
		}
	}()

	// 1. our own pending entries (delivered before, not yet acknowledged)
	res, err := s.R.XReadGroup(ctx, &redis.XReadGroupArgs{
		Group: store.EventsGroup, Consumer: consumer, Streams: []string{store.KeyEvents, "0"}, Count: count, Block: -1,
	}).Result()
	if err != nil && !errors.Is(err, redis.Nil) {
		if isNoGroup(err) {
			if err := s.EnsureGroup(ctx); err != nil {
				return nil, err
			}
			return Claim(ctx, s, consumer, count, block)
		}
		return nil, err
	}
	for _, st := range res {
		collect(st.Messages)
	}
	if len(out) > 0 || len(dead) > 0 {
		return out, nil
	}

	// 2. entries abandoned by consumers that went away
	msgs, _, err := s.R.XAutoClaim(ctx, &redis.XAutoClaimArgs{
		Stream: store.KeyEvents, Group: store.EventsGroup, Consumer: consumer, MinIdle: StaleClaimAfter, Start: "0-0", Count: count,
	}).Result()
	if err == nil && len(msgs) > 0 {
		collect(msgs)
		if len(out) > 0 {
			return out, nil
		}
	}

	// 3. new entries
	res, err = s.R.XReadGroup(ctx, &redis.XReadGroupArgs{
		Group: store.EventsGroup, Consumer: consumer, Streams: []string{store.KeyEvents, ">"}, Count: count, Block: block,
	}).Result()
	if err != nil {
		if errors.Is(err, redis.Nil) {
			return out, nil
		}
		return nil, err
	}
	for _, st := range res {
		collect(st.Messages)
	}
	return out, nil
}

func isNoGroup(err error) bool {
	return err != nil && len(err.Error()) >= 7 && err.Error()[:7] == "NOGROUP"
}

// Ack acknowledges entries.
func Ack(ctx context.Context, s *store.Store, ids []string) (int64, error) {
	if len(ids) == 0 {
		return 0, nil
	}
	return s.R.XAck(ctx, store.KeyEvents, store.EventsGroup, ids...).Result()
}
