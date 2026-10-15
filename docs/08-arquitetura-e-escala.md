# 🏗️ Arquitetura do código e escala sem reescrita

Como a aplicação é montada em código hoje e como ela cresce por estágios —
**nível de código, não infraestrutura**. O ciclo de vida de uma requisição está em
[03-arquitetura](03-arquitetura.md); os vereditos de padrões e os gatilhos, em
[06-padroes](06-padroes.md). Aqui estão os desenhos.

## 📑 Índice

1. [Arquitetura estática: módulos e núcleo](#-arquitetura-estática-módulos-e-núcleo)
2. [Leitura vs escrita: os dois caminhos](#-leitura-vs-escrita-os-dois-caminhos)
3. [Escala em estágios: o que muda no código](#-escala-em-estágios-o-que-muda-no-código)

---

## 🧩 Arquitetura estática: módulos e núcleo

Três módulos de negócio verticais sobre um núcleo compartilhado, sobre o
framework. Dependência só desce: módulo nunca importa módulo.

```mermaid
flowchart TB
    subgraph Edge["Borda HTTP — api/v1"]
        RT["routes/api/*.php<br/>método + caminho + throttle + gate"]
        RQ["FormRequests<br/>validar + normalizar + authorize"]
        RS["Resources<br/>contrato JSON de saída"]
    end
    subgraph Mod["Módulos — um por domínio"]
        US["Users<br/>Actions + Controller"]
        RO["Roles<br/>Actions + Controller"]
        AU["Auth<br/>login, MFA, perfil, reset"]
        AL["Audit<br/>leitura da trilha"]
    end
    subgraph Kernel["Núcleo compartilhado"]
        PO["Policies + Gates<br/>quem pode o quê"]
        AC["Actions base<br/>PrivilegeTransaction + audit fail-closed"]
        SU["Support<br/>EmailLookup, Filtros, Correlação, Mensagens"]
        EN["Enums<br/>Permission, Role — catálogo fechado"]
    end
    subgraph FW["Framework — não reinventar"]
        EL["Eloquent + Query Builder"]
        QU["Queue + Cache + Mail"]
        SC["Sanctum sessão/cookie + CSRF"]
    end
    Edge --> Mod
    US & RO & AU & AL --> Kernel
    Kernel --> FW
```

Regras do desenho: recurso novo = módulo novo repetindo o molde (receita A em
[07-receitas-de-features](07-receitas-de-features.md)); regra transversal = núcleo;
o que o framework dá (ORM, fila, cache, sessão) não se reimplementa.

## 🔀 Leitura vs escrita: os dois caminhos

```mermaid
flowchart LR
    subgraph Read["Leitura — sem transação de privilégio"]
        R1["Gate can:permissão"] --> R2["Action de consulta<br/>IndexUsers / ShowUser"]
        R2 --> R3["Filters allowlist<br/>+ eager loading"]
        R3 --> R4["Resource<br/>CPF só com read-cpf"]
    end
    subgraph Write["Escrita — transação + auditoria"]
        W1["Gate + Policy por objeto"] --> W2["PrivilegeTransaction<br/>lock advisory no pgsql"]
        W2 --> W3["recarrega ator + reautoriza"]
        W3 --> W4["mutação + Audit.record<br/>mesmo commit, fail-closed"]
        W4 --> W5["efeitos externos<br/>notify afterCommit"]
    end
```

A separação é o CQRS leve que já existe: consultas nunca tocam trava nem
auditoria; mutações nunca escapam das duas.

## 📈 Escala em estágios: o que muda no código

```mermaid
flowchart LR
    S1["Estágio 1 — hoje<br/>módulos por pasta, Eloquent,<br/>paginate+offset, fila database"]
    S2["Estágio 2 — sob medição<br/>simple/cursorPaginate, pg_trgm,<br/>seção crítica curta"]
    S3["Estágio 3 — pressão real<br/>read models pontuais, toBase+lazyById<br/>em exports, fila/cache Redis"]
    S4["Estágio 4 — times e domínios<br/>namespaces por módulo, Domain Services<br/>pontuais, sem microserviço sem motivo"]
    S1 --> S2 --> S3 --> S4
```

O que **muda** por estágio: S1→S2 é consulta e paginação (contrato de metadados
pode mudar — ver 06); S2→S3 é trecho quente provado por perfil, nunca a camada;
S3→S4 é organização para gente, não para máquina. O que **não muda** em nenhum
estágio: contrato v1 aditivo, auditoria fail-closed, policies por objeto,
catálogo fechado de permissões, `afterCommit` nos efeitos externos.

Cada transição é um PR próprio com medição antes/depois; o detalhamento dos
gatilhos está em [06-padroes](06-padroes.md#escalabilidade-e-performance-gatilhos-não-previsões).
