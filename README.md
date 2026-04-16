# DevEvents

Aplicação de eventos técnicos com **API REST** (Laravel) e **SPA** (React). O foco do backend é **regra de negócio e consistência**: autorização por papel, controle de ciclo de vida do evento e **contabilização de vagas** sob concorrência.

## 1. Visão geral

Perfis fixos (papéis do usuário):

- **Organizador** (`organizer`): mantém apenas os próprios eventos (CRUD), cancela eventos publicados e consulta inscrições do evento.
- **Participante** (`participant`): navega catálogo público de eventos futuros, acessa detalhe de evento publicado, inscreve-se e gerencia as próprias inscrições.

Topologia típica em Docker:

- Browser → `frontend` (Nginx em `:8080`)
- `frontend` → proxy `/api/*` → `backend` (Laravel em `:8000`)
- `backend` → PostgreSQL (`:5432`)

A API é versionada em **`/api/v1`**.

---

## 2. Stack

| Camada | Tecnologia |
|--------|------------|
| **Backend** | Laravel **11**, PHP **^8.2** (requisito do projeto; imagem Docker atual **8.4**), **Laravel Sanctum** (tokens) |
| **Frontend** | React **18**, TypeScript, **Vite 5**, React Router **6**, **Tailwind CSS 3** |
| **Banco** | **PostgreSQL 16** |
| **Infra/CI** | **Docker Compose**, **GitHub Actions** (`.github/workflows/ci.yml`) |

---

## 3. Modelo de domínio (o que o sistema garante)

### Evento

- **Campos**: `title`, `description?`, `starts_at`, `ends_at`, `capacity`, `metadata? (jsonb)`
- **Status**: controlado por `CHECK` constraint no banco (evita estados fora do contrato).
- **Invariantes**:
  - `ends_at > starts_at` (validado e reforçado via constraint).
  - `capacity > 0` (constraint).

### Inscrição

- **Estados**: modelados com constraint (`CHECK`) para evitar status inválido.
- O backend trata reativação de uma inscrição previamente cancelada (em vez de criar outra linha) para manter histórico/coerência de unicidade.

---

## 4. Regras de negócio e contabilização de vagas (entradas/saídas)

O ponto “difícil” do backend é manter **vagas disponíveis** corretas quando há múltiplos requests concorrentes e duplo submit.

### Fórmulas e derivação de dados

- **Inscrições confirmadas**: \(confirmed\_count\) (contagem de registros confirmados por evento).
- **Vagas restantes**: \(remaining = capacity - confirmed\_count\).

Esses números aparecem na listagem do organizador e direcionam a regra de elegibilidade do participante.

### Regras aplicadas ao inscrever/cancelar

- Participante só se inscreve se o evento estiver **publicado**, **no futuro**, com **vaga** e sem duplicidade confirmada.
- Cancelamento só é permitido para a **própria** inscrição.
- Reativação: se já existe inscrição cancelada, a operação promove o status de volta para confirmado (mantém identidade e evita múltiplos registros “equivalentes”).

### Concorrência (por que existe transação e lock)

As operações de inscrição/cancelamento são encapsuladas no `EventRegistrationService` com:

- `DB::transaction()` para atomicidade.
- `lockForUpdate()` em `events` e `event_registrations` para serializar o “contador lógico” de vagas por evento.
- Tratamento explícito de violação de unicidade (cenários de **duplo submit** e corrida entre requests).

Resultado: o banco não aceita “oversell” de vagas e a API retorna erro de domínio consistente quando a regra não permite a transição.

---

## 5. Arquitetura e decisões de implementação

Esta seção descreve decisões que aparecem no repositório (com o “porquê”).

