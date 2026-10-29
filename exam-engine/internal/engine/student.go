package engine

import (
	"context"
	"crypto/subtle"
	"encoding/json"
	"errors"
	"net/http"
	"sort"
	"strconv"
	"time"

	"github.com/redis/go-redis/v9"

	"github.com/sahodaya/exam-engine/internal/attempt"
	"github.com/sahodaya/exam-engine/internal/httpx"
	"github.com/sahodaya/exam-engine/internal/model"
	"github.com/sahodaya/exam-engine/internal/paper"
	"github.com/sahodaya/exam-engine/internal/store"
	"github.com/sahodaya/exam-engine/internal/syncer"
)

// StateResp is GET /api/state (CONTRACT §4). grace_seconds and heartbeat_seconds are extras the
// client may use.
type StateResp struct {
	Run               string          `json:"run"`
	RegID             int64           `json:"reg_id"`
	Name              string          `json:"name"`
	HallTicket        string          `json:"hall_ticket"`
	SchoolName        string          `json:"school_name"`
	ClassName         string          `json:"class_name"`
	Title             string          `json:"title"`
	SahodayaName      string          `json:"sahodaya_name"`
	LogoURL           *string         `json:"logo_url"`
	State             string          `json:"state"`
	ServerTime        string          `json:"server_time"`
	CheckinOpensAt    string          `json:"checkin_opens_at"`
	WaitingOpensAt    string          `json:"waiting_opens_at"`
	StartsAt          string          `json:"starts_at"`
	LateEntryUntil    string          `json:"late_entry_until"`
	Deadline          *string         `json:"deadline"`
	DurationMinutes   int             `json:"duration_minutes"`
	ExtraMinutes      int             `json:"extra_minutes"`
	Supervision       string          `json:"supervision"`
	StartPinRequired  bool            `json:"start_pin_required"`
	BundleURL         string          `json:"bundle_url"`
	BundleVersion     int             `json:"bundle_version"`
	ShuffleQuestions  bool            `json:"shuffle_questions"`
	Seed              int64           `json:"seed"`
	PaperKey          *string         `json:"paper_key"`
	Answers           json.RawMessage `json:"answers"`
	Flagged           json.RawMessage `json:"flagged"`
	Rev               int64           `json:"rev"`
	SystemCheck       json.RawMessage `json:"system_check"`
	Messages          []model.Message `json:"messages"`
	NextSyncIn        int             `json:"next_sync_in"`
	ShowScoreOnSubmit bool            `json:"show_score_on_submit"`
	RequireFullscreen bool            `json:"require_fullscreen"`
	SubmittedAt       *string         `json:"submitted_at"`
	Receipt           *string         `json:"receipt"`
	GraceSeconds      int             `json:"grace_seconds"`
	HeartbeatSeconds  int             `json:"heartbeat_seconds"`
	EntryMode         string          `json:"entry_mode"`
}

func msInt(s string) int64 {
	n, _ := strconv.ParseInt(s, 10, 64)
	return n
}

func strp(s string) *string { return &s }

func messages(rc *RunCtx) []model.Message {
	if rc.Msgs == nil {
		return []model.Message{}
	}
	return rc.Msgs
}

func errRevoked() *httpx.Error {
	return httpx.Unauthorized("You signed in on another device, so this one was signed out.")
}

