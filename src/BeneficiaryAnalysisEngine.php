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
use Ksfraser\Estate\RecommendationGenerator;
use Ksfraser\Estate\RelationshipAnalyzer;

/**
 * Beneficiary Analysis Engine
 *
 * Analyzes beneficiary designations across various accounts and policies,
 * identifies potential issues, conflicts, and optimization opportunities.
 */
class BeneficiaryAnalysisEngine
{
    private RelationshipAnalyzer $relationshipAnalyzer;
    private RecommendationGenerator $recommendationGenerator;

    public function __construct(
        RelationshipAnalyzer $relationshipAnalyzer,
        RecommendationGenerator $recommendationGenerator
    ) {
        $this->relationshipAnalyzer = $relationshipAnalyzer;
        $this->recommendationGenerator = $recommendationGenerator;
    }
    /**
     * Analyze beneficiary designations
     *
     * @param array $beneficiaries Beneficiary information
     * @param array $accounts Account and policy information
     * @param array $estateData Estate composition data
     * @return array Analysis results
     */
    public function analyzeBeneficiaries(array $beneficiaries, array $accounts, array $estateData): array
    {
        $analysis = [
            'summary' => $this->generateSummary($beneficiaries, $accounts),
            'designations' => $this->analyzeDesignations($beneficiaries, $accounts),
            'conflicts' => $this->identifyConflicts($beneficiaries, $accounts),
            'gaps' => $this->identifyCoverageGaps($beneficiaries, $accounts, $estateData),
            'recommendations' => $this->generateRecommendations($beneficiaries, $accounts, $estateData),
            'relationships' => $this->analyzeRelationships($beneficiaries),
            'tax_implications' => $this->analyzeTaxImplications($beneficiaries, $accounts)
        ];

        return $analysis;
    }

    /**
     * Generate summary of beneficiary analysis
     */
    private function generateSummary(array $beneficiaries, array $accounts): array
    {
        $totalBeneficiaries = count($beneficiaries);
        $totalAccounts = count($accounts);
        $designatedAccounts = count(array_filter($accounts, fn($acc) => !empty($acc['beneficiaries'])));

        return [
            'total_beneficiaries' => $totalBeneficiaries,
            'total_accounts' => $totalAccounts,
            'designated_accounts' => $designatedAccounts,
            'undesignated_accounts' => $totalAccounts - $designatedAccounts,
            'designation_coverage' => $totalAccounts > 0 ? round(($designatedAccounts / $totalAccounts) * 100, 1) : 0,
            'primary_beneficiaries' => count(array_filter($beneficiaries, fn($b) => ($b['type'] ?? '') === 'primary')),
            'contingent_beneficiaries' => count(array_filter($beneficiaries, fn($b) => ($b['type'] ?? '') === 'contingent'))
        ];
    }

    /**
     * Analyze beneficiary designations across accounts
     */
    private function analyzeDesignations(array $beneficiaries, array $accounts): array
    {
        $designations = [];

        foreach ($accounts as $account) {
            $accountBeneficiaries = $account['beneficiaries'] ?? [];

            $designations[] = [
                'account_name' => $account['name'] ?? 'Unknown Account',
                'account_type' => $account['type'] ?? 'unknown',
                'value' => $account['value'] ?? 0,
                'primary_beneficiaries' => array_filter($accountBeneficiaries, fn($b) => ($b['type'] ?? '') === 'primary'),
                'contingent_beneficiaries' => array_filter($accountBeneficiaries, fn($b) => ($b['type'] ?? '') === 'contingent'),
                'has_designation' => !empty($accountBeneficiaries),
                'designation_date' => $account['designation_date'] ?? null,
                'needs_update' => $this->checkIfNeedsUpdate($account)
            ];
        }

        return $designations;
    }

    /**
     * Identify conflicts in beneficiary designations
     */
    private function identifyConflicts(array $beneficiaries, array $accounts): array
    {
        $conflicts = [];

        // Check for outdated designations
        foreach ($accounts as $account) {
            if ($this->checkIfNeedsUpdate($account)) {
                $conflicts[] = [
                    'type' => 'outdated_designation',
                    'severity' => 'high',
                    'account' => $account['name'] ?? 'Unknown',
                    'issue' => 'Beneficiary designation may be outdated',
                    'recommendation' => 'Review and update beneficiary designation'
                ];
            }
        }

        // Check for inconsistent beneficiary designations
        $beneficiaryPatterns = $this->analyzeBeneficiaryPatterns($accounts);
        if ($beneficiaryPatterns['inconsistent_designations'] > 0) {
            $conflicts[] = [
                'type' => 'inconsistent_designations',
                'severity' => 'medium',
                'accounts_affected' => $beneficiaryPatterns['inconsistent_designations'],
                'issue' => 'Inconsistent beneficiary designations across accounts',
                'recommendation' => 'Standardize beneficiary designations for consistency'
            ];
        }

        // Check for minor beneficiaries without proper protections
        $minorBeneficiaries = array_filter($beneficiaries, function($b) {
            $age = $b['age'] ?? 0;
            return $age > 0 && $age < 18;
        });

        if (!empty($minorBeneficiaries)) {
            $conflicts[] = [
                'type' => 'minor_beneficiaries',
                'severity' => 'high',
                'beneficiaries' => array_column($minorBeneficiaries, 'name'),
                'issue' => 'Minor beneficiaries designated without trust protection',
                'recommendation' => 'Establish trusts or custodianship for minor beneficiaries'
            ];
        }

        // Check for estate vs. payable on death conflicts
        $conflicts = array_merge($conflicts, $this->checkEstateConflicts($accounts));

        return $conflicts;
    }

