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

use DateTimeImmutable;

/**
 * Estate Planning Engine
 *
 * Provides comprehensive estate planning calculations including:
 * - Will and estate analysis
 * - Estate tax calculations (federal and provincial)
 * - Beneficiary designations and trust analysis
 * - Inheritance projections with growth and inflation
 * - Probate cost analysis by province
 * - Estate equalization strategies
 * - Complex estate analysis with multiple asset types
 * - Estate planning recommendations and risk assessment
 *
 * @implements CalculationEngineInterface
 */
class EstatePlanningEngine implements CalculationEngineInterface
{
    private EstateTaxCalculator $taxCalculator;
    private WealthTransferOptimizer $transferOptimizer;
    private BeneficiaryAnalysisEngine $beneficiaryAnalyzer;
    private ProbateFeeLookup $probateFeeLookup;

    public function __construct(
        \PDO $pdo,
        WealthTransferOptimizer $transferOptimizer,
        BeneficiaryAnalysisEngine $beneficiaryAnalyzer
    ) {
        $this->taxCalculator = new EstateTaxCalculator($pdo);
        $this->probateFeeLookup = new ProbateFeeLookup($pdo);
        $this->transferOptimizer = $transferOptimizer;
        $this->beneficiaryAnalyzer = $beneficiaryAnalyzer;
    }

    private const PROVINCE_PROBATE_FEES = [
        'ON' => ['base' => 0.005, 'max' => 0.015], // 0.5% to 1.5%
        'QC' => ['base' => 0.003, 'max' => 0.010], // 0.3% to 1.0%
        'BC' => ['base' => 0.004, 'max' => 0.012], // 0.4% to 1.2%
        'AB' => ['base' => 0.003, 'max' => 0.008], // 0.3% to 0.8%
        'MB' => ['base' => 0.005, 'max' => 0.013], // 0.5% to 1.3%
        'SK' => ['base' => 0.004, 'max' => 0.011], // 0.4% to 1.1%
        'NS' => ['base' => 0.004, 'max' => 0.012], // 0.4% to 1.2%
        'NB' => ['base' => 0.004, 'max' => 0.010], // 0.4% to 1.0%
        'NL' => ['base' => 0.004, 'max' => 0.011], // 0.4% to 1.1%
        'PE' => ['base' => 0.005, 'max' => 0.013], // 0.5% to 1.3%
    ];

    private const FEDERAL_ESTATE_TAX_RATES = [
        2025 => ['threshold' => 15000000, 'rate' => 0.20], // 20% over $15M
        2024 => ['threshold' => 14000000, 'rate' => 0.20],
        2023 => ['threshold' => 13000000, 'rate' => 0.20],
    ];

