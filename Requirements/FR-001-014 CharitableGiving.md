# FR-001-014 CharitableGiving: Estate Charitable Giving

## Description
The system shall model charitable giving within an estate plan. It shall compute total
donations (from explicit donations or a fraction/amount of the estate), estimate the tax
savings from donation credits (capped at tax actually payable), project net value to heirs,
and recommend giving vehicles: outright gift, charitable remainder trust, and donor-advised
fund.

## Acceptance Criteria
1. The engine accepts an estate value and either explicit donations or a charitable-intent
   fraction/amount, and returns total donations and the charitable deduction.
2. Estimated tax savings is the blended donation credit (approx. 46%) capped at the estate tax
   actually payable; with no estate tax there is no savings.
3. Net value to heirs is the estate value less total donations (floor of zero).
4. Strategies include an outright gift when donations exist, a charitable remainder trust when
   requested, a donor-advised fund for larger estates, and a standing recommendation to review
   charitable intent with the advisor.
5. Calculation results are returned through the standard CalculationResult contract
   (CalculationEngineInterface) and invalid contexts raise CalculationException.
6. The tax model is a simplified planning aid, not legal/tax advice; output is labelled
   accordingly.

## Priority
Medium

## Status
Implemented (CharitableGivingEngine + unit tests)
