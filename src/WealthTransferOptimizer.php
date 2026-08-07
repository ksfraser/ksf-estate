<?php

declare(strict_types=1);

namespace Ksfraser\Estate;

use Ksfraser\ModulesCommon\CalculationEngineInterface;
use Ksfraser\ModulesCommon\CalculationContext;
use Ksfraser\ModulesCommon\CalculationResult;
use Ksfraser\ModulesCommon\ParameterDefinition;
use Ksfraser\ModulesCommon\ValidationResult;
use Ksfraser\ModulesCommon\ValidationRuleInterface;
use Ksfraser\Exceptions\Domain\CalculationException;

/**
 * Wealth Transfer Optimizer
 *
 * Optimizes wealth transfer strategies to minimize taxes and maximize beneficiary value.
 * Analyzes various transfer methods including gifts, trusts, insurance, and business interests.
 */
class WealthTransferOptimizer
{
    private EstateTaxCalculator $taxCalculator;

    public function __construct(EstateTaxCalculator $taxCalculator)
    {
        $this->taxCalculator = $taxCalculator;
    }

    /**
     * Optimize wealth transfer strategy
     *
     * @param array $estateData Estate composition and values
     * @param array $beneficiaries Beneficiary information
     * @param array $constraints Transfer constraints and preferences
     * @param int $taxYear Tax year for calculations
     * @return array Optimized transfer strategy
     */
    public function optimizeTransferStrategy(
        array $estateData,
        array $beneficiaries,
        array $constraints,
        int $taxYear
    ): array {
        $strategies = $this->generateTransferStrategies($estateData, $beneficiaries, $constraints, $taxYear);
        $evaluatedStrategies = $this->evaluateStrategies($strategies, $taxYear);

        return $this->selectOptimalStrategy($evaluatedStrategies);
    }

    /**
     * Generate potential transfer strategies
     */
    private function generateTransferStrategies(
        array $estateData,
        array $beneficiaries,
        array $constraints,
        int $taxYear
    ): array {
        $strategies = [];

        // Strategy 1: Annual gift exemption maximization
        $strategies[] = $this->createAnnualGiftStrategy($estateData, $beneficiaries, $taxYear);

        // Strategy 2: Spousal rollover optimization
        $strategies[] = $this->createSpousalRolloverStrategy($estateData, $beneficiaries, $taxYear);

        // Strategy 3: Trust utilization
        $strategies[] = $this->createTrustStrategy($estateData, $beneficiaries, $taxYear);

        // Strategy 4: Life insurance optimization
        $strategies[] = $this->createInsuranceStrategy($estateData, $beneficiaries, $taxYear);

        // Strategy 5: Business interest transfer
        $strategies[] = $this->createBusinessTransferStrategy($estateData, $beneficiaries, $taxYear);

        // Strategy 6: Charitable remainder trust
        $strategies[] = $this->createCharitableStrategy($estateData, $beneficiaries, $taxYear);

        return $strategies;
    }

    /**
     * Create annual gift exemption strategy
     */
    private function createAnnualGiftStrategy(array $estateData, array $beneficiaries, int $taxYear): array
    {
        $annualExemption = $this->getAnnualGiftExemption($taxYear);
        $totalEstate = $estateData['total_value'] ?? 0;

        $strategy = [
            'name' => 'Annual Gift Exemption Maximization',
            'type' => 'gifts',
            'description' => 'Maximize use of annual gift tax exemptions to transfer wealth tax-free',
            'transfers' => [],
            'total_transferred' => 0,
            'tax_savings' => 0,
            'time_horizon' => 'multi-year'
        ];

        $remainingEstate = $totalEstate;
        $year = 0;

        while ($remainingEstate > 0 && $year < 10) { // Limit to 10 years for practicality
            $yearlyTransfer = min($annualExemption, $remainingEstate);
            if ($yearlyTransfer <= 0) break;

            $strategy['transfers'][] = [
                'year' => $year + 1,
                'amount' => $yearlyTransfer,
                'beneficiaries' => $this->distributeToBeneficiaries($yearlyTransfer, $beneficiaries),
                'tax_impact' => 0 // Gifts under exemption are tax-free
            ];

            $remainingEstate -= $yearlyTransfer;
            $strategy['total_transferred'] += $yearlyTransfer;
            $year++;
        }

        // Calculate tax savings (avoided estate tax on transferred amount)
        $avoidedTax = $this->taxCalculator->calculateTotalEstateTax($strategy['total_transferred'], 'ON', $taxYear)['total'];
        $strategy['tax_savings'] = $avoidedTax;

        return $strategy;
    }

