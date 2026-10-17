// Package web serves the built exam client (a Vue SPA): from EXAM_ENGINE_STATIC_DIR when set,
// else from the copy embedded at build time (internal/web/dist).
package web

import (
	"bytes"
	"embed"
	"io"
	"io/fs"
	"net/http"
	"os"
	"path"
	"regexp"
	"strings"
)

//go:embed all:dist
var embedded embed.FS

// hashed matches Vite's content-hashed file names, e.g. index-BxY3k9_a.js.
var hashed = regexp.MustCompile(`[-.][A-Za-z0-9_-]{8,}\.(?:js|mjs|css|woff2?|ttf|otf|png|jpe?g|gif|svg|webp|avif|ico|wasm|json)$`)

// Handler serves the SPA. Paths that aren't files fall back to index.html.
func Handler(staticDir string) (http.Handler, error) {
	var root fs.FS
	if staticDir != "" {
		st, err := os.Stat(staticDir)
		if err != nil || !st.IsDir() {
			return nil, &fs.PathError{Op: "open", Path: staticDir, Err: fs.ErrNotExist}
		}
		root = os.DirFS(staticDir)
	} else {
		sub, err := fs.Sub(embedded, "dist")
		if err != nil {
			return nil, err
		}
		root = sub
	}
	return &handler{root: root}, nil
}

type handler struct{ root fs.FS }

func (h *handler) serveFile(w http.ResponseWriter, r *http.Request, name string, index bool) bool {
	f, err := h.root.Open(name)
	if err != nil {
		return false
	}
	defer f.Close()
	st, err := f.Stat()
	if err != nil || st.IsDir() {
		return false
	}
	hd := w.Header()
	switch {
	case index:
		hd.Set("Cache-Control", "no-cache")
	case strings.HasPrefix(name, "assets/") || hashed.MatchString(name):
		hd.Set("Cache-Control", "public, max-age=31536000, immutable")
	default:
		hd.Set("Cache-Control", "public, max-age=3600")
	}
	hd.Set("X-Content-Type-Options", "nosniff")
	if index {
		hd.Set("X-Frame-Options", "DENY")
		hd.Set("Referrer-Policy", "no-referrer")
	}
	modTime := st.ModTime()
	if rs, ok := f.(io.ReadSeeker); ok {
		http.ServeContent(w, r, path.Base(name), modTime, rs)
		return true
	}
	b, err := io.ReadAll(f)
	if err != nil {
		return false
	}
	http.ServeContent(w, r, path.Base(name), modTime, bytes.NewReader(b))
	return true
}

func (h *handler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet && r.Method != http.MethodHead {
		w.Header().Set("Allow", "GET, HEAD")
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}
	name := strings.TrimPrefix(path.Clean("/"+r.URL.Path), "/")
	if name != "" && name != "index.html" && h.serveFile(w, r, name, false) {
		return
	}
	if strings.HasPrefix(name, "assets/") {
		// a missing hashed asset must not be answered with HTML (stale index.html in a cache)
		http.NotFound(w, r)
		return
	}
	if !h.serveFile(w, r, "index.html", true) {
		http.Error(w, "client not built", http.StatusNotFound)
	}
}
