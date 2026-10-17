// Package admin implements the internal API Laravel calls (CONTRACT §2, §3): arming runs (meta,
// roster, bundle), disarm/close, results write-back, the live monitor (summary, attempts),
// coordinator actions, broadcast, pacing and the event claim/ack endpoints.
package admin

import (
	"context"
	"encoding/json"
	"errors"
	"net/http"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/redis/go-redis/v9"

	"github.com/sahodaya/exam-engine/internal/attempt"
	"github.com/sahodaya/exam-engine/internal/engine"
	"github.com/sahodaya/exam-engine/internal/events"
	"github.com/sahodaya/exam-engine/internal/httpx"
	"github.com/sahodaya/exam-engine/internal/model"
	"github.com/sahodaya/exam-engine/internal/paper"
	"github.com/sahodaya/exam-engine/internal/store"
)

// Body limits.
const (
	LimitSmall  = 64 << 10
	LimitRoster = 2 << 20
	LimitBundle = 30 << 20
)

// API is the internal API.
type API struct {
	E       *engine.Engine
	rosters sync.Map // run → *rosterIndex
}

// New creates the API.
func New(e *engine.Engine) *API { return &API{E: e} }

// Route is one internal endpoint.
type Route struct {
	Pattern string
	Limit   int64
	Handler func(http.ResponseWriter, *http.Request)
}

// Routes lists every internal endpoint (patterns use Go 1.22 method syntax).
func (a *API) Routes() []Route {
	return []Route{
		{"PUT /internal/runs/{run}", LimitRoster, a.handle(a.putMeta)},
		{"GET /internal/runs/{run}", LimitSmall, a.handle(a.getMeta)},
		{"POST /internal/runs/{run}/roster", LimitRoster, a.handle(a.postRoster)},
		{"PUT /internal/runs/{run}/roster/{reg_id}", LimitSmall, a.handle(a.putRosterEntry)},
		{"DELETE /internal/runs/{run}/roster/{reg_id}", LimitSmall, a.handle(a.deleteRosterEntry)},
		{"PUT /internal/runs/{run}/bundle", LimitBundle, a.handle(a.putBundle)},
		{"POST /internal/runs/{run}/disarm", LimitSmall, a.handle(a.disarm)},
		{"POST /internal/runs/{run}/close", LimitSmall, a.handle(a.close)},
		{"POST /internal/runs/{run}/results", LimitRoster, a.handle(a.results)},
		{"GET /internal/runs/{run}/summary", LimitSmall, a.handle(a.summary)},
		{"GET /internal/runs/{run}/attempts", LimitSmall, a.handle(a.attempts)},
		{"POST /internal/runs/{run}/attempts/{reg_id}/actions", LimitSmall, a.handle(a.action)},
		{"POST /internal/runs/{run}/broadcast", LimitSmall, a.handle(a.broadcast)},
		{"POST /internal/runs/{run}/pace", LimitSmall, a.handle(a.pace)},
		{"POST /internal/runs/{run}/extend", LimitSmall, a.handle(a.runExtend)},
		{"POST /internal/runs/{run}/hold", LimitSmall, a.handle(a.runHold)},
		{"POST /internal/events/claim", LimitSmall, a.handle(a.claim)},
		{"POST /internal/events/ack", LimitRoster, a.handle(a.ack)},
	}
}

type handlerFunc func(w http.ResponseWriter, r *http.Request) (any, error)

func (a *API) handle(fn handlerFunc) func(http.ResponseWriter, *http.Request) {
	return func(w http.ResponseWriter, r *http.Request) {
		v, err := fn(w, r)
		if err != nil {
			var he *httpx.Error
			if !errors.As(err, &he) {
				a.E.Log.Error("internal API error", "path", r.URL.Path, "err", err)
			}
			httpx.Fail(w, err)
			return
		}
		httpx.JSON(w, http.StatusOK, v)
	}
}

// runParam returns the run id; a malformed id can't name an armed run, so it is a 404 like any
// unknown run (PUT meta validates the format itself and answers 400).
func runParam(r *http.Request) (string, error) {
	run := r.PathValue("run")
	if !model.ValidRun(run) {
		return "", httpx.NotFound("This run is not armed on the engine.")
	}
	return run, nil
}