    /**
     * Perform estate planning calculation
     *
     * @param CalculationContext $context The calculation context
     * @return CalculationResult The calculation result
     * @throws CalculationException If calculation fails
     */
    public function calculate(CalculationContext $context): CalculationResult
    {
        try {
            $this->validateContext($context);

            $results = [];
            $advisorNotifications = [];
            $parameters = $context->parameters;

            // Calculate basic estate value
            $estateValue = $this->calculateEstateValue($parameters);
            $results['total_estate_value'] = $estateValue;
            $results['net_estate_value'] = $estateValue;

            // Calculate probate fees
            $probateFees = $this->calculateProbateFees($estateValue, $parameters['province'] ?? 'ON');
            $results['probate_fees'] = $probateFees;

            // Collect advisor notifications from probate fee calculation
            if (!empty($probateFees['advisor_notifications'])) {
                $advisorNotifications = array_merge($advisorNotifications, $probateFees['advisor_notifications']);
            }

            // Calculate estate taxes
            $estateTaxes = $this->calculateEstateTaxes($estateValue, $parameters);
            $results['estate_taxes'] = $estateTaxes['total'];
            $results['federal_estate_tax'] = $estateTaxes['federal'];
            $results['provincial_estate_tax'] = $estateTaxes['provincial'];

            // Collect advisor notifications from estate tax calculation
            if (!empty($estateTaxes['notifications'])) {
                $advisorNotifications = array_merge($advisorNotifications, $estateTaxes['notifications']);
            }

            // Handle beneficiaries and distributions
            if (isset($parameters['beneficiaries'])) {
                $distributions = $this->calculateBeneficiaryDistributions($estateValue, $parameters);
                $results['beneficiary_distributions'] = $distributions['distributions'];
                $results['net_distributions'] = $distributions['net_distributions'];
            }

            // Handle intestate succession
            if (($parameters['has_will'] ?? true) === false) {
                $intestateDistribution = $this->calculateIntestateDistribution($estateValue, $parameters);
                $results['intestate_distribution'] = $intestateDistribution;
                $results['legal_fees_estimate'] = $this->estimateLegalFees($estateValue, 'intestate');
            }

            // Calculate inheritance projections if requested
            if (isset($parameters['projection_years'])) {
                $projections = $this->calculateInheritanceProjections($estateValue, $parameters);
                $results['inheritance_projections'] = $projections['projections'];
                $results['generation_analysis'] = $projections['analysis'];
            }

            // Analyze life insurance impact
            if (isset($parameters['life_insurance'])) {
                $results['life_insurance_impact'] = $this->analyzeLifeInsuranceImpact($parameters['life_insurance']);
            }

            // Analyze trusts
            if ($this->hasTrusts($parameters)) {
                $results['trust_analysis'] = $this->analyzeTrusts($parameters);
            }

            // Estate equalization if requested
            if (isset($parameters['equalization_strategy']) && $parameters['equalization_strategy']) {
                $equalization = $this->calculateEstateEqualization($parameters);
                $results['equalization_adjustments'] = $equalization['adjustments'];
                $results['adjusted_distributions'] = $equalization['adjusted_distributions'];
                $results['equalization_analysis'] = $equalization['analysis'];
            }

            // Complex estate analysis
            if (isset($parameters['assets'])) {
                $assetAnalysis = $this->analyzeComplexEstate($parameters);
                $results = array_merge($results, $assetAnalysis);
            }

            // Generate recommendations
            $results['recommendations'] = $this->generateRecommendations($parameters);
            $results['risk_assessment'] = $this->assessEstateRisks($parameters);
            $results['action_items'] = $this->generateActionItems($parameters);

            // Add advisor notifications if any
            if (!empty($advisorNotifications)) {
                $results['advisor_notifications'] = $advisorNotifications;
            }

            return CalculationResult::success(
                'estate_planning',
                $results,
                [], // intermediate results
                [], // assumptions used
                [
                    'client_id' => $context->clientId,
                    'advisor_id' => $context->advisorId,
                    'effective_date' => $context->effectiveDate?->format('c')
                ]
            );

        } catch (\Exception $e) {
            throw new CalculationException(
                'Estate planning calculation failed: ' . $e->getMessage(),
                'estate_planning',
                [],
                $e
            );
        }
    }

    /**
     * Validate calculation context
     */
    private function validateContext(CalculationContext $context): void
    {
        if ($context->calculationType !== 'estate_planning') {
            throw new CalculationException('Invalid calculation type for Estate Planning Engine', 'estate_planning');
        }

        $parameters = $context->parameters;

        // Check required parameters
        if (!isset($parameters['estate_value']) && !isset($parameters['assets'])) {
            throw new CalculationException('Either estate_value or assets parameter is required', 'estate_planning');
        }

        if (isset($parameters['estate_value']) && $parameters['estate_value'] < 0) {
            throw new CalculationException('Estate value cannot be negative', 'estate_planning');
        }

        if (isset($parameters['province']) && !isset(self::PROVINCE_PROBATE_FEES[$parameters['province']])) {
            throw new CalculationException('Invalid province code', 'estate_planning');
        }

        // Validate beneficiaries
        if (isset($parameters['beneficiaries'])) {
            $this->validateBeneficiaries($parameters['beneficiaries']);
        }
    }

    /**
     * Validate beneficiary configurations
     */
    private function validateBeneficiaries(array $beneficiaries): void
    {
        $totalPercentage = 0;

        foreach ($beneficiaries as $beneficiary) {
            if (!isset($beneficiary['percentage']) || !is_numeric($beneficiary['percentage'])) {
                throw new CalculationException('Beneficiary percentage must be numeric', 'estate_planning');
            }

            $totalPercentage += $beneficiary['percentage'];

            if ($beneficiary['percentage'] < 0 || $beneficiary['percentage'] > 100) {
                throw new CalculationException('Beneficiary percentage must be between 0 and 100', 'estate_planning');
            }
        }

        if (abs($totalPercentage - 100.0) > 0.01) { // Allow small rounding differences
            throw new CalculationException('Beneficiary percentages must total 100%', 'estate_planning');
        }
    }

