<?php

declare(strict_types=1);

namespace Ksfraser\Estate;

use PDO;

/**
 * Recommendation Lookup
 *
 * Loads beneficiary recommendation templates from the
 * beneficiary_recommendations table.
 */
class RecommendationLookup
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Return all active beneficiary recommendations.
     */
    public function getAllRecommendations(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM beneficiary_recommendations WHERE is_active = 1"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return a single recommendation by its unique code.
     */
    public function getRecommendationByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM beneficiary_recommendations WHERE recommendation_code = ?"
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
