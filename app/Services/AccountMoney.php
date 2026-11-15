<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class AccountMoney
{
    public function precision(): int
    {
        return max(0, min(6, (int) config('decimal', 2)));
    }

    public function units($value): int
    {
        // Legacy DOUBLE columns are rounded once at the application's currency precision.
        return (int) round((float) $value * (10 ** $this->precision()));
    }

    public function input(string $value): int
    {
        $precision = $this->precision();
        // Compatibility columns in SalePro are DOUBLE. Keep their integer
        // minor-unit representation inside IEEE-754's exact integer range.
        $digits = min(12, 15 - $precision);
        $pattern = $precision ? '/^\d{1,'.$digits.'}(?:\.\d{1,'.$precision.'})?$/' : '/^\d{1,'.$digits.'}$/';
        if (!preg_match($pattern, $value)) {
            throw ValidationException::withMessages(['amount' => __('credit.valid_amount', ['precision' => $precision])]);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $units = (int) $whole * (10 ** $precision) + (int) str_pad($fraction, $precision, '0');
        if ($units <= 0) throw ValidationException::withMessages(['amount' => __('credit.positive_amount')]);
        return $units;
    }

    public function decimal(int $units): string
    {
        $precision = $this->precision();
        $factor = 10 ** $precision;
        return ($units < 0 ? '-' : '').intdiv(abs($units), $factor).
            ($precision ? '.'.str_pad((string) (abs($units) % $factor), $precision, '0', STR_PAD_LEFT) : '');
    }
}
