# AGENTS.md - ksf_estate

> **DO NOT MODIFY THIS FILE.** Create `AGENTS.local.md` for project-specific overrides.

## Core Philosophy

This project follows enterprise-grade software engineering principles. Every decision should align with: **SOLID**, **DRY**, **SRP**, **DI**, and **TDD**.

---

## Architecture Overview

Estate-planning business-logic library (the `ksf_estate` package) for the KSF estate
aggregator. Framework-agnostic core consumed by:

- **`ksf_FA_estate`** — FrontAccounting module (backend / source of truth)
- **`ksf_WP_estate`** — WordPress portal module (user-facing data capture)

WP is a portal: user-entered data is stored locally then pushed to FA via API. FA is the
source of truth except for fresh user input. Shared math lives here and is reused by both
modules through Composer. Jurisdiction: Canada by default; non-CA logic behind a flag.

Engines implement the shared `Ksfraser\ModulesCommon\CalculationEngineInterface`
contract (calculate / validate / getCalculationType / getRequiredParameters /
getOptionalParameters) and return a `Ksfraser\ModulesCommon\CalculationResult`.

---

## Repository Structure

```
ksf_estate/
├── src/
│   ├── EstatePlanningEngine.php          # Top-level orchestrator engine
│   ├── EstateTaxCalculator.php           # Federal/provincial estate tax
│   ├── ProbateFeeLookup.php              # DB-driven probate fees (falls back to constants)
│   ├── BeneficiaryAnalysisEngine.php     # Gaps/conflicts/recommendations
│   ├── WealthTransferOptimizer.php       # Transfer-strategy optimization
│   ├── DebtManagementEngine.php          # Debt categorization / payoff (FR-001-013)
│   ├── CharitableGivingEngine.php        # Charitable giving (FR-001-014)
│   ├── RelationshipLookup.php            # Resolve relationship terms -> types
│   ├── RelationshipAnalyzer.php          # Analyze/categorize relationships
│   ├── RecommendationLookup.php          # Load recommendation templates
│   └── RecommendationGenerator.php       # Match recommendations to trigger conditions
├── tests/
│   ├── EstatePlanningEngineTest.php
│   ├── DebtManagementEngineTest.php
│   └── CharitableGivingEngineTest.php
├── Requirements/
│   ├── BR-001 EstatePlanning.md
│   ├── FR-001-001 .. FR-001-014 (one file per requirement)
│   ├── UC-001-001 .. UC-001-006 (use cases)
│   └── Architecture/Estate_Planning_Design.md
├── composer.json
├── phpunit.xml.dist
└── AGENTS.md
```

---

## Namespace Convention

```php
Ksfraser\Estate\                            # Root namespace (PSR-4 -> src/)
Ksfraser\Estate\Tests\                      # Test namespace (PSR-4 -> tests/)
```

Shared contracts come from `Ksfraser\ModulesCommon` (CalculationEngineInterface,
CalculationContext, CalculationResult, CalculationException, ParameterDefinition,
ValidationResult) and `Ksfraser\Exceptions\Domain` (domain exceptions).

> **Namespace inconsistency note:** older KSF packages use the lowercase `ksfraser\...`
> root (e.g. `ksf_GPG`). New packages (this one, `ksf_modules_common`) use `Ksfraser\...`.
> Keep `Ksfraser\Estate\...` internally consistent within this package.

---

## Coding Standards

### PHP Compatibility — ⚠️ OPEN ISSUE

- **House standard (see ksf_GPG/AGENTS.md): PHP 7.3 (FA 2.4.19) — no PHP 8+ features.**
- **CURRENT STATE: this package currently uses PHP 8.1 syntax** (`match()`, `fn()` arrow
  functions, typed properties, `readonly` properties) and will **NOT parse on PHP 7.3**.
  This is tech debt that MUST be backported to 7.3-compatible syntax before deployment to
  the FA 2.4.19 (PHP 7.3) runtime, OR the estate/FA runtime must be upgraded to PHP 8.1
  (which requires dropping the 7.3 constraint project-wide). **This decision is pending.**
