// Package model holds the data shapes shared by the engine packages (CONTRACT §2, §4, §6).
package model

import (
	"bytes"
	"encoding/json"
	"fmt"
	"regexp"
	"strconv"
	"strings"
	"time"
)

// Question types (CONTRACT §1).
const (
	TypeSingle    = "single"
	TypeMultiple  = "multiple"
	TypeTrueFalse = "true_false"
	TypeNumeric   = "numeric"
)

// ValidType reports whether t is one of the four question types.
func ValidType(t string) bool {
	switch t {
	case TypeSingle, TypeMultiple, TypeTrueFalse, TypeNumeric:
		return true
	}
	return false
}

// Attempt states as stored in Redis (the "waiting", "locked" and "ended" views are derived).
const (
	StCheckedIn = "checked_in"
	StWriting   = "writing"
	StSubmitted = "submitted"
)

// Event types (CONTRACT §6).
const (
	EvCheckedIn      = "attempt.checked_in"
	EvStarted        = "attempt.started"
	EvSnapshot       = "attempt.snapshot"
	EvSubmitted      = "attempt.submitted"
	EvDeviceSwitched = "attempt.device_switched"
	EvAction         = "attempt.action"
	EvProctorBatch   = "proctor.batch"
	EvBroadcast      = "run.broadcast"
	EvClosed         = "run.closed"
	EvRunExtended    = "run.extended"
	EvRunHold        = "run.hold"
)

// EmptyChecksum is the SHA-256 of the empty string (no answers).
const EmptyChecksum = "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"

var runRe = regexp.MustCompile(`^[A-Za-z0-9._-]{1,64}:[0-9]{1,18}$`)

// ValidRun reports whether s looks like "{tenantId}:{examId}". The strict alphabet also keeps
// Redis keys and glob patterns built from it safe.
func ValidRun(s string) bool { return runRe.MatchString(s) }

// FlexString accepts a JSON string, number or null (Laravel may send ids as ints).
type FlexString string

func (f *FlexString) UnmarshalJSON(b []byte) error {
	b = bytes.TrimSpace(b)
	if len(b) == 0 || string(b) == "null" {
		*f = ""
		return nil
	}
	if b[0] == '"' {
		var s string
		if err := json.Unmarshal(b, &s); err != nil {
			return err
		}
		*f = FlexString(s)
		return nil
	}
	if b[0] == '-' || (b[0] >= '0' && b[0] <= '9') {
		*f = FlexString(string(b))
		return nil
	}
	if string(b) == "true" || string(b) == "false" {
		*f = FlexString(string(b))
		return nil
	}
	return fmt.Errorf("expected a string or number")
}

// TypeMap is the optional meta `questions` field: question id → type. Accepts {} or [].
type TypeMap map[string]string

func (t *TypeMap) UnmarshalJSON(b []byte) error {
	b = bytes.TrimSpace(b)
	if string(b) == "null" || string(b) == "[]" {
		*t = nil
		return nil
	}
	var m map[string]string
	if err := json.Unmarshal(b, &m); err != nil {
		return err
	}
	*t = m
	return nil
}

// Meta is the run meta (CONTRACT §2.1).
type Meta struct {
	Run                         string     `json:"run"`
	TenantID                    FlexString `json:"tenant_id"`
	ExamID                      int64      `json:"exam_id"`
	SahodayaCode                string     `json:"sahodaya_code"`
	SahodayaName                *string    `json:"sahodaya_name"`
	LogoURL                     *string    `json:"logo_url"`
	Title                       string     `json:"title"`
	Timing                      string     `json:"timing"`
	CheckinOpensAt              time.Time  `json:"checkin_opens_at"`
	WaitingOpensAt              time.Time  `json:"waiting_opens_at"`
	StartsAt                    time.Time  `json:"starts_at"`
	DurationMinutes             int        `json:"duration_minutes"`
	LateEntryMinutes            int        `json:"late_entry_minutes"`
	GraceSeconds                int        `json:"grace_seconds"`
	EntryMode                   string     `json:"entry_mode"`
	Supervision                 string     `json:"supervision"`
	StartPin                    *string    `json:"start_pin"`
	ShowScoreOnSubmit           bool       `json:"show_score_on_submit"`
	ShuffleQuestions            bool       `json:"shuffle_questions"`
	DeviceIdleMinutes           int        `json:"device_idle_minutes"`
	SyncIntervalSeconds         int        `json:"sync_interval_seconds"`
	HeartbeatSeconds            int        `json:"heartbeat_seconds"`
	CoordinatorExtendCapMinutes int        `json:"coordinator_extend_cap_minutes"`
	PasscodeSecret              string     `json:"passcode_secret"`
	QuestionCount               int        `json:"question_count"`
	MaxScore                    float64    `json:"max_score"`
	BundleVersion               int        `json:"bundle_version"`
	Questions                   TypeMap    `json:"questions,omitempty"`
}

