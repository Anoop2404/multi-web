package engine

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/redis/go-redis/v9"

	"github.com/sahodaya/exam-engine/internal/attempt"
	"github.com/sahodaya/exam-engine/internal/entry"
	"github.com/sahodaya/exam-engine/internal/httpx"
	"github.com/sahodaya/exam-engine/internal/model"
	"github.com/sahodaya/exam-engine/internal/store"
)

// LoginRequest is the body of POST /api/login plus request facts.
type LoginRequest struct {
	Sahodaya   string `json:"sahodaya"`
	Method     string `json:"method"`
	Username   string `json:"username"`
	Password   string `json:"password"`
	HallTicket string `json:"hall_ticket"`
	Passcode   string `json:"passcode"`
	Token      string `json:"token"`

	IP        string `json:"-"`
	UserAgent string `json:"-"`
}

// LoginResult carries the new session and the state to return.
type LoginResult struct {
	Session Session
	TTL     time.Duration
	State   *StateResp
}

// Lockout policy (CONTRACT §4: 5 failures → 10 min).
const (
	MaxFailures = 5
	LockFor     = 10 * time.Minute
)

func errDetails() *httpx.Error {
	return httpx.Unauthorized("Details don't match. Check them and try again.")
}

func errLocked() *httpx.Error {
	e := httpx.E(http.StatusLocked, httpx.CodeLocked, "Too many attempts. Wait 10 minutes, or ask your school's exam coordinator for help.")
	e.RetryAfter = int(LockFor.Seconds())
	return e
}

type candidate struct {
	rc     *RunCtx
	roster *model.Roster
}

func identityKey(parts ...string) string {
	h := sha256.Sum256([]byte(strings.Join(parts, "\x00")))
	return hex.EncodeToString(h[:12])
}

// Login authenticates by password, passcode or launch token and binds this device.
func (e *Engine) Login(ctx context.Context, req *LoginRequest) (*LoginResult, *httpx.Error) {
	now := e.Now()
	method := strings.ToLower(strings.TrimSpace(req.Method))
	code := model.NormaliseCode(req.Sahodaya)
	var ident string
	switch method {
	case "password":
		ident = strings.ToLower(strings.TrimSpace(req.Username))
	case "passcode":
		ident = model.NormaliseTicket(req.HallTicket)
	case "launch":
		// per token (tokens are single-use anyway): a school lab behind one NAT address
		// must not share a 10-per-minute budget
		t := strings.TrimSpace(req.Token)
		ident = "launch:" + t[:min(len(t), 64)]
	default:
		return nil, httpx.Invalid("method must be password, passcode or launch.")
	}
	if ok, wait := e.LoginLimiter.Allow(identityKey(req.IP, method, code, ident), time.Now()); !ok {
		e.M.Logins.Inc(method, "rate_limited")
		er := httpx.E(http.StatusTooManyRequests, httpx.CodeRateLimited, "Too many sign-in attempts. Please wait a minute.")
		er.RetryAfter = int(wait.Seconds()) + 1
		return nil, er
	}
	var (
		c   *candidate
		err *httpx.Error
	)
	switch method {
	case "password":
		c, err = e.loginPassword(ctx, code, ident, req.Password, now)
	case "passcode":
		c, err = e.loginPasscode(ctx, code, ident, req.Passcode, req.IP, now)
	case "launch":
		c, err = e.loginLaunch(ctx, req.Token, now)
	}
	if err != nil {
		e.M.Logins.Inc(method, err.Code)
		return nil, err
	}
	res, err := e.bind(ctx, c, method, req, now)
	if err != nil {
		e.M.Logins.Inc(method, err.Code)
		return nil, err
	}
	e.M.Logins.Inc(method, "ok")
	return res, nil
}

