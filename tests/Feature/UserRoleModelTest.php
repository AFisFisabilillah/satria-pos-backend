<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_check_if_user_is_super_admin(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin->value]);
        $cashier = User::factory()->create(['role' => UserRole::Cashier->value]);

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertFalse($cashier->isSuperAdmin());
    }

    public function test_it_can_check_if_user_is_cashier(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin->value]);
        $cashier = User::factory()->create(['role' => UserRole::Cashier->value]);

        $this->assertFalse($superAdmin->isCashier());
        $this->assertTrue($cashier->isCashier());
    }
}
