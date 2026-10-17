package admin

import (
	"context"
	"encoding/json"
	"errors"
	"net/http"
	"sort"
	"strconv"
	"strings"
	"time"

	"github.com/redis/go-redis/v9"

	"github.com/sahodaya/exam-engine/internal/attempt"
	"github.com/sahodaya/exam-engine/internal/engine"
	"github.com/sahodaya/exam-engine/internal/httpx"
	"github.com/sahodaya/exam-engine/internal/model"
	"github.com/sahodaya/exam-engine/internal/store"
)

// lightRow is the roster without secrets, for the monitor.
type lightRow struct {
	RegID      int64
	Name       string
	SchoolID   string
	SchoolName string
	ClassName  string
	HallTicket string
	search     string
}

type rosterIndex struct {
	version string
	rows    []lightRow
	byReg   map[int64]int
}

// index returns the roster index for a run, rebuilt when the roster version changes.
func (a *API) index(ctx context.Context, rc *engine.RunCtx) (*rosterIndex, error) {
	ver, err := a.E.St.R.HGet(ctx, rc.K.Agg(), "rv").Result()
	if err != nil && !errors.Is(err, redis.Nil) {
		return nil, err
	}
	if v, ok := a.rosters.Load(rc.Run); ok {
		if ix := v.(*rosterIndex); ix.version == ver {
			return ix, nil
		}
	}
	all, err := a.E.St.R.HGetAll(ctx, rc.K.Roster()).Result()
	if err != nil {
		return nil, err
	}
	ix := &rosterIndex{version: ver, rows: make([]lightRow, 0, len(all)), byReg: make(map[int64]int, len(all))}
	for _, raw := range all {
		var r model.Roster
		if json.Unmarshal([]byte(raw), &r) != nil {
			continue
		}
		ix.rows = append(ix.rows, lightRow{
			RegID: r.RegID, Name: r.Name, SchoolID: r.SchoolID, SchoolName: r.SchoolName, ClassName: r.ClassName, HallTicket: r.HallTicket,
			search: strings.ToLower(r.Name + "\x00" + r.HallTicket + "\x00" + r.Username + "\x00" + r.Email + "\x00" + strconv.FormatInt(r.RegID, 10)),
		})
	}
	sort.Slice(ix.rows, func(i, j int) bool {
		x, y := ix.rows[i], ix.rows[j]
		if x.SchoolName != y.SchoolName {
			return x.SchoolName < y.SchoolName
		}
		if x.ClassName != y.ClassName {
			return x.ClassName < y.ClassName
		}
		if x.Name != y.Name {
			return x.Name < y.Name
		}
		return x.RegID < y.RegID
	})
	for i, r := range ix.rows {
		ix.byReg[r.RegID] = i
	}
	a.rosters.Store(rc.Run, ix)
	return ix, nil
}

// ---- summary ----

type schoolRow struct {
	SchoolID   string `json:"school_id"`
	SchoolName string `json:"school_name"`
	Roster     int64  `json:"roster"`
	CheckedIn  int64  `json:"checked_in"`
	Writing    int64  `json:"writing"`
	Submitted  int64  `json:"submitted"`
	Offline    int64  `json:"offline"`
	Flagged    int64  `json:"flagged"`
}

