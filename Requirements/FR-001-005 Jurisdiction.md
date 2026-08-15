# FR-001-005 Jurisdiction: Jurisdiction-Aware Rules

## Description
Estate planning calculations and processes shall be jurisdiction-aware. Tax rates, thresholds,
and procedural rules vary by jurisdiction (country and province/territory/state); Canadian rules
are the default, and any non-Canadian logic is selected only by an explicit jurisdiction setting
and must not override Canadian defaults.

## Acceptance Criteria
1. Each estate plan records a jurisdiction (country + province/territory/state).
2. Probate fee schedules (FR-001-001) and tax rules (FR-001-002) are selected by jurisdiction.
3. Canadian (federal + provincial) rules are the default when jurisdiction is unspecified.
4. Non-Canadian logic (e.g., a US-style estate tax) is applied ONLY when the jurisdiction is set
   to that non-Canadian value; it never silently replaces Canadian calculations.
5. The active jurisdiction is shown on any estimate/report so the user knows which rules apply.

## Priority
High

## Status
Draft
