<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Enums\PaymentStatus;

final class PaymentTransitionDecision
{
    private function __construct(
        public readonly ?PaymentStatus $current,
        public readonly PaymentStatus $incoming,
        public readonly bool $apply,
        public readonly string $result,
    ) {}

    public static function of(?PaymentStatus $current, PaymentStatus $incoming, bool $apply, string $result): self
    {
        return new self($current, $incoming, $apply, $result);
    }

    public function shouldApply(): bool
    {
        return $this->apply;
    }

    public function isDowngrade(): bool
    {
        return $this->result === PaymentStatusMachine::RESULT_DOWNGRADE;
    }

    public function isUnchanged(): bool
    {
        return $this->result === PaymentStatusMachine::RESULT_UNCHANGED;
    }
}
