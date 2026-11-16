<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        return view('roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('name')->get(),
            'total' => count(Permission::allSlugs()),
        ]);
    }

    public function create()
    {
        return view('roles.form', ['role' => new Role, 'held' => [], 'groups' => config('permissions.groups')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $role = new Role(['name' => $data['name'], 'description' => $data['description'] ?? null]);
        $role->slug = Role::uniqueSlug($data['name']);
        $role->save();
        $role->syncPermissionSlugs($this->grantable($request->user(), $data['permissions'] ?? [], []));

        return redirect()->route('roles.index')->with('success', "Role \"{$role->name}\" created. Now add users under it.");
    }

    public function edit(Request $request, Role $role)
    {
        $this->guardOwnRole($request->user(), $role);

        return view('roles.form', ['role' => $role, 'held' => $role->permissionSlugs(), 'groups' => config('permissions.groups')]);
    }

    public function update(Request $request, Role $role)
    {
        $actor = $request->user();
        $this->guardOwnRole($actor, $role);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $role->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
        $role->syncPermissionSlugs($this->grantable($actor, $data['permissions'] ?? [], $role->permissionSlugs()));

        return redirect()->route('roles.index')->with('success', 'Role saved. Changes apply immediately.');
    }

    public function destroy(Role $role)
    {
        $count = $role->users()->count();
        if ($count > 0) {
            return back()->with('error', "{$count} user(s) still have this role. Move them to another role first.");
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Role deleted.');
    }

    /** You cannot widen the permissions of the role you hold yourself (the Super Admin can). */
    private function guardOwnRole(User $actor, Role $role): void
    {
        abort_if(! $actor->is_super && $actor->role_id === $role->id, 403, 'You cannot change the permissions of your own role.');
    }

    /**
     * Permissions the actor may leave ticked. A non-super editor can only grant permissions they hold themselves;
     * anything the role already had that the editor does not hold is kept untouched.
     *
     * @return list<string>
     */
    private function grantable(User $actor, array $submitted, array $existing): array
    {
        $submitted = array_values(array_intersect($submitted, Permission::allSlugs()));

        if ($actor->is_super) {
            return $submitted;
        }

        $mine = $actor->permissionSlugs();

        return array_values(array_unique(array_merge(array_intersect($submitted, $mine), array_diff($existing, $mine))));
    }
}
