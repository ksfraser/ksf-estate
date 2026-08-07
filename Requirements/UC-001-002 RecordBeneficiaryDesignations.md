# UC-001-002 RecordBeneficiaryDesignations: Record Beneficiary Designations

**Related:** BR-001 EstatePlanning, FR-001-003 InventoryCapture

**Primary actor:** Advisor
**Main flow:**
1. Advisor lists registered assets (RRSP, RRIF, TFSA, pension, insurance).
2. For each, advisor enters designated beneficiary or marks "no designation".
**Postconditions:** `ksfii_estate_beneficiary` rows saved; gap report can flag missing ones.
