# Human decision — FEAT-3

**Approver:** yanpenalva. **Date:** 2026-10-02. **Evidence:** reply in this conversation to the question about login before email verification.

## Decision

An account that has registered but not verified its email may establish a limited session. Until verification, the API permits logout, a minimal self-profile, and the verification flow; business resources remain denied. Verification automatically activates the basic profile. An explicitly deactivated account is blocked with HTTP 403.

This clarifies the accepted behavior in ADR 0004 for API #13. It does not authorize administrative activation or override the restriction that CPF is available only to its holder or management with a specific permission, and never in public responses/audit data.

The rest of the API #3 document remains a draft for review before dependent public endpoints are merged.
