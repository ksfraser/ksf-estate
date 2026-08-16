<?php

declare(strict_types=1);

namespace Ksfraser\Estate;

/**
 * Recommendation Generator
 *
 * Evaluates beneficiary recommendation templates against the computed state of
 * a client's beneficiaries and accounts, returning only the recommendations
 * whose trigger conditions are satisfied.
 */
class RecommendationGenerator
{
    private $lookup;

    public function __construct(RecommendationLookup $lookup)
    {
        $this->lookup = $lookup;
    }

    /**
     * Generate the recommendations applicable to the supplied beneficiaries,
     * accounts, and estate data.
     *
     * @param array $beneficiaries
     * @param array $accounts
     * @param array $estateData
     * @return array
     */
    public function generateRecommendations(array $beneficiaries, array $accounts, array $estateData): array
    {
        $fields = $this->computeTriggerFields($beneficiaries, $accounts, $estateData);
        $recommendations = $this->lookup->getAllRecommendations();

        $matched = [];

        foreach ($recommendations as $recommendation) {
            $conditions = json_decode($recommendation['trigger_conditions'] ?? '[]', true);
            if (!is_array($conditions)) {
                $conditions = [];
            }

            if ($this->conditionsMet($conditions, $fields)) {
                $matched[] = [
                    'code' => $recommendation['recommendation_code'],
                    'priority' => $recommendation['priority'],
                    'category' => $recommendation['category'],
                    'title' => $recommendation['title'],
                    'description' => $recommendation['description'],
                    'rationale' => $recommendation['rationale'],
                ];
            }
        }

        return $matched;
    }

    /**
     * Derive the numeric trigger fields used to evaluate recommendation
     * conditions from the client's beneficiaries and accounts.
     */
    private function computeTriggerFields(array $beneficiaries, array $accounts, array $estateData): array
    {
        $fields = [
            'outdated_accounts' => 0,
            'missing_contingents' => 0,
            'minor_beneficiaries' => 0,
            'undesignated_accounts' => 0,
            'high_concentration_risk' => 0,
            'retirement_to_estate' => 0,
        ];

        foreach ($accounts as $account) {
            $designations = $account['beneficiaries'] ?? [];

            if (empty($designations)) {
                $fields['undesignated_accounts']++;
            }

            $primaries = array_filter($designations, function ($b) { return ($b['type'] ?? '') === 'primary'; });
            $contingents = array_filter($designations, function ($b) { return ($b['type'] ?? '') === 'contingent'; });
            if (!empty($primaries) && empty($contingents)) {
                $fields['missing_contingents']++;
            }

            $designationDate = $account['designation_date'] ?? null;
            if (!$designationDate || strtotime($designationDate) < strtotime('-3 years')) {
                $fields['outdated_accounts']++;
            }

            if (($account['type'] ?? '') === 'retirement' && ($account['payable_to_estate'] ?? false)) {
                $fields['retirement_to_estate']++;
            }
        }

        foreach ($beneficiaries as $beneficiary) {
            $age = (int) ($beneficiary['age'] ?? 0);
            if ($age > 0 && $age < 18) {
                $fields['minor_beneficiaries']++;
            }
        }

        $maxPercentage = 0;
        foreach ($beneficiaries as $beneficiary) {
            $maxPercentage = max($maxPercentage, (float) ($beneficiary['percentage'] ?? 0));
        }
        if ($maxPercentage > 50 && count($beneficiaries) > 1) {
            $fields['high_concentration_risk'] = 1;
        }

        return $fields;
    }

    /**
     * Evaluate a list of trigger conditions against the computed fields.
     * All conditions must be satisfied for the recommendation to apply.
     */
    private function conditionsMet(array $conditions, array $fields): bool
    {
        if (empty($conditions)) {
            return false;
        }

        foreach ($conditions as $condition) {
            $field = (string) ($condition['field'] ?? '');
            $operator = (string) ($condition['operator'] ?? 'greater_than');
            $value = (float) ($condition['value'] ?? 0);
            $actual = (float) ($fields[$field] ?? 0);

            switch ($operator) {
                case 'less_than':
                    if (!($actual < $value)) {
                        return false;
                    }
                    break;
                case 'equals':
                    if (!($actual == $value)) {
                        return false;
                    }
                    break;
                case 'greater_than':
                default:
                    if (!($actual > $value)) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }
}
