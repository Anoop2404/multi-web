// Package syncer implements the answer-sync rules: canonical checksum (CONTRACT §1), answer
// shape validation per question type, diff application and server pacing (CONTRACT §7).
package syncer

import (
	"bytes"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"slices"
	"sort"
	"strconv"
	"strings"

	"github.com/sahodaya/exam-engine/internal/model"
)

// Answers maps question id → value. A value is a string (single, true_false, numeric) or a
// sorted []string (multiple). Unanswered questions are absent.
type Answers map[string]any

// Limits on what a client may send.
const (
	MaxAnswers     = 1000
	MaxKeyLen      = 16
	MaxNumericLen  = 32
	MaxMultiKeys   = 26
	MaxFlagged     = 1000
	MaxEventsBatch = 50
)

// compareQIDs orders numeric ids as numbers (then as text for safety).
func compareQIDs(a, b string) int {
	na, ea := strconv.ParseUint(a, 10, 64)
	nb, eb := strconv.ParseUint(b, 10, 64)
	if ea == nil && eb == nil && na != nb {
		if na < nb {
			return -1
		}
		return 1
	}
	return strings.Compare(a, b)
}

// SortQIDs sorts question ids numerically.
func SortQIDs(ids []string) { slices.SortFunc(ids, compareQIDs) }

// canonicalValue renders one value for the canonical string; "" means unanswered.
func canonicalValue(v any) string {
	switch x := v.(type) {
	case string:
		return strings.TrimSpace(x)
	case []string:
		if len(x) == 0 {
			return ""
		}
		c := slices.Clone(x)
		sort.Strings(c)
		return strings.Join(c, ",")
	case []any:
		s := make([]string, 0, len(x))
		for _, e := range x {
			if es, ok := e.(string); ok {
				s = append(s, es)
			}
		}
		return canonicalValue(s)
	case nil:
		return ""
	default:
		return strings.TrimSpace(fmt.Sprint(x))
	}
}

// Canonical builds the canonical answer string: "{qid}={value}\n" per answered question,
// by numeric question id ascending.
func Canonical(a Answers) string {
	ids := make([]string, 0, len(a))
	for k := range a {
		ids = append(ids, k)
	}
	SortQIDs(ids)
	var b strings.Builder
	for _, id := range ids {
		v := canonicalValue(a[id])
		if v == "" {
			continue
		}
		b.WriteString(id)
		b.WriteByte('=')
		b.WriteString(v)
		b.WriteByte('\n')
	}
	return b.String()
}

// Checksum is the lowercase hex SHA-256 of the canonical string.
func Checksum(a Answers) string {
	sum := sha256.Sum256([]byte(Canonical(a)))
	return hex.EncodeToString(sum[:])
}

// Clone copies an answer map (values are immutable once normalised).
func Clone(a Answers) Answers {
	out := make(Answers, len(a))
	for k, v := range a {
		out[k] = v
	}
	return out
}

// Decode parses a stored answer map (values become string or []string).
func Decode(raw string) (Answers, error) {
	if raw == "" {
		return Answers{}, nil
	}
	var m map[string]json.RawMessage
	if err := json.Unmarshal([]byte(raw), &m); err != nil {
		return nil, err
	}
	out := make(Answers, len(m))
	for k, v := range m {
		v = bytes.TrimSpace(v)
		if len(v) > 0 && v[0] == '[' {
			var s []string
			if err := json.Unmarshal(v, &s); err != nil {
				return nil, err
			}
			out[k] = s
		} else {
			var s string
			if err := json.Unmarshal(v, &s); err != nil {
				return nil, err
			}
			out[k] = s
		}
	}
	return out, nil
}

// Encode serialises an answer map (stable key order courtesy of encoding/json).
func Encode(a Answers) string {
	if len(a) == 0 {
		return "{}"
	}
	b, _ := json.Marshal(map[string]any(a))
	return string(b)
}

// ValidationError is returned for malformed answers (HTTP 400 invalid).
type ValidationError struct{ Msg string }

func (e *ValidationError) Error() string { return e.Msg }

