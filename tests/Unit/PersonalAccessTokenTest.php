<?php

namespace Tests\Unit;

use App\Models\PersonalAccessToken;
use Tests\TestCase;

class PersonalAccessTokenTest extends TestCase
{
    public function test_personal_access_token_uses_central_database_connection(): void
    {
        $token = new PersonalAccessToken;

        $this->assertSame(
            config('tenancy.database.central_connection', 'central'),
            $token->getConnectionName(),
        );
    }
}
