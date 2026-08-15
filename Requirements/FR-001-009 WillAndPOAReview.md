# FR-001-009 WillAndPOAReview: Will and Power of Attorney Review

## Description
The system shall track will and power of attorney existence, dates, and review status, and
surface when documents are stale or when a power of attorney limitation applies.

## Acceptance Criteria
1. System records will existence/date and power of attorney existence/date (per BR-001 / UC-001-005).
2. Documents older than 5 years are flagged as stale and trigger a review reminder (FR-001-007).
3. A "plan vs will" gap is surfaced: where the estate plan (beneficiary designations, asset
   ownership) disagrees with the will, the advisor is alerted.
4. The system notes POA limitations: a spouse is NOT automatically the other's attorney; a POA
   cannot change a will or a beneficiary designation; the plan reflects this.

## Priority
High

## Status
Draft
