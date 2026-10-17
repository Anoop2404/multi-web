# Cloudflare for the exam site

Cloudflare sits in front of `exam.<domain>`: DNS, TLS, CDN cache for the client app and the
encrypted paper bundles, DDoS protection, and WAF rules. Everything below works on the **Free
plan**. Replace `exam.example.org` with the real hostname (plan §11.2 #1: the exam domain is still
to be decided, and its DNS must be on Cloudflare).

## 1. DNS
| Type | Name | Content | Proxy |
|---|---|---|---|
| A | `exam` | DigitalOcean LB IP (`doctl compute load-balancer list`), or the engine droplet's IP for Stage A-lite | **Proxied (orange cloud)** |
| AAAA | `exam` | only if the origin has IPv6 | Proxied |

The origin IP must never appear in an unproxied record, or people can bypass the WAF. The LB
allow-list (lb/README.md) also blocks non-Cloudflare clients.

## 2. TLS
- **SSL/TLS -> Overview: Full (strict).**
- **SSL/TLS -> Origin Server -> Create certificate**: RSA 2048, hostnames `exam.example.org`, validity 15 years.
  Save `origin.pem` and `origin.key` (the key is shown only once, so keep it in your password manager).
  Install them on the DO LB (`doctl compute certificate create ... --type custom`), on HAProxy, or on nginx.
- Edge Certificates: **Always Use HTTPS** on, **Minimum TLS 1.2**, TLS 1.3 on, **0-RTT off** (no early-data replays),
  Automatic HTTPS Rewrites on. Turn HSTS on (max-age 6 months, no preload) once the site has been stable for a week.
- Optional (HAProxy/nginx only): **Authenticated Origin Pulls**, so the origin accepts only Cloudflare. The DO LB can't verify client certs.

## 3. Cache rules (Caching -> Cache Rules; evaluated in order, later rules override)
| # | Name | When | Then |
|---|---|---|---|
| 1 | exam assets immutable | `http.host eq "exam.example.org" and starts_with(http.request.uri.path, "/assets/")` | Eligible for cache. Edge TTL: **ignore origin, 1 year** (Vite file names are content-hashed). Browser TTL: respect origin |
| 2 | exam bundles immutable | `http.host eq "exam.example.org" and starts_with(http.request.uri.path, "/api/bundle/")` | Eligible for cache. Edge TTL: **use origin** (`public, max-age=31536000, immutable`). Cache key: no query string |
| 3 | exam api bypass | `http.host eq "exam.example.org" and (starts_with(http.request.uri.path, "/api/") or starts_with(http.request.uri.path, "/internal/") or http.request.uri.path in {"/health" "/metrics"}) and not starts_with(http.request.uri.path, "/api/bundle/")` | **Bypass cache** |

HTML (`/`, `/<sahodaya-code>`) isn't cached by default, so a new client release shows up right away.
Also turn on **Caching -> Tiered Cache -> Smart Tiered Caching** (free): only one upper-tier Cloudflare
data centre fetches each bundle from the origin, not every Indian PoP.

Check, from two different networks:
```bash
curl -sI https://exam.example.org/assets/<some-file>.js | grep -i cf-cache-status   # HIT on the 2nd request
curl -sI https://exam.example.org/api/bundle/<run>/<version> | grep -i cf-cache-status   # HIT after warm-up
curl -sI https://exam.example.org/api/state | grep -i cf-cache-status   # DYNAMIC or BYPASS, never HIT
```
The bundle response must not carry `Set-Cookie`, because Cloudflare won't cache it then.

## 4. WAF custom rules (Security -> WAF -> Custom rules; Free plan allows 5)
| # | Name | Expression | Action |
|---|---|---|---|
| 1 | internal + metrics: Laravel only | `http.host eq "exam.example.org" and ((starts_with(http.request.uri.path, "/internal/") and not ip.src in {AAPANEL_IP}) or http.request.uri.path eq "/metrics")` | **Block** |
| 2 | load test skip (temporary) | `ip.src in {LOADGEN_IP_1 LOADGEN_IP_2}` | **Skip**: rate limiting rules, remaining custom rules. Delete after the test |
| 3 | (optional) bad methods | `http.host eq "exam.example.org" and not http.request.method in {"GET" "POST" "PUT" "DELETE" "HEAD" "OPTIONS"}` | Block |

