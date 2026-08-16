<?php

declare(strict_types=1);

namespace Ksfraser\Estate;

use PDO;

/**
 * Relationship Lookup
 *
 * Resolves free-text relationship terms (e.g. "husband", "son") to canonical
 * beneficiary relationship types backed by the database tables
 * beneficiary_relationship_types and beneficiary_relationship_mappings.
 */
class RelationshipLookup
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get a relationship type row by its canonical code (e.g. "spouse", "child").
     */
    public function getRelationshipTypeByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM beneficiary_relationship_types
             WHERE relationship_code = ? AND is_active = 1"
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Get a relationship type row by its primary key.
     */
    public function getRelationshipTypeById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM beneficiary_relationship_types WHERE relationship_type_id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Resolve a free-text relationship term (e.g. "husband") to its canonical
     * relationship type via the beneficiary_relationship_mappings table.
     */
    public function resolveTerm(string $term): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.* FROM beneficiary_relationship_mappings m
             JOIN beneficiary_relationship_types t
               ON t.relationship_type_id = m.relationship_type_id
             WHERE m.relationship_term = ?"
        );
        $stmt->execute([$term]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Return all active relationship types ordered by inheritance priority.
     */
    public function getAllTypes(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM beneficiary_relationship_types
             WHERE is_active = 1
             ORDER BY inheritance_priority DESC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
