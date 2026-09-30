<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Models\Customer;
use App\Services\CustomerService;

function createTestCustomer(array $overrides = []): Customer
{
    $attributes = [
        'gender' => Gender::Male,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'country' => 'MT',
        'email' => fake()->unique()->safeEmail(),
        'bonus_percentage' => 12,
        ...$overrides,
    ];

    return resolve(CustomerService::class)->create(
        gender: $attributes['gender'],
        firstName: $attributes['first_name'],
        lastName: $attributes['last_name'],
        country: $attributes['country'],
        email: $attributes['email'],
        bonusPercentage: $attributes['bonus_percentage'],
    );
}

it('creates a customer with a random bonus percentage', function (): void {
    $response = $this->postJson('/api/v1/customers', [
        'gender' => 'male',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'country' => 'mt',
        'email' => 'John.Doe@Example.com',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.gender', 'male')
        ->assertJsonPath('data.first_name', 'John')
        ->assertJsonPath('data.last_name', 'Doe')
        ->assertJsonPath('data.country', 'MT')
        ->assertJsonPath('data.email', 'john.doe@example.com');

    expect($response->json('data.bonus_percentage'))
        ->toBeInt()
        ->toBeGreaterThanOrEqual(5)
        ->toBeLessThanOrEqual(20);
});

it('rejects a customer missing required fields', function (): void {
    $response = $this->postJson('/api/v1/customers', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['gender', 'first_name', 'last_name', 'country', 'email']);
});

it('rejects an invalid gender', function (): void {
    $response = $this->postJson('/api/v1/customers', [
        'gender' => 'unknown',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'country' => 'MT',
        'email' => 'john.doe@example.com',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['gender']);
});

it('rejects a country that is not a 2 letter code', function (): void {
    $response = $this->postJson('/api/v1/customers', [
        'gender' => 'male',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'country' => 'MLT',
        'email' => 'john.doe@example.com',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['country']);
});

it('rejects an invalid email', function (): void {
    $response = $this->postJson('/api/v1/customers', [
        'gender' => 'male',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'country' => 'MT',
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['email']);
});

it('rejects a duplicate email on creation', function (): void {
    createTestCustomer(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/v1/customers', [
        'gender' => 'female',
        'first_name' => 'Jane',
        'last_name' => 'Roe',
        'country' => 'DE',
        'email' => 'taken@example.com',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['email']);
});

it('shows a customer', function (): void {
    $customer = createTestCustomer();

    $response = $this->getJson("/api/v1/customers/{$customer->id}");

    $response->assertOk()
        ->assertJson([
            'data' => [
                'id' => $customer->id,
                'gender' => $customer->gender->value,
                'first_name' => $customer->firstName,
                'last_name' => $customer->lastName,
                'country' => $customer->country,
                'email' => $customer->email,
                'bonus_percentage' => $customer->bonusPercentage,
            ],
        ]);
});

it('returns 404 when the customer does not exist', function (): void {
    $response = $this->getJson('/api/v1/customers/999999');

    $response->assertNotFound();
});

it('returns 404 when the customer id is not numeric', function (): void {
    $response = $this->getJson('/api/v1/customers/not-a-number');

    $response->assertNotFound();
});

it('updates a customer without changing its bonus percentage', function (): void {
    $customer = createTestCustomer(['bonus_percentage' => 15]);

    $response = $this->putJson("/api/v1/customers/{$customer->id}", [
        'gender' => 'other',
        'first_name' => 'Johnny',
        'last_name' => 'Doe',
        'country' => 'de',
        'email' => 'johnny@example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.gender', 'other')
        ->assertJsonPath('data.first_name', 'Johnny')
        ->assertJsonPath('data.country', 'DE')
        ->assertJsonPath('data.email', 'johnny@example.com')
        ->assertJsonPath('data.bonus_percentage', 15);
});

it('allows a customer to keep its own email address when updating', function (): void {
    $customer = createTestCustomer(['email' => 'same@example.com']);

    $response = $this->putJson("/api/v1/customers/{$customer->id}", [
        'gender' => $customer->gender->value,
        'first_name' => $customer->firstName,
        'last_name' => $customer->lastName,
        'country' => $customer->country,
        'email' => 'same@example.com',
    ]);

    $response->assertOk()->assertJsonPath('data.email', 'same@example.com');
});

it('rejects updating a customer with another customer email', function (): void {
    createTestCustomer(['email' => 'first@example.com']);
    $second = createTestCustomer(['email' => 'second@example.com']);

    $response = $this->putJson("/api/v1/customers/{$second->id}", [
        'gender' => $second->gender->value,
        'first_name' => $second->firstName,
        'last_name' => $second->lastName,
        'country' => $second->country,
        'email' => 'first@example.com',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['email']);
});

it('rejects an update missing required fields', function (): void {
    $customer = createTestCustomer();

    $response = $this->putJson("/api/v1/customers/{$customer->id}", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['gender', 'first_name', 'last_name', 'country', 'email']);
});

it('returns 404 when updating a customer that does not exist', function (): void {
    $response = $this->putJson('/api/v1/customers/999999', [
        'gender' => 'male',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'country' => 'MT',
        'email' => 'john.doe@example.com',
    ]);

    $response->assertNotFound();
});
