# Quant Core — Batch Disbursement API

A tenant-aware Laravel API for institutional batch loan disbursements.
Operations users upload payout instructions in bulk, validate them,
route for approval, and post them asynchronously without duplicate processing.

Built for Quant Systems Backend Developer Technical Assessment.

---

## Quick Start with Docker

### Prerequisites

- Docker Desktop installed and running

### 1. Clone the repository

```bash
git clone git@github.com:Richswag009/quant-core.git
cd quant-core
```

### 2. Start the application

```bash
docker compose up --build
```

This starts two services:

- `quant-core-app` — Laravel API on port 8000
- `quant-core-queue` — Queue worker processing disbursement jobs

### 3. Verify it's running

```bash
curl http://localhost:8000/health
```

Expected response:

```json
{
    "status": "OK",
    "timestamp": "2026-06-07T00:00:00.000000Z",
    "app": "quannt-core API",
    "version": "1.0.0"
}
```

### 4. Stop the application

```bash
docker compose down
```

### 5. Import Postman Collection

Import `quant-core.postman_collection.json` from the project root into Postman
to get all endpoints pre-configured with examples.

---

## Sample Credentials

Two tenants are seeded automatically on startup.

### Tenant 1 — First Bank MFB

| Role     | Email                  | Password |
| -------- | ---------------------- | -------- |
| Admin    | admin@firstbank.com    | password |
| Approver | approver@firstbank.com | password |
| Operator | operator@firstbank.com | password |

### Tenant 2 — RMF23 Cooperative

| Role     | Email              | Password |
| -------- | ------------------ | -------- |
| Admin    | admin@rmf23.com    | password |
| Approver | approver@rmf23.com | password |
| Operator | operator@rmf23.com | password |

---

## Roles & Permissions

| Action                  | Operator | Approver | Admin |
| ----------------------- | -------- | -------- | ----- |
| Create batch            | ✅       | ✅       | ✅    |
| View own batches        | ✅       | ❌       | ❌    |
| View all tenant batches | ❌       | ✅       | ✅    |
| Validate batch          | ✅       | ✅       | ✅    |
| Submit for approval     | ✅       | ✅       | ✅    |
| Approve batch           | ❌       | ✅       | ✅    |
| Reject batch            | ❌       | ✅       | ✅    |
| Post batch              | ❌       | ❌       | ✅    |
| Retry failed items      | ❌       | ❌       | ✅    |
| Delete batch            | ❌       | ❌       | ✅    |

> Operators only see batches they created. Approvers and Admins see all batches within their tenant.

---

## Authentication

Quant Core uses Laravel Sanctum token-based authentication.

### Login

```
POST /api/v1/auth/login
```

Request:

```json
{
    "email": "operator@firstbank.com",
    "password": "password"
}
```

Response:

```json
{
    "status": true,
    "message": "Login successful",
    "data": {
        "token": "1|abc123xyz...",
        "user": {
            "name": "Operator User",
            "email": "operator@firstbank.com",
            "role": "operator"
        }
    }
}
```

### Using the token

Include the token in all subsequent requests:

```
Authorization: Bearer 1|abc123xyz...
Content-Type: application/json
```

### Get authenticated user

```
GET /api/v1/auth/user
```

### Logout

```
POST /api/v1/auth/logout
```

Revokes the current token.

---

## Batch Status Reference

| Status             | Description                                  |
| ------------------ | -------------------------------------------- |
| `draft`            | Created, not yet validated                   |
| `validated`        | All items passed validation                  |
| `pending_approval` | Submitted, awaiting approver decision        |
| `approved`         | Approved, ready for posting                  |
| `posting`          | Job dispatched, currently processing         |
| `posted`           | All items successfully disbursed             |
| `partially_posted` | Some items posted, some failed               |
| `rejected`         | Rejected by approver — terminal state        |
| `failed`           | Posting job exhausted all retries — terminal |

### Status Flow

```
draft → validated → pending_approval → approved → posting → posted
                                     → rejected (terminal)
                                                 → partially_posted (some failed)
                                                 → failed (job crashed)
```

