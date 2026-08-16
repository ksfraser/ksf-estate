<?php

declare(strict_types=1);

namespace Ksfraser\Estate;

/**
 * Relationship Analyzer
 *
 * Analyzes beneficiary relationship terms and groups beneficiaries by their
 * resolved relationship category (spouse, children, parents, siblings, etc.).
 */
class RelationshipAnalyzer
{
    private RelationshipLookup $lookup;

    public function __construct(RelationshipLookup $lookup)
    {
        $this->lookup = $lookup;
    }

    /**
     * Analyze the relationship of each beneficiary, resolving the term to a
     * canonical relationship type where possible.
     *
     * @param array $beneficiaries
     * @return array
     */
    public function analyzeRelationships(array $beneficiaries): array
    {
        $analysis = [];

        foreach ($beneficiaries as $beneficiary) {
            $term = (string) ($beneficiary['relationship'] ?? '');
            $type = $this->lookup->resolveTerm($term) ?? $this->lookup->getRelationshipTypeByCode($term);

            $analysis[] = [
                'name' => $beneficiary['name'] ?? 'Unknown',
                'relationship' => $term,
                'category' => $type['category'] ?? 'other',
                'display_name' => $type['display_name'] ?? ucfirst($term),
                'inheritance_priority' => (int) ($type['inheritance_priority'] ?? 0),
                'tax_implications' => $type['tax_implications'] ?? 'Depends on relationship',
            ];
        }

        return $analysis;
    }

    /**
     * Return only the beneficiaries whose resolved relationship category matches.
     *
     * @param array  $beneficiaries
     * @param string $category
     * @return array
     */
    public function getBeneficiariesByCategory(array $beneficiaries, string $category): array
    {
        return array_values(array_filter($beneficiaries, function ($beneficiary) use ($category) {
            $term = (string) ($beneficiary['relationship'] ?? '');
            $type = $this->lookup->resolveTerm($term) ?? $this->lookup->getRelationshipTypeByCode($term);

            return ($type['category'] ?? 'other') === $category;
        }));
    }
}
