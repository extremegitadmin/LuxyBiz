<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Run with: php artisan test --filter=RbacTest */
class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);   // permissions, first Super Admin, sample Viewer role
    }

    private function superAdmin(): User
    {
        return User::where('is_super', true)->firstOrFail();
    }

    private function roleUser(array $permissions, string $email = 'staff@example.test', string $roleName = 'Tester'): User
    {
        $role = Role::firstOrNew(['name' => $roleName]);
        if (! $role->exists) {
            $role->slug = Role::uniqueSlug($roleName);
            $role->save();
        }
        $role->syncPermissionSlugs($permissions);

        return User::forceCreate([
            'name' => 'Staff', 'email' => $email, 'password' => bcrypt('password-12345'),
            'role_id' => $role->id, 'is_super' => false, 'is_active' => true,
        ]);
    }

    public function test_guests_are_sent_to_the_one_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/users')->assertRedirect(route('login'));
    }

    public function test_super_admin_and_role_users_use_the_same_login_page(): void
    {
        $this->roleUser(['products.view'], 'staff@example.test');
        $super = $this->superAdmin();
        $super->forceFill(['password' => bcrypt('super-password-1')])->save();

        $this->post(route('login.submit'), ['email' => $super->email, 'password' => 'super-password-1'])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login.submit'), ['email' => 'staff@example.test', 'password' => 'password-12345'])->assertRedirect(route('dashboard'));
    }

    public function test_deactivated_users_cannot_sign_in(): void
    {
        $user = $this->roleUser(['products.view']);
        $user->forceFill(['is_active' => false])->save();

        $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'password-12345'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_super_admin_can_open_everything(): void
    {
        $this->actingAs($this->superAdmin());

        foreach (['/dashboard', '/users', '/users/create', '/roles', '/roles/create', '/products', '/products/create'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_role_user_sees_only_what_the_role_allows(): void
    {
        $this->actingAs($this->roleUser(['products.view']));

        $this->get('/dashboard')->assertOk();
        $this->get('/products')->assertOk();
        $this->get('/products/create')->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->get('/roles')->assertForbidden();
    }

    public function test_menu_links_follow_permissions(): void
    {
        $this->actingAs($this->roleUser(['products.view']))
            ->get('/dashboard')->assertSee('Products')->assertDontSee(route('users.index'))->assertDontSee(route('roles.index'));
    }

    public function test_role_changes_apply_immediately(): void
    {
        $user = $this->roleUser(['products.view']);
        $this->actingAs($user)->get('/products/create')->assertForbidden();

        $user->role->syncPermissionSlugs(['products.view', 'products.create']);

        $this->actingAs($user->fresh())->get('/products/create')->assertOk();
    }

    public function test_super_admin_creates_a_role_then_a_user_under_it(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('roles.store'), ['name' => 'Accounts', 'permissions' => ['products.view', 'products.edit', 'nonsense']])
            ->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'Accounts')->firstOrFail();
        $this->assertEqualsCanonicalizing(['products.view', 'products.edit'], $role->permissionSlugs());

        $this->post(route('users.store'), [
            'name' => 'Asha', 'email' => 'asha@example.test', 'role_id' => $role->id,
            'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1', 'is_active' => 1,
        ])->assertRedirect(route('users.index'));

        $asha = User::where('email', 'asha@example.test')->firstOrFail();
        $this->assertSame($role->id, $asha->role_id);
        $this->assertFalse($asha->is_super);
    }

    public function test_a_user_needs_a_role_unless_super_admin(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('users.store'), ['name' => 'No Role', 'email' => 'norole@example.test', 'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1'])
            ->assertSessionHasErrors('role_id');
    }

    public function test_only_a_super_admin_can_create_a_super_admin(): void
    {
        $manager = $this->roleUser(['users.create', 'users.view'], 'mgr@example.test', 'People');
        $role = Role::where('name', 'People')->first();

        $this->actingAs($manager)->post(route('users.store'), [
            'name' => 'Sneaky', 'email' => 'sneaky@example.test', 'role_id' => $role->id, 'is_super' => 1,
            'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1',
        ])->assertRedirect(route('users.index'));

        $this->assertFalse(User::where('email', 'sneaky@example.test')->firstOrFail()->is_super);   // the flag was ignored
    }

    public function test_non_super_users_cannot_touch_a_super_admin_account(): void
    {
        $manager = $this->roleUser(['users.view', 'users.edit', 'users.delete'], 'mgr@example.test', 'People');
        $super = $this->superAdmin();

        $this->actingAs($manager);
        $this->get(route('users.edit', $super))->assertForbidden();
        $this->patch(route('users.toggle', $super))->assertForbidden();
        $this->delete(route('users.destroy', $super))->assertForbidden();
        $this->assertTrue($super->fresh()->is_active);
    }

    public function test_a_role_editor_cannot_grant_permissions_they_do_not_hold(): void
    {
        $editor = $this->roleUser(['roles.view', 'roles.edit', 'products.view'], 'editor@example.test', 'Role editor');
        $target = Role::where('slug', 'viewer')->firstOrFail();   // has users.view, roles.view, products.view

        $this->actingAs($editor)->put(route('roles.update', $target), [
            'name' => 'Viewer', 'permissions' => ['products.view', 'products.delete', 'users.delete'],
        ])->assertRedirect(route('roles.index'));

        $slugs = $target->fresh()->permissionSlugs();
        $this->assertContains('products.view', $slugs);
        $this->assertNotContains('products.delete', $slugs);   // editor does not hold it
        $this->assertNotContains('users.delete', $slugs);
        $this->assertContains('users.view', $slugs);           // not the editor's to remove: kept
    }

    public function test_a_role_editor_cannot_edit_their_own_role(): void
    {
        $editor = $this->roleUser(['roles.view', 'roles.edit'], 'editor@example.test', 'Role editor');

        $this->actingAs($editor)->get(route('roles.edit', $editor->role))->assertForbidden();
    }

    public function test_a_role_with_users_cannot_be_deleted(): void
    {
        $user = $this->roleUser(['products.view']);
        $this->actingAs($this->superAdmin());

        $this->delete(route('roles.destroy', $user->role))->assertSessionHas('error');
        $this->assertNotNull(Role::find($user->role_id));

        $user->delete();
        $this->delete(route('roles.destroy', Role::find($user->role_id)))->assertRedirect(route('roles.index'));
        $this->assertNull(Role::find($user->role_id));
    }

    public function test_a_super_admin_cannot_remove_or_deactivate_themselves(): void
    {
        $me = $this->superAdmin();
        $this->actingAs($me);

        $this->patch(route('users.toggle', $me))->assertSessionHas('error');
        $this->delete(route('users.destroy', $me))->assertSessionHas('error');
        $this->assertTrue($me->fresh()->is_active);
        $this->assertNotNull(User::find($me->id));
    }

    public function test_every_app_route_except_login_requires_a_signed_in_user(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $action = $route->getActionName();
            if (! str_starts_with($action, 'App\\Http\\Controllers\\') || in_array($route->getName(), ['login', 'login.submit'], true)) {
                continue;
            }
            $this->assertTrue(
                collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'auth')),
                'Route is not behind auth: ' . $route->uri()
            );
        }
    }
}