func (a *API) summary(w http.ResponseWriter, r *http.Request) (any, error) {
	run, err := runParam(r)
	if err != nil {
		return nil, err
	}
	ctx := r.Context()
	rc, err := a.existing(ctx, run)
	if err != nil {
		return nil, err
	}
	now := a.E.Now()
	cutoff := now.Add(-a.E.Cfg.OfflineAfter).UnixMilli()
	pipe := a.E.St.R.Pipeline()
	aggC := pipe.HGetAll(ctx, rc.K.Agg())
	schC := pipe.HGetAll(ctx, rc.K.Schools())
	offC := pipe.ZRangeByScore(ctx, rc.K.Seen(), &redis.ZRangeBy{Min: "-inf", Max: "(" + strconv.FormatInt(cutoff, 10)})
	if _, err := pipe.Exec(ctx); err != nil && !errors.Is(err, redis.Nil) {
		return nil, err
	}
	agg := aggC.Val()
	g := func(k string) int64 { n, _ := strconv.ParseInt(agg[k], 10, 64); return n }
	schools := map[string]*schoolRow{}
	row := func(sid string) *schoolRow {
		s := schools[sid]
		if s == nil {
			s = &schoolRow{SchoolID: sid, SchoolName: schC.Val()[sid]}
			schools[sid] = s
		}
		return s
	}
	for k, v := range agg {
		if !strings.HasPrefix(k, "s:") {
			continue
		}
		i := strings.LastIndexByte(k, ':')
		if i <= 2 {
			continue
		}
		sid, field := k[2:i], k[i+1:]
		n, _ := strconv.ParseInt(v, 10, 64)
		s := row(sid)
		switch field {
		case "r":
			s.Roster = n
		case "ci":
			s.CheckedIn = n
		case "st":
			s.Writing += n
		case "sub":
			s.Submitted = n
			s.Writing -= n
		case "fl":
			s.Flagged = n
		}
	}
	offline := offC.Val()
	if len(offline) > 0 {
		ix, err := a.index(ctx, rc)
		if err != nil {
			return nil, err
		}
		for _, reg := range offline {
			id, _ := strconv.ParseInt(reg, 10, 64)
			if i, ok := ix.byReg[id]; ok {
				row(ix.rows[i].SchoolID).Offline++
			}
		}
	}
	list := make([]*schoolRow, 0, len(schools))
	for sid, s := range schools {
		if s.Roster == 0 && s.CheckedIn == 0 && s.Writing == 0 && s.Submitted == 0 {
			continue
		}
		if s.SchoolName == "" {
			s.SchoolName = schC.Val()[sid]
		}
		if s.Writing < 0 {
			s.Writing = 0
		}
		list = append(list, s)
	}
	sort.Slice(list, func(i, j int) bool {
		if list[i].SchoolName != list[j].SchoolName {
			return list[i].SchoolName < list[j].SchoolName
		}
		return list[i].SchoolID < list[j].SchoolID
	})
	ci, st, sub := g("ci"), g("st"), g("sub")
	writing := max(st-sub, 0)
	return map[string]any{
		"run": run, "state": rc.State(now), "server_time": model.ISO(now),
		"counts": map[string]int64{
			"roster": g("r"), "checked_in": ci, "waiting": max(ci-st, 0), "writing": writing,
			"submitted": sub, "locked": max(g("lk"), 0), "offline": int64(len(offline)),
		},
		"by_school":    list,
		"pace_seconds": a.E.NextSyncIn(rc),
		"pace_override": func() any {
			if rc.Pace > 0 {
				return rc.Pace
			}
			return nil
		}(),
		"unfinished":       writing,
		"flagged":          g("fl"),
		"extended_minutes": rc.RunExtra,
		"hold":             rc.Hold,
	}, nil
}

// ---- attempts ----

// AttemptRow is one row of GET /internal/runs/{run}/attempts (and the action response).
type AttemptRow struct {
	RegID        int64   `json:"reg_id"`
	Name         string  `json:"name"`
	SchoolID     string  `json:"school_id"`
	SchoolName   string  `json:"school_name"`
	ClassName    string  `json:"class_name"`
	HallTicket   string  `json:"hall_ticket"`
	State        string  `json:"state"`
	CheckedInAt  *string `json:"checked_in_at"`
	StartedAt    *string `json:"started_at"`
	Deadline     *string `json:"deadline"`
	SubmittedAt  *string `json:"submitted_at"`
	SubmitMode   *string `json:"submit_mode"`
	LastSeenAt   *string `json:"last_seen_at"`
	Answered     int64   `json:"answered"`
	Flags        int64   `json:"flags"`
	Device       string  `json:"device"`
	IP           string  `json:"ip"`
	ExtraMinutes int     `json:"extra_minutes"`
	LateChanges  int64   `json:"late_changes"`
	Offline      bool    `json:"offline"`
}

var attFields = []string{"state", "lk", "ci", "sa", "dl", "sub", "mode", "ls", "n", "fl", "ua", "ip", "late"}

func isoPtr(ms string) *string {
	n, _ := strconv.ParseInt(ms, 10, 64)
	if n <= 0 {
		return nil
	}
	s := model.ISOms(n)
	return &s
}