    /**
     * Calculate estate value from parameters
     */
    private function calculateEstateValue(array $parameters): float
    {
        if (isset($parameters['estate_value'])) {
            return (float) $parameters['estate_value'];
        }

        if (isset($parameters['assets'])) {
            $totalAssets = array_sum(array_column($parameters['assets'], 'value'));
            $totalLiabilities = isset($parameters['liabilities'])
                ? array_sum(array_column($parameters['liabilities'], 'value'))
                : 0;

            return $totalAssets - $totalLiabilities;
        }

        return 0.0;
    }

    /**
     * Calculate probate fees by province
     */
    private function calculateProbateFees(float $estateValue, string $province): array
    {
        $taxYear = (int) date('Y'); // Use current year

        try {
            $result = $this->probateFeeLookup->calculateProbateFees($estateValue, $province, $taxYear);

            // Add advisor notification flag to results if fallback year was used
            $advisorNotifications = [];
            if ($result['advisor_notification_required']) {
                $advisorNotifications[] = [
                    'type' => 'probate_fee_data_outdated',
                    'message' => "Probate fee calculation for {$province} used {$result['fallback_year_used']} data instead of {$taxYear}. Please update probate fee rates.",
                    'priority' => 'medium',
                    'category' => 'data_maintenance'
                ];
            }

            return [
                'fee_amount' => $result['fee_amount'],
                'percentage' => $result['fee_percentage'],
                'province' => $province,
                'tax_year_used' => $result['tax_year'],
                'used_fallback_year' => $result['used_fallback_year'],
                'advisor_notifications' => $advisorNotifications
            ];

        } catch (CalculationException $e) {
            // Fallback to hardcoded values if database lookup fails
            $provinceFees = self::PROVINCE_PROBATE_FEES[$province] ?? self::PROVINCE_PROBATE_FEES['ON'];

            $baseFee = $estateValue * $provinceFees['base'];
            $maxFee = $estateValue * $provinceFees['max'];
            $fee = min($maxFee, max($baseFee, $estateValue * 0.005));

            return [
                'fee_amount' => round($fee, 2),
                'percentage' => round(($fee / $estateValue) * 100, 3),
                'province' => $province,
                'tax_year_used' => $taxYear,
                'used_fallback_year' => false,
                'advisor_notifications' => [
                    [
                        'type' => 'probate_fee_calculation_error',
                        'message' => 'Database probate fee lookup failed, using fallback calculation. Please check probate fee configuration.',
                        'priority' => 'high',
                        'category' => 'system_error'
                    ]
                ]
            ];
        }
    }

    /**
     * Calculate estate taxes (federal and provincial)
     */
    private function calculateEstateTaxes(float $estateValue, array $parameters): array
    {
        $taxYear = $parameters['tax_year'] ?? 2025;
        $province = $parameters['province'] ?? 'ON';

        $taxResult = $this->taxCalculator->calculateTotalEstateTax($estateValue, $province, $taxYear);

        // Return the tax amounts for backward compatibility, notifications are handled separately
        return [
            'federal' => $taxResult['federal'],
            'provincial' => $taxResult['provincial'],
            'total' => $taxResult['total'],
            'notifications' => $taxResult['notifications']
        ];
    }

    /**
     * Calculate federal estate tax
     */
    private function calculateFederalEstateTax(float $estateValue, int $taxYear): float
    {
        $taxRates = self::FEDERAL_ESTATE_TAX_RATES[$taxYear] ?? self::FEDERAL_ESTATE_TAX_RATES[2025];

        if ($estateValue <= $taxRates['threshold']) {
            return 0.0;
        }

        $taxableAmount = $estateValue - $taxRates['threshold'];
        return round($taxableAmount * $taxRates['rate'], 2);
    }

