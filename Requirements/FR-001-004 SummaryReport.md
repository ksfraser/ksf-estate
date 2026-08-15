# FR-001-004 SummaryReport: Consolidated Estate Plan Summary

## Description
The system shall produce a consolidated, client-ready estate plan summary that compiles the
captured inventory, probate fee estimate, final-return tax estimate, beneficiary
designation gaps, will/POA status, and recommended next steps.

## Acceptance Criteria
1. Summary compiles inventory (assets/liabilities), probate estimate (FR-001-001), tax
   estimate (FR-001-002), and the gap report.
2. Summary surfaces beneficiary designation gaps and missing/needed designations.
3. Summary reports will existence/date and power of attorney existence/date, and flags
   documents older than 5 years as stale (will/POA review trigger).
4. Summary reflects the executor action framework — first 48 hours (secure house/mail, find
   original will, register death), first 60 days (notify institutions, claim CPP death
   benefit), first 6 months (probate, final CRA return, clearance certificate) — so the
   client/family knows what to do, who to call, and in what order.
5. Summary is exportable/printable for an advisor-led client meeting (advisor view) and
   shareable in a client-appropriate form (client view); it is clearly labelled an
   estimate/plan for discussion, not a legal or tax filing.
6. All data reflects the client's FA company (debtor_no) only.

## Priority
High

## Status
Draft
