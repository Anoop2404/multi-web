// Package paper handles the encrypted paper bundle (CONTRACT §2.3): validation of the PUT body,
// extraction of the question schema (ids, types, option keys — never answers) and an
// in-process cache for serving the public bundle.
package paper

import (
	"bytes"
	"compress/gzip"
	"crypto/aes"
	"crypto/cipher"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"strconv"
	"strings"
	"sync"

	"github.com/sahodaya/exam-engine/internal/model"
)

// Put is the body of PUT /internal/runs/{run}/bundle.
type Put struct {
	Version    int    `json:"version"`
	IV         string `json:"iv"`
	Ciphertext string `json:"ciphertext"`
	Key        string `json:"key"`
	SHA256     string `json:"sha256"`
}

// Public is what students download.
type Public struct {
	Version    int    `json:"version"`
	IV         string `json:"iv"`
	Ciphertext string `json:"ciphertext"`
}

type plainOption struct {
	Key string `json:"key"`
}

type plainQuestion struct {
	ID      json.Number   `json:"id"`
	Type    string        `json:"type"`
	Options []plainOption `json:"options"`
}

type plainPaper struct {
	Version   int             `json:"version"`
	Questions []plainQuestion `json:"questions"`
}

// Prepared is a validated bundle ready to store.
type Prepared struct {
	Version   int
	PublicRaw []byte // JSON of Public
	Key       string // base64 key, released to students once writing
	Schema    model.Schema
	SHA256    string
	Questions int
}

func decodeB64(s string) ([]byte, error) {
	s = strings.TrimSpace(s)
	if b, err := base64.StdEncoding.DecodeString(s); err == nil {
		return b, nil
	}
	return base64.RawStdEncoding.DecodeString(strings.TrimRight(s, "="))
}

// Prepare validates the PUT body, checks the SHA-256, decrypts with the key to make sure the
// bundle opens, and extracts the question schema used to validate answer shapes.
func Prepare(p *Put) (*Prepared, error) {
	if p.Version <= 0 {
		return nil, fmt.Errorf("version must be a positive integer")
	}
	iv, err := decodeB64(p.IV)
	if err != nil || len(iv) != 12 {
		return nil, fmt.Errorf("iv must be base64 of 12 bytes")
	}
	key, err := decodeB64(p.Key)
	if err != nil || len(key) != 32 {
		return nil, fmt.Errorf("key must be base64 of 32 bytes")
	}
	ct, err := decodeB64(p.Ciphertext)
	if err != nil || len(ct) < 17 {
		return nil, fmt.Errorf("ciphertext must be base64 of ciphertext||tag")
	}
	sum := sha256.Sum256(ct)
	got := hex.EncodeToString(sum[:])
	if want := strings.ToLower(strings.TrimSpace(p.SHA256)); want != "" && want != got {
		return nil, fmt.Errorf("sha256 does not match the ciphertext")
	}
	block, err := aes.NewCipher(key)
	if err != nil {
		return nil, err
	}
	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return nil, err
	}
	plain, err := gcm.Open(nil, iv, ct, nil)
	if err != nil {
		return nil, fmt.Errorf("the bundle does not decrypt with the given key and iv")
	}
	schema, n, err := ExtractSchema(plain)
	if err != nil {
		return nil, err
	}
	pub, _ := json.Marshal(Public{Version: p.Version, IV: base64.StdEncoding.EncodeToString(iv), Ciphertext: base64.StdEncoding.EncodeToString(ct)})
	return &Prepared{
		Version:   p.Version,
		PublicRaw: pub,
		Key:       base64.StdEncoding.EncodeToString(key),
		Schema:    schema,
		SHA256:    got,
		Questions: n,
	}, nil
}

