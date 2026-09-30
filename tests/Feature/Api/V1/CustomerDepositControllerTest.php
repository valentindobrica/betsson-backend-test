<?php

declare(strict_types=1);

use App\Actions\CreateCustomerAction;
use App\Enums\Gender;
use App\Models\Customer;

function createFundedCustomer(): Customer
{
    return resolve(CreateCustomerAction::class)->execute(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: fake()->unique()->safeEmail(),
    );
}

it('creates a pending deposit without crediting the wallet yet', function (): void {
    $customer = createFundedCustomer();

    $response = $this->postJson("/api/v1/customers/{$customer->id}/deposits", [
        'amount' => 100,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.customer_id', $customer->id)
        ->assertJsonPath('data.status', 'Pending')
        ->assertJsonPath('data.amount', '100.00')
        ->assertJsonPath('data.bonus_amount', '0.00')
        ->assertJsonPath('data.deposit_number', null)
        ->assertJsonPath('data.balance', null);
});

it('rejects a deposit with a missing amount', function (): void {
    $customer = createFundedCustomer();

    $response = $this->postJson("/api/v1/customers/{$customer->id}/deposits", []);

    $response->assertStatus(422)->assertJsonValidationErrors(['amount']);
});

it('rejects a deposit with a negative amount', function (): void {
    $customer = createFundedCustomer();

    $response = $this->postJson("/api/v1/customers/{$customer->id}/deposits", ['amount' => -10]);

    $response->assertStatus(422)->assertJsonValidationErrors(['amount']);
});

it('rejects a deposit with more than 2 decimal places', function (): void {
    $customer = createFundedCustomer();

    $response = $this->postJson("/api/v1/customers/{$customer->id}/deposits", ['amount' => 10.123]);

    $response->assertStatus(422)->assertJsonValidationErrors(['amount']);
});

it('returns 404 when depositing for a missing customer', function (): void {
    $response = $this->postJson('/api/v1/customers/999999/deposits', ['amount' => 10]);

    $response->assertNotFound();
});

it('confirms a deposit via the webhook and credits the wallet', function (): void {
    $customer = createFundedCustomer();
    $created = $this->postJson("/api/v1/customers/{$customer->id}/deposits", ['amount' => 100])
        ->json('data.id');

    $response = $this->postJson("/api/v1/deposits/{$created}/webhook", ['status' => 'approved']);

    $response->assertOk()
        ->assertJsonPath('data.status', 'Approved')
        ->assertJsonPath('data.deposit_number', 1)
        ->assertJsonPath('data.balance', '100.00');
});

it('is idempotent when the webhook is called twice for the same deposit', function (): void {
    $customer = createFundedCustomer();
    $created = $this->postJson("/api/v1/customers/{$customer->id}/deposits", ['amount' => 100])
        ->json('data.id');

    $first = $this->postJson("/api/v1/deposits/{$created}/webhook", ['status' => 'approved']);
    $second = $this->postJson("/api/v1/deposits/{$created}/webhook", ['status' => 'approved']);

    $first->assertOk()->assertJsonPath('data.balance', '100.00');
    $second->assertOk()->assertJsonPath('data.balance', '100.00');

    $balance = $this->getJson("/api/v1/customers/{$customer->id}/deposits")
        ->json('data.0.balance');

    expect($balance)->toBe('100.00');
});

it('disapproves a deposit via the webhook without crediting the wallet', function (): void {
    $customer = createFundedCustomer();
    $created = $this->postJson("/api/v1/customers/{$customer->id}/deposits", ['amount' => 100])
        ->json('data.id');

    $response = $this->postJson("/api/v1/deposits/{$created}/webhook", ['status' => 'disapproved']);

    $response->assertOk()
        ->assertJsonPath('data.status', 'Disapproved')
        ->assertJsonPath('data.balance', null);
});

it('rejects a webhook call with an invalid status', function (): void {
    $customer = createFundedCustomer();
    $created = $this->postJson("/api/v1/customers/{$customer->id}/deposits", ['amount' => 100])
        ->json('data.id');

    $response = $this->postJson("/api/v1/deposits/{$created}/webhook", ['status' => 'unknown']);

    $response->assertStatus(422)->assertJsonValidationErrors(['status']);
});

it('returns 404 when the webhook targets a missing deposit', function (): void {
    $response = $this->postJson('/api/v1/deposits/999999/webhook', ['status' => 'approved']);

    $response->assertNotFound();
});

it('lists a customer deposits paginated, newest first', function (): void {
    $customer = createFundedCustomer();
    $this->postJson("/api/v1/customers/{$customer->id}/deposits", ['amount' => 100]);
    $this->postJson("/api/v1/customers/{$customer->id}/deposits", ['amount' => 200]);

    $response = $this->getJson("/api/v1/customers/{$customer->id}/deposits");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.amount', '200.00')
        ->assertJsonPath('data.1.amount', '100.00')
        ->assertJsonPath('meta.total', 2);
});

it('paginates a customer deposits with a custom page size', function (): void {
    $customer = createFundedCustomer();

    foreach ([10, 20, 30] as $amount) {
        $this->postJson("/api/v1/customers/{$customer->id}/deposits", ['amount' => $amount]);
    }

    $response = $this->getJson("/api/v1/customers/{$customer->id}/deposits?per_page=2&page=2");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 2);
});

it('rejects listing deposits with an invalid per_page', function (): void {
    $customer = createFundedCustomer();

    $response = $this->getJson("/api/v1/customers/{$customer->id}/deposits?per_page=0");

    $response->assertStatus(422)->assertJsonValidationErrors(['per_page']);
});
