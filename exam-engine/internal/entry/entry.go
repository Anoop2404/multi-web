// Package entry implements the identity checks for the exam login (CONTRACT §4, §5): passcode
// normalisation and HMAC, Ed25519 launch tokens and the bounded bcrypt worker pool.
package entry

import (
	"context"
	"crypto/ed25519"
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"errors"
	"regexp"
	"strconv"
	"strings"
	"sync/atomic"
	"time"

	"golang.org/x/crypto/bcrypt"
)

// PasscodeAlphabet is the alphabet Laravel prints passcodes from.
const PasscodeAlphabet = "ABCDEFGHJKMNPQRSTUVWXYZ23456789"

// NormalisePasscode upper-cases and drops everything except A–Z/0–9 (CONTRACT §5.2).
func NormalisePasscode(s string) string {
	var b strings.Builder
	for _, r := range strings.ToUpper(s) {
		if (r >= 'A' && r <= 'Z') || (r >= '0' && r <= '9') {
			b.WriteRune(r)
		}
	}
	return b.String()
}

// PasscodeHMAC = hex(HMAC-SHA256(key = hex-decoded secret, "{reg_id}|{NORMALISED}")).
func PasscodeHMAC(secretHex string, regID int64, normalised string) (string, error) {
	key, err := hex.DecodeString(secretHex)
	if err != nil {
		return "", err
	}
	m := hmac.New(sha256.New, key)
	m.Write([]byte(strconv.FormatInt(regID, 10) + "|" + normalised))
	return hex.EncodeToString(m.Sum(nil)), nil
}

// CheckPasscode compares a typed passcode with the stored HMAC in constant time.
func CheckPasscode(secretHex string, regID int64, typed, storedHex string) bool {
	if storedHex == "" {
		return false
	}
	got, err := PasscodeHMAC(secretHex, regID, NormalisePasscode(typed))
	if err != nil {
		return false
	}
	want, err := hex.DecodeString(storedHex)
	if err != nil {
		return false
	}
	g, _ := hex.DecodeString(got)
	return hmac.Equal(g, want)
}

// GeneratePasscode makes a random XXXXX-XXXXX passcode (used by the load generator and tests).
func GeneratePasscode() string {
	var b [10]byte
	_, _ = rand.Read(b[:])
	out := make([]byte, 0, 11)
	for i, c := range b {
		if i == 5 {
			out = append(out, '-')
		}
		out = append(out, PasscodeAlphabet[int(c)%len(PasscodeAlphabet)])
	}
	return string(out)
}

// LaunchClaims is the launch token payload (CONTRACT §5.3).
type LaunchClaims struct {
	Run   string `json:"run"`
	RegID int64  `json:"reg_id"`
	Exp   int64  `json:"exp"`
	JTI   string `json:"jti"`
}

// Launch token errors.
var (
	ErrTokenMalformed = errors.New("malformed launch token")
	ErrTokenSignature = errors.New("bad launch token signature")
	ErrTokenExpired   = errors.New("launch token expired")
	ErrTokenTooLong   = errors.New("launch token lifetime too long")
	ErrNoLaunchKey    = errors.New("launch tokens are not configured")
)

// MaxLaunchAhead bounds exp (120 s lifetime + 60 s clock skew).
const MaxLaunchAhead = 180 * time.Second

var jtiRe = regexp.MustCompile(`^[A-Za-z0-9_-]{8,128}$`)

func b64(s string) ([]byte, error) {
	s = strings.TrimRight(s, "=")
	if b, err := base64.RawURLEncoding.DecodeString(s); err == nil {
		return b, nil
	}
	return base64.RawStdEncoding.DecodeString(s)
}

// ParsePublicKey decodes a base64 (std or url) 32-byte Ed25519 public key.
func ParsePublicKey(s string) (ed25519.PublicKey, error) {
	b, err := b64(strings.TrimSpace(s))
	if err != nil {
		return nil, err
	}
	if len(b) != ed25519.PublicKeySize {
		return nil, errors.New("launch public key must be 32 bytes")
	}
	return ed25519.PublicKey(b), nil
}