func regParam(r *http.Request) (int64, error) {
	n, err := strconv.ParseInt(r.PathValue("reg_id"), 10, 64)
	if err != nil || n <= 0 {
		return 0, httpx.Invalid("reg_id must be a positive integer")
	}
	return n, nil
}

// existing loads a run fresh from Redis (admin paths never use the cache) or 404s.
func (a *API) existing(ctx context.Context, run string) (*engine.RunCtx, error) {
	rc, err := a.E.LoadRun(ctx, run)
	if err != nil {
		return nil, err
	}
	if !rc.Exists() {
		return nil, httpx.NotFound("This run is not armed on the engine.")
	}
	return rc, nil
}

// ---- §2.1 meta ----

func (a *API) putMeta(w http.ResponseWriter, r *http.Request) (any, error) {
	run := r.PathValue("run")
	if !model.ValidRun(run) {
		return nil, httpx.Invalid("run must look like {tenantId}:{examId}")
	}
	body, herr := httpx.ReadBody(w, r, LimitRoster)
	if herr != nil {
		return nil, herr
	}
	m, perr := model.ParseMeta(body)
	if perr != nil {
		return nil, httpx.Invalid("Malformed meta: " + perr.Error())
	}
	if m.Run == "" {
		m.Run = run
	}
	if m.Run != run {
		return nil, httpx.Invalid("meta.run does not match the URL")
	}
	if verr := m.Validate(); verr != nil {
		return nil, httpx.Invalid(verr.Error())
	}
	ctx := r.Context()
	now := a.E.Now()
	old, err := a.E.LoadRun(ctx, run)
	if err != nil {
		return nil, err
	}
	if old.Exists() {
		if old.Closed {
			return nil, httpx.E(http.StatusConflict, httpx.CodeClosed, "This run was closed and can't be re-armed.")
		}
		if !now.Before(old.Meta.StartsAt) && !old.Meta.LockedEqual(m) {
			return nil, httpx.E(http.StatusConflict, httpx.CodeLocked,
				"The exam has started: only start_pin, late_entry_minutes, show_score_on_submit and sync_interval_seconds may change.")
		}
	}
	raw, _ := json.Marshal(m)
	k := store.K(run)
	pipe := a.E.St.R.TxPipeline()
	pipe.Set(ctx, k.Meta(), raw, 0)
	pipe.SAdd(ctx, store.SahodayaKey(m.SahodayaCode), run)
	pipe.SAdd(ctx, store.KeyRuns, run)
	if old.Exists() && old.Meta.SahodayaCode != m.SahodayaCode {
		pipe.SRem(ctx, store.SahodayaKey(old.Meta.SahodayaCode), run)
	}
	if _, err := pipe.Exec(ctx); err != nil {
		return nil, err
	}
	a.E.InvalidateRun(run)
	a.E.InvalidateCode(m.SahodayaCode)
	if old.Exists() {
		a.E.InvalidateCode(old.Meta.SahodayaCode)
	}
	return map[string]any{"ok": true, "run": run, "state": attempt.RunState(m, old.MaxExtra, false, now)}, nil
}

func (a *API) getMeta(w http.ResponseWriter, r *http.Request) (any, error) {
	run, err := runParam(r)
	if err != nil {
		return nil, err
	}
	rc, err := a.existing(r.Context(), run)
	if err != nil {
		return nil, err
	}
	m := *rc.Meta
	m.PasscodeSecret = ""
	return map[string]any{"meta": m, "closed": rc.Closed, "bundle_version": rc.BundleVersion, "pace_seconds": rc.Pace,
		"state": rc.State(a.E.Now()), "questions_known": len(rc.Schema)}, nil
}

// ---- §2.2 roster ----

type rosterBody struct {
	Entries []model.RosterIn `json:"entries"`
}