- **Camada HTTP**: rotas em `backend/routes/api.php`, agrupadas por `v1`. Rotas protegidas usam `auth:sanctum`. Existe `throttle` distinto para autenticação vs. resto da API para reduzir abuso sem penalizar navegação do catálogo.
- **Validação**: `FormRequest` por operação para manter regras perto do boundary HTTP. Onde há invariantes de modelo (`ends_at > starts_at`), existe validação e reforço no banco (defesa em profundidade).
- **Autorização por papel e por recurso**:
  - Gates globais (`access-organizer-api`, `access-participant-api`) aplicados por middleware `can:` nos grupos de rotas.
  - `EventPolicy` para decisões dependentes do recurso (`Event`) como update/cancel/listar inscrições do evento.
- **Persistência orientada a caso de uso**:
  - `EventRepository` concentra consultas de listagem paginada (eventos do organizador autenticado; catálogo futuro/publicado com filtros).
  - `EventRegistrationService` concentra transições e invariantes de inscrição (inclui concorrência).
- **Contrato de resposta**: API Resources (`EventResource`, `EventRegistrationResource`, etc.) padronizam `data` e paginação. Isso evita “acoplamento por shape” nos controllers e facilita evolução do payload.
- **PostgreSQL como guardião de invariantes**:
  - `metadata` em `events` como `jsonb` (nullable) para extensões sem migração a cada novo campo.
  - `timestamptz` para evitar ambiguidade de timezone no domínio de agenda.
  - `CHECK` constraints para status e regras críticas (datas/capacidade), reduzindo dependência de validação apenas na camada PHP.
- **Cancelamento com side effects controlado**: ao cancelar evento publicado, o backend despacha um job de notificação para participantes confirmados (detalhes na seção 9).

---

## 6. Funcionalidades

### Organizador

- Cadastro com papel `organizer`.
- Listagem paginada dos próprios eventos, com contagens derivadas (confirmadas / vagas restantes).
- CRUD de evento (escopo do próprio organizador).
- Cancelamento (`POST .../cancel`) com transição de estado.
- Listagem paginada de inscrições de um evento próprio (payload reduzido do participante).

### Participante

- Cadastro com papel `participant`.
- Catálogo paginado de eventos futuros e publicados, com filtros (`q`, `organizer_id`, `starts_from`, `starts_until`).
- Detalhe de evento publicado (o detalhe não é restrito a “futuros”; o catálogo é).
- Inscrição com aplicação de regras (vaga/duplicidade/reativação).
- Cancelamento da própria inscrição.
- Listagem paginada das próprias inscrições (`my-events`).

---

## 7. Execução

### Docker

**Pré-requisitos:** Docker Engine 24+ e Docker Compose v2.

Na raiz do repositório:

1) Variáveis do Compose (recomendado):

```bash
cp .env.example .env
```

No Windows (PowerShell): `Copy-Item .env.example .env`

2) Subir serviços:

```bash
docker compose up -d --build
```

3) Migrar schema (o `entrypoint` não roda migrações por padrão):

```bash
docker compose exec backend php artisan migrate --force
```

Opcional: `RUN_MIGRATIONS_ON_BOOT=true` faz o container executar `php artisan migrate --force` na inicialização (ver `backend/docker/entrypoint.sh`).

4) Seed (opcional):

```bash
docker compose exec backend php artisan db:seed --force
```

5) URLs:

- Frontend: `http://localhost:8080`
- Backend (API): `http://localhost:8000`
- PostgreSQL no host: `localhost:5432`

6) Credenciais do seed (senha: `password`):

- `organizer1@devevents.test`
- `participant1@devevents.test`

Em Docker, o frontend chama **`/api/v1`** no mesmo host; o Nginx (`frontend/nginx.conf`) encaminha `/api` para o serviço `backend`.

### Local (sem Docker)

Backend:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --force
php artisan serve --host=127.0.0.1 --port=8000
```

Frontend:

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

O Vite (`frontend/vite.config.ts`) faz proxy de `/api` para `http://localhost:8000`, coerente com `VITE_API_BASE_URL=/api/v1` em `frontend/.env.example`.

---

## 8. Autenticação

