<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $auth = auth()->user();
        $isSuper = $auth?->role?->is_super ?? false;

        $query = User::with('role:id,name,slug,is_super')
            ->select(['id', 'name', 'email', 'role_id', 'created_at'])
            ->orderBy('id');

        // If the current admin is not a super admin, hide users who are super admins
        if (!$isSuper) {
            $query->whereHas('role', function ($q) {
                $q->where('is_super', false);
            });
        }

        $users = $query->get();

        return response()->json(['data' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id'  => ['nullable', 'exists:roles,id'],
        ]);

        // Prevent non-super admins from creating super-admin users
        $roleId = $validated['role_id'] ?? null;
        if ($roleId) {
            $role = \App\Models\Role::find($roleId);
            if ($role && $role->is_super && !(auth()->user()?->role?->is_super ?? false)) {
                return response()->json(['message' => 'Tidak diizinkan membuat Super Admin.'], 403);
            }
        }

        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);
        $user->load('role:id,name,slug,is_super');

        return response()->json(['data' => $user], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('role:id,name,slug,is_super');

        // Hide super admin details from non-super-admins
        if (($user->role?->is_super ?? false) && !(auth()->user()?->role?->is_super ?? false)) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json(['data' => $user]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['sometimes', 'required', 'string', 'max:255'],
            'email'    => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role_id'  => ['nullable', 'exists:roles,id'],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        // Prevent non-super admins from assigning super role
        if (array_key_exists('role_id', $validated) && $validated['role_id']) {
            $role = \App\Models\Role::find($validated['role_id']);
            if ($role && $role->is_super && !(auth()->user()?->role?->is_super ?? false)) {
                return response()->json(['message' => 'Tidak diizinkan mengubah menjadi Super Admin.'], 403);
            }
        }

        // Prevent non-super admins from updating a super admin
        if (($user->role?->is_super ?? false) && !(auth()->user()?->role?->is_super ?? false)) {
            return response()->json(['message' => 'Tidak diizinkan mengubah Super Admin.'], 403);
        }

        $user->update($validated);
        $user->load('role:id,name,slug,is_super');

        return response()->json(['data' => $user]);
    }

    public function destroy(User $user): JsonResponse
    {
        // Prevent deleting the currently authenticated user
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Tidak dapat menghapus akun sendiri.'], 422);
        }
        // Prevent non-super admins from deleting a super admin
        if (($user->role?->is_super ?? false) && !(auth()->user()?->role?->is_super ?? false)) {
            return response()->json(['message' => 'Tidak diizinkan menghapus Super Admin.'], 403);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User dihapus.']);
    }
}
