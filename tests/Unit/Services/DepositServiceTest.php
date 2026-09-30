<?php

declare(strict_types=1);

use App\Enums\DepositStatus;
use App\Enums\Gender;
use App\Models\Customer;
use App\Models\Deposit;
use App\Services\CustomerService;
use App\Services\DepositService;
use App\Services\WalletService;

function depositService(): DepositService
{
    return resolve(DepositService::class);
}

function createDepositCustomer(int $bonusPercentage = 10): Customer
{
    $customer = resolve(CustomerService::class)->create(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: fake()->unique()->safeEmail(),
        bonusPercentage: $bonusPercentage,
    );

    resolve(WalletService::class)->initialize($customer->id);

    return $customer;
}

it('creates a pending deposit without touching the wallet', function (): void {
    $customer = createDepositCustomer();

    $deposit = depositService()->create($customer, 10_000);

    expect($deposit->status)->toBe(DepositStatus::Pending)
        ->and($deposit->customerId)->toBe($customer->id)
        ->and($deposit->amountCents)->toBe(10_000)
        ->and($deposit->bonusAmountCents)->toBe(0)
        ->and($deposit->depositNumber)->toBeNull()
        ->and($deposit->balanceAfterCents)->toBeNull()
        ->and($deposit->bonusBalanceAfterCents)->toBeNull()
        ->and($deposit->approvedAt)->toBeNull();

    $wallet = resolve(WalletService::class)->find($customer->id);
    expect($wallet->balanceCents)->toBe(0);
});

it('finds a deposit by id', function (): void {
    $customer = createDepositCustomer();
    $deposit = depositService()->create($customer, 10_000);

    expect(depositService()->find($deposit->id)?->id)->toBe($deposit->id)
        ->and(depositService()->find(999999))->toBeNull();
});

it('approves a pending deposit and credits the wallet', function (): void {
    $customer = createDepositCustomer(10);
    $deposit = depositService()->create($customer, 10_000);

    $approved = depositService()->approve($deposit->id);

    expect($approved->status)->toBe(DepositStatus::Approved)
        ->and($approved->depositNumber)->toBe(1)
        ->and($approved->bonusAmountCents)->toBe(0)
        ->and($approved->balanceAfterCents)->toBe(10_000)
        ->and($approved->bonusBalanceAfterCents)->toBe(0)
        ->and($approved->approvedAt)->not->toBeNull();

    $wallet = resolve(WalletService::class)->find($customer->id);
    expect($wallet->balanceCents)->toBe(10_000)
        ->and($wallet->bonusBalanceCents)->toBe(0);
});

it('awards a bonus on every 3rd approved deposit', function (): void {
    $customer = createDepositCustomer(10);

    $first = depositService()->create($customer, 10_000);
    $second = depositService()->create($customer, 10_000);
    $third = depositService()->create($customer, 10_000);

    depositService()->approve($first->id);
    depositService()->approve($second->id);

    $approvedThird = depositService()->approve($third->id);

    expect($approvedThird->depositNumber)->toBe(3)
        ->and($approvedThird->bonusAmountCents)->toBe(1_000)
        ->and($approvedThird->balanceAfterCents)->toBe(30_000)
        ->and($approvedThird->bonusBalanceAfterCents)->toBe(1_000);
});

it('does not count a disapproved deposit towards the bonus numbering', function (): void {
    $customer = createDepositCustomer(10);

    $first = depositService()->create($customer, 10_000);
    $second = depositService()->create($customer, 10_000);
    $disapproved = depositService()->create($customer, 10_000);
    $third = depositService()->create($customer, 10_000);

    depositService()->approve($first->id);
    depositService()->approve($second->id);
    depositService()->disapprove($disapproved->id);

    $approvedThird = depositService()->approve($third->id);

    // The disapproved deposit does not count, so this is still the "3rd"
    // approved deposit and should be bonused, not the 4th (unbonused).
    expect($approvedThird->depositNumber)->toBe(3)
        ->and($approvedThird->bonusAmountCents)->toBe(1_000);
});

it('is idempotent: approving an already-approved deposit does not credit the wallet again', function (): void {
    $customer = createDepositCustomer(10);
    $deposit = depositService()->create($customer, 10_000);

    $first = depositService()->approve($deposit->id);
    $second = depositService()->approve($deposit->id);

    expect($second->status)->toBe(DepositStatus::Approved)
        ->and($second->balanceAfterCents)->toBe($first->balanceAfterCents)
        ->and($second->approvedAt?->toDateTimeString())->toBe($first->approvedAt?->toDateTimeString());

    $wallet = resolve(WalletService::class)->find($customer->id);
    expect($wallet->balanceCents)->toBe(10_000);
});

it('disapproves a pending deposit without touching the wallet', function (): void {
    $customer = createDepositCustomer();
    $deposit = depositService()->create($customer, 10_000);

    $disapproved = depositService()->disapprove($deposit->id);

    expect($disapproved->status)->toBe(DepositStatus::Disapproved)
        ->and($disapproved->balanceAfterCents)->toBeNull()
        ->and($disapproved->approvedAt)->not->toBeNull();

    $wallet = resolve(WalletService::class)->find($customer->id);
    expect($wallet->balanceCents)->toBe(0);
});

it('is idempotent: disapproving an already-resolved deposit is a no-op', function (): void {
    $customer = createDepositCustomer();
    $deposit = depositService()->create($customer, 10_000);

    depositService()->approve($deposit->id);
    $stillApproved = depositService()->disapprove($deposit->id);

    expect($stillApproved->status)->toBe(DepositStatus::Approved);

    $wallet = resolve(WalletService::class)->find($customer->id);
    expect($wallet->balanceCents)->toBe(10_000);
});

it('throws when approving a deposit that does not exist', function (): void {
    expect(fn (): Deposit => depositService()->approve(999999))->toThrow(RuntimeException::class);
});

it('paginates a customer deposits, newest first', function (): void {
    $customer = createDepositCustomer();
    depositService()->create($customer, 10_000);
    depositService()->create($customer, 20_000);

    $page = depositService()->paginate($customer->id, page: 1, perPage: 15);

    expect($page->total())->toBe(2)
        ->and($page->items())->toHaveCount(2)
        ->and($page->items()[0]->amountCents)->toBe(20_000)
        ->and($page->items()[1]->amountCents)->toBe(10_000);
});