func (a *API) upsert(ctx context.Context, rc *engine.RunCtx, entries []model.RosterIn) (int64, error) {
	if len(entries) == 0 {
		return 0, nil
	}
	if len(entries) > 2000 {
		return 0, httpx.Invalid("Send the roster in chunks of at most 2000 entries.")
	}
	m := rc.Meta
	args := []any{rc.K.AttPrefix(), attempt.DeadlineBase(m), attempt.Duration(m).Milliseconds()}
	for i := range entries {
		e, err := entries[i].Normalise()
		if err != nil {
			return 0, httpx.Invalid(err.Error())
		}
		js, _ := json.Marshal(e)
		args = append(args, strconv.FormatInt(e.RegID, 10), string(js), e.SchoolID, e.SchoolName, e.HallTicket, e.Username, e.Email, e.ExtraMinutes)
	}
	n, err := a.E.St.Scripts.RosterUpsert.Run(ctx, a.E.St.R,
		[]string{rc.K.Roster(), rc.K.IdxUser(), rc.K.IdxTicket(), rc.K.Agg(), rc.K.Extra(), rc.K.Schools(), rc.K.Deadlines()}, args...).Int64()
	if err != nil {
		return 0, err
	}
	a.E.InvalidateRun(rc.Run)
	return n, nil
}

func (a *API) postRoster(w http.ResponseWriter, r *http.Request) (any, error) {
	run, err := runParam(r)
	if err != nil {
		return nil, err
	}
	var body rosterBody
	if herr := httpx.Decode(w, r, LimitRoster, &body, false); herr != nil {
		return nil, herr
	}
	rc, err := a.existing(r.Context(), run)
	if err != nil {
		return nil, err
	}
	if rc.Closed {
		return nil, httpx.E(http.StatusConflict, httpx.CodeClosed, "This run is closed.")
	}
	n, err := a.upsert(r.Context(), rc, body.Entries)
	if err != nil {
		return nil, err
	}
	total, _ := a.E.St.R.HGet(r.Context(), rc.K.Agg(), "r").Int64()
	return map[string]any{"ok": true, "upserted": n, "roster": total}, nil
}

func (a *API) putRosterEntry(w http.ResponseWriter, r *http.Request) (any, error) {
	run, err := runParam(r)
	if err != nil {
		return nil, err
	}
	reg, err := regParam(r)
	if err != nil {
		return nil, err
	}
	var e model.RosterIn
	if herr := httpx.Decode(w, r, LimitSmall, &e, false); herr != nil {
		return nil, herr
	}
	if e.RegID == 0 {
		e.RegID = reg
	}
	if e.RegID != reg {
		return nil, httpx.Invalid("reg_id in the body does not match the URL")
	}
	rc, err := a.existing(r.Context(), run)
	if err != nil {
		return nil, err
	}
	if rc.Closed {
		return nil, httpx.E(http.StatusConflict, httpx.CodeClosed, "This run is closed.")
	}
	if _, err := a.upsert(r.Context(), rc, []model.RosterIn{e}); err != nil {
		return nil, err
	}
	return map[string]any{"ok": true, "reg_id": reg}, nil
}

func (a *API) deleteRosterEntry(w http.ResponseWriter, r *http.Request) (any, error) {
	run, err := runParam(r)
	if err != nil {
		return nil, err
	}
	reg, err := regParam(r)
	if err != nil {
		return nil, err
	}
	rc, err := a.existing(r.Context(), run)
	if err != nil {
		return nil, err
	}
	n, err := a.E.St.Scripts.RosterDelete.Run(r.Context(), a.E.St.R,
		[]string{rc.K.Roster(), rc.K.IdxUser(), rc.K.IdxTicket(), rc.K.Agg(), rc.K.Extra()}, reg).Int64()
	if err != nil {
		return nil, err
	}
	return map[string]any{"ok": true, "deleted": n == 1}, nil
}

// ---- §2.3 bundle ----