// State builds GET /api/state. touch updates last-seen (device activity) when stale.
func (e *Engine) State(ctx context.Context, rc *RunCtx, s *Session, touch bool) (*StateResp, *httpx.Error) {
	now := e.Now()
	reg := strconv.FormatInt(s.RegID, 10)
	pipe := e.St.R.Pipeline()
	att := pipe.HGetAll(ctx, rc.K.Att(s.RegID))
	ros := pipe.HGet(ctx, rc.K.Roster(), reg)
	ext := pipe.HGet(ctx, rc.K.Extra(), reg)
	key := pipe.Get(ctx, rc.K.Key())
	ctl := pipe.HMGet(ctx, rc.K.Ctl(), "xm", "hold")
	if _, err := pipe.Exec(ctx); err != nil && !errors.Is(err, redis.Nil) {
		e.M.RedisErrors.Inc("state")
		return nil, httpx.Internal()
	}
	runExtra, hold := 0, false
	if cv := ctl.Val(); len(cv) == 2 {
		runExtra, _ = strconv.Atoi(asString(cv[0]))
		hold = asString(cv[1]) == "1"
	}
	a := att.Val()
	if a["state"] == "" {
		return nil, httpx.Unauthorized("Please sign in again.")
	}
	if a["dev"] != s.Device {
		return nil, errRevoked()
	}
	var r model.Roster
	if raw := ros.Val(); raw != "" {
		_ = json.Unmarshal([]byte(raw), &r)
	}
	m := rc.Meta
	view := attempt.View(m, attempt.Stored{State: a["state"], Locked: a["lk"] == "1"}, now)
	st := &StateResp{
		Run: rc.Run, RegID: s.RegID, Name: r.Name, HallTicket: r.HallTicket, SchoolName: r.SchoolName, ClassName: r.ClassName,
		Title: m.Title, LogoURL: m.LogoURL, State: view, ServerTime: model.ISO(now),
		CheckinOpensAt: model.ISO(m.CheckinOpensAt), WaitingOpensAt: model.ISO(m.WaitingOpensAt), StartsAt: model.ISO(m.StartsAt),
		LateEntryUntil: model.ISO(attempt.LateEntryUntil(m)), DurationMinutes: m.DurationMinutes,
		Supervision: m.Supervision, StartPinRequired: m.PinRequired(),
		BundleURL: e.BundleURL(rc.Run, rc.BundleVersion), BundleVersion: rc.BundleVersion,
		ShuffleQuestions: m.ShuffleQuestions, Seed: s.RegID,
		Rev: msInt(a["rev"]), Messages: messages(rc), NextSyncIn: e.NextSyncIn(rc),
		ShowScoreOnSubmit: m.ShowScoreOnSubmit, RequireFullscreen: m.RequireFullscreen,
		GraceSeconds: m.GraceSeconds, HeartbeatSeconds: m.HeartbeatSeconds,
		EntryMode: m.EntryMode,
	}
	if m.SahodayaName != nil {
		st.SahodayaName = *m.SahodayaName
	} else {
		st.SahodayaName = m.SahodayaCode
	}
	st.ExtraMinutes, _ = strconv.Atoi(ext.Val())
	st.ExtraMinutes += runExtra
	if dl := msInt(a["dl"]); dl > 0 && a["state"] != model.StCheckedIn {
		eff := dl + int64(runExtra)*60000
		st.Deadline = strp(model.ISOms(eff))
		if hold && a["state"] == model.StWriting && now.UnixMilli() > eff {
			st.State = attempt.ViewLocked
			st.Messages = append(append([]model.Message{}, st.Messages...), holdMessage(now))
		}
	}
	if a["state"] == model.StWriting || a["state"] == model.StSubmitted {
		if k := key.Val(); k != "" && a["sa"] != "" {
			st.PaperKey = strp(k)
		}
	}
	st.Answers = json.RawMessage(orDefault(a["ans"], "{}"))
	st.Flagged = json.RawMessage(orDefault(a["flg"], "[]"))
	st.SystemCheck = json.RawMessage(orDefault(a["sc"], "null"))
	if sub := msInt(a["sub"]); sub > 0 {
		st.SubmittedAt = strp(model.ISOms(sub))
	}
	if rcpt := a["rcpt"]; rcpt != "" {
		st.Receipt = strp(rcpt)
	}
	if touch && now.UnixMilli()-msInt(a["ls"]) > 15000 && a["state"] != model.StSubmitted {
		_ = e.St.R.HSet(ctx, rc.K.Att(s.RegID), "ls", strconv.FormatInt(now.UnixMilli(), 10)).Err()
	}
	return st, nil
}

func orDefault(s, d string) string {
	if s == "" {
		return d
	}
	return s
}

