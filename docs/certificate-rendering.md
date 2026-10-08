# Large certificate render runs

A run of 4,000 certificates is split into 200 jobs of 20 certificates. Each job
renders background and plain PDFs using up to six concurrent converter requests.
Progress continues to update every five certificates. Existing scopes (merit,
participation, school and item) continue to work.

The jobs only run in parallel when multiple queue workers are running. For a
dedicated production pool, configure:

```dotenv
CERTIFICATE_RENDER_QUEUE=certificates
CERTIFICATE_RENDER_CHUNK_SIZE=20
CERTIFICATE_RENDER_CONCURRENCY=6
DB_QUEUE_RETRY_AFTER=1900
REDIS_QUEUE_RETRY_AFTER=1900
```

Use the retry setting for the actual queue connection. It must exceed the job's
1,800-second timeout. Keep normal workers for other queues. Configure Supervisor
with four processes running this command from the application directory:

```sh
php artisan queue:work --queue=certificates --sleep=1 --tries=3 --timeout=1800
```

Four workers permit up to 24 simultaneous converter requests. Ensure the PDF
converter can handle that capacity; reduce concurrency or worker count if it
cannot. More workers do not improve a converter that is already saturated.
Use an asynchronous queue connection (database or Redis), not sync.

After changing configuration, rebuild the configuration cache and restart queue
workers with `php artisan config:cache` and `php artisan queue:restart`. New runs
use the new chunk size and queue; already queued jobs retain their original queue.
Monitor the `Certificate render chunk finished` logs for time spent preparing,
storing and waiting for the converter. No production speed estimate is guaranteed
without measuring the deployed converter and worker pool.
