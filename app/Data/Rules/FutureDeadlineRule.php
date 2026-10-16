<?php

namespace App\Data\Rules;

use App\Models\Order;
use App\Support\DisplayTime;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class FutureDeadlineRule implements ValidationRule
{
    private const string MESSAGE = 'The need-by date must be in the future (:timezone time).';

    public function __construct(private readonly ?Order $editedOrder = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $deadline = DisplayTime::toUtc((string) $value);

        if ($deadline->isAfter(CarbonImmutable::now())) {
            return;
        }

        if ($this->editedOrder?->need_by_at->equalTo($deadline)) {
            return;
        }

        $fail(str_replace(':timezone', DisplayTime::timezone(), self::MESSAGE));
    }
}