// policy applies the run-level checks after the identity is proven.
func policy(c *candidate, method string, now time.Time) *httpx.Error {
	m := c.rc.Meta
	if c.rc.Closed {
		return httpx.E(http.StatusConflict, httpx.CodeClosed, "This exam has been closed.")
	}
	if now.Before(m.CheckinOpensAt) {
		return httpx.E(http.StatusConflict, httpx.CodeNotOpenYet, "Check-in for this exam opens at "+model.ISO(m.CheckinOpensAt)+".").
			With("opens_at", model.ISO(m.CheckinOpensAt))
	}
	switch method {
	case "password":
		if !m.AllowsPassword() {
			return httpx.E(http.StatusForbidden, httpx.CodeForbidden, "This exam uses the exam passcode printed on your hall ticket.")
		}
	case "passcode":
		if !m.AllowsPasscode() {
			return httpx.E(http.StatusForbidden, httpx.CodeForbidden, "This exam uses your portal username and password.")
		}
	}
	if !c.roster.Allowed {
		return httpx.E(http.StatusForbidden, httpx.CodeForbidden, "You are not allowed to take this exam. Please contact your school's exam coordinator.")
	}
	return nil
}

func (e *Engine) loadRuns(ctx context.Context, code string) ([]*RunCtx, *httpx.Error) {
	if !model.ValidCode(code) {
		return nil, errDetails()
	}
	runs, err := e.RunsForCode(ctx, code)
	if err != nil {
		return nil, httpx.Internal()
	}
	out := make([]*RunCtx, 0, len(runs))
	for _, r := range runs {
		rc, err := e.Run(ctx, r)
		if err != nil {
			return nil, httpx.Internal()
		}
		if rc.Exists() {
			out = append(out, rc)
		} else {
			// expired 7 days after close (or disarmed elsewhere): drop it from the code's set
			_ = e.St.R.SRem(ctx, store.SahodayaKey(code), r).Err()
		}
	}
	return out, nil
}

// choose picks one candidate (CONTRACT §4: window containing now, else soonest upcoming);
// open runs are preferred over closed ones.
func choose(cands []*candidate, now time.Time) *candidate {
	if len(cands) == 0 {
		return nil
	}
	open := cands[:0:0]
	for _, c := range cands {
		if !c.rc.Closed {
			open = append(open, c)
		}
	}
	if len(open) == 0 {
		open = cands
	}
	metas := make([]*model.Meta, len(open))
	byMeta := map[*model.Meta]*candidate{}
	for i, c := range open {
		metas[i] = c.rc.Meta
		byMeta[c.rc.Meta] = c
	}
	i := attempt.ChooseRun(metas, func(m *model.Meta) int { return byMeta[m].rc.Extra() }, now)
	return open[i]
}

