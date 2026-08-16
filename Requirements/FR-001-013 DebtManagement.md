# FR-001-013 DebtManagement: Estate Debt Management

## Description
The system shall analyze the debts attached to an estate and help the advisor decide how
they are settled at death. It shall categorize obligations as secured vs. unsecured, compute
the debt-to-asset ratio and the liquidity required to settle debts payable on death, and
produce prioritized payoff / reduction recommendations.

## Acceptance Criteria
1. The engine accepts a list of assets (with values) and a list of debts (balance, interest
   rate, secured flag, payable-on-death flag) and returns total assets, total debt,
   debt-free estate value, and the debt-to-asset ratio.
2. Debts are categorized into secured and unsecured totals.
3. Liquidity needed is the sum of balances for debts flagged payable on death; debts not
   payable on death (e.g. some student loans) are excluded.
4. A payoff plan ranks debts by interest rate (highest first) and allocates available liquidity,
   reporting how much remains unfunded.
5. Recommendations flag elevated debt ratios (>= 50%), prioritize high-interest debt
   (rate >= 6%), and suggest mortgage life insurance where a secured debt is present and the
   estate can fund it.
6. Calculation results are returned through the standard CalculationResult contract
   (CalculationEngineInterface) and invalid contexts raise CalculationException.

## Priority
Medium

## Status
Implemented (DebtManagementEngine + unit tests)
