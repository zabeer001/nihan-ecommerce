<?php

use App\Models\Customer;
use App\Models\User;

function customerIdentityApiUser(): User
{
    return User::factory()->create(['status' => 'active']);
}

it('rejects a duplicate customer email regardless of casing and whitespace', function () {
    $user = customerIdentityApiUser();
    Customer::create(['name' => 'Existing', 'email' => 'Buyer@Example.com']);

    $this->actingAs($user, 'api')->postJson('/api/customers', [
        'name' => 'Duplicate',
        'email' => '  buyer@example.COM  ',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(Customer::query()->count())->toBe(1);
});

it('rejects equivalent formatted customer phone numbers', function () {
    $user = customerIdentityApiUser();
    Customer::create(['name' => 'Existing', 'mobile' => '+880 (1712) 345-678']);

    $this->actingAs($user, 'api')->postJson('/api/customers', [
        'name' => 'Duplicate',
        'mobile' => '8801712345678',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('mobile');

    expect(Customer::query()->count())->toBe(1);
});

it('allows unchanged identities on update and rejects identities owned by another customer', function () {
    $user = customerIdentityApiUser();
    $customer = Customer::create([
        'name' => 'First',
        'email' => 'first@example.com',
        'mobile' => '01711-111111',
    ]);
    Customer::create([
        'name' => 'Second',
        'email' => 'second@example.com',
        'mobile' => '01722-222222',
    ]);

    $this->actingAs($user, 'api')->putJson("/api/customers/{$customer->id}", [
        'name' => 'First Updated',
        'email' => ' FIRST@example.com ',
        'mobile' => '01711 111111',
    ])->assertOk();

    $this->actingAs($user, 'api')->putJson("/api/customers/{$customer->id}", [
        'email' => 'SECOND@example.com',
        'mobile' => '(01722) 222-222',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'mobile']);
});

it('allows multiple customers with blank email and phone', function () {
    $user = customerIdentityApiUser();

    foreach (['First', 'Second'] as $name) {
        $this->actingAs($user, 'api')->postJson('/api/customers', [
            'name' => $name,
            'email' => '',
            'mobile' => '',
        ])->assertCreated();
    }

    expect(Customer::query()->count())->toBe(2);
});
