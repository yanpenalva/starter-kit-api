# Discovery — FEAT-4

[API #4](https://github.com/yanpenalva/starter-kit-api/issues/4) é progressiva: define e ativa quality gates e smoke tests ao longo das waves, com consolidação na wave 7. Esta fatia inicial documenta a definição dos gates; nada de CI é criado ou alterado aqui.

Estado observado (atribuído ao orquestrador pai e a verificações locais anteriores): CI mínimo já integrado via PR #29 (merged 2026-10-02; Quality run #1 success) e reconhecido em [FEAT-24](../FEAT-24/verification.md); PR #30 merged com checks context/application/GitGuardian success no head 99321a9 e run de `main` 37078252890 success. O board foi movido para In progress pelo pai antes desta fatia. Done acumulado: 7/47. *(Instantâneo de 2026-10-03 pré-reconcile; atual 8/47 com SPA #1 Done — ver roadmap seção 9 e [FEAT-6 verification](../FEAT-6/verification.md).)*

Lacuna: as adições progressivas de segurança/qualidade propostas pela revisão Oracle não estão documentadas com estado planejado vs. implementado; sem isso, executores futuros não têm fronteira clara entre o que o CI já garante e o que falta.

Fontes: [quality.md](../../project/quality.md), [QUALITY_GATES.md](../../../QUALITY_GATES.md), workflow existente `.github/workflows/quality.yml` (referência somente leitura), ADRs aceitos e [contrato da API](../../project/api.md).