func verr(f string, a ...any) error { return &ValidationError{Msg: fmt.Sprintf(f, a...)} }

func isNumericText(s string) bool {
	if s == "" || len(s) > MaxNumericLen {
		return false
	}
	digits := 0
	for _, r := range s {
		switch {
		case r >= '0' && r <= '9':
			digits++
		case r == '+' || r == '-' || r == '.' || r == ',' || r == 'e' || r == 'E' || r == ' ':
		default:
			return false
		}
	}
	return digits > 0
}

// NormaliseValue validates one answer value against the question spec. It returns the stored
// value, or cleared=true for null / "" / [] (unanswered). spec may be nil when the engine
// doesn't know the paper; then only generic shapes are checked.
func NormaliseValue(qid string, raw json.RawMessage, spec *model.QSpec) (val any, cleared bool, err error) {
	if !model.ValidQID(qid) {
		return nil, false, verr("question id %q is not numeric", qid)
	}
	raw = bytes.TrimSpace(raw)
	if len(raw) == 0 || string(raw) == "null" {
		return nil, true, nil
	}
	typ := ""
	if spec != nil {
		typ = spec.T
	}
	inOptions := func(k string) bool {
		if spec == nil || len(spec.O) == 0 {
			return len(k) <= MaxKeyLen
		}
		return slices.Contains(spec.O, k)
	}
	switch raw[0] {
	case '[':
		if typ != "" && typ != model.TypeMultiple {
			return nil, false, verr("question %s takes a single value", qid)
		}
		var keys []string
		if err := json.Unmarshal(raw, &keys); err != nil {
			return nil, false, verr("question %s: options must be strings", qid)
		}
		if len(keys) > MaxMultiKeys {
			return nil, false, verr("question %s: too many options", qid)
		}
		seen := make(map[string]bool, len(keys))
		out := make([]string, 0, len(keys))
		for _, k := range keys {
			k = strings.TrimSpace(k)
			if k == "" || seen[k] {
				continue
			}
			if !inOptions(k) {
				return nil, false, verr("question %s: unknown option %q", qid, k)
			}
			seen[k] = true
			out = append(out, k)
		}
		if len(out) == 0 {
			return nil, true, nil
		}
		sort.Strings(out)
		return out, false, nil
	case '"':
		var s string
		if err := json.Unmarshal(raw, &s); err != nil {
			return nil, false, verr("question %s: bad value", qid)
		}
		s = strings.TrimSpace(s)
		if s == "" {
			return nil, true, nil
		}
		switch typ {
		case model.TypeMultiple:
			// tolerate a lone key for a multiple question
			if !inOptions(s) {
				return nil, false, verr("question %s: unknown option %q", qid, s)
			}
			return []string{s}, false, nil
		case model.TypeNumeric:
			if !isNumericText(s) {
				return nil, false, verr("question %s: %q is not a number", qid, s)
			}
			return s, false, nil
		case model.TypeSingle, model.TypeTrueFalse:
			if !inOptions(s) {
				return nil, false, verr("question %s: unknown option %q", qid, s)
			}
			return s, false, nil
		default:
			if len(s) > MaxNumericLen {
				return nil, false, verr("question %s: value too long", qid)
			}
			return s, false, nil
		}
	default:
		// a bare JSON number (numeric questions typed by a lenient client)
		if typ != "" && typ != model.TypeNumeric {
			return nil, false, verr("question %s: bad value", qid)
		}
		s := string(raw)
		if !isNumericText(s) {
			return nil, false, verr("question %s: bad value", qid)
		}
		return s, false, nil
	}
}

// Change is one parsed diff entry; Clear means "remove the answer".
type Change struct {
	QID   string
	Value any
	Clear bool
}

