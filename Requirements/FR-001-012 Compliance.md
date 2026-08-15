# FR-001-012 Compliance: Regulatory Compliance Hooks

## Description
The estate module shall integrate with the compliance obligations enforced by the CRM module
(AR-007): AML client identification, CASL/DNC consent for communications, and privacy consent.

## Acceptance Criteria
1. Estate communications (reminders, drafts) respect CASL/DNC consent and unsubscribe handling
   (per CRM module).
2. The client identity used in the estate plan is the KYC-verified identity from the CRM module.
3. No confidential client PII is exposed to external AI (see FR-001-007 guardrails).
4. Data retention/disposal follows KSFII privacy policy (retain only what is required; client
   right to access/update).

## Priority
High

## Status
Draft