// ExtractSchema reads question ids, types and option keys from the decrypted paper.
func ExtractSchema(plain []byte) (model.Schema, int, error) {
	var pp plainPaper
	dec := json.NewDecoder(bytes.NewReader(plain))
	dec.UseNumber()
	if err := dec.Decode(&pp); err != nil {
		return nil, 0, fmt.Errorf("decrypted paper is not valid JSON: %v", err)
	}
	s := make(model.Schema, len(pp.Questions))
	for _, q := range pp.Questions {
		id := q.ID.String()
		if _, err := strconv.ParseUint(id, 10, 64); err != nil {
			return nil, 0, fmt.Errorf("paper question id %q is not numeric", id)
		}
		if !model.ValidType(q.Type) {
			return nil, 0, fmt.Errorf("paper question %s has unknown type %q", id, q.Type)
		}
		spec := model.QSpec{T: q.Type}
		for _, o := range q.Options {
			k := strings.TrimSpace(o.Key)
			if k != "" {
				spec.O = append(spec.O, k)
			}
		}
		if q.Type == model.TypeTrueFalse && len(spec.O) == 0 {
			spec.O = []string{"a", "b"}
		}
		s[id] = spec
	}
	return s, len(pp.Questions), nil
}

// CheckTypes compares the paper's schema with the meta `questions` map (if any).
func CheckTypes(s model.Schema, types model.TypeMap) error {
	if len(types) == 0 {
		return nil
	}
	if len(types) != len(s) {
		return fmt.Errorf("meta.questions lists %d questions but the paper has %d", len(types), len(s))
	}
	for id, t := range types {
		spec, ok := s[id]
		if !ok {
			return fmt.Errorf("meta.questions has question %s which is not in the paper", id)
		}
		if spec.T != t {
			return fmt.Errorf("question %s is %q in meta.questions but %q in the paper", id, t, spec.T)
		}
	}
	return nil
}

// SchemaFromTypes builds a schema (types only) from the meta map.
func SchemaFromTypes(types model.TypeMap) model.Schema {
	if len(types) == 0 {
		return nil
	}
	s := make(model.Schema, len(types))
	for id, t := range types {
		spec := model.QSpec{T: t}
		if t == model.TypeTrueFalse {
			spec.O = []string{"a", "b"}
		}
		s[id] = spec
	}
	return s
}

// Cached is one public bundle held in memory.
type Cached struct {
	Version int
	Raw     []byte
	Gzip    []byte
	ETag    string
}

// Cache keeps public bundles in memory (they are immutable per version).
type Cache struct {
	mu       sync.Mutex
	m        map[string]*Cached
	order    []string
	maxBytes int
	bytes    int
}

// NewCache creates a cache bounded to maxBytes.
func NewCache(maxBytes int) *Cache { return &Cache{m: map[string]*Cached{}, maxBytes: maxBytes} }

func cacheKey(run string, v int) string { return run + "#" + strconv.Itoa(v) }

// Get returns a cached bundle.
func (c *Cache) Get(run string, v int) *Cached {
	c.mu.Lock()
	defer c.mu.Unlock()
	return c.m[cacheKey(run, v)]
}

// Put stores raw (and a gzip copy) and returns the entry.
func (c *Cache) Put(run string, v int, raw []byte) *Cached {
	var gz bytes.Buffer
	zw, _ := gzip.NewWriterLevel(&gz, gzip.BestSpeed)
	_, _ = zw.Write(raw)
	_ = zw.Close()
	sum := sha256.Sum256(raw)
	e := &Cached{Version: v, Raw: raw, Gzip: gz.Bytes(), ETag: `"` + hex.EncodeToString(sum[:8]) + `"`}
	k := cacheKey(run, v)
	c.mu.Lock()
	defer c.mu.Unlock()
	if old, ok := c.m[k]; ok {
		c.bytes -= len(old.Raw) + len(old.Gzip)
	} else {
		c.order = append(c.order, k)
	}
	c.m[k] = e
	c.bytes += len(e.Raw) + len(e.Gzip)
	for c.bytes > c.maxBytes && len(c.order) > 1 {
		oldest := c.order[0]
		c.order = c.order[1:]
		if o, ok := c.m[oldest]; ok {
			c.bytes -= len(o.Raw) + len(o.Gzip)
			delete(c.m, oldest)
		}
	}
	return e
}

// DropRun forgets every cached version of a run.
func (c *Cache) DropRun(run string) {
	c.mu.Lock()
	defer c.mu.Unlock()
	prefix := run + "#"
	keep := c.order[:0]
	for _, k := range c.order {
		if strings.HasPrefix(k, prefix) {
			if o, ok := c.m[k]; ok {
				c.bytes -= len(o.Raw) + len(o.Gzip)
				delete(c.m, k)
			}
			continue
		}
		keep = append(keep, k)
	}
	c.order = keep
}
