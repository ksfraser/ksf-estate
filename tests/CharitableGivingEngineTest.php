<?php

declare(strict_types=1);

namespace Ksfraser\Estate\Tests;

use Ksfraser\Estate\CharitableGivingEngine;
use Ksfraser\ModulesCommon\CalculationContext;
use Ksfraser\ModulesCommon\CalculationEngineInterface;
use Ksfraser\ModulesCommon\CalculationResult;
use Ksfraser\ModulesCommon\CalculationException;
use PHPUnit\Framework\TestCase;

class CharitableGivingEngineTest extends TestCase
{
    private $engine;

    protected function setUp(): void
    {
        $this->engine = new CharitableGivingEngine();
    }

    public function testImplementsCalculationEngineInterface(): void
    {
        $this->assertInstanceOf(CalculationEngineInterface::class, $this->engine);
    }

    public function testGetCalculationType(): void
    {
        $this->assertEquals('charitable_giving', $this->engine->getCalculationType());
    }

    public function testCalculateWithExplicitDonations(): void
    {
        $parameters = [
            'estate_value' => 2000000.0,
            'donations' => [
                ['charity' => 'United Way', 'amount' => 100000.0],
                ['charity' => 'Hospital', 'amount' => 50000.0],
            ],
        ];

        $context = new CalculationContext('charitable_giving', $parameters);
        $result = $this->engine->calculate($context);

        $this->assertInstanceOf(CalculationResult::class, $result);
        $results = $result->getResults();

        $this->assertEquals(2000000.0, $results['estate_value']);
        $this->assertEquals(150000.0, $results['total_donations']);
        $this->assertGreaterThanOrEqual(0.0, $results['estimated_tax_savings']);
        $this->assertEquals(1850000.0, $results['net_to_heirs']);
        $this->assertContains('outright_gift_to_charity', $results['strategies']);
        $this->assertContains('donor_advised_fund', $results['strategies']);
    }

    public function testCalculateWithCharitableIntentFraction(): void
    {
        $parameters = [
            'estate_value' => 1000000.0,
            'charitable_intent' => 0.10,
        ];

        $context = new CalculationContext('charitable_giving', $parameters);
        $result = $this->engine->calculate($context);
        $results = $result->getResults();

        $this->assertEquals(100000.0, $results['total_donations']);
    }

    public function testEstimateDonationCredit(): void
    {
        $this->assertEqualsWithDelta(46000.0, $this->engine->estimateDonationCredit(100000.0), 0.0001);
    }

    public function testEstimateTaxSavingsCappedAtTaxPayable(): void
    {
        // Estate under federal threshold => no estate tax => savings capped at 0
        $this->assertEquals(0.0, $this->engine->estimateTaxSavings(100000.0, 2000000.0));

        // Very large estate => estate tax payable, savings capped at that amount
        $savings = $this->engine->estimateTaxSavings(5000000.0, 30000000.0);
        $this->assertLessThanOrEqual($this->engine->estimateEstateTax(30000000.0), $savings);
        $this->assertGreaterThan(0.0, $savings);
    }

    public function testGenerateStrategiesIncludesCrt(): void
    {
        $strategies = $this->engine->generateStrategies(100000.0, true, 2000000.0);
        $this->assertContains('charitable_remainder_trust', $strategies);
        $this->assertContains('review_charitable_intent_with_advisor', $strategies);
    }

    public function testInvalidCalculationTypeThrows(): void
    {
        $context = new CalculationContext('wrong_type', ['estate_value' => 1000000.0]);
        $this->expectException(CalculationException::class);
        $this->engine->calculate($context);
    }

    public function testMissingRequiredParametersThrows(): void
    {
        $context = new CalculationContext('charitable_giving', []);
        $this->expectException(CalculationException::class);
        $this->engine->calculate($context);
    }
}