// Start implements POST /api/start.
func (e *Engine) Start(ctx context.Context, rc *RunCtx, s *Session, pin string) (*StateResp, *httpx.Error) {
	now := e.Now()
	m := rc.Meta
	f, err := e.St.R.HMGet(ctx, rc.K.Att(s.RegID), "state", "dev", "lk").Result()
	if err != nil {
		return nil, httpx.Internal()
	}
	state, dev, lk := asString(f[0]), asString(f[1]), asString(f[2])
	if state == "" {
		return nil, httpx.Unauthorized("Please sign in again.")
	}
	if dev != s.Device {
		return nil, errRevoked()
	}
	if state == model.StWriting || state == model.StSubmitted {
		return e.State(ctx, rc, s, false) // idempotent
	}
	if rc.Closed {
		return nil, httpx.E(http.StatusConflict, httpx.CodeClosed, "This exam has been closed.")
	}
	if lk == "1" {
		return nil, httpx.E(http.StatusLocked, httpx.CodeLocked, "Your exam is on hold. Please contact your invigilator or school's exam coordinator.")
	}
	switch attempt.CanStart(m, now) {
	case attempt.StartTooEarly:
		return nil, httpx.E(http.StatusConflict, httpx.CodeNotOpenYet, "The exam has not started yet.").With("starts_at", model.ISO(m.StartsAt))
	case attempt.StartTooLate:
		return nil, httpx.E(http.StatusConflict, httpx.CodeClosed, "Late entry for this exam has closed.")
	}
	if m.PinRequired() {
		limKey := rc.Run + "|" + strconv.FormatInt(s.RegID, 10)
		if subtle.ConstantTimeCompare([]byte(pin), []byte(m.StartPinValue())) != 1 {
			if ok, wait := e.PinLimiter.Allow(limKey, time.Now()); !ok {
				er := httpx.E(http.StatusTooManyRequests, httpx.CodeRateLimited, "Too many wrong PINs. Ask your invigilator.")
				er.RetryAfter = int(wait.Seconds()) + 1
				return nil, er
			}
			return nil, httpx.E(http.StatusForbidden, httpx.CodeWrongPin, "That start PIN is not right. Ask your invigilator for the PIN.")
		}
	}
	res, err := store.Slice(e.St.Scripts.Start.Run(ctx, e.St.R,
		[]string{rc.K.Att(s.RegID), rc.K.Agg(), store.KeyEvents, rc.K.Deadlines(), rc.K.Seen(), rc.K.Extra(), rc.K.Ctl()},
		now.UnixMilli(), s.Device, attempt.DeadlineBase(m), attempt.Duration(m).Milliseconds(), rc.Run, s.RegID, e.St.MaxLen).Result())
	if err != nil {
		e.M.RedisErrors.Inc("start")
		e.Log.Error("start script failed", "run", rc.Run, "reg_id", s.RegID, "err", err)
		return nil, httpx.Internal()
	}
	switch store.Str(res, 0) {
	case "started", "writing", "submitted":
		return e.State(ctx, rc, s, false)
	case "locked":
		return nil, httpx.E(http.StatusLocked, httpx.CodeLocked, "Your exam is on hold. Please contact your invigilator or school's exam coordinator.")
	case "closed":
		return nil, httpx.E(http.StatusConflict, httpx.CodeClosed, "The time for this exam is over.")
	case "revoked":
		return nil, errRevoked()
	default:
		return nil, httpx.Unauthorized("Please sign in again.")
	}
}

// SyncRequest is POST /api/sync.
type SyncRequest struct {
	BaseRev  *int64          `json:"base_rev"`
	Changes  json.RawMessage `json:"changes"`
	Flagged  json.RawMessage `json:"flagged"`
	Checksum string          `json:"checksum"`
	Events   json.RawMessage `json:"events"`
}

// ResyncRequest is POST /api/resync.
type ResyncRequest struct {
	Answers json.RawMessage `json:"answers"`
	Flagged json.RawMessage `json:"flagged"`
	Events  json.RawMessage `json:"events"`
}

