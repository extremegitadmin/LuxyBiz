<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('role')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('role_id'), fn ($q) => $q->where('role_id', $request->role_id))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', ['users' => $users, 'roles' => Role::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('users.form', [
            'member' => (new User)->forceFill(['is_active' => true]),
            'roles' => Role::with('permissions')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        $makeSuper = $actor->is_super && $request->boolean('is_super');   // only a Super Admin can create one

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role_id' => [Rule::requiredIf(! $makeSuper), 'nullable', 'exists:roles,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
        $user->password = Hash::make($data['password']);
        $user->forceFill([
            'role_id' => $makeSuper ? null : $data['role_id'],
            'is_super' => $makeSuper,
            'is_active' => $request->boolean('is_active', true),
            'email_verified_at' => now(),
        ])->save();

        return redirect()->route('users.index')->with('success', 'User created. They can sign in on the same login page.');
    }

    public function edit(Request $request, User $user)
    {
        $this->authorizeTarget($request->user(), $user);

        return view('users.form', ['member' => $user, 'roles' => Role::with('permissions')->orderBy('name')->get()]);
    }

    public function update(Request $request, User $user)
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);

        $isSelf = $user->id === $actor->id;
        $makeSuper = $actor->is_super ? $request->boolean('is_super') : $user->is_super;
        $active = $request->boolean('is_active');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role_id' => [Rule::requiredIf(! $makeSuper), 'nullable', 'exists:roles,id'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if ($isSelf && (! $active || ($user->is_super && ! $makeSuper))) {
            return back()->withInput()->with('error', 'You cannot deactivate yourself or remove your own Super Admin access.');
        }
        if ($isSelf && ! $actor->is_super && (int) $data['role_id'] !== (int) $user->role_id) {
            return back()->withInput()->with('error', 'You cannot change your own role.');
        }
        if ($user->is_super && (! $makeSuper || ! $active) && $this->otherActiveSupers($user) === 0) {
            return back()->withInput()->with('error', 'This is the last active Super Admin. Create another one first.');
        }

        $user->fill(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->forceFill([
            'role_id' => $makeSuper ? null : $data['role_id'],
            'is_super' => $makeSuper,
            'is_active' => $active,
        ])->save();

        return redirect()->route('users.index')->with('success', 'User saved.');
    }

    public function toggle(Request $request, User $user)
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);

        if ($user->id === $actor->id) {
            return back()->with('error', 'You cannot deactivate yourself.');
        }
        if ($user->is_active && $user->is_super && $this->otherActiveSupers($user) === 0) {
            return back()->with('error', 'This is the last active Super Admin.');
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        return back()->with('success', $user->is_active ? 'User reactivated.' : 'User deactivated. They are signed out on their next click.');
    }

    public function destroy(Request $request, User $user)
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);

        if ($user->id === $actor->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        if ($user->is_super && $this->otherActiveSupers($user) === 0) {
            return back()->with('error', 'This is the last active Super Admin.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted.');
    }

    /** Only a Super Admin can open or change a Super Admin account. */
    private function authorizeTarget(User $actor, User $target): void
    {
        abort_if($target->is_super && ! $actor->is_super, 403, 'Only a Super Admin can change a Super Admin account.');
    }

    private function otherActiveSupers(User $except): int
    {
        return User::where('is_super', true)->where('is_active', true)->where('id', '!=', $except->id)->count();
    }
}
