# DevEvents

# 1. Visão Geral

**DevEvents** é uma plataforma de **eventos técnicos** com API REST (Laravel) e SPA (React). Dois perfis fixos:

- **Organizador** (`organizer`): cria e mantém apenas os seus eventos, consulta inscrições e pode cancelar eventos publicados.
- **Participante** (`participant`): consulta o catálogo de eventos futuros publicados, vê detalhes, inscreve-se (com regras de vaga e duplicidade) e gerencia as próprias inscrições.

O tráfego típico em Docker: browser → frontend **Nginx** (`:8080`) → requisições `/api/*` encaminhadas via proxy para o **Laravel** (`:8000`) → **PostgreSQL** (`:5432`). A API expõe a versão em **`/api/v1`**.

---

# 2. Stack Tecnológica

| Camada | Tecnologia |
|--------|------------|
| **Backend** | Laravel **11**, PHP **^8.2** (requisito do projeto; imagem Docker atual **8.4**), **Laravel Sanctum** (tokens de API) |
| **Frontend** | React **18**, TypeScript, **Vite 5**, React Router **6**, **Tailwind CSS 3** |
| **Banco** | **PostgreSQL 16** (aplicação, testes automatizados e serviço no CI) |
| **Infra** | **Docker Compose** (postgres + backend + frontend), **GitHub Actions** (`.github/workflows/ci.yml`) |

---

# 3. Arquitetura e Decisões Técnicas

Esta seção descreve apenas o que está no repositório.

- **Camada HTTP**: rotas em `backend/routes/api.php`, agrupadas por prefixo `v1`, com `auth:sanctum` nas rotas protegidas e **rate limiting** (`throttle`) distinto para autenticação vs. resto da API.
- **Validação**: `FormRequest` por operação (auth, organizador, participante), com regras explícitas e, onde aplicável, validação adicional pós-regras (ex.: consistência `ends_at` > `starts_at` na atualização de eventos).
- **Autorização**:
  - **Gates** globais `access-organizer-api` e `access-participant-api` (registrados no `AppServiceProvider`) aplicados por middleware `can:` nos grupos de rotas.
  - **Policy** `EventPolicy` para operações sobre o modelo `Event` (visualização, atualização, cancelamento, listagem de inscrições do evento), usada a partir de `$this->authorize()` nos controllers e/ou `authorize()` nos `FormRequest` de escrita.
- **Domínio e persistência**:
  - **`EventRepository`**: consultas de listagem paginada (eventos do organizador autenticado; catálogo público futuro com filtros opcionais).
  - **`EventRegistrationService`**: regras de inscrição e cancelamento com **`DB::transaction`**, **`lockForUpdate()`** em `events` e `event_registrations` e tratamento de violação de unicidade (concorrência / duplo submit).
- **Recursos de API**: respostas JSON via **API Resources** (`EventResource`, `ParticipantEventResource`, `EventRegistrationResource`, etc.), alinhadas ao formato `data` / paginação do Laravel.
- **PostgreSQL**:
  - Coluna **`metadata`** em `events` como **`jsonb`** (nullable), com cast para array no modelo.
  - **`timestamptz`** nas tabelas principais.
  - **Constraints `CHECK`** em SQL para `status` de eventos e inscrições, regra `ends_at > starts_at` e `capacity > 0`.
- **Notificações**: ao cancelar um evento publicado, é despachado um **Job** que notifica participantes com inscrição confirmada (detalhe na seção 9).

---

# 4. Funcionalidades Principais

## Organizador

- Cadastro com papel `organizer` (via API).
- Listagem **paginada** apenas dos **próprios** eventos (com contagem de inscrições confirmadas e vagas restantes quando aplicável).
- Criação de evento: título, descrição opcional, início, fim, capacidade, `metadata` opcional.
- Leitura e atualização **apenas** de eventos próprios.
- Cancelamento de evento próprio (`POST .../cancel`), com transição de estado para cancelado.
- Listagem **paginada** de inscrições de um evento **próprio** (dados limitados do participante).

## Participante

- Cadastro com papel `participant`.
- Listagem **paginada** de eventos **futuros** e **publicados** (catálogo), com filtros opcionais (`q`, `organizer_id`, `starts_from`, `starts_until`) expostos na API.
- Detalhe de evento **publicado** (a API não restringe o detalhe a “futuros”; o catálogo sim).
- Inscrição num evento elegível (publicado, futuro, com vaga, sem inscrição confirmada duplicada; reativação de registro previamente cancelado é tratada no serviço).
- Cancelamento da **própria** inscrição.
- Listagem **paginada** das **próprias** inscrições (“meus eventos” / `my-events`).

---

# 5. Execução do Projeto

## Rodando com Docker

**Pré-requisitos:** Docker Engine 24+ e Docker Compose v2.

Na **raiz** do repositório:

1. **Variáveis de ambiente do Compose** (opcional mas recomendado): copie o exemplo da raiz.

   ```bash
   cp .env.example .env
   ```

   No Windows (PowerShell): `Copy-Item .env.example .env`

2. **Subir os serviços** (build na primeira vez):

   ```bash
   docker compose up -d --build
   ```

3. **Esquema do banco de dados**: por padrão o `entrypoint` do backend **não** roda migrações automaticamente. É necessário migrar **uma vez** (ou sempre que o esquema mudar), por exemplo:

   ```bash
   docker compose exec backend php artisan migrate --force
   ```

   Comportamento **opcional** já suportado pelo **container**: se existir a variável de ambiente **`RUN_MIGRATIONS_ON_BOOT=true`**, o `entrypoint` executa `php artisan migrate --force` na inicialização (útil em alguns ambientes de deploy; ver comentários em `backend/docker/entrypoint.sh`).

