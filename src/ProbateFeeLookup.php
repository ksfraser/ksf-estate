<?php

declare(strict_types=1);

namespace Ksfraser\Estate;

use Ksfraser\ModulesCommon\CalculationEngineInterface;
use Ksfraser\ModulesCommon\CalculationContext;
use Ksfraser\ModulesCommon\CalculationResult;
use Ksfraser\ModulesCommon\ParameterDefinition;
use Ksfraser\ModulesCommon\ValidationResult;
use Ksfraser\ModulesCommon\ValidationRuleInterface;
use Ksfraser\ModulesCommon\CalculationException;

use PDO;
use PDOException;

/**
 * Probate Fee Lookup
 *
 * Provides database-driven probate fee calculations by province and estate value.
 * Supports progressive fee scales and handles missing current year rates by using
 * the most recent available year with advisor notifications.
 */
class ProbateFeeLookup
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Calculate probate fees for a given estate value and province
     *
     * @param float $estateValue The estate value
     * @param string $province The province code (ON, QC, BC, etc.)
     * @param int|null $taxYear The tax year (defaults to current year)
     * @return array Probate fee calculation results
     * @throws CalculationException If calculation fails
     */
    public function calculateProbateFees(float $estateValue, string $province, ?int $taxYear = null): array
    {
        $taxYear = $taxYear ?? (int) date('Y');

        try {
            // Get probate fee structure for the province and tax year
            $feeStructure = $this->getProbateFeeStructure($province, $taxYear);

            if (empty($feeStructure)) {
                // Try to find the most recent available year
                $feeStructure = $this->getMostRecentProbateFeeStructure($province);
                $usedFallbackYear = true;
                $fallbackYear = $feeStructure['tax_year'] ?? null;
            } else {
                $usedFallbackYear = false;
                $fallbackYear = null;
            }

            if (empty($feeStructure)) {
                throw new CalculationException(
                    "No probate fee data available for province {$province}",
                    'probate_fee_lookup'
                );
            }

            $feeAmount = $this->calculateFeeAmount($estateValue, $feeStructure);
            $feePercentage = ($estateValue > 0) ? ($feeAmount / $estateValue) * 100 : 0;

            return [
                'fee_amount' => round($feeAmount, 2),
                'fee_percentage' => round($feePercentage, 3),
                'province' => $province,
                'tax_year' => $feeStructure['tax_year'],
                'used_fallback_year' => $usedFallbackYear,
                'fallback_year_used' => $fallbackYear,
                'fee_structure' => $feeStructure,
                'advisor_notification_required' => $usedFallbackYear
            ];

        } catch (PDOException $e) {
            throw new CalculationException(
                'Database error calculating probate fees: ' . $e->getMessage(),
                'probate_fee_lookup',
                [],
                $e
            );
        }
    }

    /**
     * Get probate fee structure for a specific province and tax year
     *
     * @param string $province The province code
     * @param int $taxYear The tax year
     * @return array|null The fee structure or null if not found
     */
    private function getProbateFeeStructure(string $province, int $taxYear): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM probate_fees
            WHERE province = :province
            AND tax_year = :tax_year
            AND is_active = TRUE
            AND effective_date <= CURDATE()
            AND (expiry_date IS NULL OR expiry_date >= CURDATE())
            ORDER BY min_estate_value ASC
        ");

        $stmt->execute([
            'province' => $province,
            'tax_year' => $taxYear
        ]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return empty($results) ? null : $results;
    }

    /**
     * Get the most recent probate fee structure for a province
     *
     * @param string $province The province code
     * @return array|null The most recent fee structure or null if not found
     */
    private function getMostRecentProbateFeeStructure(string $province): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM probate_fees
            WHERE province = :province
            AND is_active = TRUE
            AND effective_date <= CURDATE()
            AND (expiry_date IS NULL OR expiry_date >= CURDATE())
            ORDER BY tax_year DESC, effective_date DESC, min_estate_value ASC
        ");

        $stmt->execute(['province' => $province]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return empty($results) ? null : $results;
    }

    /**
     * Calculate the fee amount based on estate value and fee structure
     *
     * @param float $estateValue The estate value
     * @param array $feeStructure The fee structure array
     * @return float The calculated fee amount
     */
    private function calculateFeeAmount(float $estateValue, array $feeStructure): float
    {
        $totalFee = 0.0;

        // Find applicable fee tiers
        $applicableTiers = array_filter($feeStructure, function($tier) use ($estateValue) {
            return $estateValue >= $tier['min_estate_value'] &&
                   ($tier['max_estate_value'] === null || $estateValue <= $tier['max_estate_value']);
        });

        if (empty($applicableTiers)) {
            // Use the highest tier if estate value exceeds all defined ranges
            $highestTier = end($feeStructure);
            $baseFee = $estateValue * $highestTier['base_rate'];
            $maxFee = $highestTier['max_rate'] ? $estateValue * $highestTier['max_rate'] : $baseFee;
            $totalFee = min($maxFee, $baseFee) + $highestTier['flat_fee'];
        } else {
            // Calculate based on applicable tiers (simplified - using highest applicable tier)
            $tier = end($applicableTiers); // Get the highest applicable tier
            $baseFee = $estateValue * $tier['base_rate'];
            $maxFee = $tier['max_rate'] ? $estateValue * $tier['max_rate'] : $baseFee;
            $totalFee = min($maxFee, $baseFee) + $tier['flat_fee'];
        }

        return $totalFee;
    }

    /**
     * Get all available provinces with probate fee data
     *
     * @return array List of provinces with probate fee data
     */
    public function getAvailableProvinces(): array
    {
        $stmt = $this->pdo->query("
            SELECT DISTINCT province
            FROM probate_fees
            WHERE is_active = TRUE
            ORDER BY province
        ");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get probate fee preview for a province
     *
     * @param string $province The province code
     * @param int|null $taxYear The tax year
     * @return array Preview data with sample calculations
     */
    public function getProbateFeePreview(string $province, ?int $taxYear = null): array
    {
        $taxYear = $taxYear ?? (int) date('Y');

        $feeStructure = $this->getProbateFeeStructure($province, $taxYear);

        if (empty($feeStructure)) {
            $feeStructure = $this->getMostRecentProbateFeeStructure($province);
        }

        if (empty($feeStructure)) {
            return [
                'province' => $province,
                'available' => false,
                'message' => 'No probate fee data available for this province'
            ];
        }

        // Generate sample calculations for common estate values
        $sampleValues = [100000, 500000, 1000000, 2000000, 5000000];
        $samples = [];

        foreach ($sampleValues as $value) {
            $feeResult = $this->calculateProbateFees($value, $province, $taxYear);
            $samples[] = [
                'estate_value' => $value,
                'fee_amount' => $feeResult['fee_amount'],
                'fee_percentage' => $feeResult['fee_percentage']
            ];
        }

        return [
            'province' => $province,
            'tax_year' => $feeStructure[0]['tax_year'] ?? $taxYear,
            'available' => true,
            'fee_structure' => $feeStructure,
            'sample_calculations' => $samples,
            'used_fallback_year' => ($feeStructure[0]['tax_year'] ?? $taxYear) !== $taxYear
        ];
    }
}