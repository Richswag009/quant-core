# Quant Core — Batch Disbursement API

A tenant-aware Laravel API for institutional batch loan disbursements.
Operations users upload payout instructions in bulk, validate them,
route for approval, and post them asynchronously without duplicate processing.

Built for Quant Systems Backend Developer Technical Assessment.

---

## Overview

```
Operations user uploads CSV or JSON batch
        ↓
System validates every line item
        ↓
Batch routed for approval
        ↓
Approver approves or rejects
        ↓
System posts asynchronously via queued job
        ↓
Each line item marked success or failure individually
        ↓
Failed items can be retried without reposting successful ones
        ↓
Full audit trail maintained for every action
```

---

## Quick Start with Docker

### Prerequisites

- Docker Desktop installed and running

### 1. Clone the repository

```bash
git clone <repo-url>
cd bacthprocessing
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

---

## Sample Credentials

Two tenants are seeded automatically on startup.

### Tenant 1 — First Bank MFB

```
Admin:    admin@firstbank.com    / Blueprint@1995
Approver: approver@firstbank.com / Blueprint@1995
Operator: operator@firstbank.com / Blueprint@1995
```

### Tenant 2 — RMF23 Cooperative

```
Admin:    admin@rmf23.com    / Blueprint@1995
Approver: approver@rmf23.com / Blueprint@1995
Operator: operator@rmf23.com / Blueprint@1995
```

---

## Sample CSV File

Save as `sample_disbursement.csv` and use with the CSV upload endpoint.

```csv
beneficiary_name,account_number,bank_code,amount,narration,external_reference
Amaka Johnbull,0123456789,044,50000.00,Loan disbursement March 2026,REF_20260301_001
Chukwuemeka Obi,9876543210,058,75000.00,Loan disbursement March 2026,REF_20260301_002
Fatima Abdullahi,1122334455,033,100000.00,Loan disbursement March 2026,REF_20260301_003
Taiwo Adeyemi,5544332211,011,25000.00,Loan disbursement March 2026,REF_20260301_004
Ngozi Eze,6677889900,057,60000.00,Loan disbursement March 2026,REF_20260301_005
```

A copy of this file is available at `storage/samples/sample_disbursement.csv`.

---

## API Reference

Base URL: `http://localhost:8000/api/v1`

All endpoints except `/auth/login` and `/health` require:

```
Authorization: Bearer {token}
Content-Type: application/json
```

---

### Authentication

**Login**

```
POST /api/v1/auth/login
```

```json
{
    "email": "operator@firstbank.com",
    "password": "Blueprint@1995"
}
```

Response:

```json
{
    "status": true,
    "message": "Login successful",
    "data": {
        "token": "1|abc123...",
        "user": {
            "slug": 1,
            "name": "Operator User",
            "email": "operator@firstbank.com",
            "role": "operator"
        }
    }
}
```

**Get authenticated user**

```
GET /api/v1/auth/user
```

**Logout**

```
POST /api/v1/auth/logout
```

---

### Batch Management

**Create batch — JSON**

```
POST /api/v1/batches
```

```json
{
    "source": "json",
    "items": [
        {
            "beneficiary_name": "Amaka Johnbull",
            "account_number": "0123456789",
            "bank_code": "044",
            "amount": 50000,
            "narration": "Loan disbursement March 2026",
            "external_reference": "REF_001"
        }
    ]
}
```

**Create batch — CSV**

```
POST /api/v1/batches
Content-Type: multipart/form-data

source: csv
file: sample_disbursement.csv
```

**List batches**

```
GET /api/v1/batches
GET /api/v1/batches?status=validated
GET /api/v1/batches?status=approved&per_page=10
GET /api/v1/batches?source=csv
GET /api/v1/batches?from=2026-06-01&to=2026-06-07
```

**Get single batch**

```
GET /api/v1/batches/{batch_id}
```

**Get batch items**

```
GET /api/v1/batches/{batch_id}/items
GET /api/v1/batches/{batch_id}/items?status=FAILED
GET /api/v1/batches/{batch_id}/items?status=POSTED
```

**Get audit trail**

```
POST /api/v1/batches/{batch_id}/audits
```

---

### Batch Workflow

**Validate batch**

```
GET /api/v1/batches/{batch_id}/validate
```

Validates all items. Marks each as valid or invalid with error message.
Batch moves to `validated` only when all items pass.

**Submit for approval**

```
POST /api/v1/batches/{batch_id}/submit
```

Batch must be `validated`. Moves to `pending_approval`.

**Approve batch** _(approver or admin role only)_

```
POST /api/v1/batches/{batch_id}/approve
```

Batch must be `pending_approval`. Moves to `approved`.

**Reject batch** _(approver or admin role only)_

```
POST /api/v1/batches/{batch_id}/reject
```

```json
{
    "rejection_reason": "Invalid account numbers in items 2 and 4"
}
```

Batch moves to `rejected`. Terminal state — no further actions.

**Post batch** _(admin role only)_

```
POST /api/v1/batches/{batch_id}/post
```

Batch must be `approved`. Returns 202 immediately.
Posting happens asynchronously via queued job.
Protected by idempotency key — cannot be posted twice.