    /**
     * Identify coverage gaps in beneficiary designations
     */
    private function identifyCoverageGaps(array $beneficiaries, array $accounts, array $estateData): array
    {
        $gaps = [];

        // Check for accounts without beneficiary designations
        $undesignatedAccounts = array_filter($accounts, fn($acc) => empty($acc['beneficiaries']));
        if (!empty($undesignatedAccounts)) {
            $totalUndesignatedValue = array_sum(array_column($undesignatedAccounts, 'value'));
            $gaps[] = [
                'type' => 'undesignated_accounts',
                'accounts' => array_column($undesignatedAccounts, 'name'),
                'total_value' => $totalUndesignatedValue,
                'impact' => 'Assets will pass through probate',
                'recommendation' => 'Designate beneficiaries to avoid probate delays and costs'
            ];
        }

        // Check for missing contingent beneficiaries
        $missingContingents = array_filter($accounts, function($acc) {
            $beneficiaries = $acc['beneficiaries'] ?? [];
            $primaries = array_filter($beneficiaries, fn($b) => ($b['type'] ?? '') === 'primary');
            $contingents = array_filter($beneficiaries, fn($b) => ($b['type'] ?? '') === 'contingent');
            return !empty($primaries) && empty($contingents);
        });

        if (!empty($missingContingents)) {
            $gaps[] = [
                'type' => 'missing_contingents',
                'accounts' => array_column($missingContingents, 'name'),
                'impact' => 'No backup beneficiaries if primary beneficiaries predecease',
                'recommendation' => 'Designate contingent beneficiaries'
            ];
        }

        // Check for retirement accounts payable to estate
        $estatePayableRetirement = array_filter($accounts, function($acc) {
            return ($acc['type'] ?? '') === 'retirement' &&
                   isset($acc['payable_to_estate']) &&
                   $acc['payable_to_estate'] === true;
        });

        if (!empty($estatePayableRetirement)) {
            $gaps[] = [
                'type' => 'retirement_to_estate',
                'accounts' => array_column($estatePayableRetirement, 'name'),
                'impact' => 'Retirement assets subject to probate and potential loss of creditor protection',
                'recommendation' => 'Change retirement account beneficiaries from estate to individuals'
            ];
        }

        return $gaps;
    }

    /**
     * Generate recommendations for beneficiary optimization
     */
    private function generateRecommendations(array $beneficiaries, array $accounts, array $estateData): array
    {
        return $this->recommendationGenerator->generateRecommendations($beneficiaries, $accounts, $estateData);
    }

    /**
     * Analyze beneficiary relationships
     */
    private function analyzeRelationships(array $beneficiaries): array
    {
        return $this->relationshipAnalyzer->analyzeRelationships($beneficiaries);
    }

    /**
     * Analyze tax implications of beneficiary designations
     */
    private function analyzeTaxImplications(array $beneficiaries, array $accounts): array
    {
        $taxAnalysis = [
            'spousal_rollover_opportunities' => [],
            'non_spouse_beneficiaries' => [],
            'charitable_beneficiaries' => [],
            'trust_beneficiaries' => [],
            'tax_efficient_accounts' => []
        ];

        // Identify spousal rollover opportunities
        $spouseBeneficiaries = $this->relationshipAnalyzer->getBeneficiariesByCategory($beneficiaries, 'spouse');

        if (!empty($spouseBeneficiaries)) {
            $taxAnalysis['spousal_rollover_opportunities'] = [
                'available' => true,
                'beneficiaries' => array_column($spouseBeneficiaries, 'name'),
                'tax_advantage' => 'Complete tax-deferred transfer to surviving spouse'
            ];
        }

        // Identify accounts that could benefit from spousal designation
        $highValueAccounts = array_filter($accounts, fn($acc) => ($acc['value'] ?? 0) > 100000);
        $taxAnalysis['tax_efficient_accounts'] = array_map(function($acc) {
            return [
                'name' => $acc['name'],
                'value' => $acc['value'],
                'current_beneficiaries' => array_column($acc['beneficiaries'] ?? [], 'name'),
                'spousal_advantage' => 'Consider spousal beneficiary for tax-deferred growth'
            ];
        }, $highValueAccounts);

        return $taxAnalysis;
    }

    // Helper methods
    private function checkIfNeedsUpdate(array $account): bool
    {
        $designationDate = $account['designation_date'] ?? null;
        if (!$designationDate) return true;

        $date = strtotime($designationDate);
        $threeYearsAgo = strtotime('-3 years');

        return $date < $threeYearsAgo;
    }

    private function analyzeBeneficiaryPatterns(array $accounts): array
    {
        $patterns = [
            'inconsistent_designations' => 0,
            'common_beneficiaries' => [],
            'unique_designations' => 0
        ];

        // This is a simplified analysis - in practice, you'd compare beneficiary designations
        // across accounts to identify inconsistencies

        return $patterns;
    }

    private function checkEstateConflicts(array $accounts): array
    {
        $conflicts = [];

        // Check for conflicts between will/trust and beneficiary designations
        // This would require integration with estate planning documents

        return $conflicts;
    }
}