<?php

declare(strict_types=1);

namespace Ksfraser\Estate\Tests;

use Ksfraser\Estate\EstatePlanningEngine;
use Ksfraser\ModulesCommon\CalculationEngineInterface;
use Ksfraser\ModulesCommon\CalculationResult;
use Ksfraser\ModulesCommon\CalculationException;
use Ksfraser\ModulesCommon\CalculationContext;
use Ksfraser\Estate\EstateTaxCalculator;
use Ksfraser\Estate\WealthTransferOptimizer;
use Ksfraser\Estate\BeneficiaryAnalysisEngine;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for Estate Planning Engine
 *
 * Tests comprehensive estate planning calculations including:
 * - Will and estate analysis
 * - Estate tax calculations
 * - Beneficiary designations
 * - Inheritance projections
 * - Probate cost analysis
 * - Estate equalization strategies
 *
 * @covers \Ksfraser\Estate\EstatePlanningEngine
 * @uses \Ksfraser\ModulesCommon\CalculationResult
 * @uses \Ksfraser\Exceptions\Domain\CalculationException
 */
class EstatePlanningEngineTest extends TestCase
{
    private EstatePlanningEngine $engine;
    private EstateTaxCalculator $taxCalculator;
    private \PDO $pdo;
    private WealthTransferOptimizer $transferOptimizer;
    private BeneficiaryAnalysisEngine $beneficiaryAnalyzer;

    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Create estate_tax_rates table
        $this->pdo->exec("
            CREATE TABLE estate_tax_rates (
                tax_rate_id INTEGER PRIMARY KEY AUTOINCREMENT,
                tax_year INTEGER NOT NULL,
                jurisdiction VARCHAR(10) NOT NULL,
                threshold DECIMAL(15,2) DEFAULT 0.00,
                rate DECIMAL(5,4) NOT NULL,
                effective_date DATE NOT NULL,
                expiry_date DATE NULL,
                is_active BOOLEAN DEFAULT 1,
                created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_date DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create unique index
        $this->pdo->exec("CREATE UNIQUE INDEX unique_rate ON estate_tax_rates (tax_year, jurisdiction, effective_date)");
        $this->pdo->exec("CREATE INDEX idx_tax_year ON estate_tax_rates (tax_year)");
        $this->pdo->exec("CREATE INDEX idx_jurisdiction ON estate_tax_rates (jurisdiction)");
        $this->pdo->exec("CREATE INDEX idx_is_active ON estate_tax_rates (is_active)");
        $this->pdo->exec("CREATE INDEX idx_effective_date ON estate_tax_rates (effective_date)");

        // Insert test data
        $this->pdo->exec("
            INSERT INTO estate_tax_rates (tax_year, jurisdiction, threshold, rate, effective_date, is_active) VALUES
            (2025, 'ON', 5000000.00, 0.02, '2025-01-01', 1),
            (2025, 'BC', 5000000.00, 0.02, '2025-01-01', 1),
            (2025, 'QC', 5000000.00, 0.02, '2025-01-01', 1),
            (2024, 'ON', 5000000.00, 0.02, '2024-01-01', 1),
            (2024, 'BC', 5000000.00, 0.02, '2024-01-01', 1),
            (2024, 'QC', 5000000.00, 0.02, '2024-01-01', 1),
            (2025, 'FEDERAL', 15000000.00, 0.20, '2025-01-01', 1),
            (2024, 'FEDERAL', 14000000.00, 0.20, '2024-01-01', 1),
            (2023, 'FEDERAL', 13000000.00, 0.20, '2023-01-01', 1)
        ");

        // Create beneficiary relationship tables
        $this->pdo->exec("
            CREATE TABLE beneficiary_relationship_types (
                relationship_type_id INTEGER PRIMARY KEY AUTOINCREMENT,
                relationship_code VARCHAR(50) NOT NULL UNIQUE,
                category VARCHAR(50) NOT NULL,
                display_name VARCHAR(100) NOT NULL,
                description TEXT,
                inheritance_priority INTEGER DEFAULT 0,
                tax_implications VARCHAR(255),
                legal_considerations TEXT,
                is_active BOOLEAN DEFAULT 1,
                created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_date DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE beneficiary_relationship_mappings (
                mapping_id INTEGER PRIMARY KEY AUTOINCREMENT,
                relationship_term VARCHAR(100) NOT NULL UNIQUE,
                relationship_type_id INTEGER NOT NULL,
                is_primary BOOLEAN DEFAULT 0,
                created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (relationship_type_id) REFERENCES beneficiary_relationship_types(relationship_type_id)
            )
        ");

        // Insert test relationship data
        $this->pdo->exec("
            INSERT INTO beneficiary_relationship_types (relationship_code, category, display_name, inheritance_priority, tax_implications) VALUES
            ('spouse', 'spouse', 'Spouse', 10, 'Spousal rollover available'),
            ('child', 'children', 'Child', 8, 'No tax implications'),
            ('parent', 'parents', 'Parent', 5, 'Potential tax implications'),
            ('sibling', 'siblings', 'Sibling', 3, 'No tax implications'),
            ('step_child', 'step_relatives', 'Step Child', 6, 'Inheritance laws may vary'),
            ('adopted_child', 'adopted_relatives', 'Adopted Child', 8, 'Same as biological children'),
            ('other', 'other', 'Other', 1, 'Depends on relationship')
        ");

        $this->pdo->exec("
            INSERT INTO beneficiary_relationship_mappings (relationship_term, relationship_type_id, is_primary) VALUES
            ('spouse', 1, 1),
            ('husband', 1, 0),
            ('wife', 1, 0),
            ('child', 2, 1),
            ('children', 2, 0),
            ('son', 2, 0),
            ('daughter', 2, 0),
            ('parent', 3, 1),
            ('father', 3, 0),
            ('mother', 3, 0),
            ('sibling', 4, 1),
            ('brother', 4, 0),
            ('sister', 4, 0),
            ('step_child', 5, 1),
            ('step_son', 5, 0),
            ('step_daughter', 5, 0),
            ('adopted_child', 6, 1),
            ('adopted_son', 6, 0),
            ('adopted_daughter', 6, 0)
        ");

        // Create beneficiary recommendations table
        $this->pdo->exec("
            CREATE TABLE beneficiary_recommendations (
                recommendation_id INTEGER PRIMARY KEY AUTOINCREMENT,
                recommendation_code VARCHAR(100) NOT NULL UNIQUE,
                priority VARCHAR(20) NOT NULL DEFAULT 'medium',
                category VARCHAR(50) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                rationale TEXT NOT NULL,
                trigger_conditions TEXT,
                applicable_scenarios VARCHAR(255),
                is_active BOOLEAN DEFAULT 1,
                created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_date DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Insert test recommendations
        $this->pdo->exec("
            INSERT INTO beneficiary_recommendations (
                recommendation_code, priority, category, title, description, rationale, trigger_conditions
            ) VALUES
            (
                'update_outdated_designations',
                'high',
                'maintenance',
                'Update Outdated Beneficiary Designations',
                'Review and update beneficiary designations that are more than 3 years old',
                'Beneficiary designations should be reviewed regularly to ensure they reflect current wishes',
                '[{\"field\": \"outdated_accounts\", \"operator\": \"greater_than\", \"value\": 0}]'
            ),
            (
                'add_contingent_beneficiaries',
                'medium',
                'protection',
                'Add Contingent Beneficiaries',
                'Designate contingent beneficiaries for all accounts with primary beneficiaries',
                'Contingent beneficiaries ensure assets pass to intended recipients if primary beneficiaries are unavailable',
                '[{\"field\": \"missing_contingents\", \"operator\": \"greater_than\", \"value\": 0}]'
            ),
            (
                'establish_trusts_for_minors',
                'high',
                'protection',
                'Establish Trusts for Minor Beneficiaries',
                'Consider establishing trusts to protect assets for minor beneficiaries',
                'Trusts provide professional management and protection of assets for minors',
                '[{\"field\": \"minor_beneficiaries\", \"operator\": \"greater_than\", \"value\": 0}]'
            ),
            (
                'diversify_beneficiary_designations',
                'medium',
                'diversification',
                'Diversify Beneficiary Designations',
                'Reduce concentration risk by diversifying beneficiary designations',
                'Diversification protects against loss if a primary beneficiary is unavailable',
                '[{\"field\": \"high_concentration_risk\", \"operator\": \"greater_than\", \"value\": 0}]'
            ),
            (
                'change_retirement_to_individuals',
                'medium',
                'protection',
                'Change Retirement Beneficiaries from Estate to Individuals',
                'Change retirement account beneficiaries from estate to individual beneficiaries',
                'Individual designations avoid probate and provide better creditor protection',
                '[{\"field\": \"retirement_to_estate\", \"operator\": \"greater_than\", \"value\": 0}]'
            )
        ");

        $relationshipLookup = new \Ksfraser\Estate\RelationshipLookup($this->pdo);
        $relationshipAnalyzer = new \Ksfraser\Estate\RelationshipAnalyzer($relationshipLookup);
        $recommendationLookup = new \Ksfraser\Estate\RecommendationLookup($this->pdo);
        $recommendationGenerator = new \Ksfraser\Estate\RecommendationGenerator($recommendationLookup);

        $this->taxCalculator = new EstateTaxCalculator($this->pdo);
        $this->transferOptimizer = new WealthTransferOptimizer($this->taxCalculator);
        $this->beneficiaryAnalyzer = new BeneficiaryAnalysisEngine($relationshipAnalyzer, $recommendationGenerator);
        $this->engine = new EstatePlanningEngine(
            $this->pdo,
            $this->transferOptimizer,
            $this->beneficiaryAnalyzer
        );
    }

    /**
     * Test that EstatePlanningEngine implements CalculationEngineInterface
     */
    public function testImplementsCalculationEngineInterface(): void
    {
        $this->assertInstanceOf(CalculationEngineInterface::class, $this->engine);
    }

    /**
     * Test basic estate planning calculation with simple will
     */
    public function testBasicEstatePlanningCalculation(): void
    {
        $parameters = [
            'estate_value' => 1000000.00,
            'has_will' => true,
            'beneficiaries' => [
                ['name' => 'Spouse', 'relationship' => 'spouse', 'percentage' => 50.0],
                ['name' => 'Child1', 'relationship' => 'child', 'percentage' => 25.0],
                ['name' => 'Child2', 'relationship' => 'child', 'percentage' => 25.0]
            ],
            'province' => 'ON',
            'tax_year' => 2025
        ];

        $context = new CalculationContext('estate_planning', $parameters);

        $result = $this->engine->calculate($context);

        $this->assertInstanceOf(CalculationResult::class, $result);
        $this->assertEquals('estate_planning', $result->calculationType);
        $this->assertArrayHasKey('total_estate_value', $result->getResults());
        $this->assertArrayHasKey('probate_fees', $result->getResults());
        $this->assertArrayHasKey('estate_taxes', $result->getResults());
        $this->assertArrayHasKey('net_distributions', $result->getResults());
    }

    /**
     * Test intestate estate calculation without will
     */
    public function testIntestateEstateCalculation(): void
    {
        $parameters = [
            'estate_value' => 500000.00,
            'has_will' => false,
            'province' => 'BC',
            'surviving_spouse' => true,
            'children_count' => 2,
            'tax_year' => 2025
        ];

        $context = new CalculationContext('estate_planning', $parameters);

        $result = $this->engine->calculate($context);

        $this->assertInstanceOf(CalculationResult::class, $result);
        $this->assertArrayHasKey('intestate_distribution', $result->getResults());
        $this->assertArrayHasKey('legal_fees_estimate', $result->getResults());
    }

    /**
     * Test estate tax calculation for large estates
     */
    public function testEstateTaxCalculation(): void
    {
        $parameters = [
            'estate_value' => 20000000.00, // $20M to trigger federal tax
            'has_will' => true,
            'province' => 'BC',
            'tax_year' => 2025,
            'beneficiaries' => [
                ['name' => 'Spouse', 'relationship' => 'spouse', 'percentage' => 100.0]
            ]
        ];

        $context = new CalculationContext('estate_planning', $parameters);

        $result = $this->engine->calculate($context);

        $this->assertArrayHasKey('federal_estate_tax', $result->getResults());
        $this->assertArrayHasKey('provincial_estate_tax', $result->getResults());
        $this->assertGreaterThan(0, $result->getResults()['federal_estate_tax']); // Federal tax applies over $15M
    }

    /**
     * Test beneficiary designation analysis
     */
    public function testBeneficiaryDesignationAnalysis(): void
    {
        $parameters = [
            'estate_value' => 800000.00,
            'has_will' => true,
            'province' => 'QC',
            'tax_year' => 2025,
            'beneficiaries' => [
                ['name' => 'Spouse', 'relationship' => 'spouse', 'percentage' => 40.0],
                ['name' => 'Trust', 'relationship' => 'trust', 'percentage' => 60.0, 'trust_type' => 'testamentary']
            ],
            'life_insurance' => [
                ['policy_value' => 250000.00, 'beneficiary' => 'Spouse']
            ]
        ];

        $context = new CalculationContext('estate_planning', $parameters);

        $result = $this->engine->calculate($context);

        $this->assertArrayHasKey('beneficiary_distributions', $result->getResults());
        $this->assertArrayHasKey('life_insurance_impact', $result->getResults());
        $this->assertArrayHasKey('trust_analysis', $result->getResults());
    }

    /**
     * Test inheritance projections for multiple generations
     */
    public function testInheritanceProjections(): void
    {
        $parameters = [
            'estate_value' => 1500000.00,
            'has_will' => true,
            'province' => 'AB',
            'tax_year' => 2025,
            'beneficiaries' => [
                ['name' => 'Spouse', 'relationship' => 'spouse', 'percentage' => 50.0],
                ['name' => 'Children', 'relationship' => 'children', 'percentage' => 50.0, 'count' => 3]
            ],
            'projection_years' => 20,
            'growth_rate' => 0.04,
            'inflation_rate' => 0.02
        ];

        $context = new CalculationContext('estate_planning', $parameters);

        $result = $this->engine->calculate($context);

        $this->assertArrayHasKey('inheritance_projections', $result->getResults());
        $this->assertArrayHasKey('generation_analysis', $result->getResults());
        $this->assertCount(20, $result->getResults()['inheritance_projections']);
    }

    /**
     * Test probate cost analysis by province
     */
    public function testProbateCostAnalysis(): void
    {
        $provinces = ['ON', 'BC', 'QC', 'AB', 'MB', 'SK', 'NS', 'NB', 'NL', 'PE'];

        foreach ($provinces as $province) {
            $parameters = [
                'estate_value' => 750000.00,
                'has_will' => true,
                'province' => $province,
                'tax_year' => 2025,
                'beneficiaries' => [
                    ['name' => 'Spouse', 'relationship' => 'spouse', 'percentage' => 100.0]
                ]
            ];

            $context = new CalculationContext('estate_planning', $parameters);
            $result = $this->engine->calculate($context);

            $this->assertArrayHasKey('probate_fees', $result->getResults());
            $this->assertArrayHasKey('fee_amount', $result->getResults()['probate_fees']);
            $this->assertArrayHasKey('percentage', $result->getResults()['probate_fees']);
            $this->assertGreaterThanOrEqual(0, $result->getResults()['probate_fees']['fee_amount']);
        }
    }

    /**
     * Test estate equalization strategies
     */
    public function testEstateEqualizationStrategies(): void
    {
        $parameters = [
            'estate_value' => 1200000.00,
            'has_will' => true,
            'province' => 'ON',
            'tax_year' => 2025,
            'beneficiaries' => [
                ['name' => 'Child1', 'relationship' => 'child', 'percentage' => 33.33, 'prior_inheritance' => 100000.00],
                ['name' => 'Child2', 'relationship' => 'child', 'percentage' => 33.33, 'prior_inheritance' => 50000.00],
                ['name' => 'Child3', 'relationship' => 'child', 'percentage' => 33.34, 'prior_inheritance' => 0.00]
            ],
            'equalization_strategy' => true
        ];

        $context = new CalculationContext('estate_planning', $parameters);

        $result = $this->engine->calculate($context);

        $this->assertArrayHasKey('equalization_adjustments', $result->getResults());
        $this->assertArrayHasKey('adjusted_distributions', $result->getResults());
        $this->assertArrayHasKey('equalization_analysis', $result->getResults());
    }

    /**
     * Test calculation with invalid parameters
     */
    public function testInvalidParametersThrowException(): void
    {
        $parameters = [
            'estate_value' => -1000.00, // Invalid negative value
            'province' => 'INVALID'
        ];

        $context = new CalculationContext('estate_planning', $parameters);

        $this->expectException(CalculationException::class);

        $this->engine->calculate($context);
    }

    /**
     * Test calculation with missing required parameters
     */
    public function testMissingRequiredParametersThrowException(): void
    {
        $parameters = [
            // Missing estate_value
            'province' => 'ON'
        ];

        $context = new CalculationContext('estate_planning', $parameters);

        $this->expectException(CalculationException::class);

        $this->engine->calculate($context);
    }

    /**
     * Test beneficiary percentage validation
     */
    public function testBeneficiaryPercentageValidation(): void
    {
        $parameters = [
            'estate_value' => 500000.00,
            'has_will' => true,
            'province' => 'ON',
            'tax_year' => 2025,
            'beneficiaries' => [
                ['name' => 'Spouse', 'relationship' => 'spouse', 'percentage' => 50.0],
                ['name' => 'Child1', 'relationship' => 'child', 'percentage' => 60.0] // Total > 100%
            ]
        ];

        $context = new CalculationContext('estate_planning', $parameters);

        $this->expectException(CalculationException::class);

        $this->engine->calculate($context);
    }

    /**
     * Test complex estate with multiple asset types
     */
    public function testComplexEstateWithMultipleAssets(): void
    {
        $parameters = [
            'assets' => [
                ['type' => 'real_estate', 'value' => 600000.00, 'location' => 'ON'],
                ['type' => 'investments', 'value' => 400000.00, 'location' => 'ON'],
                ['type' => 'business', 'value' => 300000.00, 'location' => 'ON'],
                ['type' => 'personal_property', 'value' => 100000.00, 'location' => 'ON']
            ],
            'liabilities' => [
                ['type' => 'mortgage', 'value' => 250000.00],
                ['type' => 'loans', 'value' => 50000.00]
            ],
            'has_will' => true,
            'province' => 'ON',
            'tax_year' => 2025,
            'beneficiaries' => [
                ['name' => 'Spouse', 'relationship' => 'spouse', 'percentage' => 60.0],
                ['name' => 'Children', 'relationship' => 'children', 'percentage' => 40.0, 'count' => 2]
            ]
        ];

        $context = new CalculationContext('estate_planning', $parameters);

        $result = $this->engine->calculate($context);

        $this->assertArrayHasKey('asset_analysis', $result->getResults());
        $this->assertArrayHasKey('net_estate_value', $result->getResults());
        $this->assertArrayHasKey('asset_type_breakdown', $result->getResults());
        $this->assertEquals(1100000.00, $result->getResults()['net_estate_value']); // 1.4M - 300K liabilities
    }

    /**
     * Test estate planning recommendations
     */
    public function testEstatePlanningRecommendations(): void
    {
        $parameters = [
            'estate_value' => 2500000.00,
            'has_will' => false,
            'province' => 'ON',
            'tax_year' => 2025,
            'complexity_factors' => [
                'multiple_properties' => true,
                'business_interests' => true,
                'international_assets' => true,
                'charitable_goals' => true
            ]
        ];

        $context = new CalculationContext('estate_planning', $parameters);

        $result = $this->engine->calculate($context);

        $this->assertArrayHasKey('recommendations', $result->getResults());
        $this->assertArrayHasKey('risk_assessment', $result->getResults());
        $this->assertArrayHasKey('action_items', $result->getResults());
        $this->assertContains('will_preparation', $result->getResults()['action_items']);
        $this->assertContains('professional_estate_planning_advice', $result->getResults()['recommendations']);
    }

    /**
     * Test calculation result caching and performance
     */
    public function testCalculationResultCaching(): void
    {
        $parameters = [
            'estate_value' => 1000000.00,
            'has_will' => true,
            'province' => 'ON',
            'tax_year' => 2025,
            'beneficiaries' => [
                ['name' => 'Spouse', 'relationship' => 'spouse', 'percentage' => 100.0]
            ]
        ];

        $context = new CalculationContext('estate_planning', $parameters);

        // First calculation
        $startTime = microtime(true);
        $result1 = $this->engine->calculate($context);
        $firstDuration = microtime(true) - $startTime;

        // Second calculation with same parameters (should be cached or optimized)
        $startTime = microtime(true);
        $result2 = $this->engine->calculate($context);
        $secondDuration = microtime(true) - $startTime;

        // Results should be identical
        $this->assertEquals($result1->getResults(), $result2->getResults());

        // Second calculation should be reasonably fast (basic performance check)
        $this->assertLessThan(0.1, $secondDuration); // Less than 100ms
    }

    /**
     * Test EstateTaxCalculator federal tax calculations
     */
    public function testEstateTaxCalculatorFederalTax(): void
    {
        // Test federal tax calculation for 2024
        $result = $this->taxCalculator->calculateFederalEstateTax(1000000, 2024);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('tax_amount', $result);
        $this->assertArrayHasKey('used_fallback_year', $result);
        $this->assertArrayHasKey('fallback_year', $result);
        $this->assertIsFloat($result['tax_amount']);
        $this->assertGreaterThanOrEqual(0, $result['tax_amount']);
    }

    /**
     * Test EstateTaxCalculator provincial tax calculations
     */
    public function testEstateTaxCalculatorProvincialTax(): void
    {
        // Test Ontario provincial tax
        $result = $this->taxCalculator->calculateProvincialEstateTax(1000000, 'ON', 2024);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('tax_amount', $result);
        $this->assertArrayHasKey('used_fallback_year', $result);
        $this->assertArrayHasKey('fallback_year', $result);
        $this->assertIsFloat($result['tax_amount']);
        $this->assertGreaterThanOrEqual(0, $result['tax_amount']);
    }

    /**
     * Test EstateTaxCalculator total estate tax calculation
     */
    public function testEstateTaxCalculatorTotalTax(): void
    {
        $result = $this->taxCalculator->calculateTotalEstateTax(1000000, 'ON', 2024);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('federal', $result);
        $this->assertArrayHasKey('provincial', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('notifications', $result);
        $this->assertIsArray($result['notifications']);
        $this->assertEquals($result['federal'] + $result['provincial'], $result['total']);
    }

    /**
     * Test EstateTaxCalculator exemption handling
     */
    public function testEstateTaxCalculatorExemptions(): void
    {
        // Test amount below exemption
        $smallEstate = $this->taxCalculator->calculateTotalEstateTax(500000, 'ON', 2024);

        $this->assertEquals(0, $smallEstate['total']);

        // Test amount above exemption (use federal threshold)
        $largeEstate = $this->taxCalculator->calculateTotalEstateTax(16000000, 'BC', 2024); // $16M > $14M threshold

        $this->assertGreaterThan(0, $largeEstate['total']);
    }

    /**
     * Test WealthTransferOptimizer strategy optimization
     */
    public function testWealthTransferOptimizerOptimizeStrategy(): void
    {
        $estateData = [
            'total_value' => 2000000,
            'business_interests' => 500000,
            'life_insurance' => 200000
        ];

        $beneficiaries = [
            ['name' => 'Spouse', 'relationship' => 'spouse', 'age' => 50],
            ['name' => 'Child 1', 'relationship' => 'child', 'age' => 25],
            ['name' => 'Child 2', 'relationship' => 'child', 'age' => 22]
        ];

        $constraints = [
            'risk_tolerance' => 'moderate',
            'time_horizon' => 'long_term'
        ];

        $result = $this->transferOptimizer->optimizeTransferStrategy(
            $estateData,
            $beneficiaries,
            $constraints,
            2024
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('tax_savings', $result);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
    }

    /**
     * Test WealthTransferOptimizer annual gift strategy
     */
    public function testWealthTransferOptimizerAnnualGiftStrategy(): void
    {
        $estateData = ['total_value' => 1000000];
        $beneficiaries = [
            ['name' => 'Child 1', 'relationship' => 'child'],
            ['name' => 'Child 2', 'relationship' => 'child']
        ];

        $result = $this->transferOptimizer->optimizeTransferStrategy(
            $estateData,
            $beneficiaries,
            [],
            2024
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('transfers', $result);
        $this->assertArrayHasKey('total_transferred', $result);
        $this->assertArrayHasKey('tax_savings', $result);
    }

    /**
     * Test BeneficiaryAnalysisEngine analysis
     */
    public function testBeneficiaryAnalysisEngineAnalyzeBeneficiaries(): void
    {
        $beneficiaries = [
            ['name' => 'Spouse', 'relationship' => 'spouse', 'type' => 'primary', 'age' => 50],
            ['name' => 'Child 1', 'relationship' => 'child', 'type' => 'primary', 'age' => 25],
            ['name' => 'Child 2', 'relationship' => 'child', 'type' => 'contingent', 'age' => 22]
        ];

        $accounts = [
            [
                'name' => 'RRSP',
                'type' => 'retirement',
                'value' => 500000,
                'beneficiaries' => [
                    ['name' => 'Spouse', 'type' => 'primary'],
                    ['name' => 'Child 1', 'type' => 'contingent']
                ],
                'designation_date' => '2020-01-01'
            ],
            [
                'name' => 'TFSA',
                'type' => 'tax_free',
                'value' => 100000,
                'beneficiaries' => []
            ]
        ];

        $estateData = ['total_value' => 1000000];

        $result = $this->beneficiaryAnalyzer->analyzeBeneficiaries(
            $beneficiaries,
            $accounts,
            $estateData
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('designations', $result);
        $this->assertArrayHasKey('conflicts', $result);
        $this->assertArrayHasKey('gaps', $result);
        $this->assertArrayHasKey('recommendations', $result);
    }

    /**
     * Test BeneficiaryAnalysisEngine identifies coverage gaps
     */
    public function testBeneficiaryAnalysisEngineIdentifiesGaps(): void
    {
        $beneficiaries = [['name' => 'Spouse', 'relationship' => 'spouse']];
        $accounts = [
            [
                'name' => 'TFSA',
                'type' => 'tax_free',
                'value' => 100000,
                'beneficiaries' => [] // No beneficiaries designated
            ]
        ];
        $estateData = ['total_value' => 100000];

        $result = $this->beneficiaryAnalyzer->analyzeBeneficiaries(
            $beneficiaries,
            $accounts,
            $estateData
        );

        $this->assertNotEmpty($result['gaps']);
        $gapTypes = array_column($result['gaps'], 'type');
        $this->assertContains('undesignated_accounts', $gapTypes);
    }

    /**
     * Test EstatePlanningEngine comprehensive estate planning with new architecture
     */
    public function testEstatePlanningEngineComprehensivePlanning(): void
    {
        $clientData = [
            'personal_info' => [
                'age' => 65,
                'province' => 'ON',
                'marital_status' => 'married'
            ],
            'estate' => [
                'total_value' => 2500000,
                'real_estate' => 800000,
                'investments' => 1200000,
                'business_interests' => 300000,
                'life_insurance' => 200000
            ],
            'beneficiaries' => [
                ['name' => 'Spouse', 'relationship' => 'spouse', 'percentage' => 50],
                ['name' => 'Child 1', 'relationship' => 'child', 'percentage' => 25],
                ['name' => 'Child 2', 'relationship' => 'child', 'percentage' => 25]
            ],
            'accounts' => [
                [
                    'name' => 'RRSP',
                    'type' => 'retirement',
                    'value' => 600000,
                    'beneficiaries' => [['name' => 'Spouse', 'type' => 'primary']]
                ]
            ]
        ];

        $result = $this->engine->createComprehensiveEstatePlan($clientData, 2024);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('tax_analysis', $result);
        $this->assertArrayHasKey('transfer_strategy', $result);
        $this->assertArrayHasKey('beneficiary_analysis', $result);
        $this->assertArrayHasKey('recommendations', $result);
        $this->assertArrayHasKey('projections', $result);
    }

    /**
     * Test EstatePlanningEngine tax optimization
     */
    public function testEstatePlanningEngineTaxOptimization(): void
    {
        $estateValue = 3000000;
        $province = 'ON';
        $taxYear = 2024;

        $result = $this->engine->optimizeEstateTaxes($estateValue, $province, $taxYear);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('current_tax', $result);
        $this->assertArrayHasKey('optimized_tax', $result);
        $this->assertArrayHasKey('savings', $result);
        $this->assertArrayHasKey('strategies', $result);
        $this->assertGreaterThanOrEqual(0, $result['savings']);
    }

    /**
     * Test EstatePlanningEngine beneficiary optimization
     */
    public function testEstatePlanningEngineBeneficiaryOptimization(): void
    {
        $beneficiaries = [
            ['name' => 'Spouse', 'relationship' => 'spouse', 'age' => 60],
            ['name' => 'Child 1', 'relationship' => 'child', 'age' => 16], // Minor
            ['name' => 'Child 2', 'relationship' => 'child', 'age' => 30]
        ];

        $accounts = [
            [
                'name' => 'Investment Account',
                'value' => 500000,
                'beneficiaries' => [['name' => 'Child 1', 'type' => 'primary']] // Minor as beneficiary
            ]
        ];

        $result = $this->engine->optimizeBeneficiaryDesignations($beneficiaries, $accounts);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('recommendations', $result);
        $this->assertArrayHasKey('optimized_designations', $result);

        // Should identify minor beneficiary issue
        $issues = array_column($result['issues'], 'type');
        $this->assertContains('minor_beneficiaries', $issues);
    }

    /**
     * Test EstatePlanningEngine projection calculations
     */
    public function testEstatePlanningEngineProjections(): void
    {
        $currentEstate = 2000000;
        $annualGrowthRate = 0.05;
        $years = 10;
        $province = 'ON';

        $result = $this->engine->projectEstateGrowth(
            $currentEstate,
            $annualGrowthRate,
            $years,
            $province,
            2024
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('projected_value', $result);
        $this->assertArrayHasKey('tax_projections', $result);
        $this->assertArrayHasKey('growth_summary', $result);
        $this->assertGreaterThan($currentEstate, $result['projected_value']);
    }

    /**
     * Test invalid inputs throw exceptions
     */
    public function testInvalidInputsThrowExceptions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->taxCalculator->calculateTotalEstateTax(-1000, 'ON', 2024);
    }

    /**
     * Test edge cases
     */
    public function testEdgeCases(): void
    {
        // Test zero estate value
        $result = $this->taxCalculator->calculateTotalEstateTax(0, 'ON', 2024);
        $this->assertEquals(0, $result['total']);

        // Test very large estate
        $largeResult = $this->taxCalculator->calculateTotalEstateTax(50000000, 'ON', 2024);
        $this->assertGreaterThan(0, $largeResult['total']);
    }

    /**
     * Test integration between all components
     */
    public function testIntegrationBetweenComponents(): void
    {
        // Create test data
        $estateData = ['total_value' => 1500000, 'life_insurance' => 100000];
        $beneficiaries = [['name' => 'Spouse', 'relationship' => 'spouse']];
        $accounts = [['name' => 'RRSP', 'value' => 500000, 'beneficiaries' => $beneficiaries]];

        // Test that components work together
        $taxAnalysis = $this->taxCalculator->calculateTotalEstateTax(1500000, 'ON', 2024);
        $transferStrategy = $this->transferOptimizer->optimizeTransferStrategy(
            $estateData,
            $beneficiaries,
            [],
            2024
        );
        $beneficiaryAnalysis = $this->beneficiaryAnalyzer->analyzeBeneficiaries(
            $beneficiaries,
            $accounts,
            $estateData
        );

        // All should return valid results
        $this->assertIsArray($taxAnalysis);
        $this->assertIsArray($transferStrategy);
        $this->assertIsArray($beneficiaryAnalysis);

        // Tax savings from transfer strategy should be calculable
        $this->assertArrayHasKey('tax_savings', $transferStrategy);
        $this->assertIsNumeric($transferStrategy['tax_savings']);
    }
}