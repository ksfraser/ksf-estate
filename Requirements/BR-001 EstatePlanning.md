# BR-001 EstatePlanning: Estate Planning Module

## Description
The system shall provide an estate planning capability that lets an advisor capture a
client's estate inventory (assets, liabilities, beneficiary designations, will and power
of attorney status), estimate provincial probate/estate administration fees, estimate the
final tax return and deemed-disposition capital gains liability, surface beneficiary
designation gaps, and produce a consolidated estate plan summary. Estate planning is a core
topic every CFP discusses with clients and is currently absent from ksfii_app.

## Rationale
ksfii_app covers retirement income (BR-RET-001), insurance needs (BR-FNA-005), and budgeting
(FCAC toolkit), but has no estate planning surface. A CFP engagement is incomplete without
will/POA tracking, probate exposure, and beneficiary coordination across registered accounts.

## Scope
- In scope: estate inventory, beneficiary designation tracking, provincial probate fee
  estimation, final-return / deemed-disposition tax estimate, will & POA status, gap
  reporting, printable summary.
- Out of scope: legal document generation, trust drafting, actual tax filing, notarization.

## Acceptance Criteria
1. Advisor can record estate assets and liabilities per client (debtor_no).
2. Advisor can record beneficiary designations per registered asset and flag missing ones.
3. System estimates probate/estate administration fees for the client's province.
4. System estimates the final return tax (inclusion of RRIF/RRSP, deemed disposition of
   capital property) for a resident estate.
5. System tracks will existence/date and power of attorney existence/date.
6. System produces a consolidated estate plan summary with identified gaps.
7. All data is segregated per FA company (debtor_no); no cross-client leakage.

## Priority
High

## Status
Draft