func (a *API) putBundle(w http.ResponseWriter, r *http.Request) (any, error) {
	run, err := runParam(r)
	if err != nil {
		return nil, err
	}
	var p paper.Put
	if herr := httpx.Decode(w, r, LimitBundle, &p, false); herr != nil {
		return nil, herr
	}
	ctx := r.Context()
	rc, err := a.existing(ctx, run)
	if err != nil {
		return nil, err
	}
	if rc.Closed {
		return nil, httpx.E(http.StatusConflict, httpx.CodeClosed, "This run is closed.")
	}
	prep, perr := paper.Prepare(&p)
	if perr != nil {
		return nil, httpx.Invalid(perr.Error())
	}
	if terr := paper.CheckTypes(prep.Schema, rc.Meta.Questions); terr != nil {
		return nil, httpx.Invalid(terr.Error())
	}
	now := a.E.Now()
	if !now.Before(rc.Meta.StartsAt) {
		cur, err := a.E.St.R.Get(ctx, rc.K.Bundle()).Bytes()
		if err == nil && len(cur) > 0 {
			if string(cur) == string(prep.PublicRaw) {
				return map[string]any{"ok": true, "version": prep.Version, "questions": prep.Questions, "sha256": prep.SHA256, "unchanged": true}, nil
			}
			return nil, httpx.E(http.StatusConflict, httpx.CodeLocked, "The exam has started, so the paper can't change.")
		}
	}
	schema, _ := json.Marshal(prep.Schema)
	pipe := a.E.St.R.TxPipeline()
	pipe.Set(ctx, rc.K.Bundle(), prep.PublicRaw, 0)
	pipe.Set(ctx, rc.K.Key(), prep.Key, 0)
	pipe.Set(ctx, rc.K.Schema(), schema, 0)
	pipe.HSet(ctx, rc.K.Agg(), "bv", prep.Version)
	if _, err := pipe.Exec(ctx); err != nil {
		return nil, err
	}
	a.E.Bundles.DropRun(run)
	a.E.InvalidateRun(run)
	return map[string]any{"ok": true, "version": prep.Version, "questions": prep.Questions, "sha256": prep.SHA256}, nil
}

// ---- §2.4 disarm / close / results ----

func (a *API) disarm(w http.ResponseWriter, r *http.Request) (any, error) {
	run, err := runParam(r)
	if err != nil {
		return nil, err
	}
	_, _ = httpx.ReadBody(w, r, LimitSmall)
	ctx := r.Context()
	rc, err := a.E.LoadRun(ctx, run)
	if err != nil {
		return nil, err
	}
	if rc.Exists() && !a.E.Now().Before(rc.Meta.StartsAt) {
		return nil, httpx.E(http.StatusConflict, httpx.CodeLocked, "The exam has started, so it can only be closed, not disarmed.")
	}
	if err := a.E.St.DeleteRun(ctx, run); err != nil {
		return nil, err
	}
	pipe := a.E.St.R.Pipeline()
	pipe.SRem(ctx, store.KeyRuns, run)
	if rc.Exists() {
		pipe.SRem(ctx, store.SahodayaKey(rc.Meta.SahodayaCode), run)
		a.E.InvalidateCode(rc.Meta.SahodayaCode)
	}
	if _, err := pipe.Exec(ctx); err != nil {
		return nil, err
	}
	a.E.Bundles.DropRun(run)
	a.E.InvalidateRun(run)
	a.rosters.Delete(run)
	return map[string]any{"ok": true}, nil
}

// Finalise force-submits writing attempts (mode forced for close, auto for the sweeper). With
// onlyDue, attempts are finalised only once past deadline + grace. Returns how many were finalised.
func Finalise(ctx context.Context, e *engine.Engine, rc *engine.RunCtx, regs []string, mode string, onlyDue bool) (int, error) {
	now := e.Now()
	due := "0"
	if onlyDue {
		due = "1"
	}
	done := 0
	reloaded := false
	for i := 0; i < len(regs); i += 200 {
		end := min(i+200, len(regs))
		pipe := e.St.R.Pipeline()
		cmds := make([]*redis.Cmd, 0, end-i)
		for _, reg := range regs[i:end] {
			id, _ := strconv.ParseInt(reg, 10, 64)
			cmds = append(cmds, e.St.Scripts.Submit.EvalSha(ctx, pipe,
				[]string{rc.K.Att(id), rc.K.Agg(), store.KeyEvents, rc.K.Deadlines(), rc.K.Seen(), rc.K.Ctl()},
				now.UnixMilli(), "", mode, "none", -1, "", "", "", 0, 0, rc.GraceMs(), engine.Receipt(rc.Meta, id), rc.Run, id, e.St.MaxLen, due))
		}
		if _, err := pipe.Exec(ctx); err != nil && !errors.Is(err, redis.Nil) {
			if strings.Contains(err.Error(), "NOSCRIPT") && !reloaded {
				// Redis restarted or flushed its script cache: load and redo this chunk once
				reloaded = true
				if lerr := e.St.Scripts.Submit.Load(ctx, e.St.R).Err(); lerr != nil {
					return done, lerr
				}
				i -= 200
				continue
			}
			return done, err
		}
		for _, c := range cmds {
			if vals, err := store.Slice(c.Result()); err == nil && store.Str(vals, 0) == "submitted" {
				done++
				e.M.Submits.Inc(mode)
			}
		}
	}
	return done, nil
}