// metaIn mirrors Meta but tolerates a numeric start_pin.
type metaIn struct {
	Meta
	StartPin FlexStringPtr `json:"start_pin"`
}

// FlexStringPtr is a nullable FlexString.
type FlexStringPtr struct {
	Set   bool
	Value string
}

func (f *FlexStringPtr) UnmarshalJSON(b []byte) error {
	b = bytes.TrimSpace(b)
	if string(b) == "null" {
		*f = FlexStringPtr{}
		return nil
	}
	var s FlexString
	if err := s.UnmarshalJSON(b); err != nil {
		return err
	}
	*f = FlexStringPtr{Set: true, Value: string(s)}
	return nil
}

// ParseMeta decodes and normalises a meta document.
func ParseMeta(b []byte) (*Meta, error) {
	var in metaIn
	if err := json.Unmarshal(b, &in); err != nil {
		return nil, err
	}
	m := in.Meta
	m.StartPin = nil
	if in.StartPin.Set && strings.TrimSpace(in.StartPin.Value) != "" {
		v := strings.TrimSpace(in.StartPin.Value)
		m.StartPin = &v
	}
	m.Normalise()
	return &m, nil
}

// Normalise fills defaults and canonical forms.
func (m *Meta) Normalise() {
	m.SahodayaCode = NormaliseCode(m.SahodayaCode)
	m.CheckinOpensAt = m.CheckinOpensAt.UTC()
	m.WaitingOpensAt = m.WaitingOpensAt.UTC()
	m.StartsAt = m.StartsAt.UTC()
	if m.Timing == "" {
		m.Timing = "fixed"
	}
	if m.EntryMode == "" {
		m.EntryMode = "either"
	}
	if m.Supervision == "" {
		m.Supervision = "open"
	}
	if m.DeviceIdleMinutes <= 0 {
		m.DeviceIdleMinutes = 2
	}
	if m.SyncIntervalSeconds <= 0 {
		m.SyncIntervalSeconds = 10
	}
	if m.HeartbeatSeconds <= 0 {
		m.HeartbeatSeconds = 30
	}
	if m.GraceSeconds < 0 {
		m.GraceSeconds = 0
	}
	if m.LateEntryMinutes < 0 {
		m.LateEntryMinutes = 0
	}
	m.PasscodeSecret = strings.ToLower(strings.TrimSpace(m.PasscodeSecret))
}

var hex64 = regexp.MustCompile(`^[0-9a-f]{64}$`)

