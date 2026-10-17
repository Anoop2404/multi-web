package main

import (
	"context"
	"crypto/aes"
	"crypto/cipher"
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"fmt"
	mrand "math/rand/v2"
	"net/url"
	"runtime"
	"strconv"
	"sync"
	"time"

	"golang.org/x/crypto/bcrypt"

	"github.com/sahodaya/exam-engine/internal/entry"
)

type question struct {
	ID      int64    `json:"id"`
	Type    string   `json:"type"`
	Section string   `json:"section"`
	Body    string   `json:"body"`
	Image   *string  `json:"image"`
	Options []option `json:"options"`
	Show    *int     `json:"show_count"`
	Marks   float64  `json:"marks"`
	Neg     float64  `json:"negative_mark"`
}

type option struct {
	Key   string  `json:"key"`
	Label string  `json:"label"`
	Image *string `json:"image"`
}

type paperDoc struct {
	Version   int        `json:"version"`
	Questions []question `json:"questions"`
}

type simStudent struct {
	RegID    int64
	Username string
	Password string
	Ticket   string
	Passcode string
	ByPass   bool // logs in with password
}

type armed struct {
	Run      string
	Code     string
	StartsAt time.Time
	Deadline time.Time
	Grace    time.Duration
	Paper    paperDoc
	Students []*simStudent
}

func runPath(run, suffix string) string {
	return "/internal/runs/" + url.PathEscape(run) + suffix
}

func makePaper(n int) paperDoc {
	types := []string{"single", "multiple", "true_false", "numeric"}
	p := paperDoc{Version: 1}
	for i := 0; i < n; i++ {
		q := question{ID: int64(5001 + i), Type: types[i%4], Section: "Section " + string(rune('A'+i/25)), Body: fmt.Sprintf("Question %d: $\\frac{%d}{2}$ = ?", i+1, i), Marks: 1}
		switch q.Type {
		case "single":
			q.Options = []option{{Key: "a", Label: "1"}, {Key: "b", Label: "2"}, {Key: "c", Label: "3"}, {Key: "d", Label: "4"}}
		case "multiple":
			two := 2
			q.Show = &two
			q.Marks, q.Neg = 2, 0.5
			q.Options = []option{{Key: "a", Label: "1"}, {Key: "b", Label: "2"}, {Key: "c", Label: "3"}, {Key: "d", Label: "4"}, {Key: "e", Label: "5"}}
		case "true_false":
			q.Options = []option{{Key: "a", Label: "True"}, {Key: "b", Label: "False"}}
		case "numeric":
			q.Options = []option{}
		}
		p.Questions = append(p.Questions, q)
	}
	return p
}

