<?php

namespace Tests\Feature\MasterData;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCardAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_cards_tanpa_token_mengembalikan_401()
    {
        $employee = Employee::factory()->create();
        $response = $this->getJson("/api/employees/{$employee->id}/cards");
        
        $response->assertUnauthorized();
    }
}