- Use `declare(strict_types=1);` at the top of all PHP files.
- Until the above is resolved, development/tests run on PHP 8.1.

### Naming Conventions

- **Engines**: `XxxEngine` (e.g., `EstatePlanningEngine`), implement `CalculationEngineInterface`
- **Interfaces**: `InterfaceNameInterface`
- **Exceptions**: `ExceptionNameException` — domain exceptions from `Ksfraser\Exceptions\Domain`;
  `CalculationException` specifically comes from `Ksfraser\ModulesCommon` (NOT
  `Ksfraser\Exceptions\Domain` — that namespace has no `CalculationException`).
- **Lookup/Analyzer/Generator**: collaborator services for the beneficiary subsystem

### Documentation

Every class/method MUST have a DocBlock (`@param`, `@return`, `@throws`, `@since`).

---

## Dependencies

### Required Libraries (composer.json)

```json
{
    "require": {
        "php": ">=7.3",
        "ksfraser/exceptions": "^1.3",
        "ksfraser/ksf_modules_common": "^1.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.0"
    }
}
```

- `ksfraser/exceptions` **is on public Packagist** (v1.3.0). ✅
- `ksfraser/ksf_modules_common` is **NOT on Packagist** (it is a private/proprietary
  package). To `composer install` locally you must make it resolvable, e.g. a global path
  repository (keeps `composer.json` clean):

  ```bash
  composer config -g repositories.ksf_modules_common \
    '{"type":"path","url":"/home/kevin/Documents/ksf_modules_common","options":{"versions":{"ksfraser/ksf_modules_common":"1.0.0"}}}'
  COMPOSER_ALLOW_SUPERUSER=1 composer install
  ```

  **For CI / the `ksf_FA_estate` and `ksf_WP_estate` modules to install, `ksf_modules_common`
  must be published** (Packagist or a private Satis). This is a deployment prerequisite.

---

## Engines

| Engine | Responsibility | Requirement |
|--------|----------------|-------------|
| `EstatePlanningEngine` | Orchestrates tax/probate/beneficiary/transfer analysis | FR-001-001..012 |
| `EstateTaxCalculator` | Federal + provincial estate tax (DB-backed rates, fallback years) | FR-001-002 |
| `ProbateFeeLookup` | Province probate fees (DB-backed, constant fallback) | FR-001-001 |
| `BeneficiaryAnalysisEngine` | Gaps, conflicts, recommendations | FR-001-010 |
| `WealthTransferOptimizer` | Transfer-strategy scoring/optimization | FR-001-011 |
| `DebtManagementEngine` | Debt categorization, liquidity, payoff plan | FR-001-013 |
| `CharitableGivingEngine` | Donations, tax savings, giving vehicles | FR-001-014 |

---

## Testing Standards

### TDD Workflow

1. **RED**: Write a failing test.
2. **GREEN**: Write minimal code to pass.
3. **REFACTOR**: Improve while keeping tests green.

### Running tests

```bash
COMPOSER_ALLOW_SUPERUSER=1 composer install
vendor/bin/phpunit --testdox
```

Current suite: **47 tests / 205 assertions, all green.**

### Coverage Requirements

- **Target**: 100% code coverage for engine logic.
- All new code requires tests.
- The `EstatePlanningEngineTest` builds an in-memory SQLite `PDO` and seeds the
  `estate_tax_rates`, `beneficiary_relationship_types`, `beneficiary_relationship_mappings`,
  and `beneficiary_recommendations` tables. Note: there is intentionally **no**
  `probate_fees` table in the test DB — `ProbateFeeLookup` falls back to constant-based
  fees and the engine handles that gracefully.

---

## BABOK / Requirements Convention

- One file per requirement: `FR-XXX-YYY Name.md` with sections
  `# FR-XXX-YYY Name`, `## Description`, `## Acceptance Criteria`, `## Priority`,
  `## Status`.
- Business rules: `BR-001 EstatePlanning.md`. Use cases: `UC-001-NNN`.
- Design: `Requirements/Architecture/Estate_Planning_Design.md` (UML, DTOs, API,
  shared-math interface, identified gaps).