// VerifyLaunch checks a token: base64url(json) "." base64url(signature). The signature is the
// libsodium detached signature over the raw JSON bytes; for robustness a signature over the
// base64url payload segment is accepted too.
func VerifyLaunch(pub ed25519.PublicKey, token string, now time.Time) (*LaunchClaims, error) {
	if len(pub) != ed25519.PublicKeySize {
		return nil, ErrNoLaunchKey
	}
	if len(token) > 2048 {
		return nil, ErrTokenMalformed
	}
	payloadB64, sigB64, ok := strings.Cut(strings.TrimSpace(token), ".")
	if !ok || payloadB64 == "" || sigB64 == "" {
		return nil, ErrTokenMalformed
	}
	payload, err := b64(payloadB64)
	if err != nil {
		return nil, ErrTokenMalformed
	}
	sig, err := b64(sigB64)
	if err != nil || len(sig) != ed25519.SignatureSize {
		return nil, ErrTokenMalformed
	}
	if !ed25519.Verify(pub, payload, sig) && !ed25519.Verify(pub, []byte(payloadB64), sig) {
		return nil, ErrTokenSignature
	}
	var c LaunchClaims
	if err := json.Unmarshal(payload, &c); err != nil {
		return nil, ErrTokenMalformed
	}
	if c.Run == "" || c.RegID <= 0 || c.Exp == 0 || !jtiRe.MatchString(c.JTI) {
		return nil, ErrTokenMalformed
	}
	exp := time.Unix(c.Exp, 0)
	if now.After(exp) {
		return nil, ErrTokenExpired
	}
	if exp.Sub(now) > MaxLaunchAhead {
		return nil, ErrTokenTooLong
	}
	return &c, nil
}

// SignLaunch builds a token (tests and tooling).
func SignLaunch(priv ed25519.PrivateKey, c LaunchClaims) string {
	payload, _ := json.Marshal(c)
	sig := ed25519.Sign(priv, payload)
	return base64.RawURLEncoding.EncodeToString(payload) + "." + base64.RawURLEncoding.EncodeToString(sig)
}

// ErrBusy means the password queue is full or the wait timed out (HTTP 503 busy).
var ErrBusy = errors.New("password check queue is busy")

// Pool bounds concurrent bcrypt checks to `workers`; a request waits up to maxWait for a slot.
type Pool struct {
	// OnWait, when set, observes how long each check waited for a worker (queue wait).
	OnWait   func(time.Duration)
	sem      chan struct{}
	maxWait  time.Duration
	maxQueue int64
	waiting  atomic.Int64
	busy     atomic.Int64
	checks   atomic.Int64
	timeouts atomic.Int64
}

// NewPool creates a pool.
func NewPool(workers int, maxWait time.Duration, maxQueue int) *Pool {
	if workers < 1 {
		workers = 1
	}
	return &Pool{sem: make(chan struct{}, workers), maxWait: maxWait, maxQueue: int64(maxQueue)}
}

// Stats for metrics.
func (p *Pool) Stats() (waiting, busy, checks, timeouts int64, size int) {
	return p.waiting.Load(), p.busy.Load(), p.checks.Load(), p.timeouts.Load(), cap(p.sem)
}

// Waiting returns the number of requests queued for a slot.
func (p *Pool) Waiting() int64 { return p.waiting.Load() }

// Compare runs bcrypt.CompareHashAndPassword on a pool slot.
func (p *Pool) Compare(ctx context.Context, hash, password string) (bool, error) {
	if p.maxQueue > 0 && p.waiting.Load() >= p.maxQueue {
		p.timeouts.Add(1)
		return false, ErrBusy
	}
	p.waiting.Add(1)
	queued := time.Now()
	t := time.NewTimer(p.maxWait)
	defer t.Stop()
	select {
	case p.sem <- struct{}{}:
		p.waiting.Add(-1)
		if p.OnWait != nil {
			p.OnWait(time.Since(queued))
		}
	case <-t.C:
		p.waiting.Add(-1)
		p.timeouts.Add(1)
		if p.OnWait != nil {
			p.OnWait(time.Since(queued))
		}
		return false, ErrBusy
	case <-ctx.Done():
		p.waiting.Add(-1)
		return false, ctx.Err()
	}
	p.busy.Add(1)
	defer func() { p.busy.Add(-1); <-p.sem }()
	p.checks.Add(1)
	// Laravel writes $2y$ hashes; x/crypto/bcrypt accepts that prefix.
	err := bcrypt.CompareHashAndPassword([]byte(hash), []byte(password))
	if err == nil {
		return true, nil
	}
	if errors.Is(err, bcrypt.ErrMismatchedHashAndPassword) {
		return false, nil
	}
	return false, nil // malformed stored hash: treat as a mismatch
}
