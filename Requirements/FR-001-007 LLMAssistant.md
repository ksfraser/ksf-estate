# FR-001-007 LLMAssistant: LLM Assistant Skills

## Description
The module may provide LLM-assisted capabilities to help the advisor and client: checklist
checking (e.g., the estate plan checklist), confirming data is complete, reminders (e.g., stale
will/POA, missing beneficiary designations, 48-60-6 deadlines), and drafting summaries or
client-ready text.

## Acceptance Criteria
1. Assistant can check the estate plan checklist and report incomplete items.
2. Assistant can confirm captured data is consistent and flag gaps.
3. Assistant can surface reminders (stale documents, missing designations, executor deadlines).
4. Assistant can draft client-ready text, clearly labelled as a draft for human review.
5. Guardrails (per KSFII "Making the most of AI"): no confidential client PII (SIN, DOB, name,
   etc.) is sent to an external AI; a human reviews all output; hallucinations are possible and
   must be verified before use.

## Priority
Medium

## Status
Draft