    /**
     * Create spousal rollover strategy
     */
    private function createSpousalRolloverStrategy(array $estateData, array $beneficiaries, int $taxYear): array
    {
        $spouseBeneficiaries = array_filter($beneficiaries, fn($b) => ($b['relationship'] ?? '') === 'spouse');

        if (empty($spouseBeneficiaries)) {
            return [
                'name' => 'Spousal Rollover',
                'type' => 'spousal_rollover',
                'description' => 'Not applicable - no spouse beneficiary identified',
                'applicable' => false
            ];
        }

        $totalEstate = $estateData['total_value'] ?? 0;
        $spouse = reset($spouseBeneficiaries);

        return [
            'name' => 'Spousal Rollover Optimization',
            'type' => 'spousal_rollover',
            'description' => 'Transfer assets to surviving spouse with full rollover exemption',
            'applicable' => true,
            'transfers' => [
                [
                    'amount' => $totalEstate,
                    'beneficiary' => $spouse['name'] ?? 'Spouse',
                    'tax_impact' => 0, // Full spousal rollover exemption
                    'notes' => 'Complete tax-deferred transfer to spouse'
                ]
            ],
            'total_transferred' => $totalEstate,
            'tax_savings' => $this->taxCalculator->calculateTotalEstateTax($totalEstate, 'ON', $taxYear)['total'],
            'time_horizon' => 'immediate'
        ];
    }

    /**
     * Create trust-based transfer strategy
     */
    private function createTrustStrategy(array $estateData, array $beneficiaries, int $taxYear): array
    {
        $totalEstate = $estateData['total_value'] ?? 0;
        $trustExemption = 500000; // Lifetime trust exemption (approximate)

        return [
            'name' => 'Trust-Based Wealth Transfer',
            'type' => 'trust',
            'description' => 'Utilize trust structures for tax-efficient wealth transfer',
            'transfers' => [
                [
                    'amount' => min($totalEstate, $trustExemption),
                    'vehicle' => 'Testamentary Trust',
                    'tax_impact' => 0,
                    'notes' => 'Transfer within lifetime exemption'
                ]
            ],
            'total_transferred' => min($totalEstate, $trustExemption),
            'tax_savings' => $this->taxCalculator->calculateTotalEstateTax(min($totalEstate, $trustExemption), 'ON', $taxYear)['total'],
            'time_horizon' => 'estate_distribution',
            'considerations' => [
                'Professional trustee fees',
                'Trust administration complexity',
                'Beneficiary access restrictions'
            ]
        ];
    }

    /**
     * Create insurance-optimized strategy
     */
    private function createInsuranceStrategy(array $estateData, array $beneficiaries, int $taxYear): array
    {
        $existingInsurance = $estateData['life_insurance'] ?? 0;
        $totalEstate = $estateData['total_value'] ?? 0;

        $recommendedInsurance = $this->calculateOptimalInsurance($totalEstate, $existingInsurance, $taxYear);

        return [
            'name' => 'Life Insurance Optimization',
            'type' => 'insurance',
            'description' => 'Use life insurance to provide tax-free liquidity for estate settlement',
            'current_coverage' => $existingInsurance,
            'recommended_additional' => max(0, $recommendedInsurance - $existingInsurance),
            'total_optimized_coverage' => $recommendedInsurance,
            'tax_savings' => $this->calculateInsuranceTaxSavings($recommendedInsurance, $taxYear),
            'time_horizon' => 'policy_maturity',
            'considerations' => [
                'Insurance premium costs',
                'Policy cash value accumulation',
                'Beneficiary designation importance'
            ]
        ];
    }

    /**
     * Create business interest transfer strategy
     */
    private function createBusinessTransferStrategy(array $estateData, array $beneficiaries, int $taxYear): array
    {
        $businessInterests = $estateData['business_interests'] ?? 0;

        if ($businessInterests <= 0) {
            return [
                'name' => 'Business Interest Transfer',
                'type' => 'business',
                'description' => 'Not applicable - no business interests identified',
                'applicable' => false
            ];
        }

        return [
            'name' => 'Business Succession Planning',
            'type' => 'business',
            'description' => 'Transfer business interests using succession planning techniques',
            'applicable' => true,
            'business_value' => $businessInterests,
            'transfer_options' => [
                'gradual_transfer' => 'Transfer shares gradually to minimize tax impact',
                'trust_holding' => 'Hold business in trust for beneficiaries',
                'management_buyout' => 'Structured management buyout agreement'
            ],
            'tax_savings' => $this->calculateBusinessTransferTaxSavings($businessInterests, $taxYear),
            'time_horizon' => 'multi-year',
            'considerations' => [
                'Business valuation requirements',
                'Key person insurance needs',
                'Succession planning complexity'
            ]
        ];
    }

    /**
     * Create charitable transfer strategy
     */
    private function createCharitableStrategy(array $estateData, array $beneficiaries, int $taxYear): array
    {
        $totalEstate = $estateData['total_value'] ?? 0;
        $charitablePortion = $totalEstate * 0.1; // Assume 10% charitable intent

        return [
            'name' => 'Charitable Remainder Strategy',
            'type' => 'charitable',
            'description' => 'Utilize charitable vehicles for tax-efficient wealth transfer',
            'charitable_amount' => $charitablePortion,
            'tax_savings' => $this->calculateCharitableTaxSavings($charitablePortion, $taxYear),
            'time_horizon' => 'estate_distribution',
            'considerations' => [
                'Charitable tax receipts',
                'Income stream to beneficiaries',
                'Philanthropic goals alignment'
            ]
        ];
    }

