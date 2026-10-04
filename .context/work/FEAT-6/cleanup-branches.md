# Remote branch cleanup

Human explicitly requested removal of unused GitHub branches while keeping main/current branches before wave 2. Both current local branches were main, both repos had zero open PRs, and each deletion target was verified with `git merge-base --is-ancestor ... origin/main` before normal `git push origin --delete`.

API removed: `chore/issue-5-initialize-csd`, `docs/issue-6-roadmap-governance`, `docs/issues-2-3-foundation`, `feat/issue-7-api-scaffold`, `feat/wave-1-api-foundations`.

Frontend removed: `chore/issue-4-initialize-csd`, `docs/issue-1-integration-checkpoint`, `docs/issue-1-spa-architecture`, `feat/issue-6-spa-layouts`, `feat/wave-1-spa-foundations`.

Post-operation GitHub branch enumeration returned only `main` in each repo. Local branches and integrated history were preserved; no force push, tag deletion or repository rule change. This approval covers this bounded cleanup only, not deletion of unmerged future work.

Board enumeration returned 47 items, `hasNextPage=false`, ten closed items all already Done. Progressive issues retain In progress/Backlog according to actual scope; no partial item was closed to inflate completion.