// SyncResp is the sync/resync response.
type SyncResp struct {
	Rev        int64           `json:"rev"`
	ServerTime string          `json:"server_time"`
	Deadline   *string         `json:"deadline"`
	NextSyncIn int             `json:"next_sync_in"`
	Messages   []model.Message `json:"messages"`
	Action     string          `json:"action"`
	ChecksumOK bool            `json:"checksum_ok"`
}

var proctorTypes = map[string]bool{"tab_hidden": true, "window_blur": true, "fullscreen_exit": true, "offline": true}

type proctorEvent struct {
	Type    string `json:"type"`
	At      string `json:"at"`
	Seconds *int64 `json:"seconds,omitempty"`
}

// parseProctor keeps at most 50 known events; returns the proctor.batch data JSON and count.
func parseProctor(raw json.RawMessage, now time.Time) (string, int) {
	if len(raw) == 0 || string(raw) == "null" {
		return "", 0
	}
	var in []map[string]json.RawMessage
	if json.Unmarshal(raw, &in) != nil {
		return "", 0
	}
	out := make([]proctorEvent, 0, min(len(in), syncer.MaxEventsBatch))
	for _, ev := range in {
		if len(out) >= syncer.MaxEventsBatch {
			break
		}
		var typ, at string
		_ = json.Unmarshal(ev["type"], &typ)
		if !proctorTypes[typ] {
			continue
		}
		_ = json.Unmarshal(ev["at"], &at)
		if t, err := time.Parse(time.RFC3339Nano, at); err == nil {
			at = model.ISO(t)
		} else {
			at = model.ISO(now)
		}
		pe := proctorEvent{Type: typ, At: at}
		if s, ok := ev["seconds"]; ok {
			var n float64
			if json.Unmarshal(s, &n) == nil && n >= 0 && n < 1e7 {
				v := int64(n)
				pe.Seconds = &v
			}
		}
		out = append(out, pe)
	}
	if len(out) == 0 {
		return "", 0
	}
	b, _ := json.Marshal(map[string]any{"events": out})
	return string(b), len(out)
}

// HoldMessageID marks the synthetic message shown while a run is on hold.
const HoldMessageID = -1

func holdMessage(now time.Time) model.Message {
	return model.Message{ID: HoldMessageID, At: model.ISO(now),
		Text: "Time is up, but the exam team has paused submission. Your answers are saved. Please wait for instructions."}
}

func (e *Engine) syncAllowed(s *Session) *httpx.Error {
	if ok, wait := e.SyncLimiter.Allow(s.Token, time.Now()); !ok {
		er := httpx.E(http.StatusTooManyRequests, httpx.CodeRateLimited, "Saving too often; slowing down.")
		er.RetryAfter = int(wait.Seconds()) + 1
		return er
	}
	return nil
}

func (e *Engine) syncResp(rc *RunCtx, status string, rev, dl int64, now time.Time) *SyncResp {
	r := &SyncResp{Rev: rev, ServerTime: model.ISO(now), NextSyncIn: e.NextSyncIn(rc), Messages: messages(rc), Action: "none", ChecksumOK: true}
	if dl > 0 {
		r.Deadline = strp(model.ISOms(dl))
	}
	switch status {
	case "none":
	case "submit", "late", "submitted":
		r.Action = "submit"
	case "lock":
		r.Action = "lock"
	case "hold":
		// run on hold (incident): past the deadline nothing is accepted and nothing is submitted
		r.Action = "lock"
		r.Messages = append(append([]model.Message{}, r.Messages...), holdMessage(now))
	case "resync":
		r.Action = "resync"
		r.ChecksumOK = false
	case "not_started":
		if now.After(attempt.LateEntryUntil(rc.Meta)) {
			r.Action = "submit"
		}
	}
	e.M.SyncActions.Inc(r.Action)
	return r
}

