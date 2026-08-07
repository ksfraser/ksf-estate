# Estate Planning — Architecture & Status

**Module:** ksfii_app
**Status:** Calculation engines BUILT (shared-libraries), NOT deployed to FA module.
**Related app:** PersonalRecordsOrganizer (Estate Planning Manager / EPM) — executor-facing records.

## What already exists (do NOT re-build)

Located in `CODE/shared-libraries/CanadaLife/Calculations/` (namespace `KSFII\Calculations`),
driven by `CalculationEngineInterface` + `CalculationContext`/`CalculationResult`, PDO-backed:

| Engine | File | Covers |
|--------|------|--------|
| EstatePlanningEngine | `EstatePlanningEngine.php` (1,112 ln) | Will/intestate analysis, probate, estate tax, beneficiary distributions, inheritance projections, life insurance, trusts, equalization, recommendations, risk, action items |
| EstateTaxCalculator | `EstateTaxCalculator.php` | Federal + provincial estate tax |
| ProbateFeeLookup | `ProbateFeeLookup.php` | Province probate fees (DB-backed, with fallback) |
| BeneficiaryAnalysisEngine | `BeneficiaryAnalysisEngine.php` | Beneficiary gap / designation analysis |
| WealthTransferOptimizer | `WealthTransferOptimizer.php` | Transfer strategies |
| SuccessionPlanningEngine / BusinessValuationEngine / BuySellAgreementAnalyzer | `SuccessionPlanningEngine.php` etc. | Business-owner succession (CFP topic, already built) |
| TaxLocationOptimizer / WithdrawalSequencingEngine / InvestmentVehicleComparisonTool | — | Tax/location optimization (CFP topic, already built) |

Supporting: `CODE/tests/unit/CanadaLife/Calculations/EstatePlanningEngineTest.php`
(+ `.bak`), `scripts/seed_estate_tax_rates.php`, `CL_XLS/estateTransfer.xlsm`,
`CL_XLS/corporateEstateTransfer.xlsm`.

## The actual gap: NOT DEPLOYED

Per topology note (2026-07-29): *"Estate Planning sub-app: exists only as
code/tests/docs in ksfii_app. Not deployed. No URL, no DB tables, no credentials."*

The engines are standalone libraries. They are **not wired into**:
- the FrontAccounting module UI (`CODE/frontaccounting-module/ui/`),
- FA DB tables,
- the ksfii_app client portal.

So the CFP topics the user listed are present as *calculation logic* but absent as
*deployable features*. This is the work that remains — not re-authoring the math.

## PersonalRecordsOrganizer (EPM) relationship

`/home/kevin/Documents/PersonalRecordsOrganizer/` is a separate WordPress + SuiteCRM app
("Estate Planning Manager") that stores the *where are the things* layer:

- 17 data sections: personal info, family, key contacts (lawyers/doctors), **Wills & POA**,
  funeral/organ donation, taxes, military, employment, volunteer/charitable, **bank accounts**,
  **investments**, **real estate**, personal property, **digital assets (passwords)**,
  scheduled payments, **debtors & creditors**, **insurance & beneficiaries**.
- Architecture explicitly supports "additional platforms (e.g., FrontAccounting)" via shared
  table classes + `suitecrm_guid` / `wp_record_id` sync fields.

**Relationship:** EPM = executor-facing *record locator* (documents, accounts, passwords,
beneficiary names). ksfii_app estate engines = the *calculation/planning* layer. They are
complementary: EPM supplies the inventory/designations that the estate engines consume, and
the estate plan summary should link back to EPM records for the client meeting.

## Remaining CFP topics to verify / build

| Topic | Status |
|-------|--------|
| Estate planning | Built (engine), not deployed |
| Business succession | Built (engine), not deployed |
| Tax strategy / location | Built (TaxLocationOptimizer etc.) |
| RESP / education | Workflow doc exists (`Requirements/SuiteCRM Implementation/RESP_Workflow_Setup.md`); no dedicated calculation engine found — verify |
| Debt management | **No engine found — likely genuine gap** |
| Charitable giving / bequests | **No engine found — likely genuine gap** (EPM has a Volunteer/charitable section only) |

## Recommendation

1. Do not duplicate the estate math. Wire the existing `EstatePlanningEngine` into the FA
   module (adapter page + DB wiring) to *deploy* it.
2. Build the two genuine gaps: **Debt Management** engine and **Charitable Giving / Bequest**
   engine, in the same `KSFII\Calculations` pattern.
3. Add a cross-reference requirement linking ksfii_app estate outputs to PersonalRecordsOrganizer
   records (executor package).

## RTM (requirements present)
BR-001 EstatePlanning, FR-001-003 InventoryCapture..004, UC-001-001 EstateUseCases — formal BABOK requirement docs (added this session;
the code pre-existed). These describe the deployed feature, not the standalone engine.
