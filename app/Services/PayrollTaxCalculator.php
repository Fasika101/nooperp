<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SalaryTaxBracket;

final class PayrollTaxCalculator
{
    /**
     * Progressive marginal tax: for each bracket, tax applies to the portion of gross
     * that falls between from_amount and to_amount (inclusive lower bound).
     *
     * @return array{tax: float, net: float, lines: list<array{from: float, to: float|null, portion: float, rate_percent: float, tax: float}>}
     */
    public static function calculate(float $gross): array
    {
        if ($gross <= 0) {
            return ['tax' => 0.0, 'net' => 0.0, 'lines' => []];
        }

        $brackets = SalaryTaxBracket::query()->active()->ordered()->get();

        $totalTax = 0.0;
        $lines = [];

        foreach ($brackets as $bracket) {
            $from = (float) $bracket->from_amount;
            $to = $bracket->to_amount !== null ? (float) $bracket->to_amount : INF;
            $portion = max(0, min($gross, $to) - $from);
            if ($portion <= 0) {
                continue;
            }

            $rate = (float) $bracket->rate_percent / 100;
            $chunkTax = round($portion * $rate, 4);
            $totalTax += $chunkTax;
            $lines[] = [
                'from' => $from,
                'to' => $bracket->to_amount !== null ? (float) $bracket->to_amount : null,
                'portion' => round($portion, 2),
                'rate_percent' => (float) $bracket->rate_percent,
                'tax' => $chunkTax,
            ];
        }

        $totalTax = round($totalTax, 2);
        $net = round($gross - $totalTax, 2);

        return [
            'tax' => $totalTax,
            'net' => $net,
            'lines' => $lines,
        ];
    }

    /**
     * Short human-readable summary for form hints.
     */
    public static function summaryLine(float $gross): string
    {
        $result = self::calculate($gross);
        if ($gross <= 0) {
            return '';
        }
        if ($result['lines'] === []) {
            return 'No active salary tax brackets — configure them in Settings.';
        }

        $parts = [];
        foreach ($result['lines'] as $line) {
            $parts[] = sprintf(
                '%s @ %s%%',
                number_format($line['portion'], 2),
                rtrim(rtrim(number_format($line['rate_percent'], 2), '0'), '.')
            );
        }

        return 'Bands: '.implode('; ', $parts).sprintf(' — total tax %s', number_format($result['tax'], 2));
    }
}