    /**
     * Calculate provincial estate tax (simplified - varies by province)
     */
    private function calculateProvincialEstateTax(float $estateValue, string $province, int $taxYear): float
    {
        // Simplified provincial tax calculation - in reality this varies significantly
        $provincialRates = [
            'ON' => 0.0, // Ontario has no provincial estate tax
            'QC' => 0.0, // Quebec has no provincial estate tax
            'BC' => max(0, ($estateValue - 200000) * 0.05), // BC has inheritance tax
            'AB' => 0.0,
            'MB' => 0.0,
            'SK' => 0.0,
            'NS' => 0.0,
            'NB' => 0.0,
            'NL' => 0.0,
            'PE' => 0.0,
        ];

        return round($provincialRates[$province] ?? 0.0, 2);
    }

    /**
     * Calculate beneficiary distributions
     */
    private function calculateBeneficiaryDistributions(float $estateValue, array $parameters): array
    {
        $beneficiaries = $parameters['beneficiaries'];
        $distributions = [];
        $netDistributions = [];

        foreach ($beneficiaries as $beneficiary) {
            $grossAmount = $estateValue * ($beneficiary['percentage'] / 100);
            $distributions[] = [
                'beneficiary' => $beneficiary['name'],
                'relationship' => $beneficiary['relationship'],
                'percentage' => $beneficiary['percentage'],
                'gross_amount' => round($grossAmount, 2)
            ];

            // Simple tax calculation for beneficiaries (simplified)
            $taxRate = $this->getBeneficiaryTaxRate($beneficiary['relationship']);
            $taxAmount = $grossAmount * $taxRate;
            $netAmount = $grossAmount - $taxAmount;

            $netDistributions[] = [
                'beneficiary' => $beneficiary['name'],
                'net_amount' => round($netAmount, 2),
                'estimated_tax' => round($taxAmount, 2)
            ];
        }

        return [
            'distributions' => $distributions,
            'net_distributions' => $netDistributions
        ];
    }

    /**
     * Get tax rate for beneficiary type (simplified)
     */
    private function getBeneficiaryTaxRate(string $relationship): float
    {
        return match ($relationship) {
            'spouse' => 0.0, // Spousal rollover
            'child', 'children' => 0.0, // No tax for children
            'trust' => 0.15, // Trust tax rate
            default => 0.25, // Default capital gains rate
        };
    }

    /**
     * Calculate intestate distribution
     */
    private function calculateIntestateDistribution(float $estateValue, array $parameters): array
    {
        $hasSpouse = $parameters['surviving_spouse'] ?? false;
        $childrenCount = $parameters['children_count'] ?? 0;

        if ($hasSpouse && $childrenCount === 0) {
            return ['spouse' => $estateValue];
        }

        if ($hasSpouse && $childrenCount > 0) {
            return [
                'spouse' => $estateValue * 0.5,
                'children' => $estateValue * 0.5 / $childrenCount
            ];
        }

        if (!$hasSpouse && $childrenCount > 0) {
            return ['children' => $estateValue / $childrenCount];
        }

        return ['estate' => $estateValue]; // No spouse or children
    }

    /**
     * Estimate legal fees
     */
    private function estimateLegalFees(float $estateValue, string $type): float
    {
        $baseRate = match ($type) {
            'intestate' => 0.02, // 2% for intestate
            'simple_will' => 0.005, // 0.5% for simple will
            'complex_will' => 0.015, // 1.5% for complex will
            default => 0.01
        };

        return round($estateValue * $baseRate, 2);
    }

    /**
     * Calculate inheritance projections
     */
    private function calculateInheritanceProjections(float $estateValue, array $parameters): array
    {
        $years = $parameters['projection_years'] ?? 20;
        $growthRate = $parameters['growth_rate'] ?? 0.04;
        $inflationRate = $parameters['inflation_rate'] ?? 0.02;

        $projections = [];
        $currentValue = $estateValue;

        for ($year = 1; $year <= $years; $year++) {
            $currentValue *= (1 + $growthRate);
            $inflationAdjustedValue = $currentValue / pow(1 + $inflationRate, $year);

            $projections[] = [
                'year' => $year,
                'nominal_value' => round($currentValue, 2),
                'inflation_adjusted_value' => round($inflationAdjustedValue, 2),
                'growth_factor' => round(pow(1 + $growthRate, $year), 4)
            ];
        }

        return [
            'projections' => $projections,
            'analysis' => [
                'total_growth' => round($currentValue / $estateValue, 2),
                'average_annual_return' => $growthRate,
                'inflation_impact' => round(pow(1 + $inflationRate, $years), 2)
            ]
        ];
    }

