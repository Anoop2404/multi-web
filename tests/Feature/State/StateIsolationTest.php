<?php

namespace Tests\Feature\State;

use App\Http\Middleware\EnsureStateDomainContext;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateModel;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Support\TenantRequestResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StateIsolationTest extends TestCase
{
    public function test_all_state_models_use_dedicated_state_connection(): void
    {
        $models = [
            StateFestEvent::class,
            StateFestParticipant::class,
            StateFestRegistration::class,
            StateQualifierEntry::class,
            StateQualifierIntake::class,
        ];

        foreach ($models as $modelClass) {
            $instance = new $modelClass();
            $this->assertInstanceOf(StateModel::class, $instance, "{$modelClass} must extend StateModel");
            $this->assertEquals('state', $instance->getConnectionName(), "{$modelClass} connection name must be 'state'");
        }
    }

    public function test_state_health_command_executes_successfully(): void
    {
        Artisan::call('state:migrate');

        $exitCode = Artisan::call('state:health');
        $this->assertEquals(0, $exitCode, 'php artisan state:health should complete with 0 exit code');
        $output = Artisan::output();
        $this->assertStringContainsString('State Platform Health Check', $output);
    }

    public function test_tenant_request_resolver_bypasses_state_domain(): void
    {
        // Should not throw TenantCouldNotBeIdentifiedOnDomainException when called on state.localhost
        TenantRequestResolver::initializeFromHost(config('state.domain', 'state.localhost'));
        $this->assertTrue(true, 'State domain was successfully bypassed by TenantRequestResolver');
    }

    public function test_ensure_state_domain_context_middleware_passes_for_valid_request(): void
    {
        $middleware = new EnsureStateDomainContext();
        $request = Request::create('http://' . config('state.domain', 'state.localhost') . '/');

        $response = $middleware->handle($request, function ($req) {
            return response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }
}