func (a *API) buildRow(rc *engine.RunCtx, lr lightRow, f []any, extra string, now time.Time) AttemptRow {
	s := func(i int) string {
		if i < len(f) {
			if v, ok := f[i].(string); ok {
				return v
			}
		}
		return ""
	}
	row := AttemptRow{RegID: lr.RegID, Name: lr.Name, SchoolID: lr.SchoolID, SchoolName: lr.SchoolName, ClassName: lr.ClassName, HallTicket: lr.HallTicket}
	row.ExtraMinutes, _ = strconv.Atoi(extra)
	row.ExtraMinutes += rc.RunExtra
	state := s(0)
	if state == "" {
		row.State = "not_checked_in"
		if s(1) == "1" {
			row.State = attempt.ViewLocked
		}
		return row
	}
	row.State = attempt.View(rc.Meta, attempt.Stored{State: state, Locked: s(1) == "1"}, now)
	row.CheckedInAt = isoPtr(s(2))
	row.StartedAt = isoPtr(s(3))
	if dl, _ := strconv.ParseInt(s(4), 10, 64); dl > 0 && state != model.StCheckedIn {
		eff := dl + int64(rc.RunExtra)*60000
		row.Deadline = isoPtr(strconv.FormatInt(eff, 10))
		if rc.Hold && state == model.StWriting && now.UnixMilli() > eff {
			row.State = attempt.ViewLocked
		}
	}
	row.SubmittedAt = isoPtr(s(5))
	if m := s(6); m != "" {
		row.SubmitMode = &m
	}
	row.LastSeenAt = isoPtr(s(7))
	row.Answered, _ = strconv.ParseInt(s(8), 10, 64)
	row.Flags, _ = strconv.ParseInt(s(9), 10, 64)
	row.Device = DescribeUA(s(10))
	row.IP = s(11)
	row.LateChanges, _ = strconv.ParseInt(s(12), 10, 64)
	if state == model.StWriting {
		ls, _ := strconv.ParseInt(s(7), 10, 64)
		row.Offline = now.UnixMilli()-ls > a.E.Cfg.OfflineAfter.Milliseconds()
	}
	return row
}

// fetchRows loads attempt fields for a set of roster rows.
func (a *API) fetchRows(ctx context.Context, rc *engine.RunCtx, rows []lightRow, now time.Time) ([]AttemptRow, error) {
	out := make([]AttemptRow, 0, len(rows))
	for i := 0; i < len(rows); i += 1000 {
		end := min(i+1000, len(rows))
		pipe := a.E.St.R.Pipeline()
		atts := make([]*redis.SliceCmd, 0, end-i)
		ids := make([]string, 0, end-i)
		for _, lr := range rows[i:end] {
			atts = append(atts, pipe.HMGet(ctx, rc.K.Att(lr.RegID), attFields...))
			ids = append(ids, strconv.FormatInt(lr.RegID, 10))
		}
		ext := pipe.HMGet(ctx, rc.K.Extra(), ids...)
		if _, err := pipe.Exec(ctx); err != nil && !errors.Is(err, redis.Nil) {
			return nil, err
		}
		exv := ext.Val()
		for j, lr := range rows[i:end] {
			x := ""
			if j < len(exv) {
				x, _ = exv[j].(string)
			}
			out = append(out, a.buildRow(rc, lr, atts[j].Val(), x, now))
		}
	}
	return out, nil
}

func (a *API) attempts(w http.ResponseWriter, r *http.Request) (any, error) {
	run, err := runParam(r)
	if err != nil {
		return nil, err
	}
	ctx := r.Context()
	rc, err := a.existing(ctx, run)
	if err != nil {
		return nil, err
	}
	q := r.URL.Query()
	page, _ := strconv.Atoi(q.Get("page"))
	if page < 1 {
		page = 1
	}
	per, _ := strconv.Atoi(q.Get("per_page"))
	if per < 1 {
		per = 50
	}
	per = min(per, 500)
	school := q.Get("school_id")
	state := q.Get("state")
	flagged := q.Get("flagged") == "1" || q.Get("flagged") == "true"
	needle := strings.ToLower(strings.TrimSpace(q.Get("q")))
	ix, err := a.index(ctx, rc)
	if err != nil {
		return nil, err
	}
	cands := make([]lightRow, 0, len(ix.rows))
	for _, lr := range ix.rows {
		if school != "" && lr.SchoolID != school {
			continue
		}
		if needle != "" && !strings.Contains(lr.search, needle) {
			continue
		}
		cands = append(cands, lr)
	}
	now := a.E.Now()
	var data []AttemptRow
	total := 0
	if state == "" && !flagged {
		total = len(cands)
		from := min((page-1)*per, total)
		to := min(from+per, total)
		data, err = a.fetchRows(ctx, rc, cands[from:to], now)
		if err != nil {
			return nil, err
		}
	} else {
		all, err := a.fetchRows(ctx, rc, cands, now)
		if err != nil {
			return nil, err
		}
		filtered := all[:0]
		for _, row := range all {
			if flagged && row.Flags == 0 {
				continue
			}
			if state != "" {
				if state == "offline" {
					if !row.Offline {
						continue
					}
				} else if row.State != state {
					continue
				}
			}
			filtered = append(filtered, row)
		}
		total = len(filtered)
		from := min((page-1)*per, total)
		to := min(from+per, total)
		data = filtered[from:to]
	}
	if data == nil {
		data = []AttemptRow{}
	}
	return map[string]any{"data": data, "total": total, "page": page, "per_page": per}, nil
}

