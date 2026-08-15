# FR-001-006 TwoViews: Advisor and Client Views

## Description
The estate planning module shall support two roles: an Advisor (FA staff) who can create, edit,
and run plans, and a Client (the FA customer, debtor_no) who can view and confirm their data
through the WordPress portal. Access is enforced by RBAC; a client never sees another client's
data.

## Acceptance Criteria
1. Advisor view (FA) allows full create/read/update on estate plans for assigned clients.
2. Client view (WP portal)) allows read of the client's own plan and entry/confirmation of their
   own data; it cannot edit advisor-only fields.
3. All reads/writes are scoped to the client's FA company (debtor_no); no cross-client leakage.
4. Role is derived from the authenticated user (API auth + company number, per AR-003).

## Priority
High

## Status
Draft
