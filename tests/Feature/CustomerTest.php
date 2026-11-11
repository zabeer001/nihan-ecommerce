<?php

use App\Models\User;

it('lets administrators manage customers and denies regular users', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api');

    $url = '/api/admin/customers';
    $customer = ['name' => 'Example Customer', 'phone' => '+8801700000000'];
    $id = $this->postJson($url, $customer)->assertCreated()->json('data.id');

    $this->getJson($url.'?search=Example')->assertOk()->assertJsonPath('data.data.0.id', $id);
    $this->putJson("$url/$id", [...$customer, 'email' => 'customer@example.com'])
        ->assertOk()->assertJsonPath('data.email', 'customer@example.com');

    $this->actingAs(User::factory()->create(), 'api');
    $this->getJson($url)->assertForbidden();
    $this->deleteJson("$url/$id")->assertForbidden();

    $this->actingAs($admin, 'api')->deleteJson("$url/$id")->assertOk();
    $this->assertDatabaseMissing('customers', ['id' => $id]);
});
