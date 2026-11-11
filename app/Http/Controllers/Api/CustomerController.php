<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255']]);

        $customers = Customer::query()->when($filters['search'] ?? null, fn ($query, $search) =>
            $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
        )->orderBy('name')->orderBy('id')->paginate(20);

        return $this->successResponse($customers);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->successResponse(Customer::create($this->validateCustomer($request)), 'Customer added.', 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $customer->update($this->validateCustomer($request));

        return $this->successResponse($customer, 'Customer updated.');
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return $this->successResponse(message: 'Customer deleted.');
    }

    private function validateCustomer(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
