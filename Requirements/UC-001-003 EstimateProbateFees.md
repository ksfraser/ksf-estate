# UC-001-003 EstimateProbateFees: Estimate Probate Fees

**Related:** BR-001 EstatePlanning, FR-001-001 ProbateFees

**Primary actor:** System (on save / recalc)
**Main flow:**
1. System reads province and probate-subject estate value.
2. System applies `ksfii_estate_probate_schedule` for the province.
3. System displays estimated fee.
**Postconditions:** Probate estimate shown on summary.
