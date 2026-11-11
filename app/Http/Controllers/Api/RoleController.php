<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()->where('guard_name', 'api')->with('permissions:id,name')->orderBy('name')->get();

        return $this->successResponse($roles->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions,
            'assignable' => $this->canAssignRole($request->user(), $role),
            'protected' => in_array($role->name, config('rbac.system_roles'), true),
        ]));
    }

    public function options(): JsonResponse
    {
        return $this->successResponse(Permission::query()->where('guard_name', 'api')
            ->where('name', '!=', 'roles.manage')->orderBy('name')->get(['id', 'name']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateRole($request);

        $role = DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'api']);
            $role->syncPermissions(array_map('intval', $data['permission_ids']));

            return $role;
        });

        return $this->successResponse($role->load('permissions'), 'Role created.', 201);
    }

    public function update(Request $request, int $role): JsonResponse
    {
        DB::transaction(function () use ($request, $role) {
            $role = $this->lockCustomRole($role);
            $data = $this->validateRole($request, $role);
            $previousName = $role->name;

            $role->update(['name' => $data['name']]);
            $role->syncPermissions(array_map('intval', $data['permission_ids']));

            if ($role->name !== $previousName) {
                User::query()->where('role', $previousName)->update(['role' => $role->name]);
            }
        });

        return $this->successResponse(message: 'Role updated.');
    }

    public function destroy(int $role): JsonResponse
    {
        DB::transaction(function () use ($role) {
            $role = $this->lockCustomRole($role);

            if (User::query()->where('role', $role->name)->exists()) {
                throw ValidationException::withMessages(['role' => 'Reassign the users with this role before deleting it.']);
            }

            $role->delete();
        });

        return $this->successResponse(message: 'Role deleted.');
    }

    public function assign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role_id' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($request, $data) {
            $role = Role::query()->where('guard_name', 'api')->lockForUpdate()->findOrFail($data['role_id']);
            $user = User::query()->where('email', $data['email'])->lockForUpdate()->firstOrFail();
            $actor = $request->user();

            abort_unless($actor->canChangeRoleOf($user) && $this->canAssignRole($actor, $role), 403);

            $user->update(['role' => $role->name]);
        });

        return $this->successResponse(message: 'Role assigned.');
    }

    private function canAssignRole(User $actor, Role $role): bool
    {
        return $role->name !== 'superadmin' && ($role->name !== 'admin' || $actor->role === 'superadmin');
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_-]*$/D',
                Rule::unique('roles')->where('guard_name', 'api')->ignore($role)],
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')->where(
                fn ($query) => $query->where('guard_name', 'api')->where('name', '!=', 'roles.manage')
            )],
        ]);
    }

    private function lockCustomRole(int $id): Role
    {
        $role = Role::query()->where('guard_name', 'api')->lockForUpdate()->findOrFail($id);
        abort_if(in_array($role->name, config('rbac.system_roles'), true), 403);

        return $role;
    }
}
