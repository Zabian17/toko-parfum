<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::withCount('users')->orderBy('id')->get();
        return response()->json(['data' => $roles]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'slug'        => ['required', 'string', 'max:100', 'unique:roles,slug'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'is_super'    => ['boolean'],
        ]);

        $role = Role::create($validated);
        return response()->json(['data' => $role], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json(['data' => $role->loadCount('users')]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:100'],
            'slug'        => ['sometimes', 'required', 'string', 'max:100', Rule::unique('roles', 'slug')->ignore($role->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'is_super'    => ['boolean'],
        ]);

        $role->update($validated);
        return response()->json(['data' => $role->loadCount('users')]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->users()->exists()) {
            return response()->json(['message' => 'Role memiliki user, tidak dapat dihapus.'], 422);
        }
        $role->delete();
        return response()->json(['message' => 'Role dihapus.']);
    }
}