// Sync implements POST /api/sync.
func (e *Engine) Sync(ctx context.Context, rc *RunCtx, s *Session, req *SyncRequest) (*SyncResp, *httpx.Error) {
	if err := e.syncAllowed(s); err != nil {
		return nil, err
	}
	now := e.Now()
	changes, err := syncer.ParseChanges(req.Changes, rc.Schema)
	if err != nil {
		return nil, httpx.Invalid(err.Error())
	}
	flagged, hasFlagged, err := syncer.ParseFlagged(req.Flagged, rc.Schema)
	if err != nil {
		return nil, httpx.Invalid(err.Error())
	}
	if !hasFlagged {
		flagged = ""
	}
	pdata, pcount := parseProctor(req.Events, now)
	baseRev := int64(-1)
	if req.BaseRev != nil {
		baseRev = *req.BaseRev
	}
	mode := "hb"
	var newAns, newCks string
	var answered, changed int
	if len(changes) > 0 {
		f, err := e.St.R.HMGet(ctx, rc.K.Att(s.RegID), "rev", "ans").Result()
		if err != nil {
			e.M.RedisErrors.Inc("sync_read")
			return nil, httpx.Internal()
		}
		storedRev := msInt(asString(f[0]))
		if baseRev == storedRev {
			stored, derr := syncer.Decode(asString(f[1]))
			if derr != nil {
				stored = syncer.Answers{}
			}
			next, n := syncer.Apply(stored, changes)
			newCks = syncer.Checksum(next)
			if newCks == req.Checksum {
				mode, newAns, answered, changed = "apply", syncer.Encode(next), len(next), n
			} else {
				mode = "resync"
			}
		}
		// base_rev behind: "hb" mode compares revs and answers resync
	}
	res, rerr := store.Slice(e.St.Scripts.Sync.Run(ctx, e.St.R,
		[]string{rc.K.Att(s.RegID), rc.K.Agg(), store.KeyEvents, rc.K.Seen(), rc.K.Ctl()},
		now.UnixMilli(), s.Device, mode, baseRev, req.Checksum, newAns, newCks, answered, changed, flagged,
		rc.GraceMs(), pdata, pcount, rc.Run, s.RegID, e.St.MaxLen).Result())
	if rerr != nil {
		e.M.RedisErrors.Inc("sync")
		e.Log.Error("sync script failed", "run", rc.Run, "reg_id", s.RegID, "err", rerr)
		return nil, httpx.Internal()
	}
	return e.mapSync(rc, res, now)
}

func (e *Engine) mapSync(rc *RunCtx, res []any, now time.Time) (*SyncResp, *httpx.Error) {
	status := store.Str(res, 0)
	switch status {
	case "gone":
		return nil, httpx.Unauthorized("Please sign in again.")
	case "revoked":
		return nil, errRevoked()
	}
	return e.syncResp(rc, status, store.Int(res, 1), store.Int(res, 2), now), nil
}

// Resync implements POST /api/resync: the full map replaces the stored one.
func (e *Engine) Resync(ctx context.Context, rc *RunCtx, s *Session, req *ResyncRequest) (*SyncResp, *httpx.Error) {
	if err := e.syncAllowed(s); err != nil {
		return nil, err
	}
	now := e.Now()
	full, err := syncer.ParseFull(req.Answers, rc.Schema)
	if err != nil {
		return nil, httpx.Invalid(err.Error())
	}
	flagged, hasFlagged, err := syncer.ParseFlagged(req.Flagged, rc.Schema)
	if err != nil {
		return nil, httpx.Invalid(err.Error())
	}
	if !hasFlagged {
		flagged = ""
	}
	pdata, pcount := parseProctor(req.Events, now)
	changed := 0
	if raw, err := e.St.R.HGet(ctx, rc.K.Att(s.RegID), "ans").Result(); err == nil {
		if stored, derr := syncer.Decode(raw); derr == nil {
			changed = syncer.Diff(stored, full)
		}
	}
	res, rerr := store.Slice(e.St.Scripts.Sync.Run(ctx, e.St.R,
		[]string{rc.K.Att(s.RegID), rc.K.Agg(), store.KeyEvents, rc.K.Seen(), rc.K.Ctl()},
		now.UnixMilli(), s.Device, "replace", -1, "", syncer.Encode(full), syncer.Checksum(full), len(full), changed, flagged,
		rc.GraceMs(), pdata, pcount, rc.Run, s.RegID, e.St.MaxLen).Result())
	if rerr != nil {
		e.M.RedisErrors.Inc("resync")
		return nil, httpx.Internal()
	}
	return e.mapSync(rc, res, now)
}

