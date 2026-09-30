<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Enums\WithdrawStatus;
use App\Models\Customer;
use App\Models\Withdrawal;
use App\Services\CustomerService;
use App\Services\DepositService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Validation\ValidationException;

function withdrawalService(): WithdrawalService
{
    return resolve(WithdrawalService::class);
}

function createFundedWithdrawalCustomer(int $balanceCents): Customer
{
    $customer = resolve(CustomerService::class)->create(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: fake()->unique()->safeEmail(),
        bonusPercentage: 10,
    );

    resolve(WalletService::class)->initialize($customer->id);

    $deposit = resolve(DepositService::class)->create($customer, $balanceCents);
    resolve(DepositService::class)->approve($deposit->id);

    return $customer;
}

it('creates a pending withdrawal and immediately reserves the funds', function (): void {
    $customer = createFundedWithdrawalCustomer(10_000);

    $withdrawal = withdrawalService()->create($customer, 4_000);

    expect($withdrawal->status)->toBe(WithdrawStatus::Pending)
        ->and($withdrawal->amountCents)->toBe(4_000)
        ->and($withdrawal->preApproved)->toBeFalse()
        ->and($withdrawal->balanceAfterCents)->toBe(6_000)
        ->and($withdrawal->processedAt)->toBeNull();

    $wallet = resolve(WalletService::class)->find($customer->id);
    expect($wallet->balanceCents)->toBe(6_000);
});

it('rejects a withdrawal larger than the real balance', function (): void {
    $customer = createFundedWithdrawalCustomer(10_000);

    expect(fn (): Withdrawal => withdrawalService()->create($customer, 10_001))
        ->toThrow(ValidationException::class);
});

it('finds a withdrawal by id', function (): void {
    $customer = createFundedWithdrawalCustomer(10_000);
    $withdrawal = withdrawalService()->create($customer, 1_000);

    expect(withdrawalService()->find($withdrawal->id)?->id)->toBe($withdrawal->id)
        ->and(withdrawalService()->find(999999))->toBeNull();
});

it('marks every pending withdrawal as in-progress and pre-approved', function (): void {
    $customer = createFundedWithdrawalCustomer(10_000);
    $first = withdrawalService()->create($customer, 1_000);
    $second = withdrawalService()->create($customer, 2_000);

    $ids = withdrawalService()->markPendingAsInProgress();

    expect($ids)->toEqualCanonicalizing([$first->id, $second->id]);

    $reloaded = withdrawalService()->find($first->id);
    expect($reloaded->status)->toBe(WithdrawStatus::InProgress)
        ->and($reloaded->preApproved)->toBeTrue();
});

it('returns an empty list when there are no pending withdrawals', function (): void {
    expect(withdrawalService()->markPendingAsInProgress())->toBe([]);
});

it('approves a pre-approved, in-progress withdrawal', function (): void {
    $customer = createFundedWithdrawalCustomer(10_000);
    $withdrawal = withdrawalService()->create($customer, 1_000);
    withdrawalService()->markPendingAsInProgress();

    $approved = withdrawalService()->approve($withdrawal->id);

    expect($approved->status)->toBe(WithdrawStatus::Approved)
        ->and($approved->processedAt)->not->toBeNull();
});

it('does not approve a withdrawal that was never pre-approved', function (): void {
    $customer = createFundedWithdrawalCustomer(10_000);
    $withdrawal = withdrawalService()->create($customer, 1_000);

    $result = withdrawalService()->approve($withdrawal->id);

    expect($result->status)->toBe(WithdrawStatus::Pending);
});

it('is idempotent: approving an already-approved withdrawal is a no-op', function (): void {
    $customer = createFundedWithdrawalCustomer(10_000);
    $withdrawal = withdrawalService()->create($customer, 1_000);
    withdrawalService()->markPendingAsInProgress();

    $first = withdrawalService()->approve($withdrawal->id);
    $second = withdrawalService()->approve($withdrawal->id);

    expect($second->processedAt?->toDateTimeString())->toBe($first->processedAt?->toDateTimeString());
});

it('throws when approving a withdrawal that does not exist', function (): void {
    expect(fn (): Withdrawal => withdrawalService()->approve(999999))->toThrow(RuntimeException::class);
});

it('paginates a customer withdrawals, newest first', function (): void {
    $customer = createFundedWithdrawalCustomer(10_000);
    withdrawalService()->create($customer, 1_000);
    withdrawalService()->create($customer, 2_000);

    $page = withdrawalService()->paginate($customer->id, page: 1, perPage: 15);

    expect($page->total())->toBe(2)
        ->and($page->items())->toHaveCount(2)
        ->and($page->items()[0]->amountCents)->toBe(2_000)
        ->and($page->items()[1]->amountCents)->toBe(1_000);
});
