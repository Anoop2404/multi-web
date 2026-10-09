<?php

namespace Tests\Feature;

use App\Http\Middleware\MonitorRequests;
use App\Support\Monitoring\RequestTrace;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TrafficMonitoringTest extends TestCase
{
    private string $monitorStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monitorStorage = sys_get_temp_dir().'/traffic-monitor-'.bin2hex(random_bytes(8));
        mkdir($this->monitorStorage.'/framework', 0700, true);
        mkdir($this->monitorStorage.'/logs', 0700);
        $this->app->useStoragePath($this->monitorStorage);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->monitorStorage.'/*/*') as $file) {
            unlink($file);
        }
        rmdir($this->monitorStorage.'/framework');
        rmdir($this->monitorStorage.'/logs');
        rmdir($this->monitorStorage);
        parent::tearDown();
    }

    public function test_capture_correlates_queries_with_request_and_omits_sensitive_values(): void
    {
        $this->artisan('monitor:traffic', ['--minutes' => 15])->assertSuccessful();
        $request = Request::create('https://example.test/users?token=secret');
        $middleware = new MonitorRequests;
        $response = $middleware->handle($request, function ($request) {
            $trace = $request->attributes->get('_monitoring_trace');
            $trace->query(new QueryExecuted("select * from users where email = 'private@example.test' and id = 42 and password = ?", ['binding-secret'], 12.5, DB::connection()));

            return new Response('', 503);
        });
        $middleware->terminate($request, $response);
        $raw = file_get_contents(glob($this->monitorStorage.'/logs/*')[0]);
        $entries = array_map(fn ($line) => json_decode($line, true), explode("\n", trim($raw)));
        $this->assertCount(2, $entries);
        $this->assertSame($entries[0]['request_id'], $entries[1]['request_id']);
        $this->assertSame(12.5, $entries[0]['duration_ms']);
        $this->assertSame(503, $entries[1]['status']);
        $this->assertSame(1, $entries[1]['query_count']);
        $this->assertSame('https://example.test/users', $entries[1]['url']);
        $this->assertStringNotContainsString('secret', $raw);
        $this->assertStringNotContainsString('private@example.test', $raw);
        $this->assertStringNotContainsString('42', $entries[0]['sql']);
        $this->assertNull($request->attributes->get('_monitoring_trace'));
    }

    public function test_expired_or_stopped_capture_does_not_log_requests(): void
    {
        file_put_contents(RequestTrace::statePath(), time() - 1);
        $request = Request::create('/up');
        $middleware = new MonitorRequests;
        $response = $middleware->handle($request, fn () => new Response);
        $middleware->terminate($request, $response);
        $this->assertSame([], glob($this->monitorStorage.'/logs/*'));
        $this->artisan('monitor:traffic', ['action' => 'stop'])->assertSuccessful();
        $this->assertSame(0, RequestTrace::until());
        $this->artisan('monitor:traffic', ['--minutes' => 0])->assertFailed();
    }
}
