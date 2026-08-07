# FR-001-001 ProbateFees: Provincial Probate / Estate Administration Fee Estimation

## Description
The system shall estimate provincial probate (estate administration) fees based on the
client's province of residence and the value of the estate passing through the estate
(assets not passing by beneficiary designation or joint tenancy).

## Acceptance Criteria
1. A province-specific fee schedule table drives the calculation (AB, BC, MB, NB, NL, NS,
   NT, NU, ON, PE, QC, SK, YT).
2. Calculation uses the estate value subject to probate (gross estate minus assets with
   valid beneficiary designations and jointly-held assets, minus debts).
3. Output includes the estimated fee and the marginal/blended rate where applicable.
4. Unknown province defaults to a clear "not configured" message rather than a wrong number.
5. Fee schedule is data-driven (editable lookup table `ksfii_estate_probate_schedule`)
   so rates can be updated without code changes.

## Priority
High

## Status
Draft