// Validate checks a normalised meta document.
func (m *Meta) Validate() error {
	var errs []string
	add := func(f string, a ...any) { errs = append(errs, fmt.Sprintf(f, a...)) }
	if !ValidRun(m.Run) {
		add("run must look like {tenantId}:{examId}")
	}
	if m.SahodayaCode == "" || !codeRe.MatchString(m.SahodayaCode) {
		add("sahodaya_code is required (letters, digits, - or _)")
	}
	if strings.TrimSpace(m.Title) == "" {
		add("title is required")
	}
	if m.Timing != "fixed" && m.Timing != "individual" {
		add("timing must be fixed or individual")
	}
	if m.StartsAt.IsZero() || m.CheckinOpensAt.IsZero() || m.WaitingOpensAt.IsZero() {
		add("checkin_opens_at, waiting_opens_at and starts_at are required")
	} else {
		if m.CheckinOpensAt.After(m.WaitingOpensAt) {
			add("checkin_opens_at must not be after waiting_opens_at")
		}
		if m.WaitingOpensAt.After(m.StartsAt) {
			add("waiting_opens_at must not be after starts_at")
		}
	}
	if m.DurationMinutes <= 0 || m.DurationMinutes > 24*60 {
		add("duration_minutes must be 1..1440")
	}
	if m.LateEntryMinutes > 24*60 {
		add("late_entry_minutes is too large")
	}
	if m.GraceSeconds > 3600 {
		add("grace_seconds must be at most 3600")
	}
	switch m.EntryMode {
	case "either", "portal_password", "exam_passcode":
	default:
		add("entry_mode must be either, portal_password or exam_passcode")
	}
	switch m.Supervision {
	case "open", "supervised":
	default:
		add("supervision must be open or supervised")
	}
	if m.Supervision == "supervised" && (m.StartPin == nil || *m.StartPin == "") {
		add("start_pin is required when supervision is supervised")
	}
	if m.SyncIntervalSeconds < 2 || m.SyncIntervalSeconds > 600 {
		add("sync_interval_seconds must be 2..600")
	}
	if m.EntryMode != "portal_password" && !hex64.MatchString(m.PasscodeSecret) {
		add("passcode_secret must be 64 hex characters")
	} else if m.PasscodeSecret != "" && !hex64.MatchString(m.PasscodeSecret) {
		add("passcode_secret must be 64 hex characters")
	}
	for id, t := range m.Questions {
		if !ValidQID(id) {
			add("questions: %q is not a numeric question id", id)
			break
		}
		if !ValidType(t) {
			add("questions: %q has unknown type %q", id, t)
			break
		}
	}
	if len(m.Questions) > 2000 {
		add("questions: too many questions")
	}
	if len(errs) > 0 {
		return fmt.Errorf("%s", strings.Join(errs, "; "))
	}
	return nil
}

var codeRe = regexp.MustCompile(`^[A-Z0-9_-]{1,32}$`)

// NormaliseCode upper-cases and trims a Sahodaya code.
func NormaliseCode(s string) string { return strings.ToUpper(strings.TrimSpace(s)) }

// ValidCode reports whether a normalised code is acceptable.
func ValidCode(s string) bool { return codeRe.MatchString(s) }

var qidRe = regexp.MustCompile(`^[0-9]{1,18}$`)

// ValidQID reports whether s is a numeric question id.
func ValidQID(s string) bool { return qidRe.MatchString(s) }

// NormaliseTicket canonicalises a hall ticket number for indexing and matching.
func NormaliseTicket(s string) string { return strings.ToUpper(strings.TrimSpace(s)) }

// Locked fields: after starts_at only these may change (CONTRACT §2.1).
func (m *Meta) LockedEqual(o *Meta) bool {
	a, b := *m, *o
	a.StartPin, b.StartPin = nil, nil
	a.LateEntryMinutes, b.LateEntryMinutes = 0, 0
	a.ShowScoreOnSubmit, b.ShowScoreOnSubmit = false, false
	a.SyncIntervalSeconds, b.SyncIntervalSeconds = 0, 0
	ja, _ := json.Marshal(a)
	jb, _ := json.Marshal(b)
	return bytes.Equal(ja, jb)
}

// StartPinValue returns the PIN or "".
func (m *Meta) StartPinValue() string {
	if m.StartPin == nil {
		return ""
	}
	return *m.StartPin
}

// PinRequired reports whether /api/start needs a PIN.
func (m *Meta) PinRequired() bool { return m.Supervision == "supervised" && m.StartPinValue() != "" }

// AllowsPassword / AllowsPasscode apply entry_mode.
func (m *Meta) AllowsPassword() bool {
	return m.EntryMode == "either" || m.EntryMode == "portal_password"
}
func (m *Meta) AllowsPasscode() bool {
	return m.EntryMode == "either" || m.EntryMode == "exam_passcode"
}

