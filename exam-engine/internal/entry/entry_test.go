package entry

import (
	"context"
	"crypto/ed25519"
	"crypto/rand"
	"encoding/base64"
	"encoding/json"
	"errors"
	"strings"
	"testing"
	"time"

	"golang.org/x/crypto/bcrypt"
)

const secret = "00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff"

func TestPasscodeNormalisationAndHMAC(t *testing.T) {
	for _, in := range []string{"K7M2P-Q9RTX", "k7m2p q9rtx", " k7m2p–q9rtx ", "K7M2PQ9RTX"} {
		if got := NormalisePasscode(in); got != "K7M2PQ9RTX" {
			t.Errorf("NormalisePasscode(%q) = %q", in, got)
		}
	}
	// reference value computed independently (Python hmac) — same as Laravel's hash_hmac.
	h, err := PasscodeHMAC(secret, 101, "K7M2PQ9RTX")
	if err != nil {
		t.Fatal(err)
	}
	if h != "28a37d7761eaca913667656004e4462a5adf543af2560d4188836b6f6eb8cb47" {
		t.Fatalf("hmac = %s", h)
	}
	if !CheckPasscode(secret, 101, "k7m2p-q9rtx", h) {
		t.Fatal("typed passcode should match")
	}
	if CheckPasscode(secret, 102, "k7m2p-q9rtx", h) {
		t.Fatal("HMAC must be bound to the reg id")
	}
	if CheckPasscode(secret, 101, "K7M2P-Q9RTY", h) {
		t.Fatal("wrong passcode matched")
	}
	p := GeneratePasscode()
	if len(p) != 11 || p[5] != '-' || strings.Trim(strings.ReplaceAll(p, "-", ""), PasscodeAlphabet) != "" {
		t.Fatalf("generated passcode %q", p)
	}
}

func TestLaunchTokenVerify(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(rand.Reader)
	now := time.Unix(1_800_000_000, 0)
	claims := LaunchClaims{Run: "tenant:12", RegID: 101, Exp: now.Add(120 * time.Second).Unix(), JTI: "0123456789abcdef0123456789abcdef"}
	tok := SignLaunch(priv, claims)
	got, err := VerifyLaunch(pub, tok, now)
	if err != nil || got.RegID != 101 || got.Run != "tenant:12" {
		t.Fatalf("verify: %v %+v", err, got)
	}
	if _, err := VerifyLaunch(pub, tok, now.Add(121*time.Second)); !errors.Is(err, ErrTokenExpired) {
		t.Fatalf("expired: %v", err)
	}
	// tampered payload
	parts := strings.SplitN(tok, ".", 2)
	payload, _ := base64.RawURLEncoding.DecodeString(parts[0])
	var m map[string]any
	_ = json.Unmarshal(payload, &m)
	m["reg_id"] = 102
	p2, _ := json.Marshal(m)
	if _, err := VerifyLaunch(pub, base64.RawURLEncoding.EncodeToString(p2)+"."+parts[1], now); !errors.Is(err, ErrTokenSignature) {
		t.Fatalf("tampered: %v", err)
	}
	// another key
	pub2, _, _ := ed25519.GenerateKey(rand.Reader)
	if _, err := VerifyLaunch(pub2, tok, now); !errors.Is(err, ErrTokenSignature) {
		t.Fatalf("wrong key: %v", err)
	}
	// lifetime too long
	long := claims
	long.Exp = now.Add(time.Hour).Unix()
	if _, err := VerifyLaunch(pub, SignLaunch(priv, long), now); !errors.Is(err, ErrTokenTooLong) {
		t.Fatalf("too long: %v", err)
	}
	// signature over the base64url segment is accepted too
	seg := parts[0]
	sig := ed25519.Sign(priv, []byte(seg))
	if _, err := VerifyLaunch(pub, seg+"."+base64.RawURLEncoding.EncodeToString(sig), now); err != nil {
		t.Fatalf("segment signature: %v", err)
	}
	if _, err := VerifyLaunch(nil, tok, now); !errors.Is(err, ErrNoLaunchKey) {
		t.Fatalf("no key: %v", err)
	}
	if _, err := VerifyLaunch(pub, "garbage", now); !errors.Is(err, ErrTokenMalformed) {
		t.Fatalf("garbage: %v", err)
	}
	// public key parsing (std base64 as libsodium tooling prints it)
	pk, err := ParsePublicKey(base64.StdEncoding.EncodeToString(pub))
	if err != nil || !pk.Equal(pub) {
		t.Fatalf("parse key: %v", err)
	}
}

func TestPoolBoundsAndBusy(t *testing.T) {
	hash, _ := bcrypt.GenerateFromPassword([]byte("secret"), bcrypt.MinCost)
	// Laravel writes $2y$ hashes
	y := "$2y$" + string(hash[4:])
	p := NewPool(1, 50*time.Millisecond, 10)
	ok, err := p.Compare(context.Background(), y, "secret")
	if err != nil || !ok {
		t.Fatalf("compare $2y$: %v %v", ok, err)
	}
	if ok, _ := p.Compare(context.Background(), y, "wrong"); ok {
		t.Fatal("wrong password accepted")
	}
	// hold the only slot; the next caller must give up with ErrBusy
	p.sem <- struct{}{}
	_, err = p.Compare(context.Background(), y, "secret")
	<-p.sem
	if !errors.Is(err, ErrBusy) {
		t.Fatalf("expected ErrBusy, got %v", err)
	}
}
