# CLAUDE.md — KYC Project

This file provides guidance for AI assistants working in this repository. It covers project conventions, architecture decisions, development workflows, and key rules to follow.

---

## Project Overview

**KYC** (Know Your Customer) — a compliance and identity verification platform. This system collects, validates, and manages user identity data to satisfy regulatory requirements (AML, KYC/CDD). Typical capabilities include:

- Document upload and OCR extraction
- Identity verification (liveness checks, document authenticity)
- Risk scoring and compliance rule evaluation
- Case management and audit logging
- Integration with third-party verification providers (e.g. Onfido, Jumio, Persona)

---

## Repository & Branch Conventions

- **Main branch**: `main` (protected; never push directly)
- **Feature branches**: `feature/<short-description>`
- **Bug fix branches**: `fix/<short-description>`
- **Claude AI branches**: `claude/<task-id>` (as assigned per session)
- All changes must go through pull requests; no direct pushes to `main`.
- Commit messages follow the **Conventional Commits** format:
  ```
  <type>(scope): <short summary>

  Examples:
  feat(verification): add liveness check integration
  fix(ocr): handle rotated document images
  chore(deps): upgrade tesseract to v5
  docs(api): update KYC submission endpoint docs
  ```

---

## Expected Stack (establish at project init)

| Layer | Technology |
|---|---|
| Runtime | Node.js 20+ / Python 3.11+ |
| Framework | Express / FastAPI (TBD) |
| Language | TypeScript / Python |
| Database | PostgreSQL (primary), Redis (cache/queue) |
| ORM | Prisma (if TS) / SQLAlchemy (if Python) |
| Queue | BullMQ / Celery |
| Storage | S3-compatible (documents) |
| Auth | JWT + refresh tokens |
| Testing | Vitest / Pytest |
| Containerization | Docker + Docker Compose |

> When the stack is decided, update this table and remove the "(TBD)" entries.

---

## Directory Structure (target layout)

```
KYC/
├── src/
│   ├── api/              # Route handlers / controllers
│   ├── services/         # Business logic (verification, risk scoring)
│   ├── models/           # DB models / Prisma schema
│   ├── workers/          # Background job processors
│   ├── integrations/     # Third-party provider clients (Onfido, etc.)
│   ├── middleware/        # Auth, validation, rate limiting
│   ├── utils/            # Shared helpers (crypto, date, file)
│   └── config/           # App configuration and env parsing
├── tests/
│   ├── unit/
│   ├── integration/
│   └── e2e/
├── migrations/            # DB migration files
├── docs/                  # Architecture diagrams, API specs
├── scripts/               # Dev/ops utility scripts
├── docker-compose.yml
├── Dockerfile
├── .env.example
└── CLAUDE.md
```

---

## Environment Variables

Never commit real secrets. Always use `.env.example` as the template:

```
# App
NODE_ENV=development
PORT=3000
LOG_LEVEL=info

# Database
DATABASE_URL=postgresql://user:password@localhost:5432/kyc_db

# Redis
REDIS_URL=redis://localhost:6379

# JWT
JWT_SECRET=<generate-with-openssl-rand-base64-32>
JWT_EXPIRY=15m
REFRESH_TOKEN_EXPIRY=7d

# Storage
S3_BUCKET=kyc-documents
S3_REGION=us-east-1
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=

# KYC Providers
ONFIDO_API_KEY=
JUMIO_API_KEY=
JUMIO_API_SECRET=

# Encryption
DOCUMENT_ENCRYPTION_KEY=<32-byte-hex>
```

---

## Security Rules (CRITICAL)

KYC systems handle PII and are high-value targets. Enforce these strictly:

1. **Encrypt PII at rest** — all identity fields (name, DOB, document numbers, addresses) must be encrypted in the database using AES-256. Never store plaintext PII.
2. **Encrypt documents** — files uploaded to S3 must use SSE-S3 or SSE-KMS.
3. **No PII in logs** — scrub or mask all identity fields before logging. Use structured logging with explicit allowlists.
4. **Access control** — implement RBAC. Operations are gated by role: `applicant`, `agent`, `admin`, `compliance-officer`.
5. **Audit log everything** — every state transition, document access, and manual review action must be written to an immutable audit log with actor, timestamp, and IP.
6. **Rate limiting** — all public endpoints must be rate-limited. Verification endpoints get stricter limits (e.g. 5 req/min per IP).
7. **No secrets in code** — use environment variables only. Never hardcode keys, tokens, or passwords.
8. **Input validation** — validate all request inputs at the API boundary. Reject unexpected fields. Use strict schema validation (Zod / Pydantic).
9. **File validation** — validate MIME type and file content (not just extension) before processing uploads. Enforce max file size.
10. **SQL injection** — always use parameterized queries or ORM. Never concatenate user input into queries.