---

## Batch Item Status Reference

| Status    | Description                                |
| --------- | ------------------------------------------ |
| `pending` | Created, not yet validated                 |
| `valid`   | Passed all validation rules                |
| `invalid` | Failed validation — see `validation_error` |
| `posted`  | Successfully disbursed                     |
| `failed`  | Posting failed — see `posting_error`       |

---

## API Reference

Base URL: `http://localhost:8000/api/v1`

All endpoints except `/auth/login` and `/health` require:

```
Authorization: Bearer {token}
Content-Type: application/json
```

---

### Create Batch — JSON

```
POST /api/v1/batches
```

Request:

```json
{
    "source": "json",
    "items": [
        {
            "beneficiary_name": "Amaka Johnbull",
            "account_number": "0123456789",
            "bank_code": "044",
            "amount": 50000.0,
            "narration": "Loan disbursement March 2026",
            "external_reference": "REF_20260301_001"
        }
    ]
}
```

Response `201`:

```json
{
    "status": true,
    "message": "Batch created successfully",
    "data": {
        "id": "uuid",
        "status": "draft",
        "source": "json",
        "total_items": 1,
        "total_amount": "50000.00",
        "created_at": "2026-06-07T00:00:00.000000Z"
    }
}
```

---

### Create Batch — CSV

```
POST /api/v1/batches
Content-Type: multipart/form-data
```

| Field  | Value             |
| ------ | ----------------- |
| source | csv               |
| file   | disbursements.csv |

---

### CSV Format Guide

```csv
beneficiary_name,account_number,bank_code,amount,narration,external_reference
Amaka Johnbull,0123456789,044,50000.00,Loan disbursement March 2026,REF_20260301_001
Chukwuemeka Obi,9876543210,058,75000.00,Loan disbursement March 2026,REF_20260301_002
Fatima Abdullahi,1122334455,033,100000.00,Loan disbursement March 2026,REF_20260301_003
Taiwo Adeyemi,5544332211,011,25000.00,Loan disbursement March 2026,REF_20260301_004
Ngozi Eze,6677889900,057,60000.00,Loan disbursement March 2026,REF_20260301_005
```

A sample file is available at `storage/samples/sample_disbursement.csv`.

### CSV Field Rules

| Field              | Required | Format                     |
| ------------------ | -------- | -------------------------- |
| beneficiary_name   | Yes      | String                     |
| account_number     | Yes      | Exactly 10 digits          |
| bank_code          | Yes      | Exactly 3 digits           |
| amount             | Yes      | Numeric, greater than 0    |
| narration          | Yes      | String, max 100 characters |
| external_reference | Yes      | Unique within the batch    |

### CSV Parsing Errors

| Error                   | Cause                             |
| ----------------------- | --------------------------------- |
| Missing required column | CSV header row is missing a field |
| Invalid file format     | File is not a valid CSV           |
| Empty file              | CSV has no data rows              |

---

### Validate Batch

```
POST /api/v1/batches/{batch_id}/validate
```

Validates all items against the rules above. Items already marked `valid`
are skipped on re-validation.

Response:

```json
{
    "status": true,
    "message": "Batch validated",
    "data": {
        "batch_id": "uuid",
        "status": "validated",
        "valid_items": 4,
        "invalid_items": 1
    }
}
```

### Validation Rules Per Item

| Field              | Rule                                       |
| ------------------ | ------------------------------------------ |
| beneficiary_name   | Required                                   |
| account_number     | Required, exactly 10 digits (`/^\d{10}$/`) |
| bank_code          | Required, exactly 3 digits (`/^\d{3}$/`)   |
| amount             | Required, numeric, greater than 0          |
| narration          | Required, max 100 characters               |
| external_reference | Required, unique within this batch         |

### Validation Error Example

```json
{
    "id": "uuid",
    "status": "invalid",
    "validation_error": "account_number must be exactly 10 digits, bank_code must be exactly 3 digits"
}
```

---

### Submit Batch for Approval