4. **Dados de demonstração** (opcional):

   ```bash
   docker compose exec backend php artisan db:seed --force
   ```

5. **URLs** (portas padrão do `docker-compose.yml`):

   - Frontend: `http://localhost:8080`
   - Backend (API): `http://localhost:8000`
   - PostgreSQL no host: `localhost:5432`

6. **Credenciais de seed** (após `DevEventsSeeder`; senha comum: `password`):

   - `organizer1@devevents.test`
   - `participant1@devevents.test`

O frontend, em Docker, envia requisições para **`/api/v1`** no mesmo host; o **Nginx** do serviço `frontend` encaminha `/api` para o serviço `backend` (ver `frontend/nginx.conf`).

## Rodando localmente (sem Docker)

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --force
php artisan serve --host=127.0.0.1 --port=8000
```

Ajuste `DB_*` em `backend/.env` para o seu PostgreSQL. No Windows (PowerShell): `Copy-Item .env.example .env`

### Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

O Vite (`frontend/vite.config.ts`) define **proxy** de `/api` para `http://localhost:8000`, coerente com `VITE_API_BASE_URL=/api/v1` em `frontend/.env.example`.

---

# 6. Autenticação

- **Sanctum** com **Personal Access Tokens** (`personal_access_tokens`). O cadastro e o login devolvem o usuário em `data` e o token em `meta.token` (ver `AuthController` e `UserResource`).
- O cliente React (`frontend/src/shared/api/client.ts`) envia **`Authorization: Bearer <token>`** em requisições autenticadas.
- O token é guardado em **`localStorage`** (chave interna do projeto; ver o mesmo arquivo). Em aplicações reais costuma haver discussão sobre `localStorage` vs. cookies `httpOnly`; aqui o desenho é explícito e simples para uma SPA com API em domínio/porta diferentes conforme ambiente.

---

# 7. Testes

**Comando** (na pasta `backend`, com dependências instaladas):

```bash
composer install
php artisan test
```

**O que está coberto**

- **Testes de API** (`tests/Feature/Api/V1/`): autenticação, organizador (CRUD de eventos, autorização entre organizadores, cancelamento com notificação falsa), participante (catálogo, detalhe, inscrições, regras de negócio, isolamento por papel).
- **Testes unitários** do serviço de inscrições (`tests/Unit/EventRegistrationServiceTest.php`).

**O que não está coberto por esta suíte**

- **Sem testes automatizados no frontend** (não há script `test` em `frontend/package.json` além de `lint` e `build`).

Foi versionado um exemplo de saída de testes em `docs/test-run-sample.txt` (o conteúdo pode ficar desatualizado em relação ao número exato de testes; a fonte da verdade é sempre `php artisan test`).

---

# 8. API e Postman

- **Coleção:** `docs/postman/DevEvents.postman_collection.json`
- **Variáveis da coleção:** `baseUrl` (ex.: `http://localhost:8000/api/v1` com a API direta, ou `http://localhost:8080/api/v1` através do proxy do frontend em Docker) e `token` (preencher após login/cadastro a partir de `meta.token`).

---

# 9. Filas e Notificações

- Existe o job **`NotifyRegisteredParticipantsEventCancelled`**, despachado quando um evento **publicado** é cancelado, que percorre inscrições confirmadas e envia **`EventCancelledNotification`** por canal `mail`.
- Tanto o job quanto a notificação implementam **`ShouldQueue`**; o comportamento efetivo depende da configuração de filas do ambiente.
- Nos **testes**, `backend/phpunit.xml` define explicitamente **`QUEUE_CONNECTION=sync`**, o que força a fila em modo **síncrono** (processamento imediato, sem worker separado) durante a suíte.
- O **`docker-compose.yml` atual não inclui** um processo dedicado tipo `queue:work`; em desenvolvimento Docker típico, **`sync`** (ou ausência de worker) implica que filas assíncronas reais **não** seriam consumidas automaticamente — o importante é saber **qual** `QUEUE_CONNECTION` está ativo no `.env` do backend em cada ambiente.

---

# 10. CI/CD

Arquivo: `.github/workflows/ci.yml`.

- **Job `backend`:** checkout, PHP 8.2, extensões `pgsql`, `composer install`, serviço PostgreSQL 16, `php artisan test`.
- **Job `frontend`:** checkout, Node 20, cache npm, `npm ci`, `npm run build`.

Não há pipeline de deploy para produção neste arquivo — apenas verificação de build e testes.

---

# 11. Limitações Conhecidas

- **Sem testes automatizados no frontend** (apenas lint e build no `package.json` / CI).
- **Filas / e-mail**: notificação de cancelamento depende de configuração de mail (`MAIL_*`) e de fila; o Compose local não inclui worker de fila dedicado.
- **Mensagens de erro de domínio** em parte da API (ex.: regras de inscrição no serviço) estão em **inglês**, enquanto outras mensagens (ex.: `lang/pt_BR/auth.php`) estão em **português** — inconsistência de idioma na superfície HTTP.
- **README / `.env`**: existem dois níveis de exemplo (`.env.example` na raiz para Compose; `backend/.env.example` para execução direta do Laravel); `APP_URL` e `SANCTUM_STATEFUL_DOMAINS` diferem conforme o cenário (SPA na porta 5173 vs. 8080 vs. API na 8000).

---

# 12. Possíveis Melhorias

- Unificar **idioma** das mensagens de validação e de negócio expostas na API (ou documentar contrato bilíngue de propósito).
- Acrescentar **testes de UI ou contrato** mínimos no frontend, se o critério de avaliação o valorizar.
- Documentar no próprio repositório a **estratégia de filas** pretendida para demo vs. produção (uma linha no `.env.example` do backend pode bastar).

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
