<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleEnumTest extends TestCase
{
    public function test_it_returns_correct_labels(): void
    {
        $this->assertEquals('Super Admin', UserRole::SuperAdmin->label());
        $this->assertEquals('Cashier', UserRole::Cashier->label());
    }

    public function test_it_has_expected_values(): void
    {
        $this->assertEquals('super_admin', UserRole::SuperAdmin->value);
        $this->assertEquals('cashier', UserRole::Cashier->value);
    }
}
