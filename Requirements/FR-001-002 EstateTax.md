# FR-001-002 EstateTax: Final Return and Deemed-Disposition Tax Estimate

## Description
The system shall estimate the income tax on a resident deceased taxpayer's final return,
including the inclusion of RRSP/RRIF balances and the deemed disposition of capital
property at fair market value.

## Acceptance Criteria
1. RRSP/RRIF balances are included in income at death (unless rolled to a qualifying
   beneficiary).
2. Non-registered investments are subject to deemed disposition at FMV; the estimate uses
   an assumed cost base and an effective capital gains inclusion rate.
3. The estimate applies a configurable effective marginal tax rate (default from
   `TaxRate` service / province).
4. Life insurance death benefit proceeds are excluded from income (except for exempt
   policy gains where applicable).
5. Output reports: included RRSP/RRIF income, estimated capital gains tax, total estimated
   final-return tax.
6. Calculation is clearly labelled an estimate for planning, not a filing.

## Priority
Medium

## Status
Draft
