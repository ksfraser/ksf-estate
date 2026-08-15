# BR-001 EstatePlanning: Estate Planning Module

## Description
The system shall provide an estate planning capability that lets an advisor capture a
client's estate inventory (assets, liabilities, beneficiary designations, will and power
of attorney status), estimate provincial probate/estate administration fees, estimate the
final tax return and deemed-disposition capital gains liability, surface beneficiary
designation gaps, present the executor 48-60-6 action framework, support wealth-transfer
and estate-freeze planning (corporate freeze, LCGE, life insurance, trusts), and produce a
consolidated estate plan summary. It is jurisdiction-aware (Canadian default), supports both
advisor and client views, and may use LLM-assisted checklist/confirmation/reminder/draft
capabilities within guardrails. Estate planning is a core topic every CFP discusses with
clients and is currently absent from ksfii_app.

## Rationale
ksfii_app covers retirement income (BR-RET-001), insurance needs (BR-FNA-005), and budgeting
(FCAC toolkit), but has no estate planning surface. A CFP engagement is incomplete without
will/POA tracking, probate exposure, beneficiary coordination across registered accounts, and
wealth-transfer planning.

## Scope
- In scope: estate inventory (FR-001-003), beneficiary designation tracking (FR-001-010),
  provincial probate fee estimation (FR-001-001), final-return / deemed-disposition tax
  estimate (FR-001-002), will & POA status and review (FR-001-009), jurisdiction-aware rules
  (FR-001-005), advisor/client two views (FR-001-006), LLM assistant skills (FR-001-007),
  executor 48-60-6 framework (FR-001-008), wealth transfer / estate freeze (FR-001-011),
  compliance hooks (FR-001-012), gap reporting, printable summary (FR-001-004).
- Out of scope: legal document generation, trust drafting, actual tax filing, notarization,
  AML/CASL record-keeping (owned by the CRM module, AR-007).

## Acceptance Criteria
1. Advisor can record estate assets and liabilities per client (debtor_no) (FR-001-003).
2. Advisor can record beneficiary designations per registered asset and flag missing ones
   (FR-001-010).
3. System estimates probate/estate administration fees for the client's jurisdiction
   (FR-001-001, FR-001-005).
4. System estimates the final return tax (inclusion of RRIF/RRSP, deemed disposition of
   capital property) for a resident estate (FR-001-002, FR-001-005).
5. System tracks will existence/date and power of attorney existence/date, and flags stale
   or conflicting documents (FR-001-009).
6. System produces a consolidated estate plan summary with identified gaps (FR-001-004).
7. System presents the executor 48-60-6 framework and wealth-transfer/freeze planning aids
   (FR-001-008, FR-001-011).
8. All data is segregated per FA company (debtor_no); no cross-client leakage (FR-001-006).

## Priority
High

## Status
Draft