func (e *Engine) rosterEntry(ctx context.Context, rc *RunCtx, reg int64) (*model.Roster, error) {
	raw, err := e.St.R.HGet(ctx, rc.K.Roster(), strconv.FormatInt(reg, 10)).Result()
	if errors.Is(err, redis.Nil) {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	var r model.Roster
	if err := json.Unmarshal([]byte(raw), &r); err != nil {
		return nil, err
	}
	return &r, nil
}

func (e *Engine) failCount(ctx context.Context, rc *RunCtx, id string) int64 {
	n, _ := e.St.R.Get(ctx, rc.K.Fail(id)).Int64()
	return n
}

func (e *Engine) recordFailure(ctx context.Context, rc *RunCtx, id string) int64 {
	k := rc.K.Fail(id)
	pipe := e.St.R.TxPipeline()
	incr := pipe.Incr(ctx, k)
	pipe.PExpire(ctx, k, LockFor)
	_, _ = pipe.Exec(ctx)
	return incr.Val()
}

func (e *Engine) loginPassword(ctx context.Context, code, username, password string, now time.Time) (*candidate, *httpx.Error) {
	if username == "" || password == "" || len(username) > 254 || len(password) > 512 {
		return nil, errDetails()
	}
	runs, herr := e.loadRuns(ctx, code)
	if herr != nil {
		return nil, herr
	}
	if len(runs) == 0 {
		return nil, errDetails()
	}
	pipe := e.St.R.Pipeline()
	cmds := make([]*redis.StringCmd, len(runs))
	for i, rc := range runs {
		cmds[i] = pipe.HGet(ctx, rc.K.IdxUser(), username)
	}
	if _, err := pipe.Exec(ctx); err != nil && !errors.Is(err, redis.Nil) {
		return nil, httpx.Internal()
	}
	var cands []*candidate
	for i, cmd := range cmds {
		reg, err := cmd.Int64()
		if err != nil || reg <= 0 {
			continue
		}
		r, err := e.rosterEntry(ctx, runs[i], reg)
		if err != nil {
			return nil, httpx.Internal()
		}
		if r != nil {
			cands = append(cands, &candidate{rc: runs[i], roster: r})
		}
	}
	c := choose(cands, now)
	if c == nil {
		return nil, errDetails()
	}
	failID := "u:" + username
	if e.failCount(ctx, c.rc, failID) >= MaxFailures {
		return nil, errLocked()
	}
	if c.roster.PasswordHash == "" {
		if e.recordFailure(ctx, c.rc, failID) >= MaxFailures {
			return nil, errLocked()
		}
		return nil, errDetails()
	}
	pw := password
	if len(pw) > 72 { // PHP's bcrypt uses the first 72 bytes
		pw = pw[:72]
	}
	ok, err := e.Pool.Compare(ctx, c.roster.PasswordHash, pw)
	if err != nil {
		if errors.Is(err, entry.ErrBusy) {
			w, _, _, _, size := e.Pool.Stats()
			er := httpx.E(http.StatusServiceUnavailable, httpx.CodeBusy, "Many students are signing in right now. Please try again in a few seconds.")
			er.RetryAfter = 5 + int(w)/max(size, 1)/4
			return nil, er.With("queue", w)
		}
		return nil, httpx.E(http.StatusServiceUnavailable, httpx.CodeBusy, "Sign-in was interrupted. Please try again.")
	}
	if !ok {
		if e.recordFailure(ctx, c.rc, failID) >= MaxFailures {
			return nil, errLocked()
		}
		return nil, errDetails()
	}
	_ = e.St.R.Del(ctx, c.rc.K.Fail(failID)).Err()
	if err := policy(c, "password", now); err != nil {
		return nil, err
	}
	return c, nil
}

func (e *Engine) loginPasscode(ctx context.Context, code, ticket, passcode, ip string, now time.Time) (*candidate, *httpx.Error) {
	norm := entry.NormalisePasscode(passcode)
	if ticket == "" || len(ticket) > 64 || norm == "" || len(norm) > 32 {
		return nil, errDetails()
	}
	runs, herr := e.loadRuns(ctx, code)
	if herr != nil {
		return nil, herr
	}
	if len(runs) == 0 {
		return nil, errDetails()
	}
	pipe := e.St.R.Pipeline()
	cmds := make([]*redis.StringCmd, len(runs))
	for i, rc := range runs {
		cmds[i] = pipe.HGet(ctx, rc.K.IdxTicket(), ticket)
	}
	if _, err := pipe.Exec(ctx); err != nil && !errors.Is(err, redis.Nil) {
		return nil, httpx.Internal()
	}
	// Hall ticket numbers repeat across classes, so the lockout key includes the client IP:
	// one student's typos must not lock out classmates elsewhere sharing the number.
	failID := "t:" + ticket + "|" + ip
	var withTicket []*RunCtx
	var matches []*candidate
	for i, cmd := range cmds {
		raw, err := cmd.Result()
		if err != nil || raw == "" {
			continue
		}
		var regs []int64
		if json.Unmarshal([]byte(raw), &regs) != nil || len(regs) == 0 {
			continue
		}
		rc := runs[i]
		withTicket = append(withTicket, rc)
		if e.failCount(ctx, rc, failID) >= MaxFailures {
			return nil, errLocked()
		}
		ids := make([]string, len(regs))
		for j, r := range regs {
			ids[j] = strconv.FormatInt(r, 10)
		}
		vals, err := e.St.R.HMGet(ctx, rc.K.Roster(), ids...).Result()
		if err != nil {
			return nil, httpx.Internal()
		}
		for _, v := range vals {
			s, _ := v.(string)
			if s == "" {
				continue
			}
			var r model.Roster
			if json.Unmarshal([]byte(s), &r) != nil {
				continue
			}
			if r.PasscodeHMAC != "" && rc.Meta.PasscodeSecret != "" &&
				entry.CheckPasscode(rc.Meta.PasscodeSecret, r.RegID, norm, r.PasscodeHMAC) {
				rr := r
				matches = append(matches, &candidate{rc: rc, roster: &rr})
			}
		}
	}
	c := choose(matches, now)
	if c == nil {
		locked := false
		for _, rc := range withTicket {
			if e.recordFailure(ctx, rc, failID) >= MaxFailures {
				locked = true
			}
		}
		if locked {
			return nil, errLocked()
		}
		return nil, errDetails()
	}
	_ = e.St.R.Del(ctx, c.rc.K.Fail(failID)).Err()
	if err := policy(c, "passcode", now); err != nil {
		return nil, err
	}
	return c, nil
}

func (e *Engine) loginLaunch(ctx context.Context, token string, now time.Time) (*candidate, *httpx.Error) {
	claims, err := entry.VerifyLaunch(e.Cfg.LaunchPublicKey, token, now)
	if err != nil {
		if errors.Is(err, entry.ErrNoLaunchKey) {
			return nil, httpx.E(http.StatusForbidden, httpx.CodeForbidden, "One-click entry is not available. Sign in with your details instead.")
		}
		return nil, httpx.Unauthorized("This entry link has expired. Go back to the portal and press Enter exam again.")
	}
	if !model.ValidRun(claims.Run) {
		return nil, errDetails()
	}
	ok, rerr := e.St.R.SetNX(ctx, store.JTIPrefix+claims.JTI, "1", 300*time.Second).Result()
	if rerr != nil {
		return nil, httpx.Internal()
	}
	if !ok {
		return nil, httpx.Unauthorized("This entry link was already used. Go back to the portal and press Enter exam again.")
	}
	rc, rerr := e.Run(ctx, claims.Run)
	if rerr != nil {
		return nil, httpx.Internal()
	}
	if !rc.Exists() {
		return nil, httpx.E(http.StatusForbidden, httpx.CodeForbidden, "This exam is not open on the exam site.")
	}
	r, rerr := e.rosterEntry(ctx, rc, claims.RegID)
	if rerr != nil {
		return nil, httpx.Internal()
	}
	if r == nil {
		return nil, httpx.E(http.StatusForbidden, httpx.CodeForbidden, "You are not on the list for this exam. Please contact your school's exam coordinator.")
	}
	c := &candidate{rc: rc, roster: r}
	if err := policy(c, "launch", now); err != nil {
		return nil, err
	}
	return c, nil
}

// bind creates the session and binds the device (CONTRACT §5.1).
func (e *Engine) bind(ctx context.Context, c *candidate, method string, req *LoginRequest, now time.Time) (*LoginResult, *httpx.Error) {
	rc := c.rc
	reg := c.roster.RegID
	dev := NewDeviceID()
	token := NewToken()
	ua := req.UserAgent
	if len(ua) > 300 {
		ua = ua[:300]
	}
	ciData, _ := json.Marshal(map[string]any{"ip": req.IP, "user_agent": ua, "method": method})
	swData, _ := json.Marshal(map[string]any{"ip": req.IP, "user_agent": ua})
	idle := time.Duration(rc.Meta.DeviceIdleMinutes) * time.Minute
	res, err := store.Slice(e.St.Scripts.Checkin.Run(ctx, e.St.R,
		[]string{rc.K.Att(reg), rc.K.Agg(), store.KeyEvents},
		now.UnixMilli(), dev, idle.Milliseconds(), c.roster.SchoolID, req.IP, ua, method, rc.Run, reg, token,
		e.St.MaxLen, string(ciData), string(swData)).Result())
	if err != nil {
		e.M.RedisErrors.Inc("checkin")
		e.Log.Error("checkin script failed", "run", rc.Run, "reg_id", reg, "err", err)
		return nil, httpx.Internal()
	}
	if store.Str(res, 0) == "conflict" {
		return nil, httpx.E(http.StatusConflict, httpx.CodeDeviceConflict,
			"You are already signed in on another device. Ask your school's exam coordinator to allow a device switch, or wait a few minutes and try again.")
	}
	if old := store.Str(res, 1); old != "" {
		e.RevokeSession(ctx, old)
	}
	sess := Session{Token: token, Run: rc.Run, RegID: reg, Device: dev}
	ttl := e.SessionTTL(rc, now)
	b, _ := json.Marshal(sess)
	if err := e.St.R.Set(ctx, store.SessionPrefix+token, b, ttl).Err(); err != nil {
		e.M.RedisErrors.Inc("session_set")
		return nil, httpx.Internal()
	}
	e.CacheSession(sess)
	st, herr := e.State(ctx, rc, &sess, false)
	if herr != nil {
		return nil, herr
	}
	return &LoginResult{Session: sess, TTL: ttl, State: st}, nil
}
