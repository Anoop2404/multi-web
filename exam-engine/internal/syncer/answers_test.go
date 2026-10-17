package syncer

import (
	"encoding/json"
	"testing"

	"github.com/sahodaya/exam-engine/internal/model"
)

func TestChecksumCanonicalisation(t *testing.T) {
	// numeric qid order (10000 after 5502), multiple keys sorted and joined, numeric trimmed,
	// cleared/empty values left out.
	a := Answers{
		"10000": " 12.5 ",
		"5502":  "b",
		"5501":  []string{"c", "a"},
		"5503":  []string{},
		"5504":  "",
	}
	want := "5501=a,c\n5502=b\n10000=12.5\n"
	if got := Canonical(a); got != want {
		t.Fatalf("canonical = %q, want %q", got, want)
	}
	if got := Checksum(a); got != "494232431186c8dfed0c69a94d8b8a70dc588acc090a4fea100472d260d6de33" {
		t.Fatalf("checksum = %s", got)
	}
	if got := Checksum(Answers{}); got != model.EmptyChecksum {
		t.Fatalf("empty checksum = %s", got)
	}
	// round trip through storage keeps the checksum
	b, err := Decode(Encode(a))
	if err != nil {
		t.Fatal(err)
	}
	if Checksum(b) != Checksum(a) {
		t.Fatal("checksum changed after encode/decode")
	}
}

func TestNormaliseValue(t *testing.T) {
	schema := model.Schema{
		"1": {T: model.TypeSingle, O: []string{"a", "b", "c", "d"}},
		"2": {T: model.TypeMultiple, O: []string{"a", "b", "c", "d", "e"}},
		"3": {T: model.TypeTrueFalse, O: []string{"a", "b"}},
		"4": {T: model.TypeNumeric},
	}
	cases := []struct {
		qid, raw string
		want     string // JSON of normalised value, "" = cleared, "ERR" = error
	}{
		{"1", `"b"`, `"b"`},
		{"1", `"z"`, "ERR"},
		{"1", `["a"]`, "ERR"},
		{"1", `null`, ""},
		{"2", `["c","a","c"]`, `["a","c"]`},
		{"2", `[]`, ""},
		{"2", `["x"]`, "ERR"},
		{"2", `"b"`, `["b"]`},
		{"3", `"a"`, `"a"`},
		{"3", `"c"`, "ERR"},
		{"4", `" 12.5 "`, `"12.5"`},
		{"4", `-3`, `"-3"`},
		{"4", `"abc"`, "ERR"},
		{"4", `""`, ""},
		{"9", `"a"`, "ERR"}, // not on the paper (checked in ParseChanges)
	}
	for _, c := range cases {
		ch, err := ParseChanges(json.RawMessage(`{"`+c.qid+`":`+c.raw+`}`), schema)
		if c.want == "ERR" {
			if err == nil {
				t.Errorf("%s %s: expected an error, got %+v", c.qid, c.raw, ch)
			}
			continue
		}
		if err != nil {
			t.Errorf("%s %s: unexpected error %v", c.qid, c.raw, err)
			continue
		}
		if c.want == "" {
			if !ch[0].Clear {
				t.Errorf("%s %s: expected cleared", c.qid, c.raw)
			}
			continue
		}
		got, _ := json.Marshal(ch[0].Value)
		if string(got) != c.want {
			t.Errorf("%s %s: got %s want %s", c.qid, c.raw, got, c.want)
		}
	}
	// without a schema only generic shapes are checked
	if _, err := ParseChanges(json.RawMessage(`{"77":"anything"}`), nil); err != nil {
		t.Errorf("schema-less change rejected: %v", err)
	}
	if _, err := ParseChanges(json.RawMessage(`{"x1":"a"}`), nil); err == nil {
		t.Error("non-numeric qid accepted")
	}
}

func TestApplyAndDiff(t *testing.T) {
	base := Answers{"1": "a", "2": []string{"a", "b"}}
	next, n := Apply(base, []Change{{QID: "1", Value: "a"}, {QID: "2", Clear: true}, {QID: "3", Value: "b"}})
	if n != 2 {
		t.Fatalf("changed = %d, want 2", n)
	}
	if _, ok := next["2"]; ok {
		t.Fatal("cleared answer still present")
	}
	if next["3"] != "b" || next["1"] != "a" {
		t.Fatalf("unexpected map %v", next)
	}
	if _, ok := base["3"]; ok {
		t.Fatal("Apply mutated its input")
	}
	if d := Diff(base, next); d != 2 {
		t.Fatalf("diff = %d, want 2", d)
	}
}

func TestParseFlagged(t *testing.T) {
	s, ok, err := ParseFlagged(json.RawMessage(`["10","9",9,"10"]`), nil)
	if err != nil || !ok || s != `["9","10"]` {
		t.Fatalf("got %q %v %v", s, ok, err)
	}
	if _, ok, _ := ParseFlagged(nil, nil); ok {
		t.Fatal("absent flagged should keep the stored list")
	}
}

func TestPaceThresholds(t *testing.T) {
	th := [3]int{500, 1500, 3000}
	cases := []struct {
		base, override int
		inflight       int64
		want           int
	}{
		{10, 0, 0, 10},
		{10, 0, 500, 10},
		{10, 0, 501, 20},
		{10, 0, 1501, 30},
		{10, 0, 3001, 60},
		{45, 0, 1501, 45},  // a slower base is kept
		{10, 15, 5000, 15}, // override wins
	}
	for _, c := range cases {
		if got := Pace(c.base, c.override, c.inflight, th); got != c.want {
			t.Errorf("Pace(%d,%d,%d) = %d, want %d", c.base, c.override, c.inflight, got, c.want)
		}
	}
}