// SubmitRequest is POST /api/submit. `answers` (full map) is an engine extension: when present
// it replaces the stored map like /api/resync before finalising.
type SubmitRequest struct {
	BaseRev  *int64          `json:"base_rev"`
	Changes  json.RawMessage `json:"changes"`
	Checksum string          `json:"checksum"`
	Answers  json.RawMessage `json:"answers"`
}

// SubmitResp is the submit response.
type SubmitResp struct {
	Submitted   bool   `json:"submitted"`
	SubmittedAt string `json:"submitted_at"`
	Receipt     string `json:"receipt"`
	Mode        string `json:"mode"`
}

// Submit implements POST /api/submit (idempotent).
func (e *Engine) Submit(ctx context.Context, rc *RunCtx, s *Session, req *SubmitRequest) (*SubmitResp, *httpx.Error) {
	if err := e.syncAllowed(s); err != nil {
		return nil, err
	}
	now := e.Now()
	kind := "hb"
	var expRev int64 = -1
	var newAns, newCks string
	var answered, changed int
	f, err := e.St.R.HMGet(ctx, rc.K.Att(s.RegID), "state", "rev", "ans", "rcpt", "sub", "mode", "dev").Result()
	if err != nil {
		return nil, httpx.Internal()
	}
	if asString(f[6]) != s.Device {
		if asString(f[0]) == "" {
			return nil, httpx.Unauthorized("Please sign in again.")
		}
		return nil, errRevoked()
	}
	if asString(f[0]) == model.StSubmitted {
		return &SubmitResp{Submitted: true, SubmittedAt: model.ISOms(msInt(asString(f[4]))), Receipt: asString(f[3]), Mode: asString(f[5])}, nil
	}
	stored, derr := syncer.Decode(asString(f[2]))
	if derr != nil {
		stored = syncer.Answers{}
	}
	if len(req.Answers) > 0 && string(req.Answers) != "null" {
		full, err := syncer.ParseFull(req.Answers, rc.Schema)
		if err != nil {
			return nil, httpx.Invalid(err.Error())
		}
		kind, newAns, newCks, answered, changed = "replace", syncer.Encode(full), syncer.Checksum(full), len(full), syncer.Diff(stored, full)
	} else {
		changes, err := syncer.ParseChanges(req.Changes, rc.Schema)
		if err != nil {
			return nil, httpx.Invalid(err.Error())
		}
		if len(changes) > 0 {
			next, n := syncer.Apply(stored, changes)
			cks := syncer.Checksum(next)
			if cks != req.Checksum {
				return nil, e.resyncNeeded(msInt(asString(f[1])))
			}
			// The resulting map equals the client's view, so accept it even if base_rev is behind.
			kind, expRev, newAns, newCks, answered, changed = "apply", msInt(asString(f[1])), syncer.Encode(next), cks, len(next), n
		} else if req.Checksum == "" {
			kind = "none"
		}
	}
	res, rerr := store.Slice(e.St.Scripts.Submit.Run(ctx, e.St.R,
		[]string{rc.K.Att(s.RegID), rc.K.Agg(), store.KeyEvents, rc.K.Deadlines(), rc.K.Seen(), rc.K.Ctl()},
		now.UnixMilli(), s.Device, "manual", kind, expRev, req.Checksum, newAns, newCks, answered, changed,
		rc.GraceMs(), Receipt(rc.Meta, s.RegID), rc.Run, s.RegID, e.St.MaxLen, "0").Result())
	if rerr != nil {
		e.M.RedisErrors.Inc("submit")
		e.Log.Error("submit script failed", "run", rc.Run, "reg_id", s.RegID, "err", rerr)
		return nil, httpx.Internal()
	}
	switch store.Str(res, 0) {
	case "submitted":
		e.M.Submits.Inc(store.Str(res, 3))
		fallthrough
	case "already":
		return &SubmitResp{Submitted: true, SubmittedAt: model.ISOms(store.Int(res, 2)), Receipt: store.Str(res, 1), Mode: store.Str(res, 3)}, nil
	case "resync":
		return nil, e.resyncNeeded(store.Int(res, 4))
	case "locked":
		return nil, httpx.E(http.StatusLocked, httpx.CodeLocked, "Your exam is on hold. Please contact your invigilator or school's exam coordinator.")
	case "held":
		return nil, httpx.E(http.StatusLocked, httpx.CodeLocked, holdMessage(now).Text).With("action", "lock")
	case "not_started":
		return nil, httpx.E(http.StatusConflict, httpx.CodeInvalid, "You have not started this exam.")
	case "revoked":
		return nil, errRevoked()
	default:
		return nil, httpx.Unauthorized("Please sign in again.")
	}
}

