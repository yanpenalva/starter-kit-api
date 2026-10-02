# Preflight — FEAT-3

- User explicitly asked to advance the next two API issues; API #3 is now In progress on the board.
- API #13 defines the exact login/profile/logout paths and expected failure status families. Accepted ADR 0004 plus the user's answer determine pending-verification access.
- The draft does not add endpoints, dependencies, migrations, or runtime security claims.
- A session cookie scheme is documented as the first-party mechanism; it is not Bearer or a PAT. Production cookie/CORS configuration remains open.
- API #11 owns Scramble installation/export after real endpoint code exists.
- Independent preflight reviewer: `NOT FOUND`; final public-contract review remains required before API #13 implementation is merged.

**Gate:** safe to write a reviewable, pre-implementation contract draft; do not treat it as generated/runtime evidence.