    /**
     * Evaluate and score transfer strategies
     */
    private function evaluateStrategies(array $strategies, int $taxYear): array
    {
        $evaluated = [];

        foreach ($strategies as $strategy) {
            if (isset($strategy['applicable']) && $strategy['applicable'] === false) {
                $strategy['score'] = 0;
                $strategy['recommendation'] = 'Not Applicable';
            } else {
                $score = $this->calculateStrategyScore($strategy, $taxYear);
                $strategy['score'] = $score;
                $strategy['recommendation'] = $this->getRecommendation($score);
            }

            $evaluated[] = $strategy;
        }

        return $evaluated;
    }

    /**
     * Calculate strategy score based on multiple factors
     */
    private function calculateStrategyScore(array $strategy, int $taxYear): float
    {
        $score = 0;

        // Tax savings (40% weight)
        $taxSavings = $strategy['tax_savings'] ?? 0;
        $score += min(40, ($taxSavings / 100000) * 40); // Normalize to $100K tax savings = 40 points

        // Implementation complexity (20% weight) - simpler is better
        $complexityPenalty = match($strategy['type']) {
            'gifts' => 5,      // Simple annual gifts
            'spousal_rollover' => 2, // Very simple
            'insurance' => 8,  // Moderate complexity
            'trust' => 15,     // Complex
            'business' => 18,  // Very complex
            'charitable' => 12, // Moderately complex
            default => 10
        };
        $score += (20 - $complexityPenalty);

        // Time horizon benefit (20% weight) - longer time horizons allow more planning
        $timeBonus = match($strategy['time_horizon'] ?? 'immediate') {
            'immediate' => 5,
            'estate_distribution' => 10,
            'multi-year' => 20,
            'policy_maturity' => 15,
            default => 10
        };
        $score += $timeBonus;

        // Beneficiary impact (20% weight)
        $beneficiaryCount = count($strategy['beneficiaries'] ?? []);
        $score += min(20, $beneficiaryCount * 4); // Up to 20 points for 5+ beneficiaries

        return max(0, min(100, $score));
    }

    /**
     * Get recommendation based on score
     */
    private function getRecommendation(float $score): string
    {
        return match(true) {
            $score >= 80 => 'Highly Recommended',
            $score >= 60 => 'Recommended',
            $score >= 40 => 'Consider',
            $score >= 20 => 'Limited Benefit',
            default => 'Not Recommended'
        };
    }

    /**
     * Select the optimal strategy
     */
    private function selectOptimalStrategy(array $evaluatedStrategies): array
    {
        // Sort by score descending
        usort($evaluatedStrategies, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        $optimal = $evaluatedStrategies[0] ?? [];

        // Add comparison data
        $optimal['comparison'] = array_map(function($strategy) {
            return [
                'name' => $strategy['name'],
                'score' => $strategy['score'],
                'tax_savings' => $strategy['tax_savings'] ?? 0,
                'recommendation' => $strategy['recommendation']
            ];
        }, array_slice($evaluatedStrategies, 1, 3)); // Top 4 strategies

        return $optimal;
    }

    // Helper methods
    private function getAnnualGiftExemption(int $taxYear): float
    {
        return match($taxYear) {
            2025 => 17156,
            2024 => 16476,
            2023 => 15576,
            2022 => 15380,
            2021 => 15380,
            default => 17156
        };
    }

    private function distributeToBeneficiaries(float $amount, array $beneficiaries): array
    {
        if (empty($beneficiaries)) return [];

        $distribution = [];
        $perBeneficiary = $amount / count($beneficiaries);

        foreach ($beneficiaries as $beneficiary) {
            $distribution[] = [
                'name' => $beneficiary['name'] ?? 'Unknown',
                'amount' => $perBeneficiary,
                'relationship' => $beneficiary['relationship'] ?? 'unknown'
            ];
        }

        return $distribution;
    }

    private function calculateOptimalInsurance(float $estateValue, float $existingInsurance, int $taxYear): float
    {
        $estimatedTax = $this->taxCalculator->calculateTotalEstateTax($estateValue, 'ON', $taxYear)['total'];
        $liquidityNeed = $estimatedTax * 1.3; // 30% buffer for other expenses

        return max(0, $liquidityNeed - $existingInsurance);
    }

    private function calculateInsuranceTaxSavings(float $insuranceAmount, int $taxYear): float
    {
        // Life insurance proceeds are generally tax-free to beneficiaries
        // This represents avoided estate tax on the insurance amount
        return $this->taxCalculator->calculateTotalEstateTax($insuranceAmount, 'ON', $taxYear)['total'];
    }

    private function calculateBusinessTransferTaxSavings(float $businessValue, int $taxYear): float
    {
        // Business transfers may qualify for special tax treatment
        // This is a simplified calculation
        return $this->taxCalculator->calculateTotalEstateTax($businessValue, 'ON', $taxYear)['total'] * 0.5;
    }

    private function calculateCharitableTaxSavings(float $charitableAmount, int $taxYear): float
    {
        // Charitable donations can reduce taxable estate
        return $this->taxCalculator->calculateTotalEstateTax($charitableAmount, 'ON', $taxYear)['total'];
    }
}