// RosterIn is one roster entry as Laravel sends it (CONTRACT §2.2).
type RosterIn struct {
	RegID        int64      `json:"reg_id"`
	Name         string     `json:"name"`
	SchoolID     FlexString `json:"school_id"`
	SchoolName   *string    `json:"school_name"`
	ClassName    *string    `json:"class_name"`
	HallTicket   FlexString `json:"hall_ticket"`
	Username     *string    `json:"username"`
	Email        *string    `json:"email"`
	PasswordHash *string    `json:"password_hash"`
	PasscodeHMAC *string    `json:"passcode_hmac"`
	ExtraMinutes int        `json:"extra_minutes"`
	Allowed      *bool      `json:"allowed"`
}

// Roster is the stored roster entry: nulls become "" so the Lua scripts stay simple.
type Roster struct {
	RegID        int64  `json:"reg_id"`
	Name         string `json:"name"`
	SchoolID     string `json:"school_id"`
	SchoolName   string `json:"school_name"`
	ClassName    string `json:"class_name"`
	HallTicket   string `json:"hall_ticket"`
	Username     string `json:"username"`
	Email        string `json:"email"`
	PasswordHash string `json:"password_hash"`
	PasscodeHMAC string `json:"passcode_hmac"`
	ExtraMinutes int    `json:"extra_minutes"`
	Allowed      bool   `json:"allowed"`
}

func deref(p *string) string {
	if p == nil {
		return ""
	}
	return strings.TrimSpace(*p)
}

// Normalise validates an incoming entry and converts it to the stored form.
func (r *RosterIn) Normalise() (*Roster, error) {
	if r.RegID <= 0 {
		return nil, fmt.Errorf("reg_id must be a positive integer")
	}
	out := &Roster{
		RegID:        r.RegID,
		Name:         strings.TrimSpace(r.Name),
		SchoolID:     strings.TrimSpace(string(r.SchoolID)),
		SchoolName:   deref(r.SchoolName),
		ClassName:    deref(r.ClassName),
		HallTicket:   NormaliseTicket(string(r.HallTicket)),
		Username:     strings.ToLower(deref(r.Username)),
		Email:        strings.ToLower(deref(r.Email)),
		PasswordHash: deref(r.PasswordHash),
		PasscodeHMAC: strings.ToLower(deref(r.PasscodeHMAC)),
		ExtraMinutes: r.ExtraMinutes,
		Allowed:      r.Allowed == nil || *r.Allowed,
	}
	if out.Name == "" {
		out.Name = "Candidate " + strconv.FormatInt(r.RegID, 10)
	}
	if out.ExtraMinutes < 0 || out.ExtraMinutes > 24*60 {
		return nil, fmt.Errorf("reg_id %d: extra_minutes must be 0..1440", r.RegID)
	}
	if len(out.Name) > 300 || len(out.SchoolName) > 300 || len(out.ClassName) > 100 || len(out.HallTicket) > 64 ||
		len(out.Username) > 200 || len(out.Email) > 254 || len(out.PasswordHash) > 200 || len(out.SchoolID) > 64 {
		return nil, fmt.Errorf("reg_id %d: a field is too long", r.RegID)
	}
	if strings.ContainsAny(out.SchoolID, "\t\n:") {
		return nil, fmt.Errorf("reg_id %d: school_id has invalid characters", r.RegID)
	}
	if out.PasscodeHMAC != "" && !hex64.MatchString(out.PasscodeHMAC) {
		return nil, fmt.Errorf("reg_id %d: passcode_hmac must be 64 hex characters", r.RegID)
	}
	return out, nil
}

// Message is a broadcast shown to students (CONTRACT §4).
type Message struct {
	ID   int64  `json:"id"`
	At   string `json:"at"`
	Text string `json:"text"`
}

// QSpec is what the engine knows about one question: type and option keys (no answers).
type QSpec struct {
	T string   `json:"t"`
	O []string `json:"o,omitempty"`
}

// Schema maps question id → spec.
type Schema map[string]QSpec

// ISO formats a time as RFC 3339 UTC with Z and second precision.
func ISO(t time.Time) string { return t.UTC().Format("2006-01-02T15:04:05Z") }

// ISOms formats unix milliseconds.
func ISOms(ms int64) string { return ISO(time.UnixMilli(ms)) }

// Ptr helpers.
func StrPtr(s string) *string { return &s }