// ParseChanges validates a diff object (qid → value|null).
func ParseChanges(raw json.RawMessage, schema model.Schema) ([]Change, error) {
	raw = bytes.TrimSpace(raw)
	if len(raw) == 0 || string(raw) == "null" || string(raw) == "[]" || string(raw) == "{}" {
		return nil, nil
	}
	var m map[string]json.RawMessage
	if err := json.Unmarshal(raw, &m); err != nil {
		return nil, verr("changes must be an object of question id → value")
	}
	if len(m) > MaxAnswers {
		return nil, verr("too many changes")
	}
	out := make([]Change, 0, len(m))
	for qid, v := range m {
		var spec *model.QSpec
		if schema != nil {
			s, ok := schema[qid]
			if !ok {
				return nil, verr("question %s is not on this paper", qid)
			}
			spec = &s
		}
		val, clear, err := NormaliseValue(qid, v, spec)
		if err != nil {
			return nil, err
		}
		out = append(out, Change{QID: qid, Value: val, Clear: clear})
	}
	return out, nil
}

// ParseFull validates a full answer map (resync); null entries are ignored.
func ParseFull(raw json.RawMessage, schema model.Schema) (Answers, error) {
	ch, err := ParseChanges(raw, schema)
	if err != nil {
		return nil, err
	}
	out := make(Answers, len(ch))
	for _, c := range ch {
		if !c.Clear {
			out[c.QID] = c.Value
		}
	}
	return out, nil
}

func sameValue(a, b any) bool {
	return canonicalValue(a) == canonicalValue(b)
}

// Apply applies changes to a copy of base and returns it with the number of questions whose
// answer actually changed.
func Apply(base Answers, changes []Change) (Answers, int) {
	out := Clone(base)
	n := 0
	for _, c := range changes {
		old, had := out[c.QID]
		if c.Clear {
			if had {
				delete(out, c.QID)
				n++
			}
			continue
		}
		if !had || !sameValue(old, c.Value) {
			n++
		}
		out[c.QID] = c.Value
	}
	return out, n
}

// Diff counts questions whose answer differs between a and b.
func Diff(a, b Answers) int {
	n := 0
	for k, v := range a {
		if w, ok := b[k]; !ok || !sameValue(v, w) {
			n++
		}
	}
	for k := range b {
		if _, ok := a[k]; !ok {
			n++
		}
	}
	return n
}

// ParseFlagged validates the flagged list and returns its canonical JSON (sorted, unique).
// ok=false means the field was absent (keep the stored list).
func ParseFlagged(raw json.RawMessage, schema model.Schema) (string, bool, error) {
	raw = bytes.TrimSpace(raw)
	if len(raw) == 0 || string(raw) == "null" {
		return "", false, nil
	}
	var items []json.RawMessage
	if err := json.Unmarshal(raw, &items); err != nil {
		return "", false, verr("flagged must be a list of question ids")
	}
	if len(items) > MaxFlagged {
		return "", false, verr("too many flagged questions")
	}
	seen := map[string]bool{}
	ids := make([]string, 0, len(items))
	for _, it := range items {
		var s string
		if err := json.Unmarshal(it, &s); err != nil {
			// tolerate numbers
			var n json.Number
			if err2 := json.Unmarshal(it, &n); err2 != nil {
				return "", false, verr("flagged must be a list of question ids")
			}
			s = n.String()
		}
		s = strings.TrimSpace(s)
		if !model.ValidQID(s) {
			return "", false, verr("flagged: %q is not a question id", s)
		}
		if schema != nil {
			if _, ok := schema[s]; !ok {
				continue // ignore ids not on the paper
			}
		}
		if !seen[s] {
			seen[s] = true
			ids = append(ids, s)
		}
	}
	SortQIDs(ids)
	b, _ := json.Marshal(ids)
	return string(b), true, nil
}

// Pace returns next_sync_in: the override if set, else sync_interval raised to 20/30/60 when
// in-flight API requests exceed the thresholds (CONTRACT §7).
func Pace(base int, override int, inflight int64, thresholds [3]int) int {
	if override > 0 {
		return override
	}
	level := 0
	switch {
	case thresholds[2] > 0 && inflight > int64(thresholds[2]):
		level = 60
	case thresholds[1] > 0 && inflight > int64(thresholds[1]):
		level = 30
	case thresholds[0] > 0 && inflight > int64(thresholds[0]):
		level = 20
	}
	if base < level {
		return level
	}
	if base <= 0 {
		return 10
	}
	return base
}
