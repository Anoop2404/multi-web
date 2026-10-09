# Traffic monitoring

Run in the deployed project directory:

```bash
php artisan monitor:traffic start --minutes=15 --fresh
php artisan monitor:traffic status
tail -f storage/logs/monitoring-$(date +%F).jsonl
php artisan monitor:traffic stop
```

The start command returns immediately. Incoming HTTP requests are captured for the next 15 minutes, then new requests stop being captured automatically. Requests already in progress finish their traces. No cron is needed. Capture is inactive by default.

Daily JSONL files contain request_started records (written before processing), query records (SQL with literals redacted, connection name, URL, method and duration in milliseconds) and request summaries (URL without query string, method, route name, HTTP status, elapsed time through termination, total query count/time, and peak process memory). Match records by request_id. Timestamps include timezone offsets. SQL bindings, headers and request bodies are excluded; URL paths and SQL identifiers can still contain sensitive information, so treat these files as private diagnostics.

Monitoring captures queries during HTTP requests, including tenant connections. It does not capture queue workers or console commands, failed SQL executions, static files served directly by the web server, or external server availability. It adds one file write per completed query. Log write failures do not interrupt user requests. Logs are not automatically deleted; remove or rotate old files according to your retention needs.

Enable separately on each server/container: state and logs use local storage. Ensure both CLI and web process can read storage/framework/monitoring-until and write storage/logs. The middleware/provider changes must be deployed before enabling capture; restart long-running application servers after deployment.

The optional `--fresh` flag deletes only dated monitoring JSONL files and creates an empty log immediately. It preserves laravel.log. Omit it to append. Previously in-flight requests may still append after clearing. Request summaries include response_duration_ms (time until the response is ready) and duration_ms (time through termination). These measure application processing, not browser/network latency. Each query includes request_elapsed_ms, its URL, SQL template, and its own duration_ms.