**Retry failed items** _(admin role only)_

```
POST /api/v1/batches/{batch_id}/retry
```

Only retries items with `failed` status.
Never reposts items already marked `posted`.

---

## Batch Status Flow

```
draft → validated → pending_approval → approved → posting → posted
                                     → rejected (terminal)
posted → partially_posted (if any items failed)
```

---

## Batch Item Statuses

```
pending          ← created, not yet validated
valid            ← passed validation
invalid          ← failed validation (see validation_error)
posted           ← successfully disbursed
failed           ← posting failed (see posting_error)
```

---

## Validation Rules

Per line item:

```
beneficiary_name    required
account_number      required, exactly 10 digits
bank_code           required, exactly 3 digits
amount              required, numeric, greater than 0
narration           required, max 100 characters
external_reference  required, unique within the batch
```

---

## Architecture Notes

### Tenant Isolation

Every database table includes a `tenant_id` column. A `TenantScope` global
scope is applied to all models, automatically filtering every query to the
authenticated user's tenant. No cross-tenant data leakage is possible.

```php
// TenantScope applied automatically to every query
Batch::all(); // only returns batches for the current tenant
```

### Async Posting

Batch posting is handled by `PostBatchJob` dispatched to the database queue.
The API returns 202 immediately without waiting for posting to complete.
The queue worker processes each line item individually, recording success
or failure per item.

### Retry Logic

Retry dispatches the same `PostBatchJob`. The job filters items by status:

```
whereIn('status', ['valid', 'failed'])
```

Items already marked `posted` are never reprocessed. Safe to retry multiple times.

### Audit Trail

Every state change is logged to `audit_trails` with:

- tenant_id, batch_id, user_id
- action (created, validated, submitted, approved, rejected, posted, retried)
- metadata (contextual data per action)
- timestamp

---

## Idempotency Strategy

Batch posting is protected against double-posting using an idempotency key
stored in the `idempotency_keys` table.

```
key format: post_batch_{batch_id}
```

Before dispatching the posting job:

1. Check if key exists for this tenant
2. If exists → return error (already posted)
3. If not → store key + update status + dispatch job (wrapped in DB transaction)

The key creation and status update happen in the same database transaction.
If the job dispatch fails after the transaction, the key prevents a second
dispatch. If needed, an admin can delete the idempotency key and retry.

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── BatchController.php
│   │   └── AuditTrailController.php
│   ├── Requests/
│   │   ├── CreateBatchRequest.php
│   │   └── RejectBatchRequest.php
│   └── Traits/
│       └── ResponseTrait.php
├── Models/
│   ├── Tenant.php
│   ├── User.php
│   ├── Batch.php
│   ├── BatchItem.php
│   ├── AuditTrail.php
│   ├── IdempotencyKey.php
│   └── Scopes/TenantScope.php
├── Services/
│   ├── Batch/
│   │   ├── BatchService.php
│   │   ├── BatchValidationService.php
│   │   ├── BatchParserService.php
│   │   └── FakePostingService.php
│   └── AuditTrails/
│       └── AuditTrailService.php
├── Jobs/
│   └── PostBatchJob.php
└── Enums/
    ├── BatchStatus.php
    └── BatchStatusItem.php
```

---

## Assumptions and Tradeoffs

**SQLite over PostgreSQL**
Chosen per assessment requirement for self-contained, filesystem-backed
persistence. SQLite runs inside the container with no external dependencies.
In production this would be PostgreSQL with connection pooling.

**FakePostingService**
Real bank API integration was not required. The fake service simulates
10% failure and 10% timeout rates via configurable environment variables.
In production this would call Paystack, Flutterwave, or NIBSS.

**Role-based access without a permissions table**
Roles are stored as a string enum on the users table (operator, approver, admin).
A full RBAC system with a permissions table would be more flexible but adds
complexity beyond what this assessment requires.

**Batch validation as a separate step**
Validation is intentionally separated from creation. This allows operations
users to upload a batch, fix individual items externally, and re-validate
without recreating the entire batch.

**Re-validation only processes invalid items**
Once an item is marked valid it is not re-validated on subsequent runs.
This prevents unnecessary processing and avoids overwriting correct items.

---

## What I Would Improve With More Time

- Replace FakePostingService with a real bank API integration
- Add webhook notifications when batch posting completes
- Add a CSV template download endpoint
- Implement a full RBAC permissions system
- Add rate limiting on API endpoints
- Write comprehensive unit and integration tests
- Add batch expiry — auto-reject batches pending approval for too long
- Add soft deletes on batches for audit compliance
- Add a force-retry mechanism for FAILED batches that clears
  the idempotency key after admin confirmation, with safeguards
  against partial double-posting

---

## AI Assistance Disclosure

As requested by the assessment, the following areas involved AI assistance:

- **Docker setup** — Dockerfile and docker-compose.yml configuration,
  specifically the entrypoint setup and SQLite path resolution between
  host and container environments.

All business logic, domain modeling, service architecture, tenant isolation
strategy, validation rules, approval workflow, idempotency implementation,
and audit trail design were written independently.

---

## Health Check

```
GET /health
```

No authentication required. Returns API status and version.