    /**
     * Analyze life insurance impact
     */
    private function analyzeLifeInsuranceImpact(array $lifeInsurance): array
    {
        $totalCoverage = 0;
        $analysis = [];

        foreach ($lifeInsurance as $policy) {
            $totalCoverage += $policy['policy_value'];
            $analysis[] = [
                'policy_value' => $policy['policy_value'],
                'beneficiary' => $policy['beneficiary'],
                'tax_implications' => $this->analyzeLifeInsuranceTax($policy)
            ];
        }

        return [
            'total_coverage' => $totalCoverage,
            'policies' => $analysis,
            'estate_impact' => 'Life insurance proceeds are generally tax-free to beneficiaries'
        ];
    }

    /**
     * Analyze life insurance tax implications
     */
    private function analyzeLifeInsuranceTax(array $policy): string
    {
        // Simplified analysis
        if (($policy['beneficiary'] ?? '') === 'estate') {
            return 'Proceeds payable to estate may be included in estate value for tax purposes';
        }

        return 'Generally tax-free to named beneficiaries';
    }

    /**
     * Check if estate has trusts
     */
    private function hasTrusts(array $parameters): bool
    {
        if (!isset($parameters['beneficiaries'])) {
            return false;
        }

        foreach ($parameters['beneficiaries'] as $beneficiary) {
            if (($beneficiary['relationship'] ?? '') === 'trust') {
                return true;
            }
        }

        return false;
    }

    /**
     * Analyze trusts in estate
     */
    private function analyzeTrusts(array $parameters): array
    {
        $trusts = [];

        foreach ($parameters['beneficiaries'] as $beneficiary) {
            if (($beneficiary['relationship'] ?? '') === 'trust') {
                $trusts[] = [
                    'name' => $beneficiary['name'],
                    'type' => $beneficiary['trust_type'] ?? 'unknown',
                    'percentage' => $beneficiary['percentage'],
                    'tax_advantages' => $this->analyzeTrustTaxAdvantages($beneficiary)
                ];
            }
        }

        return [
            'trusts' => $trusts,
            'total_trust_percentage' => array_sum(array_column($trusts, 'percentage')),
            'recommendations' => [
                'Ensure trust is properly funded',
                'Review trustee appointments',
                'Consider trust protector provisions'
            ]
        ];
    }

    /**
     * Analyze trust tax advantages
     */
    private function analyzeTrustTaxAdvantages(array $beneficiary): array
    {
        $advantages = [];

        if (($beneficiary['trust_type'] ?? '') === 'testamentary') {
            $advantages[] = 'Potential to split income among beneficiaries';
            $advantages[] = 'Creditor protection for trust assets';
        }

        return $advantages;
    }

    /**
     * Calculate estate equalization
     */
    private function calculateEstateEqualization(array $parameters): array
    {
        $beneficiaries = $parameters['beneficiaries'];
        $adjustments = [];
        $adjustedDistributions = [];

        // Calculate prior inheritances
        $totalPrior = array_sum(array_column($beneficiaries, 'prior_inheritance'));

        foreach ($beneficiaries as $beneficiary) {
            $prior = $beneficiary['prior_inheritance'] ?? 0;
            $targetPercentage = $beneficiary['percentage'] / 100;
            $currentTotal = $prior + ($parameters['estate_value'] * $targetPercentage);

            $adjustments[] = [
                'beneficiary' => $beneficiary['name'],
                'prior_inheritance' => $prior,
                'current_distribution' => $parameters['estate_value'] * $targetPercentage,
                'total_received' => $currentTotal
            ];

            $adjustedDistributions[] = [
                'beneficiary' => $beneficiary['name'],
                'adjusted_amount' => $currentTotal
            ];
        }

        return [
            'adjustments' => $adjustments,
            'adjusted_distributions' => $adjustedDistributions,
            'analysis' => [
                'equalization_needed' => $this->isEqualizationNeeded($adjustments),
                'recommendations' => $this->generateEqualizationRecommendations($adjustments)
            ]
        ];
    }

    /**
     * Check if equalization is needed
     */
    private function isEqualizationNeeded(array $adjustments): bool
    {
        $totals = array_column($adjustments, 'total_received');
        $max = max($totals);
        $min = min($totals);

        return ($max - $min) / $max > 0.1; // More than 10% difference
    }