// row returns one attempt row.
func (a *API) row(ctx context.Context, rc *engine.RunCtx, reg int64) (*AttemptRow, error) {
	ix, err := a.index(ctx, rc)
	if err != nil {
		return nil, err
	}
	i, ok := ix.byReg[reg]
	if !ok {
		return nil, httpx.NotFound("This candidate is not on the roster.")
	}
	rows, err := a.fetchRows(ctx, rc, []lightRow{ix.rows[i]}, a.E.Now())
	if err != nil {
		return nil, err
	}
	return &rows[0], nil
}

// ---- actions ----

type actionBody struct {
	Action  string   `json:"action"`
	Minutes *float64 `json:"minutes"`
	Reason  string   `json:"reason"`
	Actor   string   `json:"actor"`
}

func (a *API) action(w http.ResponseWriter, r *http.Request) (any, error) {
	run, err := runParam(r)
	if err != nil {
		return nil, err
	}
	reg, err := regParam(r)
	if err != nil {
		return nil, err
	}
	var body actionBody
	if herr := httpx.Decode(w, r, LimitSmall, &body, false); herr != nil {
		return nil, herr
	}
	ctx := r.Context()
	rc, err := a.existing(ctx, run)
	if err != nil {
		return nil, err
	}
	if _, err := a.row(ctx, rc, reg); err != nil {
		return nil, err
	}
	if len(body.Reason) > 500 {
		body.Reason = body.Reason[:500]
	}
	if len(body.Actor) > 200 {
		body.Actor = body.Actor[:200]
	}
	minutes := 0
	switch body.Action {
	case "extend":
		if body.Minutes == nil || *body.Minutes < 1 || *body.Minutes > 600 {
			return nil, httpx.Invalid("minutes must be 1..600 for extend")
		}
		minutes = int(*body.Minutes)
	case "force_submit", "lock", "unlock", "allow_switch":
	default:
		return nil, httpx.Invalid("action must be extend, force_submit, lock, unlock or allow_switch")
	}
	data := map[string]any{"action": body.Action, "reason": body.Reason, "actor": body.Actor}
	if body.Action == "extend" {
		data["minutes"] = minutes
	} else {
		data["minutes"] = nil
	}
	dataJS, _ := json.Marshal(data)
	now := a.E.Now()
	keys := []string{rc.K.Att(reg), rc.K.Agg(), store.KeyEvents, rc.K.Deadlines(), rc.K.Extra()}
	m := rc.Meta
	if body.Action == "force_submit" {
		res, err := store.Slice(a.E.St.Scripts.Submit.Run(ctx, a.E.St.R,
			[]string{rc.K.Att(reg), rc.K.Agg(), store.KeyEvents, rc.K.Deadlines(), rc.K.Seen(), rc.K.Ctl()},
			now.UnixMilli(), "", "forced", "none", -1, "", "", "", 0, 0, rc.GraceMs(), engine.Receipt(m, reg), run, reg, a.E.St.MaxLen, "0").Result())
		if err != nil {
			return nil, err
		}
		switch store.Str(res, 0) {
		case "submitted":
			a.E.M.Submits.Inc("forced")
		case "already":
			return nil, httpx.E(http.StatusConflict, httpx.CodeAlreadySubmitted, "This attempt was already submitted.")
		default:
			return nil, httpx.E(http.StatusConflict, httpx.CodeInvalid, "This candidate has not started the exam.")
		}
		if err := a.E.St.Scripts.Action.Run(ctx, a.E.St.R, keys, now.UnixMilli(), "event", 0, run, reg, a.E.St.MaxLen, string(dataJS), 0, 0).Err(); err != nil {
			return nil, err
		}
	} else {
		res, err := store.Slice(a.E.St.Scripts.Action.Run(ctx, a.E.St.R, keys, now.UnixMilli(), body.Action, minutes, run, reg, a.E.St.MaxLen,
			string(dataJS), attempt.DeadlineBase(m), attempt.Duration(m).Milliseconds()).Result())
		if err != nil {
			return nil, err
		}
		if store.Str(res, 0) == "submitted" {
			return nil, httpx.E(http.StatusConflict, httpx.CodeAlreadySubmitted, "This attempt was already submitted.")
		}
		if body.Action == "extend" {
			a.E.InvalidateRun(run) // max extra may have grown
		}
	}
	return a.row(ctx, rc, reg)
}
