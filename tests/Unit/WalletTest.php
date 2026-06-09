<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    protected Wallet $wallet;

    public function setUp(): void
    {
        parent::setUp();
        $user = User::create([
            'name' => 'Test', 'email' => 'test@test.com',
            'password' => 'password', 'role' => 'customer', 'status' => 'active',
        ]);
        $this->wallet = Wallet::create(['user_id' => $user->id, 'balance' => 100000]);
    }

    public function test_credit_increases_balance(): void
    {
        $tx = $this->wallet->credit(50000, 'Test credit');
        $this->assertEquals(150000, (float) $this->wallet->fresh()->balance);
        $this->assertEquals('credit', $tx->type);
        $this->assertEquals(100000, (float) $tx->balance_before);
        $this->assertEquals(150000, (float) $tx->balance_after);
    }

    public function test_debit_decreases_balance(): void
    {
        $tx = $this->wallet->debit(30000, 'Test debit');
        $this->assertEquals(70000, (float) $this->wallet->fresh()->balance);
        $this->assertEquals('debit', $tx->type);
        $this->assertEquals(100000, (float) $tx->balance_before);
        $this->assertEquals(70000, (float) $tx->balance_after);
    }

    public function test_wallet_transaction_is_recorded(): void
    {
        $this->wallet->credit(25000, 'Bonus referral', 'referral', 1);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $this->wallet->id,
            'amount' => 25000,
            'type' => 'credit',
            'reference_type' => 'referral',
        ]);
    }
}