---

## Data Models (core entities)

```
Applicant
  id, created_at, updated_at
  status: enum(pending, in_review, approved, rejected, requires_resubmission)
  risk_level: enum(low, medium, high)
  encrypted PII fields (name, dob, nationality, address)

KycDocument
  id, applicant_id (FK), type, status
  s3_key (encrypted reference), mime_type, size_bytes
  ocr_result (JSONB), confidence_score
  uploaded_at, reviewed_at, reviewed_by

VerificationCheck
  id, applicant_id (FK), provider, check_type
  status, result (JSONB), provider_reference
  requested_at, completed_at

AuditLog
  id, timestamp, actor_id, actor_role
  action, resource_type, resource_id
  ip_address, user_agent, metadata (JSONB)
  (append-only — never update or delete)
```

---

## API Conventions

- Base path: `/api/v1/`
- All responses use consistent envelope:
  ```json
  { "data": {}, "meta": {}, "error": null }
  ```
- Error responses:
  ```json
  { "data": null, "error": { "code": "VALIDATION_ERROR", "message": "...", "details": [] } }
  ```
- HTTP status codes: 200 OK, 201 Created, 400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found, 422 Unprocessable Entity, 429 Too Many Requests, 500 Internal Server Error
- Pagination: `?page=1&limit=20` with response meta `{ total, page, limit, totalPages }`
- Dates: ISO 8601 (UTC) everywhere

---

## KYC State Machine

```
PENDING → IN_REVIEW → APPROVED
                    → REJECTED
                    → REQUIRES_RESUBMISSION → PENDING (loop)
```

State transitions must be validated server-side. Never trust client-supplied status fields. Log every transition to `AuditLog`.

---

## Testing Standards

- **Unit tests**: pure functions, services, and utilities — no DB or network calls
- **Integration tests**: test routes with a real DB (use test transactions, rollback after each test)
- **E2E tests**: critical user flows only (submit KYC, approve, reject)
- Minimum coverage targets: 80% lines, 70% branches
- Test files live alongside source (`*.test.ts`) or in `tests/` — be consistent within the project
- Mock external provider calls (Onfido, Jumio, etc.) in all tests; never call live APIs in CI

---

## Development Workflow

```bash
# Start dependencies
docker compose up -d postgres redis

# Install dependencies
npm install   # or: pip install -r requirements.txt

# Run migrations
npm run migrate   # or: alembic upgrade head

# Start dev server (hot reload)
npm run dev   # or: uvicorn app.main:app --reload

# Run tests
npm test              # all tests
npm run test:unit     # unit only
npm run test:int      # integration only

# Lint & format
npm run lint
npm run format

# Type check
npm run typecheck
```

---

## CI/CD

All PRs must pass:
1. Lint (ESLint / Ruff)
2. Type check (tsc / mypy)
3. Unit tests
4. Integration tests
5. Security scan (npm audit / pip-audit)

Deployment pipeline (once configured):
- `main` → staging (automatic on merge)
- `main` → production (manual approval gate)

---

## Working with AI Assistants (Claude-specific notes)

- Before modifying any file, **read it first** to understand existing patterns.
- **Do not introduce new dependencies** without checking if an existing utility already covers the need.
- **Never log PII** — if adding logging statements, ensure all identity fields are omitted or masked.
- **Never bypass auth middleware** — every route that accesses applicant data must be protected.
- When adding endpoints, follow the existing response envelope format exactly.
- When writing migrations, make them **reversible** (include a `down` migration).
- Always run `npm run typecheck` and `npm run lint` before committing.
- Keep commits atomic: one logical change per commit.
- Update `.env.example` if you add a new environment variable.
- Add a corresponding test for any new service method or API endpoint.

---

## Key Files to Read First

When starting work in this repository, read these files in order:

1. `CLAUDE.md` (this file)
2. `src/config/index.ts` — environment variable parsing and validation
3. `src/models/` — data model definitions
4. `src/api/` — route definitions
5. `src/services/` — core business logic
6. `tests/` — understand test patterns before writing new tests

---

*Last updated: 2026-03-12*
