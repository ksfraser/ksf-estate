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
 * Charitable Giving Engine
 *
 * Models charitable giving within an estate plan: computes total donations
 * (explicit donations or a percentage of the estate), estimates the tax
 * savings from donation credits, projects net value to heirs, and recommends
 * giving vehicles (outright gift, charitable remainder trust, donor-advised fund).
 *
 * Tax model is intentionally simplified: a blended federal/provincial donation
 * credit rate is applied to the donation amount, capped at the estimated tax
 * otherwise payable on the estate.
 */
class CharitableGivingEngine implements CalculationEngineInterface
{
    private const BLENDED_CREDIT_RATE = 0.46;
    private const FEDERAL_ESTATE_THRESHOLD = 15000000.0;
    private const FEDERAL_ESTATE_RATE = 0.20;

    public function getCalculationType(): string
    {
        return 'charitable_giving';
    }

    public function calculate(CalculationContext $context): CalculationResult
    {
        $this->validateOrThrow($context);

        $parameters = $context->parameters;
        $estateValue = (float) ($parameters['estate_value'] ?? $this->sumAssets($parameters['assets'] ?? []));
        $donations = $parameters['donations'] ?? [];
        $totalDonations = $this->sumDonations($donations);

        if (isset($parameters['charitable_intent'])) {
            $intent = (float) $parameters['charitable_intent'];
            $implied = $intent <= 1.0 ? $estateValue * $intent : $intent;
            $totalDonations = max($totalDonations, $implied);
        }

        $useCrt = (bool) ($parameters['use_charitable_remainder_trust'] ?? false);
        $taxSavings = $this->estimateTaxSavings($totalDonations, $estateValue);
        $netToHeirs = max(0.0, $estateValue - $totalDonations);
        $strategies = $this->generateStrategies($totalDonations, $useCrt, $estateValue);

        $results = [
            'estate_value' => round($estateValue, 2),
            'total_donations' => round($totalDonations, 2),
            'charitable_deduction' => round($totalDonations, 2),
            'estimated_tax_savings' => round($taxSavings, 2),
            'net_to_heirs' => round($netToHeirs, 2),
            'strategies' => $strategies,
        ];

        return CalculationResult::success(
            'charitable_giving',
            $results,
            [],
            [],
            ['client_id' => $context->clientId]
        );
    }

    public function validate(CalculationContext $context): ValidationResult
    {
        if ($context->calculationType !== 'charitable_giving') {
            return new ValidationResult(false, ['Invalid calculation type for Charitable Giving Engine']);
        }

        $parameters = $context->parameters;
        if (!isset($parameters['estate_value']) && !isset($parameters['assets']) && !isset($parameters['donations'])) {
            return new ValidationResult(false, ['estate_value, assets, or donations parameter is required']);
        }

        return new ValidationResult(true, []);
    }

    public function getRequiredParameters(): array
    {
        return [
            'estate_value' => new ParameterDefinition('estate_value', 'float', 'Total estate value', true),
        ];
    }

    public function getOptionalParameters(): array
    {
        return [
            'donations' => new ParameterDefinition('donations', 'array', 'List of charitable donations', false, []),
            'charitable_intent' => new ParameterDefinition('charitable_intent', 'float', 'Fraction or amount of estate to give', false, 0.0),
            'use_charitable_remainder_trust' => new ParameterDefinition('use_charitable_remainder_trust', 'bool', 'Use a charitable remainder trust', false, false),
        ];
    }

    // --- Public helpers (unit tested directly) ---

    public function sumAssets(array $assets): float
    {
        return (float) array_sum(array_column($assets, 'value'));
    }

    public function sumDonations(array $donations): float
    {
        return (float) array_sum(array_column($donations, 'amount'));
    }

    public function estimateDonationCredit(float $amount): float
    {
        return $amount * self::BLENDED_CREDIT_RATE;
    }

    public function estimateEstateTax(float $estateValue): float
    {
        if ($estateValue <= self::FEDERAL_ESTATE_THRESHOLD) {
            return 0.0;
        }

        return ($estateValue - self::FEDERAL_ESTATE_THRESHOLD) * self::FEDERAL_ESTATE_RATE;
    }

    public function estimateTaxSavings(float $donations, float $estateValue): float
    {
        $credit = $this->estimateDonationCredit($donations);
        $taxPayable = $this->estimateEstateTax($estateValue);

        // Donation credits can only offset tax actually payable; with no estate
        // tax there is nothing for the credit to offset.
        return min($credit, $taxPayable);
    }

    public function generateStrategies(float $donations, bool $useCrt, float $estateValue): array
    {
        $strategies = [];

        if ($donations > 0.0) {
            $strategies[] = 'outright_gift_to_charity';
        }

        if ($useCrt) {
            $strategies[] = 'charitable_remainder_trust';
        }

        if ($estateValue > 1000000.0) {
            $strategies[] = 'donor_advised_fund';
        }

        $strategies[] = 'review_charitable_intent_with_advisor';

        return array_values(array_unique($strategies));
    }

    private function validateOrThrow(CalculationContext $context): void
    {
        $result = $this->validate($context);
        if (!$result->isValid) {
            throw new CalculationException(
                implode('; ', $result->errors),
                'charitable_giving'
            );
        }
    }
}
