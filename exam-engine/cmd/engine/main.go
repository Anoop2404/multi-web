// Command engine runs the exam engine HTTP server (student API, internal API, client SPA) and
// the leader-elected sweeper. Configuration comes from EXAM_ENGINE_* environment variables.
package main

import (
	"context"
	"errors"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"strings"
	"syscall"
	"time"

	"github.com/sahodaya/exam-engine/internal/config"
	"github.com/sahodaya/exam-engine/internal/engine"
	"github.com/sahodaya/exam-engine/internal/server"
	"github.com/sahodaya/exam-engine/internal/store"
	"github.com/sahodaya/exam-engine/internal/sweeper"
	"github.com/sahodaya/exam-engine/internal/web"
)

// version is stamped at build time: go build -ldflags "-X main.version=1.2.3" ./cmd/engine
var version = "dev"

func main() {
	config.Version = version
	cfg, err := config.FromEnv()
	if err != nil {
		slog.Error("configuration", "err", err)
		os.Exit(2)
	}
	level := slog.LevelInfo
	switch strings.ToLower(cfg.LogLevel) {
	case "debug":
		level = slog.LevelDebug
	case "warn":
		level = slog.LevelWarn
	case "error":
		level = slog.LevelError
	}
	log := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: level}))
	slog.SetDefault(log)

	if cfg.InternalToken == "" {
		log.Warn("EXAM_ENGINE_INTERNAL_TOKEN is empty: every /internal request will be refused")
	} else if len(cfg.InternalToken) < 24 {
		log.Warn("EXAM_ENGINE_INTERNAL_TOKEN is short; use at least 32 random characters")
	}
	if cfg.LaunchPublicKey == nil {
		log.Warn("EXAM_ENGINE_LAUNCH_PUBLIC_KEY is not set: portal launch tokens are disabled")
	}

	st, err := store.New(store.Options{URL: cfg.RedisURL, Sentinels: cfg.RedisSentinels, Master: cfg.RedisMaster, PoolSize: cfg.RedisPoolSize})
	if err != nil {
		log.Error("redis", "err", err)
		os.Exit(2)
	}
	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	// Redis may come up after us; keep trying for a while, then start anyway (health reports it).
	for i := 0; ; i++ {
		pctx, cancel := context.WithTimeout(ctx, 3*time.Second)
		err = st.Prepare(pctx)
		cancel()
		if err == nil || i >= 10 || ctx.Err() != nil {
			break
		}
		log.Warn("waiting for redis", "err", err)
		time.Sleep(time.Second)
	}
	if err != nil {
		log.Error("redis not ready; starting anyway", "err", err)
	}

	eng := engine.New(cfg, st, log)
	eng.StartBackground(ctx)

	static, err := web.Handler(cfg.StaticDir)
	if err != nil {
		log.Error("static client", "dir", cfg.StaticDir, "err", err)
		os.Exit(2)
	}
	srv := server.New(eng, static)

	sw := sweeper.New(eng)
	swCtx, swCancel := context.WithCancel(context.Background())
	swDone := make(chan struct{})
	go func() { sw.Run(swCtx); close(swDone) }()

	httpSrv := &http.Server{
		Addr:              cfg.Addr,
		Handler:           srv.Handler(),
		ReadHeaderTimeout: 10 * time.Second,
		ReadTimeout:       120 * time.Second, // 30 MB bundle uploads over a slow tunnel
		WriteTimeout:      60 * time.Second,
		IdleTimeout:       120 * time.Second,
		MaxHeaderBytes:    64 << 10,
	}
	errc := make(chan error, 1)
	go func() {
		log.Info("exam engine listening", "addr", cfg.Addr, "version", config.Version, "node", cfg.NodeID)
		errc <- httpSrv.ListenAndServe()
	}()

	select {
	case <-ctx.Done():
	case err := <-errc:
		if err != nil && !errors.Is(err, http.ErrServerClosed) {
			log.Error("http server", "err", err)
		}
	}
	log.Info("shutting down")
	shCtx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()
	_ = httpSrv.Shutdown(shCtx)
	swCancel()
	<-swDone
	_ = st.Close()
}
