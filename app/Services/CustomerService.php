<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Customer::withCount('orders');
        $columnFilters = $this->normalizePayloadArray($filters['filters'] ?? []);
        $sorters       = $this->normalizePayloadArray($filters['sorters'] ?? []);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
            });
        }

        $isActive = $this->toNullableBool($filters['is_active'] ?? null);
        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        foreach ($columnFilters as $filter) {
            $field = $filter['field'] ?? null;
            $value = trim((string) ($filter['value'] ?? ''));

            if (!$field || $value === '') {
                continue;
            }

            if (in_array($field, ['name', 'email', 'phone', 'city'], true)) {
                $query->where($field, 'like', '%' . $value . '%');
                continue;
            }

            if ($field === 'created_at') {
                $query->whereDate('created_at', $value);
            }
        }

        if (!empty($sorters)) {
            $sorter = $sorters[0];
            $field = (string) ($sorter['field'] ?? 'name');
            $dir = strtolower((string) ($sorter['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

            if (in_array($field, ['name', 'email', 'phone', 'city', 'created_at'], true)) {
                $query->orderBy($field, $dir);
            }
        } else {
            $query->orderBy('name');
        }

        return $query->paginate($filters['per_page'] ?? 15);
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

    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer->fresh();
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    public function findOrCreateGuest(array $data): Customer
    {
        if (!empty($data['email'])) {
            return Customer::firstOrCreate(
                ['email' => $data['email']],
                $data
            );
        }

        return Customer::create($data);
    }
}
