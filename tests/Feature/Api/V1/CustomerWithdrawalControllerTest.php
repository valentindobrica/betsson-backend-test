<?php

declare(strict_types=1);

use App\Actions\CreateCustomerAction;
use App\Enums\Gender;
use App\Models\Customer;

function createCustomerWithApprovedDeposit(float $amount = 100): Customer
{
    $customer = resolve(CreateCustomerAction::class)->execute(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: fake()->unique()->safeEmail(),
    );

    $depositId = test()->postJson("/api/v1/customers/{$customer->id}/deposits", ['amount' => $amount])
        ->assertCreated()
        ->json('data.id');

    test()->postJson("/api/v1/deposits/{$depositId}/webhook", ['status' => 'approved'])
        ->assertOk();

    return $customer;
}

it('withdraws money for a customer, reserving it immediately', function (): void {
    $customer = createCustomerWithApprovedDeposit(100);

    $response = $this->postJson("/api/v1/customers/{$customer->id}/withdrawals", [
        'amount' => 40,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.customer_id', $customer->id)
        ->assertJsonPath('data.status', 'Pending')
        ->assertJsonPath('data.amount', '40.00')
        ->assertJsonPath('data.pre_approved', false)
        ->assertJsonPath('data.balance', '60.00')
        ->assertJsonPath('data.processed_at', null);
});

it('rejects a withdrawal larger than the real balance', function (): void {
    $customer = createCustomerWithApprovedDeposit(100);

    $response = $this->postJson("/api/v1/customers/{$customer->id}/withdrawals", [
        'amount' => 100.01,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['amount']);
});

it('rejects a withdrawal with a missing amount', function (): void {
    $customer = createCustomerWithApprovedDeposit(100);

    $response = $this->postJson("/api/v1/customers/{$customer->id}/withdrawals", []);

    $response->assertStatus(422)->assertJsonValidationErrors(['amount']);
});

it('returns 404 when withdrawing for a missing customer', function (): void {
    $response = $this->postJson('/api/v1/customers/999999/withdrawals', ['amount' => 10]);

    $response->assertNotFound();
});

it('is processed to approved by the scheduled command and its job', function (): void {
    $customer = createCustomerWithApprovedDeposit(100);
    $this->postJson("/api/v1/customers/{$customer->id}/withdrawals", ['amount' => 40])
        ->assertCreated();

    $this->artisan('withdrawals:process-pending');

    $response = $this->getJson("/api/v1/customers/{$customer->id}/withdrawals");

    $response->assertOk()
        ->assertJsonPath('data.0.status', 'Approved')
        ->assertJsonPath('data.0.pre_approved', true);
});

it('lists a customer withdrawals paginated, newest first', function (): void {
    $customer = createCustomerWithApprovedDeposit(500);
    $this->postJson("/api/v1/customers/{$customer->id}/withdrawals", ['amount' => 50]);
    $this->postJson("/api/v1/customers/{$customer->id}/withdrawals", ['amount' => 30]);

    $response = $this->getJson("/api/v1/customers/{$customer->id}/withdrawals");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.amount', '30.00')
        ->assertJsonPath('data.1.amount', '50.00')
        ->assertJsonPath('meta.total', 2);
});