func arm(ctx context.Context, a *api, o *opts) (*armed, error) {
	now := time.Now().UTC().Truncate(time.Second)
	durMin := int((o.duration + time.Minute - time.Second) / time.Minute)
	if durMin < 1 {
		durMin = 1
	}
	ar := &armed{Run: o.run, Code: o.code, StartsAt: now.Add(o.lead), Grace: time.Duration(o.grace) * time.Second, Paper: makePaper(o.questions)}
	ar.Deadline = ar.StartsAt.Add(time.Duration(durMin) * time.Minute)
	secret := make([]byte, 32)
	_, _ = rand.Read(secret)
	secretHex := hex.EncodeToString(secret)
	tenant, examID := o.run, int64(1)
	for i := len(o.run) - 1; i >= 0; i-- {
		if o.run[i] == ':' {
			tenant = o.run[:i]
			examID, _ = strconv.ParseInt(o.run[i+1:], 10, 64)
			break
		}
	}
	types := map[string]string{}
	for _, q := range ar.Paper.Questions {
		types[strconv.FormatInt(q.ID, 10)] = q.Type
	}
	iso := func(t time.Time) string { return t.UTC().Format("2006-01-02T15:04:05Z") }
	meta := map[string]any{
		"run": o.run, "tenant_id": tenant, "exam_id": examID, "sahodaya_code": o.code, "sahodaya_name": "Load Test Sahodaya",
		"logo_url": nil, "title": "Load test", "timing": "fixed",
		"checkin_opens_at": iso(now.Add(-time.Minute)), "waiting_opens_at": iso(now.Add(-30 * time.Second)), "starts_at": iso(ar.StartsAt),
		"duration_minutes": durMin, "late_entry_minutes": 5, "grace_seconds": o.grace, "entry_mode": "either", "supervision": "open",
		"start_pin": nil, "show_score_on_submit": false, "shuffle_questions": true, "device_idle_minutes": 2,
		"sync_interval_seconds": 10, "heartbeat_seconds": 30, "coordinator_extend_cap_minutes": 10, "passcode_secret": secretHex,
		"question_count": len(ar.Paper.Questions), "max_score": 0, "bundle_version": 1, "questions": types,
	}
	if r, _ := a.do(ctx, "internal:meta", "PUT", runPath(o.run, ""), meta, "", true); !r.ok() {
		return nil, fmt.Errorf("PUT meta: %s", r)
	}

	// roster: passcodes for everyone, real bcrypt hashes for the password share
	rng := mrand.New(mrand.NewPCG(o.seed, 7))
	ar.Students = make([]*simStudent, o.students)
	entries := make([]map[string]any, o.students)
	var wg sync.WaitGroup
	jobs := make(chan int)
	workers := runtime.NumCPU()
	hashOnce := map[int]bool{}
	for i := 0; i < o.students; i++ {
		s := &simStudent{RegID: int64(100000 + i), Username: fmt.Sprintf("lt/%d", i), Password: fmt.Sprintf("Passw0rd-%d", i),
			Ticket: strconv.Itoa(i%500 + 1), Passcode: entry.GeneratePasscode(), ByPass: rng.Float64()*100 < o.passwordPct}
		ar.Students[i] = s
		hm, _ := entry.PasscodeHMAC(secretHex, s.RegID, entry.NormalisePasscode(s.Passcode))
		entries[i] = map[string]any{
			"reg_id": s.RegID, "name": fmt.Sprintf("Student %d", i), "school_id": fmt.Sprintf("school-%d", i%40),
			"school_name": fmt.Sprintf("School %02d", i%40), "class_name": "Class 8", "hall_ticket": s.Ticket,
			"username": s.Username, "email": fmt.Sprintf("lt%d@example.test", i), "password_hash": nil, "passcode_hmac": hm,
			"extra_minutes": 0, "allowed": true,
		}
		if s.ByPass {
			hashOnce[i] = true
		}
	}
	start := time.Now()
	for w := 0; w < workers; w++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			for i := range jobs {
				h, _ := bcrypt.GenerateFromPassword([]byte(ar.Students[i].Password), o.bcryptCost)
				entries[i]["password_hash"] = "$2y$" + string(h[4:])
			}
		}()
	}
	for i := range hashOnce {
		jobs <- i
	}
	close(jobs)
	wg.Wait()
	if len(hashOnce) > 0 {
		fmt.Printf("  hashed %d passwords (bcrypt cost %d) in %s\n", len(hashOnce), o.bcryptCost, time.Since(start).Round(time.Millisecond))
	}
	for i := 0; i < len(entries); i += 2000 {
		end := min(i+2000, len(entries))
		if r, _ := a.do(ctx, "internal:roster", "POST", runPath(o.run, "/roster"), map[string]any{"entries": entries[i:end]}, "", true); !r.ok() {
			return nil, fmt.Errorf("POST roster: %s", r)
		}
	}

	// encrypted bundle (CONTRACT §2.3)
	plain, _ := json.Marshal(ar.Paper)
	key := make([]byte, 32)
	iv := make([]byte, 12)
	_, _ = rand.Read(key)
	_, _ = rand.Read(iv)
	blk, _ := aes.NewCipher(key)
	g, _ := cipher.NewGCM(blk)
	ct := g.Seal(nil, iv, plain, nil)
	sum := sha256.Sum256(ct)
	bundle := map[string]any{"version": 1, "iv": base64.StdEncoding.EncodeToString(iv), "ciphertext": base64.StdEncoding.EncodeToString(ct),
		"key": base64.StdEncoding.EncodeToString(key), "sha256": hex.EncodeToString(sum[:])}
	if r, _ := a.do(ctx, "internal:bundle", "PUT", runPath(o.run, "/bundle"), bundle, "", true); !r.ok() {
		return nil, fmt.Errorf("PUT bundle: %s", r)
	}
	return ar, nil
}

func decryptPaper(keyB64, ivB64, ctB64 string) (*paperDoc, error) {
	key, err := base64.StdEncoding.DecodeString(keyB64)
	if err != nil {
		return nil, err
	}
	iv, err := base64.StdEncoding.DecodeString(ivB64)
	if err != nil {
		return nil, err
	}
	ct, err := base64.StdEncoding.DecodeString(ctB64)
	if err != nil {
		return nil, err
	}
	blk, err := aes.NewCipher(key)
	if err != nil {
		return nil, err
	}
	g, err := cipher.NewGCM(blk)
	if err != nil {
		return nil, err
	}
	plain, err := g.Open(nil, iv, ct, nil)
	if err != nil {
		return nil, err
	}
	var p paperDoc
	if err := json.Unmarshal(plain, &p); err != nil {
		return nil, err
	}
	return &p, nil
}
