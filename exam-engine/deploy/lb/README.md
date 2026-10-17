# Load balancing for the exam engine

Request path: **student -> Cloudflare (proxy, TLS, cache, WAF) -> load balancer -> engine nodes :8090**.
Engine nodes are stateless (all state in Redis), so any node can serve any request. Three options:

| Option | When | Files |
|---|---|---|
| **DigitalOcean Load Balancer** (recommended) | Stage A and B. Managed, HA, targets droplets **by tag**, so scale-out nodes join on their own | this README |
| HAProxy droplet | You want per-IP rate limits and ACLs at the origin, or fewer moving parts you don't control. It's a single point of failure unless you run two with a reserved IP | `haproxy/` |
| nginx on the engine droplet | Stage A-lite: one engine droplet, no LB (saves about $12/month). Moving to Stage B needs the DO LB first | `../nginx/exam-site.conf` |

## DigitalOcean Load Balancer

### Settings (and why)

| Setting | Value | Why |
|---|---|---|
| Region / VPC | `blr1`, the exam VPC | the LB reaches droplets on their private IPs |
| Size | 1 node (Stage A). 2–3 nodes for busy Stage B slots if the load test shows the need | each node has fixed connection and request limits; check DO's current per-node numbers |
| Forwarding rule 1 | `HTTPS 443 -> HTTP 8090`, certificate = Cloudflare **Origin** certificate (uploaded as a custom cert) | Cloudflare runs SSL **Full (strict)** to the LB |
| Forwarding rule 2 | `HTTP 80 -> HTTP 8090` + **Redirect HTTP to HTTPS** | Cloudflare "Always Use HTTPS" covers this too |
| Target | **tag `exam-engine`** (not a droplet list) | scale-out/in only add or remove the tag |
| Health check | `HTTP :8090 /health`, interval 10 s, timeout 5 s, unhealthy 3, healthy 3 | `/health` returns 200 only when the engine is up **and** Redis answers |
| Sticky sessions | **OFF** (`type: none`) | design rule 2: no sticky sessions. Sessions are a cookie mapped in Redis |
| HTTP idle timeout | 60 s | login waits up to 20 s in the bcrypt queue; no long polls from students |
| Backend keepalive | **on** | reuses LB->engine connections at 2–6k rps |
| HTTP/2 | entry HTTP/2 is fine; target stays HTTP/1.1 | |
| PROXY protocol | off | the engine reads `X-Forwarded-For` (`EXAM_ENGINE_TRUST_PROXY=true`) |
| Firewall (LB allow-list) | Cloudflare IPv4/IPv6 ranges + the aaPanel server IP (+ load-generator IPs during a load test) | nobody can bypass Cloudflare's WAF and rate limits |
| WebSockets | not needed | control messages ride on the sync response |

Client IP: Cloudflare sets `X-Forwarded-For: <student>` and the DO LB appends the Cloudflare edge IP,
so the left-most entry is the student. Because the LB allow-list only admits Cloudflare and the
aaPanel server, that entry can't be spoofed by connecting to the LB directly. Check once with a
test request that the engine logs the right IP. If DO's header handling ever differs, switch the
engine to `CF-Connecting-IP`.

### Create it (doctl)

```bash
# 1. Cloudflare Origin certificate (cloudflare/README.md, step 2) -> DO custom certificate
doctl compute certificate create --name exam-origin-2026 --type custom \
  --leaf-certificate-path origin.pem --private-key-path origin.key
doctl compute certificate list            # note the certificate ID

# 2. The LB. Replace CERT_ID, VPC_UUID, AAPANEL_IP. The allow-list holds Cloudflare's ranges
#    (https://www.cloudflare.com/ips/) plus the aaPanel server.
CF4=$(grep -v '^#' haproxy/cloudflare-ips.lst | grep -v ':' | sed 's/^/cidr:/' | paste -sd, -)
CF6=$(grep -v '^#' haproxy/cloudflare-ips.lst | grep ':' | sed 's/^/cidr:/' | paste -sd, -)
doctl compute load-balancer create --name exam-lb --region blr1 --vpc-uuid VPC_UUID \
  --size-unit 1 --tag-name exam-engine \
  --forwarding-rules "entry_protocol:https,entry_port:443,target_protocol:http,target_port:8090,certificate_id:CERT_ID,tls_passthrough:false entry_protocol:http,entry_port:80,target_protocol:http,target_port:8090" \
  --redirect-http-to-https \
  --health-check "protocol:http,port:8090,path:/health,check_interval_seconds:10,response_timeout_seconds:5,healthy_threshold:3,unhealthy_threshold:3" \
  --sticky-sessions "type:none" \
  --http-idle-timeout-seconds 60 --enable-backend-keepalive \
  --allow-list "$CF4,$CF6,ip:AAPANEL_IP"
doctl compute load-balancer list            # note the ID -> LB_ID in env/ops.env, the IP -> Cloudflare DNS
```

Check the flags against `doctl compute load-balancer create --help` for your doctl version. LB
firewall rules (`--allow-list`/`--deny-list`) and `--enable-backend-keepalive` are newer flags; if your
doctl lacks one, set it in the control panel (Networking -> Load Balancers -> Settings).

> **`doctl compute load-balancer update` replaces the whole configuration.** Always pass every flag
> again (forwarding rules, health check, tag, allow-list). Leaving one out removes it. When in doubt,
> change settings in the control panel.

### Operating it
- **Nodes join and leave by tag.** `scale-out.sh` creates droplets tagged `exam-engine`.
  `scale-in.sh` and `rolling-update.sh` remove the tag first (the LB stops routing), wait
  `DRAIN_SECONDS`, then stop or delete the node.
- A new node gets traffic once it passes 3 health checks (about 30 s). The scripts wait `LB_ADMIT_SECONDS` (40).
- **LB size during Stage B:** if the 90k load test shows the LB as the bottleneck (5xx without the
  engine's JSON `{"error": ...}` body while the engines stay idle), raise `--size-unit` to 2–3 at T-24h with the full update
  command, and lower it after the slot.
- During a load test, add the generator droplets' IPs to the allow-list (full update command) and remove them afterwards.
- Renew the origin certificate only when you replace it (Cloudflare Origin CA certificates last up to 15 years).

## HAProxy alternative (`haproxy/`)

- `haproxy/haproxy.cfg` -> `/etc/haproxy/conf.d/00-exam.cfg`. It does TLS termination with the
  Cloudflare origin cert, admits only Cloudflare and trusted IPs, gets the real client IP from
  `CF-Connecting-IP`, rate-limits per client IP (`/api/login` and `/api/*` separately), restricts
  `/internal/*` to the aaPanel server, hides `/metrics`, and health-checks engines on `/health`.
- `scripts/haproxy-sync-backends.sh` generates `/etc/haproxy/conf.d/50-engines.cfg` from the droplets
  tagged `exam-engine` and reloads HAProxy without dropping connections. scale-out/in run it when `LB_KIND=haproxy`.
- Ubuntu 24.04 ships HAProxy 2.8 LTS. Point the service at the directory:
  `echo 'CONFIG="/etc/haproxy/conf.d"' | sudo tee -a /etc/default/haproxy`.
- Files: `cloudflare-ips.lst` (refresh from https://www.cloudflare.com/ips/), `trusted-ips.lst`
  (aaPanel server, load generators), `internal-ips.lst` (aaPanel server only). Copy the `.example` files and fill them in.
- Droplet: `c-2` is plenty for Stage A; size it with the load test for Stage B. Firewall: 80/443 from
  Cloudflare and trusted IPs only, 22 from admin IPs.
