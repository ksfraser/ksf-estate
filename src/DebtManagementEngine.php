<?php

declare(strict_types=1);

namespace Ksfraser\Estate;

use Ksfraser\ModulesCommon\CalculationEngineInterface;
use Ksfraser\ModulesCommon\CalculationContext;
use Ksfraser\ModulesCommon\CalculationResult;
use Ksfraser\ModulesCommon\ParameterDefinition;
use Ksfraser\ModulesCommon\ValidationResult;
use Ksfraser\ModulesCommon\CalculationException;

/**
 * Debt Management Engine
 *
 * Analyzes estate debts, categorizes secured vs unsecured obligations,
 * computes the debt-to-asset ratio and the liquidity required to settle
 * debts payable on death, and produces prioritized payoff / reduction
 * recommendations.
 */
class DebtManagementEngine implements CalculationEngineInterface
{
    private const HIGH_INTEREST_THRESHOLD = 0.06;
    private const HEALTHY_RATIO = 0.20;
    private const ELEVATED_RATIO = 0.50;

    public function getCalculationType(): string
    {
        return 'debt_management';
    }

    public function calculate(CalculationContext $context): CalculationResult
    {
        $this->validateOrThrow($context);

        $parameters = $context->parameters;
        $assets = $this->sumAssets($parameters['assets'] ?? []);
        $debts = $parameters['debts'] ?? [];

        $totalDebt = $this->sumDebts($debts);
        $categorized = $this->categorizeDebts($debts);
        $ratio = $this->calculateDebtToAssetRatio($assets, $totalDebt);
        $liquidityNeeded = $this->calculateLiquidityNeeded($debts);
        $payoff = $this->analyzeDebtPayoff($debts, $liquidityNeeded);
        $recommendations = $this->generateRecommendations($debts, $ratio, $assets);

        $results = [
            'total_assets' => round($assets, 2),
            'total_debt' => round($totalDebt, 2),
            'debt_free_estate' => round($assets - $totalDebt, 2),
            'debt_to_asset_ratio' => round($ratio, 4),
            'secured_debt' => round($categorized['secured'], 2),
            'unsecured_debt' => round($categorized['unsecured'], 2),
            'liquidity_needed' => round($liquidityNeeded, 2),
            'debt_payoff' => $payoff,
            'recommendations' => $recommendations,
        ];

        return CalculationResult::success(
            'debt_management',
            $results,
            [],
            [],
            ['client_id' => $context->clientId]
        );
    }

    public function validate(CalculationContext $context): ValidationResult
    {
        if ($context->calculationType !== 'debt_management') {
            return new ValidationResult(false, ['Invalid calculation type for Debt Management Engine']);
        }

        $parameters = $context->parameters;
        if (!isset($parameters['assets']) && !isset($parameters['estate_value'])) {
            return new ValidationResult(false, ['Either assets or estate_value parameter is required']);
        }

        return new ValidationResult(true, []);
    }

    public function getRequiredParameters(): array
    {
        return [
            'assets' => new ParameterDefinition('assets', 'array', 'List of estate assets with values', true),
        ];
    }

    public function getOptionalParameters(): array
    {
        return [
            'debts' => new ParameterDefinition('debts', 'array', 'List of debts with balances and rates', false, []),
            'estate_value' => new ParameterDefinition('estate_value', 'float', 'Total estate value', false, 0.0),
        ];
    }

    // --- Public helpers (unit tested directly) ---

    public function sumAssets(array $assets): float
    {
        return (float) array_sum(array_column($assets, 'value'));
    }

    public function sumDebts(array $debts): float
    {
        return (float) array_sum(array_column($debts, 'balance'));
    }

    public function categorizeDebts(array $debts): array
    {
        $secured = 0.0;
        $unsecured = 0.0;

        foreach ($debts as $debt) {
            $balance = (float) ($debt['balance'] ?? 0);
            if ($debt['secured'] ?? false) {
                $secured += $balance;
            } else {
                $unsecured += $balance;
            }
        }

        return ['secured' => $secured, 'unsecured' => $unsecured];
    }

    public function calculateDebtToAssetRatio(float $assets, float $debt): float
    {
        if ($assets <= 0.0) {
            return $debt > 0.0 ? 1.0 : 0.0;
        }

        return min(1.0, $debt / $assets);
    }

    public function calculateLiquidityNeeded(array $debts): float
    {
        $needed = 0.0;

        foreach ($debts as $debt) {
            if (($debt['payable_on_death'] ?? true)) {
                $needed += (float) ($debt['balance'] ?? 0);
            }
        }

        return $needed;
    }

    public function analyzeDebtPayoff(array $debts, float $availableLiquidity): array
    {
        $ranked = $debts;
        usort($ranked, fn($a, $b) => ($b['rate'] ?? 0.0) <=> ($a['rate'] ?? 0.0));

        $remaining = $availableLiquidity;
        $plan = [];

        foreach ($ranked as $debt) {
            $balance = (float) ($debt['balance'] ?? 0);
            $paid = min($remaining, $balance);
            $remaining -= $paid;

            $plan[] = [
                'debt' => $debt['type'] ?? 'unknown',
                'rate' => (float) ($debt['rate'] ?? 0.0),
                'paid' => round($paid, 2),
                'remaining' => round($balance - $paid, 2),
            ];
        }

        $totalDebt = $this->sumDebts($debts);

        return [
            'available_liquidity' => round($availableLiquidity, 2),
            'plan' => $plan,
            'unfunded' => round(max(0.0, $totalDebt - $availableLiquidity), 2),
        ];
    }

    public function generateRecommendations(array $debts, float $ratio, float $assets): array
    {
        $recommendations = [];

        if ($ratio >= self::ELEVATED_RATIO) {
            $recommendations[] = 'consider_debt_reduction';
        } elseif ($ratio <= self::HEALTHY_RATIO) {
            $recommendations[] = 'debt_levels_healthy';
        }

        foreach ($debts as $debt) {
            $rate = (float) ($debt['rate'] ?? 0.0);
            if ($rate >= self::HIGH_INTEREST_THRESHOLD) {
                $recommendations[] = 'prioritize_high_interest_debt:' . ($debt['type'] ?? 'unknown');
            }
        }

        $secured = $this->categorizeDebts($debts)['secured'];
        if ($secured > 0.0 && $assets >= $secured) {
            $recommendations[] = 'consider_mortgage_life_insurance';
        }

        return array_values(array_unique($recommendations));
    }

    private function validateOrThrow(CalculationContext $context): void
    {
        $result = $this->validate($context);
        if (!$result->isValid) {
            throw new CalculationException(
                implode('; ', $result->errors),
                'debt_management'
            );
        }
    }
}
