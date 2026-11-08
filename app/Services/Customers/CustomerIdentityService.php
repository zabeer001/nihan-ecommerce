<?php

namespace App\Services\Customers;

use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class CustomerIdentityService
{
    public function synchronize(Customer $customer): void
    {
        $customer->setAttribute('normalized_email', $this->normalizeEmail($customer->email));
        $customer->setAttribute('normalized_mobile', $this->normalizeMobile($customer->mobile));
    }

    public function createUnique(array $customerData): Customer
    {
        $customerData = $this->normalizeStoredValues($customerData);
        $this->assertAvailable($customerData);

        try {
            return Customer::create($customerData);
        } catch (QueryException $exception) {
            $this->assertAvailable($customerData);

            throw $exception;
        }
    }

    public function updateUnique(Customer $customer, array $customerData): Customer
    {
        $customerData = $this->normalizeStoredValues($customerData);
        $this->assertAvailable($customerData, $customer);

        try {
            $customer->update($customerData);
        } catch (QueryException $exception) {
            $this->assertAvailable($customerData, $customer);

            throw $exception;
        }

        return $customer->refresh();
    }

    public function resolveForOrder(array $customerData): Customer
    {
        $customerData = $this->normalizeStoredValues($customerData);
        $matches = $this->findMatches($customerData);
        $customer = $this->singleMatchOrFail($matches);

        if ($customer) {
            return $customer;
        }

        try {
            return Customer::create($customerData);
        } catch (QueryException $exception) {
            // A concurrent request may have created this identity after our lookup.
            $customer = $this->singleMatchOrFail($this->findMatches($customerData));

            if ($customer) {
                return $customer;
            }

            throw $exception;
        }
    }

    public function normalizeEmail(mixed $email): ?string
    {
        $normalized = mb_strtolower(trim((string) $email));

        return $normalized === '' ? null : $normalized;
    }

    public function normalizeMobile(mixed $mobile): ?string
    {
        $normalized = preg_replace('/[^0-9]+/', '', trim((string) $mobile)) ?? '';

        return $normalized === '' ? null : $normalized;
    }

    private function assertAvailable(array $customerData, ?Customer $ignoredCustomer = null): void
    {
        $matches = $this->findMatches($customerData, $ignoredCustomer?->id);
        $errors = [];

        if ($matches['email']) {
            $errors['email'] = 'A customer with this email already exists.';
        }

        if ($matches['mobile']) {
            $errors['mobile'] = 'A customer with this phone number already exists.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array{email: ?Customer, mobile: ?Customer}
     */
    private function findMatches(array $customerData, ?int $ignoredCustomerId = null): array
    {
        $email = $this->normalizeEmail($customerData['email'] ?? null);
        $mobile = $this->normalizeMobile($customerData['mobile'] ?? null);

        $emailMatch = $email === null ? null : Customer::query()
            ->when($ignoredCustomerId, fn ($query) => $query->whereKeyNot($ignoredCustomerId))
            ->where('normalized_email', $email)
            ->first();
        $mobileMatch = $mobile === null ? null : Customer::query()
            ->when($ignoredCustomerId, fn ($query) => $query->whereKeyNot($ignoredCustomerId))
            ->where('normalized_mobile', $mobile)
            ->first();

        return ['email' => $emailMatch, 'mobile' => $mobileMatch];
    }

    /**
     * @param  array{email: ?Customer, mobile: ?Customer}  $matches
     */
    private function singleMatchOrFail(array $matches): ?Customer
    {
        if ($matches['email'] && $matches['mobile'] && $matches['email']->isNot($matches['mobile'])) {
            throw ValidationException::withMessages([
                'customer.email' => 'The email and phone number belong to different customers.',
                'customer.mobile' => 'The email and phone number belong to different customers.',
            ]);
        }

        return $matches['email'] ?? $matches['mobile'];
    }

    private function normalizeStoredValues(array $customerData): array
    {
        if (array_key_exists('email', $customerData)) {
            $email = trim((string) $customerData['email']);
            $customerData['email'] = $email === '' ? null : $email;
        }

        if (array_key_exists('mobile', $customerData)) {
            $mobile = trim((string) $customerData['mobile']);
            $customerData['mobile'] = $mobile === '' ? null : $mobile;
        }

        $customerData['shipping_address'] ??= $customerData['delivery_address'] ?? null;

        return $customerData;
    }
}