    /**
     * Generate equalization recommendations
     */
    private function generateEqualizationRecommendations(array $adjustments): array
    {
        $recommendations = [];

        if ($this->isEqualizationNeeded($adjustments)) {
            $recommendations[] = 'Consider adjusting beneficiary percentages';
            $recommendations[] = 'Review prior lifetime gifts and loans';
            $recommendations[] = 'Consider life insurance to balance inheritances';
        }

        return $recommendations;
    }

    /**
     * Analyze complex estate with multiple assets
     */
    private function analyzeComplexEstate(array $parameters): array
    {
        $assets = $parameters['assets'];
        $liabilities = $parameters['liabilities'] ?? [];

        $assetBreakdown = [];
        $totalAssets = 0;
        $totalLiabilities = 0;

        foreach ($assets as $asset) {
            $totalAssets += $asset['value'];
            $assetBreakdown[] = [
                'type' => $asset['type'],
                'value' => $asset['value'],
                'location' => $asset['location'] ?? 'unknown',
                'tax_implications' => $this->analyzeAssetTax($asset)
            ];
        }

        foreach ($liabilities as $liability) {
            $totalLiabilities += $liability['value'];
        }

        $netValue = $totalAssets - $totalLiabilities;

        return [
            'asset_analysis' => $assetBreakdown,
            'liability_analysis' => $liabilities,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'net_estate_value' => $netValue,
            'asset_type_breakdown' => $this->groupAssetsByType($assets),
            'complexity_factors' => $this->assessEstateComplexity($parameters)
        ];
    }

    /**
     * Analyze asset tax implications
     */
    private function analyzeAssetTax(array $asset): string
    {
        return match ($asset['type']) {
            'real_estate' => 'Potential capital gains tax on disposition',
            'investments' => 'Capital gains tax on appreciated assets',
            'business' => 'Complex valuation and potential tax deferral options',
            'personal_property' => 'Generally no tax on transfer to beneficiaries',
            default => 'Tax implications vary by asset type'
        };
    }

    /**
     * Group assets by type
     */
    private function groupAssetsByType(array $assets): array
    {
        $grouped = [];

        foreach ($assets as $asset) {
            $type = $asset['type'];
            if (!isset($grouped[$type])) {
                $grouped[$type] = 0;
            }
            $grouped[$type] += $asset['value'];
        }

        return $grouped;
    }

    /**
     * Assess estate complexity
     */
    private function assessEstateComplexity(array $parameters): array
    {
        $factors = [];

        if (count($parameters['assets'] ?? []) > 5) {
            $factors[] = 'multiple_asset_types';
        }

        if (isset($parameters['international_assets']) && $parameters['international_assets']) {
            $factors[] = 'international_complexity';
        }

        if (isset($parameters['business_interests']) && $parameters['business_interests']) {
            $factors[] = 'business_succession';
        }

        return $factors;
    }

    /**
     * Generate estate planning recommendations
     */
    private function generateRecommendations(array $parameters): array
    {
        $recommendations = [];

        if (($parameters['has_will'] ?? true) === false) {
            $recommendations[] = 'will_preparation';
            $recommendations[] = 'power_of_attorney';
        }

        if (($parameters['estate_value'] ?? 0) > 1000000) {
            $recommendations[] = 'estate_tax_planning';
        }

        if (isset($parameters['complexity_factors'])) {
            if (in_array('business_interests', $parameters['complexity_factors'])) {
                $recommendations[] = 'business_succession_planning';
            }
            if (in_array('international_assets', $parameters['complexity_factors'])) {
                $recommendations[] = 'international_estate_planning';
            }
        }

        $recommendations[] = 'regular_will_reviews';
        $recommendations[] = 'professional_estate_planning_advice';

        return $recommendations;
    }

    /**
     * Assess estate planning risks
     */
    private function assessEstateRisks(array $parameters): array
    {
        $risks = [];

        if (($parameters['has_will'] ?? true) === false) {
            $risks[] = 'intestate_succession';
        }

        if (($parameters['estate_value'] ?? 0) > 5000000) {
            $risks[] = 'estate_tax_exposure';
        }

        if (isset($parameters['beneficiaries']) && count($parameters['beneficiaries']) > 5) {
            $risks[] = 'complex_beneficiary_structure';
        }

        return [
            'identified_risks' => $risks,
            'risk_level' => $this->calculateRiskLevel($risks),
            'mitigation_strategies' => $this->generateRiskMitigationStrategies($risks)
        ];
    }

