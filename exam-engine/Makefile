# Exam engine — build, test and run (see README.md).
GO      ?= go
VERSION ?= $(shell git describe --tags --always --dirty 2>/dev/null || echo dev)
LDFLAGS := -s -w -X main.version=$(VERSION)

# Local defaults: Redis database 14 keeps the engine away from Laravel's own database 0.
EXAM_ENGINE_ADDR           ?= :8090
EXAM_ENGINE_REDIS_URL      ?= redis://127.0.0.1:6379/14
EXAM_ENGINE_INTERNAL_TOKEN ?= local-dev-internal-token-change-me-0123456789
LOADGEN_ARGS               ?= -students 2000 -questions 50 -duration 3m -compress 5

.PHONY: all client build test vet run loadgen clean

all: build

## client: build the Vue client and copy it into internal/web/dist (embedded by `build`)
client:
	@if [ -f client/package.json ]; then \
		cd client && npm ci && npm run build && cd .. && \
		find internal/web/dist -mindepth 1 -delete && \
		cp -R client/dist/. internal/web/dist/ && \
		echo "client copied into internal/web/dist"; \
	else \
		echo "client/package.json not found: keeping the placeholder page"; \
	fi

## build: the engine binary (bin/exam-engine) and the load generator (bin/loadgen)
build:
	$(GO) build -trimpath -ldflags "$(LDFLAGS)" -o bin/exam-engine ./cmd/engine
	$(GO) build -trimpath -o bin/loadgen ./cmd/loadgen

vet:
	$(GO) vet ./...

## test: unit tests + Redis integration tests (Redis database 15 only; skipped if unreachable)
test: vet
	$(GO) test -count=1 ./...

## run: the engine on :8090 against local Redis database 14
run: build
	EXAM_ENGINE_ADDR=$(EXAM_ENGINE_ADDR) \
	EXAM_ENGINE_REDIS_URL=$(EXAM_ENGINE_REDIS_URL) \
	EXAM_ENGINE_INTERNAL_TOKEN=$(EXAM_ENGINE_INTERNAL_TOKEN) \
	./bin/exam-engine

## loadgen: drive a running engine (make run in another terminal); LOADGEN_ARGS to tune
loadgen:
	$(GO) run ./cmd/loadgen -base http://127.0.0.1$(EXAM_ENGINE_ADDR) -token $(EXAM_ENGINE_INTERNAL_TOKEN) $(LOADGEN_ARGS)

clean:
	rm -rf bin