func (a *API) close(w http.ResponseWriter, r *http.Request) (any, error) {
	run, err := runParam(r)
	if err != nil {
		return nil, err
	}
	_, _ = httpx.ReadBody(w, r, LimitSmall)
	ctx := r.Context()
	rc, err := a.existing(ctx, run)
	if err != nil {
		return nil, err
	}
	now := a.E.Now()
	first, err := a.E.St.R.HSetNX(ctx, rc.K.Ctl(), "closed", "1").Result()
	if err != nil {
		return nil, err
	}
	if first {
		_ = a.E.St.R.HSet(ctx, rc.K.Ctl(), "closed_at", now.UnixMilli()).Err()
	}
	a.E.InvalidateRun(run)
	rc.Closed = true
	regs, err := a.E.St.R.ZRange(ctx, rc.K.Deadlines(), 0, -1).Result()
	if err != nil {
		return nil, err
	}
	forced, err := Finalise(ctx, a.E, rc, regs, "forced", false)
	if err != nil {
		return nil, err
	}
	if first {
		if _, err := events.Emit(ctx, a.E.St, model.EvClosed, run, 0, now, map[string]any{"unfinished_forced": forced}); err != nil {
			return nil, err
		}
	}
	if err := a.E.St.ExpireRun(ctx, run, store.CloseRetention); err != nil {
		return nil, err
	}
	left, _ := a.E.St.R.ZCard(ctx, rc.K.Deadlines()).Result()
	a.E.InvalidateCode(rc.Meta.SahodayaCode)
	return map[string]any{"ok": true, "unfinished": left, "forced": forced}, nil
}

type resultIn struct {
	Score      *float64 `json:"score"`
	MaxScore   *float64 `json:"max_score"`
	Percentage *float64 `json:"percentage"`
	Grade      *string  `json:"grade"`
}

func (a *API) results(w http.ResponseWriter, r *http.Request) (any, error) {
	run, err := runParam(r)
	if err != nil {
		return nil, err
	}
	var body struct {
		Results map[string]resultIn `json:"results"`
	}
	if herr := httpx.Decode(w, r, LimitRoster, &body, false); herr != nil {
		return nil, herr
	}
	ctx := r.Context()
	rc, err := a.existing(ctx, run)
	if err != nil {
		return nil, err
	}
	if len(body.Results) == 0 {
		return map[string]any{"ok": true, "stored": 0}, nil
	}
	vals := make([]any, 0, len(body.Results)*2)
	for reg, res := range body.Results {
		if n, err := strconv.ParseInt(reg, 10, 64); err != nil || n <= 0 {
			return nil, httpx.Invalid("results keys must be reg ids")
		}
		js, _ := json.Marshal(res)
		vals = append(vals, reg, string(js))
	}
	if err := a.E.St.R.HSet(ctx, rc.K.Results(), vals...).Err(); err != nil {
		return nil, err
	}
	if rc.Closed {
		_ = a.E.St.R.Expire(ctx, rc.K.Results(), store.CloseRetention).Err()
	}
	return map[string]any{"ok": true, "stored": len(body.Results)}, nil
}

// ---- broadcast / pace ----

func (a *API) broadcast(w http.ResponseWriter, r *http.Request) (any, error) {
	run, err := runParam(r)
	if err != nil {
		return nil, err
	}
	var body struct {
		Message string `json:"message"`
		Actor   string `json:"actor"`
	}
	if herr := httpx.Decode(w, r, LimitSmall, &body, false); herr != nil {
		return nil, herr
	}
	body.Message = strings.TrimSpace(body.Message)
	if body.Message == "" || len(body.Message) > 1000 {
		return nil, httpx.Invalid("message must be 1..1000 characters")
	}
	if len(body.Actor) > 200 {
		body.Actor = body.Actor[:200]
	}
	ctx := r.Context()
	rc, err := a.existing(ctx, run)
	if err != nil {
		return nil, err
	}
	now := a.E.Now()
	id, err := a.E.St.R.HIncrBy(ctx, rc.K.Agg(), "msgid", 1).Result()
	if err != nil {
		return nil, err
	}
	msg, _ := json.Marshal(model.Message{ID: id, At: model.ISO(now), Text: body.Message})
	pipe := a.E.St.R.TxPipeline()
	pipe.RPush(ctx, rc.K.Msgs(), msg)
	pipe.LTrim(ctx, rc.K.Msgs(), -50, -1)
	if _, err := pipe.Exec(ctx); err != nil {
		return nil, err
	}
	if _, err := events.Emit(ctx, a.E.St, model.EvBroadcast, run, 0, now, map[string]any{"message": body.Message, "actor": body.Actor}); err != nil {
		return nil, err
	}
	a.E.InvalidateRun(run)
	return map[string]any{"ok": true, "id": id}, nil
}

