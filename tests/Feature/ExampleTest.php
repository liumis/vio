<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guests_to_filament_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_root_redirects_authenticated_users_to_admin_panel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('filament.admin.resources.imports.index'));
    }
}
