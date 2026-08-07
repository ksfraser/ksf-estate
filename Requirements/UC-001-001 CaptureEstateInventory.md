# UC-001-001 CaptureEstateInventory: Capture Estate Inventory

**Related:** BR-001 EstatePlanning, FR-001-003 InventoryCapture

## Actors
- **Advisor** — FA staff user managing a client's estate plan.
- **Client** — FA customer (debtor_no) whose estate is being planned.

## Capture Estate Inventory
**Primary actor:** Advisor
**Preconditions:** Client exists in FA (debtor_no known).
**Main flow:**
1. Advisor opens Estate Planning for the client.
2. System loads any existing plan.
3. Advisor enters asset and liability values and saves.
**Postconditions:** `ksfii_estate_plan` row created/updated for debtor_no.