func (a *API) pace(w http.ResponseWriter, r *http.Request) (any, error) {
	run, err := runParam(r)
	if err != nil {
		return nil, err
	}
	var body struct {
		Seconds *float64 `json:"seconds"`
	}
	if herr := httpx.Decode(w, r, LimitSmall, &body, true); herr != nil {
		return nil, herr
	}
	ctx := r.Context()
	rc, err := a.existing(ctx, run)
	if err != nil {
		return nil, err
	}
	if body.Seconds == nil || *body.Seconds <= 0 {
		if err := a.E.St.R.HDel(ctx, rc.K.Ctl(), "pace").Err(); err != nil {
			return nil, err
		}
		a.E.InvalidateRun(run)
		return map[string]any{"ok": true, "pace_seconds": nil}, nil
	}
	s := int(*body.Seconds)
	if s < 5 || s > 600 {
		return nil, httpx.Invalid("seconds must be 5..600, or null for automatic")
	}
	if err := a.E.St.R.HSet(ctx, rc.K.Ctl(), "pace", s).Err(); err != nil {
		return nil, err
	}
	a.E.InvalidateRun(run)
	return map[string]any{"ok": true, "pace_seconds": s}, nil
}

// ---- whole-run incident controls ----

// runExtend adds minutes to every attempt: a run-level extra (ctl.xm) that the Lua scripts add to
// each stored deadline, so writing attempts move at once and later starts include it. O(1).
func (a *API) runExtend(w http.ResponseWriter, r *http.Request) (any, error) {
	run, err := runParam(r)
	if err != nil {
		return nil, err
	}
	var body struct {
		Minutes *float64 `json:"minutes"`
		Actor   string   `json:"actor"`
		Reason  string   `json:"reason"`
	}
	if herr := httpx.Decode(w, r, LimitSmall, &body, false); herr != nil {
		return nil, herr
	}
	if body.Minutes == nil || *body.Minutes < 1 || *body.Minutes > 600 || *body.Minutes != float64(int(*body.Minutes)) {
		return nil, httpx.Invalid("minutes must be a whole number 1..600")
	}
	minutes := int(*body.Minutes)
	body.Actor = clip(body.Actor, 200)
	ctx := r.Context()
	rc, err := a.existing(ctx, run)
	if err != nil {
		return nil, err
	}
	if rc.Closed {
		return nil, httpx.E(http.StatusConflict, httpx.CodeClosed, "This run is closed.")
	}
	now := a.E.Now()
	data, _ := json.Marshal(map[string]any{"minutes": minutes, "actor": body.Actor, "reason": clip(body.Reason, 500)})
	pipe := a.E.St.R.TxPipeline()
	total := pipe.HIncrBy(ctx, rc.K.Ctl(), "xm", int64(minutes))
	xadd := &redis.XAddArgs{Stream: store.KeyEvents, Values: []any{"type", model.EvRunExtended, "run", run, "reg_id", "", "at", model.ISO(now), "data", string(data)}}
	if a.E.St.MaxLen > 0 {
		xadd.MaxLen, xadd.Approx = a.E.St.MaxLen, true
	}
	pipe.XAdd(ctx, xadd)
	if _, err := pipe.Exec(ctx); err != nil {
		return nil, err
	}
	a.E.InvalidateRun(run)
	return map[string]any{"ok": true, "minutes": minutes, "extended_minutes": total.Val()}, nil
}

