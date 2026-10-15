<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryService
{
    public function list(array $filters = [])
    {
        $query = Category::withCount('products');
        $columnFilters = $this->normalizePayloadArray($filters['filters'] ?? []);
        $sorters       = $this->normalizePayloadArray($filters['sorters'] ?? []);

        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        $isActive = $this->toNullableBool($filters['is_active'] ?? null);
        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        if (isset($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }

        foreach ($columnFilters as $filter) {
            $field = $filter['field'] ?? null;
            $value = trim((string) ($filter['value'] ?? ''));

            if (!$field || $value === '') {
                continue;
            }

            if ($field === 'name') {
                $query->where('name', 'like', '%' . $value . '%');
                continue;
            }

            if ($field === 'slug') {
                $query->where('slug', 'like', '%' . $value . '%');
                continue;
            }

            if ($field === 'is_active') {
                $bool = $this->toNullableBool($value);
                if ($bool !== null) {
                    $query->where('is_active', $bool);
                }
            }
        }

        if (!empty($sorters)) {
            $sorter = $sorters[0];
            $field = (string) ($sorter['field'] ?? 'sort_order');
            $dir = strtolower((string) ($sorter['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

            if (in_array($field, ['name', 'slug', 'sort_order', 'is_active', 'products_count'], true)) {
                $query->orderBy($field, $dir);
            }
        } else {
            $query->orderBy('sort_order')->orderBy('name');
        }

        if (!empty($filters['per_page'])) {
            return $query->paginate((int) $filters['per_page']);
        }

        return $query->get();
    }

    private function normalizePayloadArray(mixed $payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($payload) ? $payload : [];
    }

    private function toNullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower((string) $value);
        return in_array($normalized, ['1', 'true', 'yes'], true);
    }

    public function create(array $data, ?UploadedFile $image = null): Category
    {
        if ($image) {
            $data['image'] = $image->store('categories', 'public');
        }

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        return Category::create($data);
    }

    public function update(Category $category, array $data, ?UploadedFile $image = null): Category
    {
        if ($image) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $image->store('categories', 'public');
        }

        $category->update($data);

        return $category->fresh();
    }

    public function delete(Category $category): void
    {
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();
    }
}
