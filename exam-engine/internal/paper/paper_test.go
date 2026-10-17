package paper

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"testing"

	"github.com/sahodaya/exam-engine/internal/model"
)

func seal(t *testing.T, plain []byte) *Put {
	key := make([]byte, 32)
	iv := make([]byte, 12)
	_, _ = rand.Read(key)
	_, _ = rand.Read(iv)
	b, _ := aes.NewCipher(key)
	g, _ := cipher.NewGCM(b)
	ct := g.Seal(nil, iv, plain, nil)
	sum := sha256.Sum256(ct)
	return &Put{Version: 3, IV: base64.StdEncoding.EncodeToString(iv), Ciphertext: base64.StdEncoding.EncodeToString(ct),
		Key: base64.StdEncoding.EncodeToString(key), SHA256: hex.EncodeToString(sum[:])}
}

func TestPrepareExtractsSchema(t *testing.T) {
	plain := []byte(`{"version":3,"questions":[
	 {"id":5501,"type":"multiple","section":"A","body":"x","image":null,"options":[{"key":"a","label":"1","image":null},{"key":"b","label":"2","image":null},{"key":"c","label":"3","image":null}],"show_count":2,"marks":2,"negative_mark":0.5},
	 {"id":5502,"type":"numeric","section":null,"body":"y","image":null,"options":[],"show_count":null,"marks":1,"negative_mark":0},
	 {"id":5503,"type":"true_false","section":null,"body":"z","image":null,"options":[{"key":"a","label":"True","image":null},{"key":"b","label":"False","image":null}],"show_count":null,"marks":1,"negative_mark":0}]}`)
	p := seal(t, plain)
	prep, err := Prepare(p)
	if err != nil {
		t.Fatal(err)
	}
	if prep.Questions != 3 || prep.Schema["5501"].T != "multiple" || len(prep.Schema["5501"].O) != 3 || prep.Schema["5502"].T != "numeric" {
		t.Fatalf("schema %+v", prep.Schema)
	}
	if err := CheckTypes(prep.Schema, model.TypeMap{"5501": "multiple", "5502": "numeric", "5503": "true_false"}); err != nil {
		t.Fatal(err)
	}
	if err := CheckTypes(prep.Schema, model.TypeMap{"5501": "single", "5502": "numeric", "5503": "true_false"}); err == nil {
		t.Fatal("type mismatch not detected")
	}
	bad := *p
	bad.SHA256 = "00"
	if _, err := Prepare(&bad); err == nil {
		t.Fatal("bad sha accepted")
	}
	wrongKey := *p
	k := make([]byte, 32)
	wrongKey.Key = base64.StdEncoding.EncodeToString(k)
	if _, err := Prepare(&wrongKey); err == nil {
		t.Fatal("wrong key accepted")
	}
}
