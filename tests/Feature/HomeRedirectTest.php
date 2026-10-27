<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_super_admin_is_sent_to_the_central_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]))
            ->get(route('home'))
            ->assertRedirect(route('central.dashboard'));
    }

    public function test_user_without_a_store_is_sent_back_to_the_login_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('login'));
    }
}