```
POST /api/v1/batches/{batch_id}/submit
```

Batch must be in `validated` status.

Response `200`:

```json
{
    "status": true,
    "message": "Batch submitted for approval",
    "data": {
        "id": "uuid",
        "status": "pending_approval",
        "submitted_at": "2026-06-07T00:00:00.000000Z"
    }
}
```

---

### Approve Batch

```
POST /api/v1/batches/{batch_id}/approve
```

Requires `approver` or `admin` role. Batch must be `pending_approval`.

Response `200`:

```json
{
    "status": true,
    "message": "Batch approved successfully",
    "data": {
        "id": "uuid",
        "status": "approved",
        "approved_at": "2026-06-07T00:00:00.000000Z"
    }
}
```

---

### Reject Batch

```
POST /api/v1/batches/{batch_id}/reject
```

Requires `approver` or `admin` role. Batch must be `pending_approval`.

Request:

```json
{
    "rejection_reason": "Invalid account numbers in items 2 and 4"
}
```

Response `200`:

```json
{
    "status": true,
    "message": "Batch rejected",
    "data": {
        "id": "uuid",
        "status": "rejected",
        "rejection_reason": "Invalid account numbers in items 2 and 4",
        "rejected_at": "2026-06-07T00:00:00.000000Z"
    }
}
```

---

### Post Batch

```
POST /api/v1/batches/{batch_id}/post
```

Requires `admin` role. Batch must be `approved`.

Returns 202 immediately. Posting happens asynchronously via queued job.
Protected by idempotency key — calling this twice returns an error.

Response `202`:

```json
{
    "status": true,
    "message": "Batch posting initiated",
    "data": {
        "batch_id": "uuid"
    }
}
```

---

### Retry Failed Items

```
POST /api/v1/batches/{batch_id}/retry
```

Requires `admin` role. Batch must be `posted` or `partially_posted`.

Only retries items with `failed` status. Items already marked `posted`
are never reprocessed.

Response `202`:

```json
{
    "status": true,
    "message": "Retry initiated for failed items"
}
```

---

### Get All Batches

```
GET /api/v1/batches
GET /api/v1/batches?status=validated
GET /api/v1/batches?source=csv
GET /api/v1/batches?from=2026-06-01&to=2026-06-07
GET /api/v1/batches?per_page=10&page=2
```

> Operators see only their own batches. Approvers and Admins see all tenant batches.

### Available Filters

| Filter   | Example             | Description                 |
| -------- | ------------------- | --------------------------- |
| status   | `?status=validated` | Filter by batch status      |
| source   | `?source=csv`       | Filter by source type       |
| from     | `?from=2026-06-01`  | Created from date           |
| to       | `?to=2026-06-07`    | Created to date             |
| per_page | `?per_page=20`      | Items per page (default 20) |

---

### Get Single Batch

```
GET /api/v1/batches/{batch_id}
```

Response includes batch summary:

```json
{
    "status": true,
    "data": {
        "id": "uuid",
        "status": "partially_posted",
        "total_items": 5,
        "total_amount": "310000.00",
        "summary": {
            "posted": 4,
            "failed": 1,
            "pending": 0,
            "invalid": 0
        },
        "created_by": {
            "name": "Operator User",
            "email": "operator@firstbank.com"
        }
    }
}
```

---

### Get Batch Items

```
GET /api/v1/batches/{batch_id}/items
GET /api/v1/batches/{batch_id}/items?status=failed
GET /api/v1/batches/{batch_id}/items?status=posted
```

| Filter | Values                                  |
| ------ | --------------------------------------- |
| status | pending, valid, invalid, posted, failed |

Response:

```json
{
    "status": true,
    "data": [
        {
            "id": 1,
            "beneficiary_name": "Amaka Johnbull",
            "account_number": "0123456789",
            "bank_code": "044",
            "amount": "50000.00",
            "status": "failed",
            "posting_error": "Bank API timeout: could not reach provider",
            "posted_at": null
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 1,
        "last_page": 1
    }
}
```

---

### Delete Batch

