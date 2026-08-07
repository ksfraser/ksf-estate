<?php

declare(strict_types=1);

/**
 * Estate Tax Rates Data Seeding Script
 *
 * This script populates the estate_tax_rates table with current Canadian estate tax rates.
 * Run this script after creating the estate_tax_rates table in your database.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Database connection
$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST'] ?? 'localhost',
    $_ENV['DB_NAME'] ?? 'ksfii_app'
);

$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Connected to database successfully.\n";

    // Clear existing data
    $pdo->exec("DELETE FROM estate_tax_rates");
    echo "Cleared existing estate tax rates.\n";

    // Federal estate tax rates
    $federalRates = [
        ['year' => 2025, 'threshold' => 15000000.00, 'rate' => 0.20],
        ['year' => 2024, 'threshold' => 14000000.00, 'rate' => 0.20],
        ['year' => 2023, 'threshold' => 13000000.00, 'rate' => 0.20],
    ];

    // Provincial estate tax rates (sample - adjust as needed)
    $provincialRates = [
        'ON' => [
            ['year' => 2025, 'threshold' => 5000000.00, 'rate' => 0.02],
            ['year' => 2024, 'threshold' => 5000000.00, 'rate' => 0.02],
        ],
        'QC' => [
            ['year' => 2025, 'threshold' => 5000000.00, 'rate' => 0.02],
            ['year' => 2024, 'threshold' => 5000000.00, 'rate' => 0.02],
        ],
        'BC' => [
            ['year' => 2025, 'threshold' => 5000000.00, 'rate' => 0.02],
            ['year' => 2024, 'threshold' => 5000000.00, 'rate' => 0.02],
        ],
        'AB' => [
            ['year' => 2025, 'threshold' => 0.00, 'rate' => 0.00], // No estate tax
            ['year' => 2024, 'threshold' => 0.00, 'rate' => 0.00],
        ],
        // Add other provinces as needed
    ];

    $stmt = $pdo->prepare("
        INSERT INTO estate_tax_rates (
            tax_year, jurisdiction, threshold, rate, effective_date, is_active
        ) VALUES (?, ?, ?, ?, ?, 1)
    ");

    // Insert federal rates
    foreach ($federalRates as $rate) {
        $effectiveDate = $rate['year'] . '-01-01';
        $stmt->execute([
            $rate['year'],
            'FEDERAL',
            $rate['threshold'],
            $rate['rate'],
            $effectiveDate
        ]);
        echo "Inserted federal rate for {$rate['year']}: threshold \${$rate['threshold']}, rate {$rate['rate']}%\n";
    }

    // Insert provincial rates
    foreach ($provincialRates as $province => $rates) {
        foreach ($rates as $rate) {
            $effectiveDate = $rate['year'] . '-01-01';
            $stmt->execute([
                $rate['year'],
                $province,
                $rate['threshold'],
                $rate['rate'],
                $effectiveDate
            ]);
            echo "Inserted {$province} rate for {$rate['year']}: threshold \${$rate['threshold']}, rate " . ($rate['rate'] * 100) . "%\n";
        }
    }

    echo "\nEstate tax rates seeding completed successfully!\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}