func (e *Engine) resyncNeeded(rev int64) *httpx.Error {
	return httpx.E(http.StatusConflict, httpx.CodeInvalid, "Your answers are out of step with the server. Send them again (resync), then submit.").
		With("action", "resync").With("rev", rev)
}

// ResultResp is GET /api/result.
type ResultResp struct {
	Status     string   `json:"status"`
	Score      *float64 `json:"score,omitempty"`
	MaxScore   *float64 `json:"max_score,omitempty"`
	Percentage *float64 `json:"percentage,omitempty"`
	Grade      *string  `json:"grade,omitempty"`
}

// Result implements GET /api/result.
func (e *Engine) Result(ctx context.Context, rc *RunCtx, s *Session) (*ResultResp, *httpx.Error) {
	if !rc.Meta.ShowScoreOnSubmit {
		return &ResultResp{Status: "hidden"}, nil
	}
	reg := strconv.FormatInt(s.RegID, 10)
	pipe := e.St.R.Pipeline()
	st := pipe.HMGet(ctx, rc.K.Att(s.RegID), "state", "dev")
	rs := pipe.HGet(ctx, rc.K.Results(), reg)
	if _, err := pipe.Exec(ctx); err != nil && !errors.Is(err, redis.Nil) {
		return nil, httpx.Internal()
	}
	f := st.Val()
	if len(f) < 2 || asString(f[1]) != s.Device {
		return nil, errRevoked()
	}
	raw := rs.Val()
	if asString(f[0]) != model.StSubmitted || raw == "" {
		return &ResultResp{Status: "pending"}, nil
	}
	var out ResultResp
	if json.Unmarshal([]byte(raw), &out) != nil {
		return &ResultResp{Status: "pending"}, nil
	}
	out.Status = "ready"
	return &out, nil
}

// Logout releases the device binding and deletes the session (attempt state is kept).
func (e *Engine) Logout(ctx context.Context, token string) {
	s, _ := e.LookupSession(ctx, token)
	if s != nil {
		_ = e.St.Scripts.Logout.Run(ctx, e.St.R, []string{store.K(s.Run).Att(s.RegID)}, s.Device).Err()
	}
	e.RevokeSession(ctx, token)
}

// SystemCheckRequest is POST /api/system-check.
type SystemCheckRequest struct {
	UserAgent string `json:"user_agent"`
	Screen    string `json:"screen"`
	Online    *bool  `json:"online"`
	LatencyMs *int64 `json:"latency_ms"`
	StorageOK *bool  `json:"storage_ok"`
	CryptoOK  *bool  `json:"crypto_ok"`
}