```
DELETE /api/v1/batches/{batch_id}
```

Requires `admin` role. Only batches in `draft` or `rejected` status can be deleted.
Batches that have been submitted, approved, posted, or partially posted cannot be deleted.

Response `200`:

```json
{
    "status": true,
    "message": "Batch deleted successfully"
}
```

Error when status is invalid:

```json
{
    "status": false,
    "message": "Only draft or rejected batches can be deleted"
}
```

---

### Get Audit Trail

```
GET /api/v1/batches/{batch_id}/audits
```

Response:

```json
{
    "status": true,
    "data": [
        {
            "action": "CREATED",
            "performed_by": "Operator User",
            "metadata": { "total_items": 5, "source": "csv" },
            "created_at": "2026-06-07T00:00:00.000000Z"
        },
        {
            "action": "VALIDATED",
            "performed_by": "Operator User",
            "metadata": { "valid": 4, "invalid": 1 },
            "created_at": "2026-06-07T00:01:00.000000Z"
        },
        {
            "action": "APPROVED",
            "performed_by": "Approver User",
            "metadata": {},
            "created_at": "2026-06-07T00:05:00.000000Z"
        },
        {
            "action": "POSTED",
            "performed_by": "system",
            "metadata": { "posted": 4, "failed": 1 },
            "created_at": "2026-06-07T00:06:00.000000Z"
        }
    ]
}
```

---

## HTTP Status Codes Reference

| Code | Meaning                                              |
| ---- | ---------------------------------------------------- |
| 200  | Success                                              |
| 201  | Resource created                                     |
| 202  | Accepted — async job dispatched                      |
| 400  | Bad request — invalid action for current batch state |
| 401  | Unauthenticated — missing or invalid token           |
| 403  | Forbidden — insufficient role                        |
| 404  | Batch not found                                      |
| 409  | Conflict — idempotency violation (already posted)    |
| 503  | Server error — unexpected failure                    |

---

## Common Errors & Troubleshooting

| Error                             | Cause                       | Fix                                |
| --------------------------------- | --------------------------- | ---------------------------------- |
| Batch must be VALIDATED           | Wrong status for action     | Validate batch first               |
| Batch already submitted           | Duplicate submit            | Check batch status                 |
| Only approvers can perform action | Wrong role                  | Login as approver or admin         |
| Batch already posted              | Idempotency key exists      | Batch was already dispatched       |
| No failed items to retry          | Nothing to retry            | Check item statuses                |
| Database file does not exist      | SQLite path issue in Docker | Run `docker compose up --build`    |
| Queue not processing              | Worker not running          | Check `quant-core-queue` container |

---

## Architecture Notes

### Tenant Isolation

Every database table includes a `tenant_id` column. A `TenantScope` global
scope is applied to all models, automatically filtering every query to the
authenticated user's tenant. No cross-tenant data leakage is possible.

### Async Posting

Batch posting is handled by `PostBatchJob` dispatched to the database queue.
The API returns 202 immediately. The queue worker processes each line item
individually, recording success or failure per item.

Job retry configuration:

```
$tries   = 3
$timeout = 60 seconds
$backoff = [10, 30, 60] seconds (exponential)
```

If the job exhausts all retries, `failed()` is called and batch status is
set to `FAILED`. The audit trail records the failure with the exception message.

### Retry Logic

Retry dispatches the same `PostBatchJob`. The job filters:

```php
whereIn('status', ['valid', 'failed'])
```

Items already marked `posted` are never reprocessed.

### Audit Trail

Every state change is logged with tenant_id, batch_id, user_id, action,
metadata, and timestamp. The queue worker passes its user ID explicitly
since `auth()` is unavailable in background jobs.

---

## Idempotency Strategy

Batch posting is protected against double-posting using an idempotency key
stored in the `idempotency_keys` table.

```
key format: post_batch_{batch_id}
```

Before dispatching:

1. Check if key exists for this tenant — return 409 if yes
2. Store key + update status in DB transaction
3. Dispatch job after transaction commits

