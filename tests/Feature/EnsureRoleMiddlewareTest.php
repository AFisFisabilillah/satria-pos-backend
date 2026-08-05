<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureRoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aborts_with_401_if_user_is_not_authenticated(): void
    {
        $middleware = new EnsureRole();
        $request = Request::create('/test', 'GET');

        $this->expectException(HttpException::class);

        try {
            $middleware->handle($request, function () {}, 'super_admin');
        } catch (HttpException $e) {
            $this->assertEquals(401, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_it_aborts_with_403_if_user_does_not_have_required_role(): void
    {
        $user = User::factory()->create(['role' => UserRole::Cashier->value]);
        $middleware = new EnsureRole();
        
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Insufficient permissions.');

        try {
            $middleware->handle($request, function () {}, 'super_admin');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_it_allows_request_if_user_has_required_role(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin->value]);
        $middleware = new EnsureRole();
        
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $nextCalled = false;
        $response = $middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;
            return response('OK');
        }, 'super_admin');

        $this->assertTrue($nextCalled);
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_it_allows_request_if_user_has_one_of_the_allowed_roles(): void
    {
        $user = User::factory()->create(['role' => UserRole::Cashier->value]);
        $middleware = new EnsureRole();
        
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $nextCalled = false;
        $response = $middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;
            return response('OK');
        }, 'super_admin', 'cashier');

        $this->assertTrue($nextCalled);
        $this->assertEquals('OK', $response->getContent());
    }
}
