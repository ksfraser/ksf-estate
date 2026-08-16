<?php

declare(strict_types=1);

namespace Ksfraser\Estate\Tests;

use Ksfraser\Estate\DebtManagementEngine;
use Ksfraser\ModulesCommon\CalculationContext;
use Ksfraser\ModulesCommon\CalculationEngineInterface;
use Ksfraser\ModulesCommon\CalculationResult;
use Ksfraser\ModulesCommon\CalculationException;
use PHPUnit\Framework\TestCase;

class DebtManagementEngineTest extends TestCase
{
    private DebtManagementEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new DebtManagementEngine();
    }

    public function testImplementsCalculationEngineInterface(): void
    {
        $this->assertInstanceOf(CalculationEngineInterface::class, $this->engine);
    }

    public function testGetCalculationType(): void
    {
        $this->assertEquals('debt_management', $this->engine->getCalculationType());
    }

    public function testCalculateProducesFullAnalysis(): void
    {
        $parameters = [
            'assets' => [
                ['type' => 'home', 'value' => 800000.0],
                ['type' => 'investments', 'value' => 200000.0],
            ],
            'debts' => [
                ['type' => 'mortgage', 'balance' => 400000.0, 'rate' => 0.035, 'secured' => true, 'payable_on_death' => true],
                ['type' => 'loc', 'balance' => 50000.0, 'rate' => 0.07, 'secured' => false, 'payable_on_death' => true],
            ],
        ];

        $context = new CalculationContext('debt_management', $parameters);
        $result = $this->engine->calculate($context);

        $this->assertInstanceOf(CalculationResult::class, $result);
        $results = $result->getResults();

        $this->assertEquals(1000000.0, $results['total_assets']);
        $this->assertEquals(450000.0, $results['total_debt']);
        $this->assertEquals(550000.0, $results['debt_free_estate']);
        $this->assertEqualsWithDelta(0.45, $results['debt_to_asset_ratio'], 0.0001);
        $this->assertEquals(400000.0, $results['secured_debt']);
        $this->assertEquals(50000.0, $results['unsecured_debt']);
        $this->assertEquals(450000.0, $results['liquidity_needed']);
        $this->assertArrayHasKey('debt_payoff', $results);
        $this->assertContains('prioritize_high_interest_debt:loc', $results['recommendations']);
        $this->assertContains('consider_mortgage_life_insurance', $results['recommendations']);
    }

    public function testDebtToAssetRatio(): void
    {
        $this->assertEqualsWithDelta(0.5, $this->engine->calculateDebtToAssetRatio(1000000.0, 500000.0), 0.0001);
        $this->assertEquals(0.0, $this->engine->calculateDebtToAssetRatio(1000000.0, 0.0));
        $this->assertEquals(1.0, $this->engine->calculateDebtToAssetRatio(0.0, 100.0));
    }

    public function testCategorizeDebts(): void
    {
        $debts = [
            ['type' => 'mortgage', 'balance' => 400000.0, 'secured' => true],
            ['type' => 'loc', 'balance' => 50000.0, 'secured' => false],
        ];
        $categorized = $this->engine->categorizeDebts($debts);
        $this->assertEquals(400000.0, $categorized['secured']);
        $this->assertEquals(50000.0, $categorized['unsecured']);
    }

    public function testLiquidityNeededIgnoresNonPodDebts(): void
    {
        $debts = [
            ['type' => 'mortgage', 'balance' => 400000.0, 'payable_on_death' => true],
            ['type' => 'student_loan', 'balance' => 30000.0, 'payable_on_death' => false],
        ];
        $this->assertEquals(400000.0, $this->engine->calculateLiquidityNeeded($debts));
    }

    public function testAnalyzeDebtPayoffPrioritizesHighInterest(): void
    {
        $debts = [
            ['type' => 'mortgage', 'balance' => 400000.0, 'rate' => 0.035],
            ['type' => 'loc', 'balance' => 50000.0, 'rate' => 0.07],
        ];
        $payoff = $this->engine->analyzeDebtPayoff($debts, 100000.0);

        $this->assertEquals(100000.0, $payoff['available_liquidity']);
        $this->assertEquals(350000.0, $payoff['unfunded']);
        // First plan entry should be the higher-interest LOC
        $this->assertEquals('loc', $payoff['plan'][0]['debt']);
        $this->assertEquals(50000.0, $payoff['plan'][0]['paid']);
    }

    public function testInvalidCalculationTypeThrows(): void
    {
        $context = new CalculationContext('wrong_type', ['assets' => []]);
        $this->expectException(CalculationException::class);
        $this->engine->calculate($context);
    }

    public function testMissingRequiredParametersThrows(): void
    {
        $context = new CalculationContext('debt_management', []);
        $this->expectException(CalculationException::class);
        $this->engine->calculate($context);
    }
}
