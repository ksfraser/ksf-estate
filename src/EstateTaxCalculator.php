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
 * Estate Tax Calculator
 *
 * Handles federal and provincial estate tax calculations for Canadian estates.
 * Provides accurate tax calculations based on current Canadian tax laws.
 */
class EstateTaxCalculator
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get tax rate for a specific jurisdiction and year
     *
     * @param string $jurisdiction The jurisdiction (FEDERAL, ON, QC, etc.)
     * @param int $taxYear The tax year
     * @return array{threshold: float, rate: float, used_fallback_year: bool, fallback_year: ?int} The tax rate data
     */
    private function getTaxRate(string $jurisdiction, int $taxYear): array
    {
        $stmt = $this->pdo->prepare("
            SELECT threshold, rate, tax_year
            FROM estate_tax_rates
            WHERE jurisdiction = ?
              AND tax_year = ?
              AND is_active = 1
              AND effective_date <= date('now')
              AND (expiry_date IS NULL OR expiry_date >= date('now'))
            ORDER BY effective_date DESC
            LIMIT 1
        ");

        $stmt->execute([$jurisdiction, $taxYear]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $usedFallbackYear = false;
        $fallbackYear = null;

        if (!$result) {
            // Try to find the most recent available year
            $stmt = $this->pdo->prepare("
                SELECT threshold, rate, tax_year
                FROM estate_tax_rates
                WHERE jurisdiction = ?
                  AND tax_year <= ?
                  AND is_active = 1
                  AND effective_date <= date('now')
                  AND (expiry_date IS NULL OR expiry_date >= date('now'))
                ORDER BY tax_year DESC, effective_date DESC
                LIMIT 1
            ");

            $stmt->execute([$jurisdiction, $taxYear]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result) {
                $usedFallbackYear = true;
                $fallbackYear = (int) $result['tax_year'];
            } else {
                // Return default values if no rate found
                return [
                    'threshold' => 0.0,
                    'rate' => 0.0,
                    'used_fallback_year' => false,
                    'fallback_year' => null
                ];
            }
        }

        return [
            'threshold' => (float) $result['threshold'],
            'rate' => (float) $result['rate'],
            'used_fallback_year' => $usedFallbackYear,
            'fallback_year' => $fallbackYear
        ];
    }

    /**
     * Calculate federal estate tax
     *
     * @param float $estateValue The total estate value
     * @param int $taxYear The tax year for calculation
     * @return array{tax_amount: float, used_fallback_year: bool, fallback_year: ?int} The federal estate tax calculation
     */
    public function calculateFederalEstateTax(float $estateValue, int $taxYear): array
    {
        $rate = $this->getTaxRate('FEDERAL', $taxYear);

        if ($estateValue <= $rate['threshold']) {
            return [
                'tax_amount' => 0.0,
                'used_fallback_year' => $rate['used_fallback_year'],
                'fallback_year' => $rate['fallback_year']
            ];
        }

        $taxableAmount = $estateValue - $rate['threshold'];
        return [
            'tax_amount' => $taxableAmount * $rate['rate'],
            'used_fallback_year' => $rate['used_fallback_year'],
            'fallback_year' => $rate['fallback_year']
        ];
    }

    /**
     * Calculate provincial estate tax
     *
     * @param float $estateValue The estate value
     * @param string $province The province code
     * @param int $taxYear The tax year
     * @return array{tax_amount: float, used_fallback_year: bool, fallback_year: ?int} The provincial estate tax calculation
     */
    public function calculateProvincialEstateTax(float $estateValue, string $province, int $taxYear): array
    {
        $rate = $this->getTaxRate($province, $taxYear);

        if ($estateValue <= $rate['threshold']) {
            return [
                'tax_amount' => 0.0,
                'used_fallback_year' => $rate['used_fallback_year'],
                'fallback_year' => $rate['fallback_year']
            ];
        }

        $taxableAmount = $estateValue - $rate['threshold'];
        return [
            'tax_amount' => $taxableAmount * $rate['rate'],
            'used_fallback_year' => $rate['used_fallback_year'],
            'fallback_year' => $rate['fallback_year']
        ];
    }    /**
     * Calculate total estate tax (federal + provincial)
     *
     * @param float $estateValue The total estate value
     * @param string $province The province code
     * @param int $taxYear The tax year for calculation
     * @return array{federal: float, provincial: float, total: float, notifications: array} The tax breakdown with notifications
     */
    public function calculateTotalEstateTax(float $estateValue, string $province, int $taxYear): array
    {
        if ($estateValue < 0) {
            throw new \InvalidArgumentException('Estate value cannot be negative');
        }

        $federalTax = $this->calculateFederalEstateTax($estateValue, $taxYear);
        $provincialTax = $this->calculateProvincialEstateTax($estateValue, $province, $taxYear);
        $totalTax = $federalTax['tax_amount'] + $provincialTax['tax_amount'];

        $notifications = [];

        if ($federalTax['used_fallback_year']) {
            $notifications[] = [
                'type' => 'federal_tax_rate_outdated',
                'message' => "Federal estate tax calculation used {$federalTax['fallback_year']} data instead of {$taxYear}. Please update federal tax rates.",
                'priority' => 'medium',
                'category' => 'data_maintenance'
            ];
        }

        if ($provincialTax['used_fallback_year']) {
            $notifications[] = [
                'type' => 'provincial_tax_rate_outdated',
                'message' => "Provincial estate tax calculation for {$province} used {$provincialTax['fallback_year']} data instead of {$taxYear}. Please update provincial tax rates.",
                'priority' => 'medium',
                'category' => 'data_maintenance'
            ];
        }

        return [
            'federal' => round($federalTax['tax_amount'], 2),
            'provincial' => round($provincialTax['tax_amount'], 2),
            'total' => round($totalTax, 2),
            'notifications' => $notifications
        ];
    }

    /**
     * Calculate estate tax with exemptions and credits
     *
     * @param float $estateValue The total estate value
     * @param array $exemptions Array of exemption amounts
     * @param array $credits Array of tax credit amounts
     * @param string $province The province code
     * @param int $taxYear The tax year for calculation
     * @return array{details: array, net_tax: float} Tax calculation details
     */
    public function calculateEstateTaxWithAdjustments(
        float $estateValue,
        array $exemptions,
        array $credits,
        string $province,
        int $taxYear
    ): array {
        $adjustedValue = $estateValue - array_sum($exemptions);

        $taxCalculation = $this->calculateTotalEstateTax($adjustedValue, $province, $taxYear);
        $netTax = max(0, $taxCalculation['total'] - array_sum($credits));

        return [
            'details' => [
                'gross_estate_value' => $estateValue,
                'total_exemptions' => array_sum($exemptions),
                'adjusted_value' => $adjustedValue,
                'federal_tax' => $taxCalculation['federal'],
                'provincial_tax' => $taxCalculation['provincial'],
                'gross_tax' => $taxCalculation['total'],
                'total_credits' => array_sum($credits),
                'exemptions_breakdown' => $exemptions,
                'credits_breakdown' => $credits
            ],
            'net_tax' => $netTax
        ];
    }

    /**
     * Get available tax years
     *
     * @return array List of available tax years
     */
    public function getAvailableTaxYears(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT tax_year FROM estate_tax_rates ORDER BY tax_year");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get supported provinces
     *
     * @return array List of supported province codes
     */
    public function getSupportedProvinces(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT jurisdiction FROM estate_tax_rates WHERE jurisdiction != 'FEDERAL' ORDER BY jurisdiction");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Check if a province has estate tax
     *
     * @param string $province The province code
     * @param int $taxYear The tax year
     * @return bool True if province has estate tax
     */
    public function provinceHasEstateTax(string $province, int $taxYear): bool
    {
        $rate = $this->getTaxRate($province, $taxYear);
        return $rate['rate'] > 0.0;
    }
}