- Sanctum com **Personal Access Tokens** (`personal_access_tokens`).
- Cadastro/login retornam usuário em `data` e token em `meta.token` (ver `AuthController` e `UserResource`).
- O cliente React (`frontend/src/shared/api/client.ts`) envia `Authorization: Bearer <token>`.
- O token fica em `localStorage`. A decisão foi manter uma SPA simples; alternativas (cookies `httpOnly` + CSRF) mudam o desenho e não são o objetivo aqui.

---

## 9. Testes

Rodar (na pasta `backend`):

```bash
composer install
php artisan test
```

Cobertura atual:

- **Feature tests da API** (`tests/Feature/Api/V1/`): auth, organizador (CRUD, autorização entre organizadores, cancelamento + notificação fake), participante (catálogo, detalhe, inscrições e regras de negócio).
- **Unit tests** do `EventRegistrationService` (`tests/Unit/EventRegistrationServiceTest.php`), focando invariantes e cenários de concorrência/duplicidade.

Não coberto:

- Frontend sem suíte de testes (há `lint` e `build`).

Existe um exemplo de saída em `docs/test-run-sample.txt` (pode divergir do estado atual; a fonte de verdade é `php artisan test`).

---

## 10. API e Postman

- Coleção: `docs/postman/DevEvents.postman_collection.json`
- Variáveis:
  - `baseUrl`: `http://localhost:8000/api/v1` (API direta) ou `http://localhost:8080/api/v1` (via proxy do frontend em Docker)
  - `token`: preencher após login/cadastro com `meta.token`

---

## 11. Filas e notificações

- Job `NotifyRegisteredParticipantsEventCancelled` é despachado quando um evento **publicado** é cancelado e envia `EventCancelledNotification` via `mail`.
- Job e notificação implementam `ShouldQueue`; o efeito depende do `QUEUE_CONNECTION`.
- Nos testes, `backend/phpunit.xml` define `QUEUE_CONNECTION=sync` para processamento imediato.
- O `docker-compose.yml` não inclui `queue:work`; para fila realmente assíncrona em Docker, é necessário um worker dedicado.

---

## 12. CI

Arquivo: `.github/workflows/ci.yml`.

- `backend`: PHP 8.2 + `pgsql`, PostgreSQL 16 como service, `php artisan test`.
- `frontend`: Node 20, `npm ci`, `npm run build`.

Não há deploy automatizado — o pipeline é de build/test.

---

## 13. Limitações conhecidas

- Frontend sem testes automatizados.
- Notificação por e-mail/filas depende de `MAIL_*` e de worker quando `QUEUE_CONNECTION` não é `sync`.
- Mensagens de erro misturam português/inglês (contrato HTTP não está unificado).
- Dois níveis de `.env.example` (raiz para Compose; `backend/.env.example` para execução direta). `APP_URL` e `SANCTUM_STATEFUL_DOMAINS` mudam conforme portas (5173/8080/8000).

---

## 14. Possíveis melhorias / roadmap

- Unificar idioma e padronizar **códigos de erro de domínio** (ex.: `EVENT_FULL`, `ALREADY_REGISTERED`) para facilitar UX e integrações.
- Adicionar testes no frontend (contrato ou smoke) cobrindo fluxo de login, catálogo e inscrição/cancelamento.
- Introduzir **idempotency key** na inscrição para reduzir retrabalho em retries do cliente (além do tratamento atual de corrida).
- Adicionar um serviço de worker no Compose para cenários com `QUEUE_CONNECTION=redis|database`.
- Evoluir “contabilização” de vagas para relatórios (ex.: séries temporais de ocupação) sem sobrecarregar endpoints transacionais.

---

## Estrutura do repositório

```text
SINAPSE/
├── backend/          # API Laravel
├── frontend/         # SPA React (Vite)
├── docs/             # Postman, exemplos auxiliares
├── docker-compose.yml
└── README.md
```
