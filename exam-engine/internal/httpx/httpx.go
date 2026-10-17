// Package httpx has the small HTTP helpers shared by the student and internal APIs: JSON
// responses, the contract's error shape, body limits and client IP resolution.
package httpx

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net"
	"net/http"
	"strconv"
	"strings"
)

// Error codes (CONTRACT §1).
const (
	CodeUnauthorized     = "unauthorized"
	CodeForbidden        = "forbidden"
	CodeNotFound         = "not_found"
	CodeInvalid          = "invalid"
	CodeNotOpenYet       = "not_open_yet"
	CodeClosed           = "closed"
	CodeLocked           = "locked"
	CodeDeviceConflict   = "device_conflict"
	CodeBusy             = "busy"
	CodeRateLimited      = "rate_limited"
	CodeAlreadySubmitted = "already_submitted"
	CodeWrongPin         = "wrong_pin"
	CodeInternal         = "internal"
)

// Error is an API error rendered as {"error": code, "message": text, ...extra}.
type Error struct {
	Status     int
	Code       string
	Message    string
	RetryAfter int // seconds; sets Retry-After when > 0
	Extra      map[string]any
}

func (e *Error) Error() string { return fmt.Sprintf("%d %s: %s", e.Status, e.Code, e.Message) }

// E builds an Error.
func E(status int, code, msg string) *Error { return &Error{Status: status, Code: code, Message: msg} }

// With adds an extra field to the error body.
func (e *Error) With(k string, v any) *Error {
	if e.Extra == nil {
		e.Extra = map[string]any{}
	}
	e.Extra[k] = v
	return e
}

// Common errors.
func Unauthorized(msg string) *Error { return E(http.StatusUnauthorized, CodeUnauthorized, msg) }
func Invalid(msg string) *Error      { return E(http.StatusBadRequest, CodeInvalid, msg) }
func NotFound(msg string) *Error     { return E(http.StatusNotFound, CodeNotFound, msg) }
func Internal() *Error {
	return E(http.StatusInternalServerError, CodeInternal, "Something went wrong. Please try again.")
}

// JSON writes v with status. API responses are never cached unless the caller set a policy.
func JSON(w http.ResponseWriter, status int, v any) {
	var buf bytes.Buffer
	enc := json.NewEncoder(&buf)
	enc.SetEscapeHTML(false)
	if err := enc.Encode(v); err != nil {
		status = http.StatusInternalServerError
		buf.Reset()
		buf.WriteString(`{"error":"internal","message":"encoding failed"}`)
	}
	h := w.Header()
	h.Set("Content-Type", "application/json; charset=utf-8")
	if h.Get("Cache-Control") == "" {
		h.Set("Cache-Control", "no-store")
	}
	h.Set("Content-Length", strconv.Itoa(buf.Len()))
	w.WriteHeader(status)
	_, _ = w.Write(buf.Bytes())
}

// Fail writes an error. Non-*Error values become 500 internal.
func Fail(w http.ResponseWriter, err error) {
	var e *Error
	if !errors.As(err, &e) {
		e = Internal()
	}
	body := map[string]any{"error": e.Code, "message": e.Message}
	for k, v := range e.Extra {
		body[k] = v
	}
	if e.RetryAfter > 0 {
		w.Header().Set("Retry-After", strconv.Itoa(e.RetryAfter))
	}
	JSON(w, e.Status, body)
}

// ReadBody reads at most limit bytes (413 invalid beyond that).
func ReadBody(w http.ResponseWriter, r *http.Request, limit int64) ([]byte, *Error) {
	r.Body = http.MaxBytesReader(w, r.Body, limit)
	b, err := io.ReadAll(r.Body)
	if err != nil {
		var mbe *http.MaxBytesError
		if errors.As(err, &mbe) {
			return nil, E(http.StatusRequestEntityTooLarge, CodeInvalid, "Request body is too large.")
		}
		return nil, Invalid("Could not read the request body.")
	}
	return b, nil
}

// Decode reads and decodes a JSON body into v. An empty body (or "[]", which PHP sends for an
// empty array) is accepted when allowEmpty is set.
func Decode(w http.ResponseWriter, r *http.Request, limit int64, v any, allowEmpty bool) *Error {
	b, e := ReadBody(w, r, limit)
	if e != nil {
		return e
	}
	t := bytes.TrimSpace(b)
	if len(t) == 0 || string(t) == "[]" || string(t) == "null" {
		if allowEmpty {
			return nil
		}
		return Invalid("A JSON body is required.")
	}
	if err := json.Unmarshal(t, v); err != nil {
		return Invalid("Malformed JSON: " + trimErr(err))
	}
	return nil
}

func trimErr(err error) string {
	s := err.Error()
	if len(s) > 200 {
		s = s[:200]
	}
	return s
}

// ClientIP returns the caller's IP. With trustProxy it prefers CF-Connecting-IP, then the
// left-most X-Forwarded-For entry, then X-Real-IP.
func ClientIP(r *http.Request, trustProxy bool) string {
	if trustProxy {
		if v := strings.TrimSpace(r.Header.Get("CF-Connecting-IP")); v != "" && net.ParseIP(v) != nil {
			return v
		}
		if xff := r.Header.Get("X-Forwarded-For"); xff != "" {
			first, _, _ := strings.Cut(xff, ",")
			if v := strings.TrimSpace(first); net.ParseIP(v) != nil {
				return v
			}
		}
		if v := strings.TrimSpace(r.Header.Get("X-Real-IP")); v != "" && net.ParseIP(v) != nil {
			return v
		}
	}
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		return r.RemoteAddr
	}
	return host
}

// IsHTTPS reports whether the original request used TLS.
func IsHTTPS(r *http.Request, trustProxy bool) bool {
	if r.TLS != nil {
		return true
	}
	return trustProxy && strings.EqualFold(r.Header.Get("X-Forwarded-Proto"), "https")
}