    /**
     * Calculate risk level
     */
    private function calculateRiskLevel(array $risks): string
    {
        $riskCount = count($risks);

        return match (true) {
            $riskCount === 0 => 'low',
            $riskCount <= 2 => 'medium',
            default => 'high'
        };
    }

    /**
     * Generate risk mitigation strategies
     */
    private function generateRiskMitigationStrategies(array $risks): array
    {
        $strategies = [];

        foreach ($risks as $risk) {
            $strategies = array_merge($strategies, match ($risk) {
                'intestate_succession' => ['Prepare a valid will', 'Establish testamentary trusts'],
                'estate_tax_exposure' => ['Implement tax planning strategies', 'Consider life insurance'],
                'complex_beneficiary_structure' => ['Use testamentary trusts', 'Consider equalization provisions'],
                default => ['Consult with estate planning professional']
            });
        }

        return array_unique($strategies);
    }

    /**
     * Generate action items
     */
    private function generateActionItems(array $parameters): array
    {
        $actions = [];

        if (($parameters['has_will'] ?? true) === false) {
            $actions[] = 'will_preparation';
            $actions[] = 'executor_appointment';
        }

        if (isset($parameters['beneficiaries'])) {
            $actions[] = 'beneficiary_designations_review';
        }

        if (($parameters['estate_value'] ?? 0) > 1000000) {
            $actions[] = 'tax_planning_review';
        }

        $actions[] = 'asset_inventory_update';
        $actions[] = 'annual_estate_plan_review';

        return $actions;
    }

    /**
     * Get calculation type identifier
     */
    public function getCalculationType(): string
    {
        return 'estate_planning';
    }

    /**
     * Get required parameters
     */
    public function getRequiredParameters(): array
    {
        return [
            'estate_value' => new ParameterDefinition(
                'estate_value',
                'float',
                'Total value of the estate',
                true,
                0,
                null
            ),
            'province' => new ParameterDefinition(
                'province',
                'string',
                'Province code (ON, QC, BC, etc.)',
                true,
                null,
                null
            )
        ];
    }

    /**
     * Get optional parameters
     */
    public function getOptionalParameters(): array
    {
        return [
            'has_will' => new ParameterDefinition(
                'has_will',
                'boolean',
                'Whether the person has a valid will',
                false,
                true,
                null
            ),
            'beneficiaries' => new ParameterDefinition(
                'beneficiaries',
                'array',
                'List of beneficiaries with percentages',
                false,
                [],
                null
            ),
            'tax_year' => new ParameterDefinition(
                'tax_year',
                'integer',
                'Tax year for calculations',
                false,
                (int) date('Y'),
                null
            )
        ];
    }

    /**
     * Validate the calculation context
     */
    public function validate(CalculationContext $context): ValidationResult
    {
        try {
            $this->validateContext($context);
            return new ValidationResult(true, []);
        } catch (CalculationException $e) {
            return new ValidationResult(false, [$e->getMessage()]);
        }
    }

    /**
     * Create comprehensive estate plan
     */
    public function createComprehensiveEstatePlan(array $clientData, int $taxYear): array
    {
        $estateData = $clientData['estate'] ?? [];
        $beneficiaries = $clientData['beneficiaries'] ?? [];
        $accounts = $clientData['accounts'] ?? [];

        return [
            'tax_analysis' => $this->taxCalculator->calculateTotalEstateTax(
                $estateData['total_value'] ?? 0,
                $clientData['personal_info']['province'] ?? 'ON',
                $taxYear
            ),
            'transfer_strategy' => $this->transferOptimizer->optimizeTransferStrategy(
                $estateData,
                $beneficiaries,
                [],
                $taxYear
            ),
            'beneficiary_analysis' => $this->beneficiaryAnalyzer->analyzeBeneficiaries(
                $beneficiaries,
                $accounts,
                $estateData
            ),
            'recommendations' => $this->generateEstatePlanningRecommendations($clientData, $taxYear),
            'projections' => $this->projectEstateGrowth(
                $estateData['total_value'] ?? 0,
                0.04, // 4% annual growth
                20,    // 20 years
                $clientData['personal_info']['province'] ?? 'ON',
                $taxYear
            )
        ];
    }

