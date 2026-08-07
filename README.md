# ksf_estate

Estate planning **business-logic** engines for the KSF financial-planning platform.
Part of the `ksf_estate` / `ksf_FA_estate` / `ksf_WP_estate` triple — common calculation
logic shared across FrontAccounting, SuiteCRM, and WordPress (following the
`ksf_<module>` = logic / `ksf_FA_<module>` = FA UI / `ksf_WP_<module>` = WordPress UI pattern).

## Engines (namespace `Ksfraser\Estate`)

| Class | Responsibility |
|-------|----------------|
| `EstatePlanningEngine` | Orchestrator: will/intestate analysis, probate, estate tax, beneficiary distributions, inheritance projections, life insurance, trusts, equalization, recommendations |
| `EstateTaxCalculator` | Federal + provincial estate/deemed-disposition tax |
| `ProbateFeeLookup` | Provincial probate (estate administration) fees, DB-backed with fallback |
| `BeneficiaryAnalysisEngine` | Beneficiary designation / gap analysis |
| `WealthTransferOptimizer` | Transfer-strategy optimization |
| `CalculationEngineInterface` / `CalculationContext` / `CalculationResult` / `CalculationException` | Shared calculation-framework contracts (bundled here; to be extracted to `ksf_calculation` when further domains split) |

## Requirements (BABOK)

See `Requirements/` — per-file BABOK naming (`BR-EST-*`, `FR-EST-*`, `UC-EST-*`).

## Usage

```php
use Ksfraser\Estate\EstatePlanningEngine;
use Ksfraser\Estate\CalculationContext;

$engine = new EstatePlanningEngine($pdo, $transferOptimizer, $beneficiaryAnalyzer);
$result = $engine->calculate(new CalculationContext('estate_planning', $parameters));
```

## Tests

```bash
composer install
vendor/bin/phpunit
```

## Status

Extracted from `ksfii_app` (was `CODE/shared-libraries/CanadaLife/Calculations/`).
Not yet wired into the FA module UI — that lives in `ksf_FA_estate`.
