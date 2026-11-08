<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;

function orderCustomerIdentityApiUser(): User
{
    return User::factory()->create(['status' => 'active']);
}

it('links an existing customer by normalized email without overwriting it', function () {
    $user = orderCustomerIdentityApiUser();
    $customer = Customer::create([
        'name' => 'Saved Customer',
        'email' => 'buyer@example.com',
        'mobile' => '01700-000001',
    ]);

    $this->actingAs($user, 'api')->postJson('/api/orders', [
        'order_number' => 'ORD-IDENTITY-EMAIL',
        'customer' => [
            'name' => 'Typed Different Name',
            'email' => ' BUYER@EXAMPLE.COM ',
            'mobile' => '01900-000009',
        ],
    ])->assertCreated()
        ->assertJsonPath('data.customer.id', $customer->id);

    expect(Customer::query()->count())->toBe(1)
        ->and($customer->fresh()->name)->toBe('Saved Customer')
        ->and($customer->fresh()->mobile)->toBe('01700-000001');
});

it('links an existing customer by normalized phone', function () {
    $user = orderCustomerIdentityApiUser();
    $customer = Customer::create([
        'name' => 'Phone Customer',
        'mobile' => '+880 (1712) 345-678',
    ]);

    $this->actingAs($user, 'api')->postJson('/api/orders', [
        'order_number' => 'ORD-IDENTITY-PHONE',
        'customer' => [
            'name' => 'Inline Customer',
            'mobile' => '8801712345678',
        ],
    ])->assertCreated()
        ->assertJsonPath('data.customer.id', $customer->id);

    expect(Customer::query()->count())->toBe(1);
});

it('links when email and phone match the same customer', function () {
    $user = orderCustomerIdentityApiUser();
    $customer = Customer::create([
        'name' => 'Same Customer',
        'email' => 'same@example.com',
        'mobile' => '01733-333333',
    ]);

    $this->actingAs($user, 'api')->postJson('/api/orders', [
        'order_number' => 'ORD-IDENTITY-BOTH',
        'customer' => [
            'email' => 'SAME@example.com',
            'mobile' => '(01733) 333-333',
        ],
    ])->assertCreated()
        ->assertJsonPath('data.customer.id', $customer->id);

    expect(Customer::query()->count())->toBe(1);
});

it('creates a new customer when neither identity matches', function () {
    $user = orderCustomerIdentityApiUser();

    $this->actingAs($user, 'api')->postJson('/api/orders', [
        'order_number' => 'ORD-IDENTITY-NEW',
        'customer' => [
            'name' => 'New Customer',
            'email' => 'new@example.com',
            'mobile' => '01844-444444',
        ],
    ])->assertCreated();

    $this->assertDatabaseHas('customers', [
        'name' => 'New Customer',
        'normalized_email' => 'new@example.com',
        'normalized_mobile' => '01844444444',
    ]);
});

it('rejects an order when email and phone belong to different customers', function () {
    $user = orderCustomerIdentityApiUser();
    Customer::create(['name' => 'Email Owner', 'email' => 'owner@example.com']);
    Customer::create(['name' => 'Phone Owner', 'mobile' => '01755-555555']);

    $this->actingAs($user, 'api')->postJson('/api/orders', [
        'order_number' => 'ORD-IDENTITY-CONFLICT',
        'customer' => [
            'email' => 'owner@example.com',
            'mobile' => '01755555555',
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['customer.email', 'customer.mobile']);

    expect(Order::query()->count())->toBe(0)
        ->and(Customer::query()->count())->toBe(2);
});

it('uses the same identity resolution when updating an order', function () {
    $user = orderCustomerIdentityApiUser();
    $originalCustomer = Customer::create(['name' => 'Original']);
    $matchedCustomer = Customer::create([
        'name' => 'Matched',
        'email' => 'matched@example.com',
        'mobile' => '01666-666666',
    ]);
    $order = Order::create([
        'customer_id' => $originalCustomer->id,
        'order_number' => 'ORD-IDENTITY-UPDATE',
    ]);

    $this->actingAs($user, 'api')->putJson("/api/orders/{$order->id}", [
        'customer' => [
            'name' => 'Must Not Overwrite',
            'email' => 'MATCHED@example.com',
            'mobile' => '(01666) 666-666',
        ],
    ])->assertOk()
        ->assertJsonPath('data.customer.id', $matchedCustomer->id);

    expect($order->fresh()->customer_id)->toBe($matchedCustomer->id)
        ->and($matchedCustomer->fresh()->name)->toBe('Matched')
        ->and(Customer::query()->count())->toBe(2);
});