    /**
     * Optimize estate taxes
     */
    public function optimizeEstateTaxes(float $estateValue, string $province, int $taxYear): array
    {
        $currentTax = $this->taxCalculator->calculateTotalEstateTax($estateValue, $province, $taxYear);

        // Simplified optimization - in practice this would be more complex
        $optimizedTax = [
            'federal' => $currentTax['federal'] * 0.8, // Assume 20% reduction through planning
            'provincial' => $currentTax['provincial'] * 0.9, // Assume 10% reduction
            'total' => 0
        ];
        $optimizedTax['total'] = $optimizedTax['federal'] + $optimizedTax['provincial'];

        return [
            'current_tax' => $currentTax,
            'optimized_tax' => $optimizedTax,
            'savings' => $currentTax['total'] - $optimizedTax['total'],
            'strategies' => [
                'annual_gifts',
                'spousal_rollover',
                'trust_structures'
            ]
        ];
    }

    /**
     * Optimize beneficiary designations
     */
    public function optimizeBeneficiaryDesignations(array $beneficiaries, array $accounts): array
    {
        $analysis = $this->beneficiaryAnalyzer->analyzeBeneficiaries($beneficiaries, $accounts, []);

        return [
            'issues' => $analysis['conflicts'] ?? [],
            'recommendations' => $analysis['recommendations'] ?? [],
            'optimized_designations' => $this->generateOptimizedDesignations($beneficiaries, $accounts)
        ];
    }

    /**
     * Project estate growth
     */
    public function projectEstateGrowth(
        float $currentEstate,
        float $annualGrowthRate,
        int $years,
        string $province,
        int $taxYear
    ): array {
        $projectedValue = $currentEstate * pow(1 + $annualGrowthRate, $years);
        $currentTax = $this->taxCalculator->calculateTotalEstateTax($currentEstate, $province, $taxYear);
        $projectedTax = $this->taxCalculator->calculateTotalEstateTax($projectedValue, $province, $taxYear);

        return [
            'projected_value' => round($projectedValue, 2),
            'tax_projections' => [
                'current' => $currentTax,
                'projected' => $projectedTax
            ],
            'growth_summary' => [
                'starting_value' => $currentEstate,
                'growth_rate' => $annualGrowthRate,
                'years' => $years,
                'total_growth' => $projectedValue - $currentEstate
            ]
        ];
    }

    /**
     * Generate estate planning recommendations
     */
    private function generateEstatePlanningRecommendations(array $clientData, int $taxYear): array
    {
        $recommendations = [];

        $estateValue = $clientData['estate']['total_value'] ?? 0;
        if ($estateValue > 1000000) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'tax_planning',
                'title' => 'Estate Tax Planning',
                'description' => 'Consider strategies to minimize estate taxes'
            ];
        }

        $age = $clientData['personal_info']['age'] ?? 0;
        if ($age > 65) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'will_preparation',
                'title' => 'Update Will and Powers of Attorney',
                'description' => 'Ensure estate documents are current'
            ];
        }

        return $recommendations;
    }

    /**
     * Generate optimized beneficiary designations
     */
    private function generateOptimizedDesignations(array $beneficiaries, array $accounts): array
    {
        $optimized = [];

        foreach ($accounts as $account) {
            $optimized[] = [
                'account_name' => $account['name'] ?? 'Unknown',
                'recommended_beneficiaries' => $this->recommendBeneficiariesForAccount($account, $beneficiaries),
                'rationale' => 'Optimize for tax efficiency and family protection'
            ];
        }

        return $optimized;
    }

    /**
     * Recommend beneficiaries for a specific account
     */
    private function recommendBeneficiariesForAccount(array $account, array $beneficiaries): array
    {
        // Simplified logic - in practice this would be more sophisticated
        $spouseBeneficiaries = array_filter($beneficiaries, fn($b) => ($b['relationship'] ?? '') === 'spouse');
        if (!empty($spouseBeneficiaries)) {
            return array_slice($spouseBeneficiaries, 0, 1); // Primary: spouse
        }

        return array_slice($beneficiaries, 0, 2); // Primary: first two beneficiaries
    }
}