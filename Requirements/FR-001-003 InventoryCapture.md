# FR-001-003 InventoryCapture: Estate Inventory Capture

## Description
The system shall let an advisor capture a client's estate inventory — assets (registered
and non-registered), liabilities, and beneficiary designations — per FA company
(debtor_no), and persist it for use by probate, tax, and summary calculations. Capture
also records where key documents (will, deeds, account list) are kept, supporting the
executor "plan" the family needs after a death.

## Acceptance Criteria
1. Advisor can enter asset records (type, description, value, registration/ownership form,
   beneficiary designation) for a client (debtor_no).
2. Advisor can enter liability records (type, description, balance) for the client.
3. Advisor can record beneficiary designations per registered asset and flag
   missing/needed ones (e.g., RRSP/RRIF/TFSA/insurance with no or stale designation).
4. Capture records where the original will, property deeds, and the account/policy list are
   kept (institution name and location only — never passwords or PINs), per the Estate Kit
   "records that feed the plan" model.
5. Data persists to the estate plan store (e.g., `ksfii_estate_plan`) keyed by debtor_no;
   updates amend the existing record rather than duplicating.
6. All data is segregated per FA company (debtor_no); no cross-client leakage.
7. Capture is usable from both the advisor view (FA) and the client view (WP portal);
   user-entered data in WP is stored locally then sent via API to FA, which remains the
   source of truth.

## Priority
High

## Status
Draft