Laravel calls the internal API through Cloudflare (`EXAM_ENGINE_INTERNAL_URL=https://exam.example.org`)
with its bearer token. Rule 1 means only the aaPanel server's IP can get there. If that server's IP
changes, update rule 1 **before** exam day (preflight reports 403 from Laravel).

**Don't** add country blocks, managed challenges, or JS challenges on this hostname: the exam client
calls the API with `fetch()` and can't solve a challenge. Students write from anywhere.

## 5. Rate limiting rule (Security -> WAF -> Rate limiting rules; Free plan: 1 rule, 10 s period)
| Field | Value |
|---|---|
| Expression | `http.host eq "exam.example.org" and http.request.uri.path eq "/api/login" and http.request.method eq "POST"` |
| Characteristics | IP |
| Rate | **100 requests / 10 seconds** |
| Action | Block for 10 seconds (custom JSON response if your plan allows: status 429, `{"error":"rate_limited","message":"Too many attempts. Wait a few seconds."}`) |

Keep it generous. A school lab of 60 students shares one NAT IP, and Jio/Airtel CGNAT puts
thousands of phones behind a handful of IPs. The engine already locks an identity after 5 failures
and queues bcrypt work, so this rule is only a flood brake. If coordinators report "Too many
attempts" for a whole school, raise it.

## 6. Settings to turn OFF for the exam zone/hostname
Use **Rules -> Configuration Rules** scoped to `http.host eq "exam.example.org"` where a setting is zone-wide:
- **Bot Fight Mode: off.** It can challenge API calls, and the Free plan can't skip it with a WAF rule. Use a separate zone for the exam domain if the main zone needs it.
- **Browser Integrity Check: off** (Configuration Rule). Some school/kiosk browsers send unusual headers.
- **Rocket Loader, Email Obfuscation, Automatic Signed Exchanges, Mirage: off** (Configuration Rule). They rewrite HTML/JS.
- **"I'm Under Attack" mode: never** during an exam. Its interstitial breaks the API. Under a real attack, use WAF rules on specific paths and IPs.
- **Always Online: off.** Development Mode: off.
- HTTP/2 and HTTP/3: on. WebSockets: not needed.

Cloudflare's proxy read timeout on Free is 100 s. The longest engine call is login (up to 20 s
in the bcrypt queue) and Laravel's event claim (2 s block), so this is fine.

## 7. R2 (optional, Stage B)
Not required: rules 1–2 already make Cloudflare serve `/assets/*` and `/api/bundle/*` from cache.
R2 helps only if you want the origin out of the asset path entirely:
- Bucket `exam-client` for client assets: build the client with `--base=https://exam-cdn.example.org/`
  and serve the bucket on the custom domain `exam-cdn.example.org` (R2 -> Settings -> Custom Domains, proxied).
  CORS: allow `GET` from `https://exam.example.org`.
- Bucket `exam-bundles` for encrypted papers: set `EXAM_ENGINE_BUNDLE_BASE_URL=https://exam-cdn.example.org/bundles`.
  **This needs a publisher that uploads each bundle to R2 when a run is armed. It isn't built yet**
  (Laravel's bridge sends bundles to the engine only), so leave the default `/api/bundle` until it is.
- R2 egress is free; storage stays inside the free 10 GB.

## 8. Cloudflare outage fallback (optional hardening)
With only an Origin CA certificate, the origin isn't browser-trusted, so a Cloudflare outage
can't be bypassed by switching the record to "DNS only". If you want that escape hatch, also issue
a publicly trusted certificate for `exam.example.org` (for example Let's Encrypt with a DNS-01
challenge, using a Cloudflare API token limited to this zone), install it on the LB as a second
custom certificate, and renew it every 60 days. In an outage: switch the `exam` record to DNS-only,
which takes effect within minutes because proxied records have a short TTL. Load and abuse protection
are gone while it's switched, so revert as soon as Cloudflare recovers. The runbook's incident section
covers the decision.

## 9. Checklist
- [ ] Zone active, `exam` record proxied, SSL Full (strict), origin cert installed on LB/HAProxy/nginx
- [ ] Cache rules 1–3 + Smart Tiered Cache, verified with `cf-cache-status`
- [ ] WAF rule 1 (internal/metrics) with the current aaPanel IP; `exam-day-preflight.sh` shows `/internal/*` 403 from outside and 404 from Laravel
- [ ] Rate limiting rule on `/api/login`
- [ ] Bot Fight Mode, Browser Integrity Check, Rocket Loader, Email Obfuscation off for the exam host
- [ ] Load-test skip rule removed after the test
