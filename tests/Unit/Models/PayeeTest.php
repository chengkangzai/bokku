<?php

use App\Models\Category;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;

describe('Payee Model', function () {
    it('can be created with factory', function () {
        $payee = Payee::factory()->create();

        expect($payee)
            ->toBeInstanceOf(Payee::class)
            ->and($payee->name)->toBeString()
            ->and($payee->is_active)->toBeBool();
    });

    it('belongs to user', function () {
        $user = User::factory()->create();
        $payee = Payee::factory()->create(['user_id' => $user->id]);

        expect($payee->user)
            ->toBeInstanceOf(User::class)
            ->and($payee->user->id)->toBe($user->id);
    });

    it('can have a default category', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $payee = Payee::factory()->create([
            'user_id' => $user->id,
            'default_category_id' => $category->id,
        ]);

        expect($payee->defaultCategory)
            ->toBeInstanceOf(Category::class)
            ->and($payee->defaultCategory->id)->toBe($category->id);
    });

    it('can have no default category', function () {
        $payee = Payee::factory()->create([
            'default_category_id' => null,
        ]);

        expect($payee->defaultCategory)->toBeNull();
    });

    it('has many transactions', function () {
        $user = User::factory()->create();
        $payee = Payee::factory()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        Transaction::factory()->count(3)->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'payee_id' => $payee->id,
        ]);

        expect($payee->transactions)
            ->toHaveCount(3);
    });

    it('can be active or inactive', function () {
        $activePayee = Payee::factory()->active()->create();
        $inactivePayee = Payee::factory()->inactive()->create();

        expect($activePayee->is_active)->toBeTrue();
        expect($inactivePayee->is_active)->toBeFalse();
    });

    it('has correct fillable attributes', function () {
        $fillable = (new Payee)->getFillable();

        expect($fillable)->toContain(
            'user_id',
            'name',
            'default_category_id',
            'is_active'
        );
    });

    it('casts attributes correctly', function () {
        $payee = Payee::factory()->create();
        $casts = $payee->getCasts();

        expect($casts)
            ->toHaveKey('is_active', 'boolean');
    });

    it('can be created with default category via factory state', function () {
        $payee = Payee::factory()->withDefaultCategory()->create();

        expect($payee->defaultCategory)
            ->toBeInstanceOf(Category::class)
            ->and($payee->default_category_id)->not->toBeNull();
    });
});
