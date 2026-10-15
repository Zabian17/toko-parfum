<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function index(Request $request): JsonResponse
    {
        $customers = $this->service->list($request->only([
            'search', 'is_active', 'per_page', 'filters', 'sorters',
        ]));

        return response()->json($customers->through(fn($c) => new CustomerResource($c)));
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->load('orders');

        return response()->json(['data' => new CustomerResource($customer)]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->service->create($request->validated());

        return response()->json(['data' => new CustomerResource($customer)], 201);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->service->update($customer, $request->validated());

        return response()->json(['data' => new CustomerResource($customer)]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->service->delete($customer);

        return response()->json(['message' => 'Customer deleted.']);
    }
}