// runHold toggles the run-wide hold: while on, the sweeper doesn't auto-submit and students past
// their deadline get action "lock" (with a message) instead of "submit".
func (a *API) runHold(w http.ResponseWriter, r *http.Request) (any, error) {
	run, err := runParam(r)
	if err != nil {
		return nil, err
	}
	var body struct {
		On     *bool  `json:"on"`
		Actor  string `json:"actor"`
		Reason string `json:"reason"`
	}
	if herr := httpx.Decode(w, r, LimitSmall, &body, false); herr != nil {
		return nil, herr
	}
	if body.On == nil {
		return nil, httpx.Invalid("on must be true or false")
	}
	ctx := r.Context()
	rc, err := a.existing(ctx, run)
	if err != nil {
		return nil, err
	}
	if rc.Closed {
		return nil, httpx.E(http.StatusConflict, httpx.CodeClosed, "This run is closed.")
	}
	now := a.E.Now()
	data, _ := json.Marshal(map[string]any{"on": *body.On, "actor": clip(body.Actor, 200), "reason": clip(body.Reason, 500)})
	pipe := a.E.St.R.TxPipeline()
	if *body.On {
		pipe.HSet(ctx, rc.K.Ctl(), "hold", "1", "hold_at", now.UnixMilli())
	} else {
		pipe.HDel(ctx, rc.K.Ctl(), "hold", "hold_at")
	}
	xadd := &redis.XAddArgs{Stream: store.KeyEvents, Values: []any{"type", model.EvRunHold, "run", run, "reg_id", "", "at", model.ISO(now), "data", string(data)}}
	if a.E.St.MaxLen > 0 {
		xadd.MaxLen, xadd.Approx = a.E.St.MaxLen, true
	}
	pipe.XAdd(ctx, xadd)
	if _, err := pipe.Exec(ctx); err != nil {
		return nil, err
	}
	a.E.InvalidateRun(run)
	return map[string]any{"ok": true, "hold": *body.On}, nil
}

func clip(s string, n int) string {
	if len(s) > n {
		return s[:n]
	}
	return s
}

// ---- events ----

func (a *API) claim(w http.ResponseWriter, r *http.Request) (any, error) {
	var body struct {
		Consumer string `json:"consumer"`
		Count    int64  `json:"count"`
		BlockMs  int64  `json:"block_ms"`
	}
	if herr := httpx.Decode(w, r, LimitSmall, &body, false); herr != nil {
		return nil, herr
	}
	body.Consumer = strings.TrimSpace(body.Consumer)
	if body.Consumer == "" || len(body.Consumer) > 64 {
		return nil, httpx.Invalid("consumer is required (at most 64 characters)")
	}
	if body.Count <= 0 {
		body.Count = 200
	}
	body.Count = min(body.Count, 1000)
	block := time.Duration(min(max(body.BlockMs, 0), 10000)) * time.Millisecond
	if block == 0 {
		block = -1 // no blocking
	}
	evs, err := events.Claim(r.Context(), a.E.St, body.Consumer, body.Count, block)
	if err != nil {
		if errors.Is(err, context.Canceled) {
			return map[string]any{"events": []events.Event{}}, nil
		}
		return nil, err
	}
	a.E.M.EventsClaimed.Add(int64(len(evs)))
	if evs == nil {
		evs = []events.Event{}
	}
	return map[string]any{"events": evs}, nil
}

func validStreamID(s string) bool {
	a, b, ok := strings.Cut(s, "-")
	if !ok || a == "" || b == "" {
		return false
	}
	_, e1 := strconv.ParseUint(a, 10, 64)
	_, e2 := strconv.ParseUint(b, 10, 64)
	return e1 == nil && e2 == nil
}

func (a *API) ack(w http.ResponseWriter, r *http.Request) (any, error) {
	var body struct {
		IDs []string `json:"ids"`
	}
	if herr := httpx.Decode(w, r, LimitRoster, &body, false); herr != nil {
		return nil, herr
	}
	if len(body.IDs) > 10000 {
		return nil, httpx.Invalid("at most 10000 ids per call")
	}
	for _, id := range body.IDs {
		if !validStreamID(id) {
			return nil, httpx.Invalid("bad event id " + strconv.Quote(id))
		}
	}
	n, err := events.Ack(r.Context(), a.E.St, body.IDs)
	if err != nil {
		return nil, err
	}
	a.E.M.EventsAcked.Add(n)
	return map[string]any{"acked": n}, nil
}
