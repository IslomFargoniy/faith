<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(int $role, string $password = 'secret-pass'): User
    {
        return User::factory()->create(['role' => $role, 'password' => Hash::make($password)]);
    }

    public function test_own_password_change_needs_the_current_password(): void
    {
        $admin = $this->makeUser(2, 'old-pass');

        $this->actingAs($admin)->from(route('admin.edit', $admin->id))
            ->put(route('admin.update', $admin->id), [
                'name' => 'Admin', 'email' => $admin->email, 'password' => 'new-pass',
            ])->assertSessionHasErrors('current_password');

        $this->actingAs($admin)->from(route('admin.edit', $admin->id))
            ->put(route('admin.update', $admin->id), [
                'name' => 'Admin', 'email' => $admin->email, 'password' => 'new-pass', 'current_password' => 'wrong',
            ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-pass', $admin->fresh()->password));

        $this->actingAs($admin)->put(route('admin.update', $admin->id), [
            'name' => 'Admin', 'email' => $admin->email, 'password' => 'new-pass', 'current_password' => 'old-pass',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.edit', $admin->id));

        $this->assertTrue(Hash::check('new-pass', $admin->fresh()->password));
    }

    public function test_super_admin_resets_another_users_password_without_a_current_password(): void
    {
        $super = $this->makeUser(1);
        $other = $this->makeUser(3, 'their-pass');

        $this->actingAs($super)->put(route('admin.update', $other->id), [
            'name' => 'Manager', 'email' => $other->email, 'password' => 'reset-pass',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.index'));

        $this->assertTrue(Hash::check('reset-pass', $other->fresh()->password));
    }

    public function test_admin_resets_a_buyers_password_without_a_current_password(): void
    {
        $admin = $this->makeUser(2);
        $buyer = $this->makeUser(0, 'their-pass');

        $this->actingAs($admin)->put(route('admin.update', $buyer->id), [
            'name' => 'Buyer', 'email' => $buyer->email, 'password' => 'reset-pass',
        ])->assertSessionHasNoErrors()->assertRedirect(route('buyer.index'));

        $this->assertTrue(Hash::check('reset-pass', $buyer->fresh()->password));
    }
}