// SystemCheck stores the client's check results on the attempt.
func (e *Engine) SystemCheck(ctx context.Context, rc *RunCtx, s *Session, req *SystemCheckRequest) *httpx.Error {
	t := func(b *bool) bool { return b == nil || *b }
	ok := t(req.Online) && t(req.StorageOK) && t(req.CryptoOK)
	clip := func(x string, n int) string {
		if len(x) > n {
			return x[:n]
		}
		return x
	}
	rec := map[string]any{
		"ok": ok, "user_agent": clip(req.UserAgent, 300), "screen": clip(req.Screen, 32),
		"online": t(req.Online), "storage_ok": t(req.StorageOK), "crypto_ok": t(req.CryptoOK),
		"at": model.ISO(e.Now()),
	}
	if req.LatencyMs != nil {
		rec["latency_ms"] = *req.LatencyMs
	}
	b, _ := json.Marshal(rec)
	dev, err := e.St.R.HGet(ctx, rc.K.Att(s.RegID), "dev").Result()
	if err != nil && !errors.Is(err, redis.Nil) {
		return httpx.Internal()
	}
	if dev != s.Device {
		return errRevoked()
	}
	if err := e.St.R.HSet(ctx, rc.K.Att(s.RegID), "sc", string(b), "ls", strconv.FormatInt(e.Now().UnixMilli(), 10)).Err(); err != nil {
		return httpx.Internal()
	}
	return nil
}

// SahodayaRun is one row of GET /api/sahodaya/{code}.
type SahodayaRun struct {
	Run       string `json:"run"`
	Title     string `json:"title"`
	StartsAt  string `json:"starts_at"`
	State     string `json:"state"`
	EntryMode string `json:"entry_mode"`
}

// SahodayaResp is GET /api/sahodaya/{code}.
type SahodayaResp struct {
	Code    string        `json:"code"`
	Name    string        `json:"name"`
	LogoURL *string       `json:"logo_url"`
	Runs    []SahodayaRun `json:"runs"`
}

// Sahodaya lists armed, not-closed runs for a code.
func (e *Engine) Sahodaya(ctx context.Context, code string) (*SahodayaResp, *httpx.Error) {
	code = model.NormaliseCode(code)
	if !model.ValidCode(code) {
		return nil, httpx.NotFound("No online exams found for this Sahodaya.")
	}
	runs, herr := e.loadRuns(ctx, code)
	if herr != nil {
		return nil, herr
	}
	now := e.Now()
	out := &SahodayaResp{Code: code, Name: code, Runs: []SahodayaRun{}}
	sort.Slice(runs, func(i, j int) bool { return runs[i].Meta.StartsAt.Before(runs[j].Meta.StartsAt) })
	for _, rc := range runs {
		if rc.Closed {
			continue
		}
		m := rc.Meta
		if m.SahodayaName != nil && *m.SahodayaName != "" {
			out.Name = *m.SahodayaName
		}
		if m.LogoURL != nil {
			out.LogoURL = m.LogoURL
		}
		out.Runs = append(out.Runs, SahodayaRun{Run: rc.Run, Title: m.Title, StartsAt: model.ISO(m.StartsAt), State: rc.State(now), EntryMode: m.EntryMode})
	}
	if len(out.Runs) == 0 {
		return nil, httpx.NotFound("No online exams found for this Sahodaya.")
	}
	return out, nil
}

// Bundle returns the public bundle for (run, version) from the in-process cache or Redis.
func (e *Engine) Bundle(ctx context.Context, run string, version int) (*paper.Cached, *httpx.Error) {
	if c := e.Bundles.Get(run, version); c != nil {
		return c, nil
	}
	raw, err := e.St.R.Get(ctx, store.K(run).Bundle()).Bytes()
	if errors.Is(err, redis.Nil) {
		return nil, httpx.NotFound("No such paper.")
	}
	if err != nil {
		return nil, httpx.Internal()
	}
	var pub paper.Public
	if json.Unmarshal(raw, &pub) != nil || pub.Version != version {
		return nil, httpx.NotFound("No such paper version.")
	}
	return e.Bundles.Put(run, version, raw), nil
}