If the job dispatch fails after the transaction, the key prevents a second
dispatch. This guarantees at-most-once dispatch even under failure conditions.

---

## Project Structure

```
app/
├── Actions/
│   ├── Auth/
│   │   └── CreateLogin.php              ← login action
│   └── Batch/
│       └── CreateBatch.php              ← bulk batch creation with transaction
├── Enums/
│   ├── BatchStatus.php                  ← batch status state machine
│   └── BatchStatusItem.php              ← individual item status
├── Exceptions/
│   ├── BatchException.php               ← known business rule violations (400)
│   ├── BuildResponse.php                ← response builder
│   └── ValidationResponseException.php ← validation error formatting
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── BatchController.php
│   │   ├── BatchItemController.php
│   │   ├── AuditTrailController.php
│   │   └── TenantController.php
│   ├── Requests/
│   │   ├── CreateBatchRequest.php       ← conditional rules for JSON and CSV
│   │   ├── LoginRequest.php
│   │   └── RejectBatchRequest.php
│   ├── Resources/
│   │   ├── BatchResource.php
│   │   ├── BatchItemResource.php
│   │   ├── AuditLogResource.php
│   │   ├── TenantResource.php
│   │   └── UserResource.php
│   └── Traits/
│       └── ResponseTrait.php            ← consistent JSON response formatting
├── Jobs/
│   └── PostBatchJob.php                 ← async posting with retry and backoff
├── Models/
│   ├── Tenant.php
│   ├── User.php
│   ├── Batch.php
│   ├── BatchItem.php
│   ├── AuditTrail.php
│   ├── IdempotencyKey.php
│   └── Scopes/TenantScope.php           ← global tenant isolation scope
├── Providers/
│   └── AppServiceProvider.php
└── Services/
    ├── AuditTrails/
    │   └── AuditTrailService.php        ← centralised audit logging
    └── Batch/
        ├── BatchService.php             ← orchestrates all batch operations
        ├── BatchValidationService.php   ← per-item validation rules
        ├── BatchParserService.php       ← CSV and JSON parsing
        └── FakePostingService.php       ← stubbed bank API with failure simulation
```

---

## Assumptions and Tradeoffs

**SQLite over PostgreSQL**
Chosen per assessment requirement. In production this would be PostgreSQL
with connection pooling and read replicas.

**FakePostingService**
Simulates 10% failure and 10% timeout rates via configurable env vars.
In production this would integrate with Paystack, Flutterwave, or NIBSS.

**Role-based access without a permissions table**
Roles stored as string enum (operator, approver, admin). A full RBAC
system would be more flexible but adds complexity beyond this assessment.

**Batch validation as a separate step**
Intentionally separated from creation. Operators can upload, fix items
externally, and re-validate without recreating the entire batch.

**Re-validation skips valid items**
Once marked valid an item is not re-validated. Prevents overwriting
correct items on subsequent validation runs.

**Operator batch visibility**
Operators only see batches they created. Approvers and admins see all
batches within their tenant. Enforced via `visibleTo()` model scope.

---

## What I Would Improve With More Time

- Replace FakePostingService with real bank API (Paystack/NIBSS)
- Add webhook notifications when batch posting completes
- Add CSV template download endpoint
- Implement full RBAC permissions table
- Add rate limiting on API endpoints
- Write comprehensive unit and integration tests
- Add batch expiry — auto-reject batches pending approval too long
- Add soft deletes on batches for audit compliance
- Add force-retry mechanism for FAILED batches with idempotency
  key clearance and admin confirmation safeguards

---

## AI Assistance Disclosure

As requested by the assessment, the following areas involved AI assistance:

- **Docker setup** — Dockerfile and docker-compose.yml configuration,
  specifically entrypoint setup and SQLite path resolution between
  host and container environments.
- **README writing** — Structure, formatting, and documentation content
  was drafted with AI assistance based on the actual implementation.

All business logic, domain modeling, service architecture, tenant isolation,
validation rules, approval workflow, idempotency, audit trail design,
and all PHP code were written independently.
