# SDSS Backend — System Flow Documentation

This document explains how every flow in the SDSS (Secure Document Sharing System) backend works, from registration to file access.

---

## Architecture Overview

```
Client (Postman / Frontend)
    │
    ▼
  Nginx (:8088)
    │
    ▼
  PHP-FPM (Laravel 12)
    │
    ├── Middleware Pipeline
    │     ├── auth:api (JWT verification via tymon/jwt-auth)
    │     ├── account.approved (EnsureAccountIsApproved)
    │     └── role:admin (EnsureRole)
    │
    ├── Controllers → Services → Models → PostgreSQL
    │
    └── Audit Logging (every significant action)
```

**Stack:** Laravel 12 + PHP 8.2 + PostgreSQL 15 + Nginx + Docker
**Auth:** JWT (tymon/jwt-auth) — stateless, no sessions

---

## Flow 1: User Registration

```
User submits POST /api/register
    │
    ▼
RegisterRequest validates:
    ├── first_name: required, string, max 255
    ├── last_name:  required, string, max 255
    ├── email:      required, valid email, unique in users table
    ├── password:   required, min 8, must match confirmation
    └── password_confirmation: required
    │
    ▼ (validation passes)
AuthController@register
    │
    ├── User::create() — password is auto-hashed via setPasswordAttribute() mutator
    │     └── status = 'pending', role = 'user'
    │
    ├── AuditLog entry: 'user.registered'
    │
    └── Response 201: { id, first_name, last_name, email }
```

**Key point:** The user CANNOT login yet. Status is `pending` — they must wait for admin approval.

### Pre-Registration: Email Check

```
User submits POST /api/check-email
    │
    ▼
Validates { email: required, valid email }
    │
    ▼
Checks User::where('email', ...)->exists()
    │
    └── Response 200: { available: true/false }
```

Use this before registration to show real-time email validation on the frontend.

---

## Flow 2: Admin Approval Pipeline

```
Admin logs in → GET /api/admin/users?status=pending
    │
    ▼ (sees pending users)
    │
    ├── PATCH /api/admin/users/{id}/approve
    │     ├── Validates: not already approved, not admin account
    │     ├── Sets status = 'approved'
    │     ├── AuditLog: 'user.approved'
    │     ├── Sends AccountApproved notification (database channel)
    │     └── User can now login
    │
    ├── PATCH /api/admin/users/{id}/reject
    │     ├── Validates: not admin account
    │     ├── Sets status = 'rejected'
    │     ├── AuditLog: 'user.rejected'
    │     ├── Sends AccountRejected notification
    │     └── User gets 403 "Registration was not approved" on login attempt
    │
    └── PATCH /api/admin/users/{id}/suspend
          ├── Validates: must be currently 'approved', not admin
          ├── Sets status = 'suspended'
          ├── AuditLog: 'user.suspended'
          ├── Sends AccountSuspended notification
          └── User gets 403 "Account has been suspended" on next login
```

**Status lifecycle:**
```
pending → approved → suspended
pending → rejected
```

Admin cannot change their own status (safety guard).

---

## Flow 3: Login & Authentication

```
User submits POST /api/login { email, password }
    │
    ▼
LoginRequest validates email + password format
    │
    ▼
auth('api')->attempt() — JWT driver checks credentials
    │
    ├── FAIL → 401 "Invalid credentials"
    │
    └── SUCCESS → check user.status
          │
          ├── status = 'approved' → proceed
          │     ├── AuditLog: 'user.logged_in'
          │     └── Response 200: { token, token_type, expires_in, user }
          │
          ├── status = 'pending'   → logout + 403 "Account awaiting admin approval"
          ├── status = 'suspended' → logout + 403 "Account has been suspended"
          └── status = 'rejected'  → logout + 403 "Registration was not approved"
```

**After login, the client stores the JWT token and sends it in all subsequent requests as:**
```
Authorization: Bearer <token>
```

---

## Flow 4: Middleware Pipeline (Every Authenticated Request)

Every request to a protected route goes through this pipeline:

```
Request arrives with Authorization: Bearer <token>
    │
    ▼
[1] auth:api middleware (JWT Guard)
    ├── Decodes JWT, verifies signature + expiry
    ├── FAIL → 401 "Unauthenticated."
    └── PASS → sets auth('api')->user()
    │
    ▼
[2] account.approved middleware (EnsureAccountIsApproved)
    ├── Re-fetches user from DB (fresh status check)
    │     └── Why? Admin may have suspended user AFTER token was issued
    ├── status = 'approved'  → PASS
    ├── status = 'pending'   → 403
    ├── status = 'suspended' → 403
    └── status = 'rejected'  → 403
    │
    ▼
[3] role:admin middleware (admin routes only)
    ├── Checks user.role === 'admin'
    ├── FAIL → 403 "Unauthorized. Insufficient permissions."
    └── PASS → proceeds to controller
```

**Important:** Even if a user has a valid JWT, if the admin suspends them, the `account.approved` middleware blocks them on the NEXT request.

---

## Flow 5: Token Lifecycle

```
Login → token issued (TTL = 60 min from config)
    │
    ├── POST /api/auth/refresh → new token issued, old one invalidated
    │     └── Tip: call this before token expires to stay logged in
    │
    └── POST /api/logout → token invalidated immediately
          └── Any request with that token → 401
```

---

## Flow 6: Folder Management

```
All folder operations require auth + approved status.
Ownership enforced via FolderPolicy (user_id must match auth user).

CREATE FOLDER:
    POST /api/folders { name, parent_id? }
        │
        ├── If parent_id given → verify parent exists & owned by user
        ├── Check no duplicate name at same level (user + parent_id + name)
        ├── Create Folder record
        ├── AuditLog: 'folder.created'
        └── Response 201

VIEW FOLDER:
    GET /api/folders/{id}
        │
        ├── FolderPolicy: must own folder
        └── Returns: folder metadata + subfolders[] + files[]

LIST ROOT FOLDERS:
    GET /api/folders
        └── Returns all folders where parent_id = null for auth user

RENAME FOLDER:
    PATCH /api/folders/{id} { name }
        ├── FolderPolicy: must own
        ├── Duplicate name check at same level
        └── AuditLog: 'folder.renamed'

MOVE FOLDER:
    PATCH /api/folders/{id}/move { parent_id }
        ├── FolderPolicy: must own
        ├── Cannot move into itself
        ├── Cannot move into own descendant (circular reference check)
        │     └── isDescendantOf() walks up the parent chain
        ├── Target parent must be owned by user
        ├── Duplicate name check at target level
        └── AuditLog: 'folder.moved'

DELETE FOLDER:
    DELETE /api/folders/{id}
        ├── FolderPolicy: must own
        ├── Cascading delete via FK constraints (subfolders + files)
        └── AuditLog: 'folder.deleted'
```

**Folder tree structure:**
```
User's storage
├── Folder A (parent_id: null)
│   ├── Subfolder A1 (parent_id: Folder A)
│   │   └── file1.pdf
│   └── file2.doc
├── Folder B (parent_id: null)
└── file3.txt (no folder — root level)
```

---

## Flow 7: File Upload & Management

### Upload Flow

```
POST /api/files (multipart/form-data)
    │
    ▼
StoreFileRequest validates:
    ├── file: required, actual file, max 50MB
    └── folder_id: nullable, integer, exists in folders table
    │
    ▼
FileController@store
    │
    ├── If folder_id given → verify folder exists & owned by user
    │
    ├── FileStorageService@store:
    │     ├── Generate unique storage path: users/{user_id}/{uuid}.{ext}
    │     ├── Write file to local disk (storage/app/users/...)
    │     └── Create File record with:
    │           ├── name = original filename
    │           ├── original_name = original filename
    │           ├── storage_path = internal path (HIDDEN from API responses)
    │           ├── mime_type = detected MIME
    │           └── size = file size in bytes
    │
    ├── AuditLog: 'file.uploaded'
    └── Response 201: FileResource (no storage_path exposed)
```

**Security:** `storage_path` is in `$hidden` on the File model — never leaked to clients.

### Download Flow

```
GET /api/files/{id}/download
    │
    ├── FilePolicy@download: must own file
    ├── AuditLog: 'file.downloaded'
    └── StreamedResponse via Storage::download()
```

### Rename / Move / Delete

```
RENAME: PATCH /api/files/{id} { name }
    ├── FilePolicy: must own
    ├── Duplicate name check in same folder
    └── AuditLog: 'file.renamed'

MOVE: PATCH /api/files/{id}/move { folder_id }
    ├── FilePolicy: must own
    ├── Target folder must be owned by user
    ├── Duplicate name check in target folder
    └── AuditLog: 'file.moved'

DELETE: DELETE /api/files/{id}
    ├── FilePolicy: must own
    ├── Revokes all active authorization tokens for this file
    ├── Rejects all pending access requests for this file
    ├── FileStorageService@delete: removes file from disk + DB
    └── AuditLog: 'file.deleted'
```

---

## Flow 8: Browsing Other Users' Files (Not Yet Active)

> Routes are currently commented out.

```
GET /api/browse/users
    └── Lists all approved non-admin users (except self)

GET /api/browse/users/{id}/folders
    └── Lists target user's root folders (metadata only)

GET /api/browse/users/{id}/folders/{folder}
    └── Shows folder contents (subfolders + files, metadata only)

GET /api/browse/users/{id}/files
    └── Lists target user's root-level files (metadata only)
```

**You can see WHAT files exist, but you CANNOT download them.** You must submit an access request.

---

## Flow 9: Access Request & Authorization Token (Not Yet Active)

> Routes are currently commented out.

This is the core security flow for sharing files between users:

```
Step 1: User B browses User A's files and finds a file they need.

Step 2: User B requests access
    POST /api/files/{file_id}/access-requests { message? }
        │
        ├── Cannot request access to own file → 403
        ├── Cannot have duplicate pending request for same file → 409
        ├── Creates AccessRequest: status = 'pending'
        ├── Notifies file owner (User A) via database notification
        └── AuditLog: 'access.requested'

Step 3: User A sees incoming request
    GET /api/access-requests/incoming
        └── Shows all access requests for files owned by User A

Step 4a: User A APPROVES
    PATCH /api/access-requests/{id}/approve
        │
        ├── AccessRequestPolicy: must own the file
        ├── Sets status = 'approved'
        ├── Generates AuthorizationToken:
        │     ├── token = random 64-char string
        │     ├── Tied to: user_id (requester), file_id, access_request_id
        │     └── expires_at = now + 5 MINUTES
        │
        ├── Notifies User B with token details
        ├── AuditLog: 'access.approved' + 'token.generated'
        └── Response includes the authorization token

Step 4b: User A REJECTS
    PATCH /api/access-requests/{id}/reject { rejection_reason? }
        ├── Sets status = 'rejected'
        ├── Notifies User B
        └── AuditLog: 'access.rejected'

Step 5: User B downloads file using token
    GET /api/files/access/{token}
        │
        ├── AuthorizationTokenService@validate:
        │     ├── Token exists?    → 404 if not
        │     ├── Token expired?   → 403 (5-min window)
        │     ├── Already used?    → 403 (single-use)
        │     └── Revoked?         → 403
        │
        ├── Verify token.user_id === auth user (can't use someone else's token)
        │     └── Mismatch → 403 + AuditLog: 'file.unauthorized_access_attempt'
        │
        ├── Consume token (set used_at = now) BEFORE streaming
        │     └── Security: token is burned even if download fails
        │
        ├── AuditLog: 'file.accessed_via_token'
        └── Stream file download
```

**Authorization Token Security:**
- **Single-use:** Once consumed, cannot be reused
- **Short-lived:** Expires after 5 minutes
- **Owner-bound:** Only the intended user can use it
- **Revocable:** Tokens are revoked when the file is deleted
- **Audited:** Every access attempt is logged

---

## Flow 10: Notifications (Not Yet Active)

> Routes are currently commented out.

```
GET /api/notifications             → paginated list of all notifications
GET /api/notifications/unread-count → { count: N }
PATCH /api/notifications/{id}/read → mark single notification as read
POST /api/notifications/read-all   → mark all as read
```

**Notifications are triggered by:**
| Event | Recipient | Content |
|-------|-----------|---------|
| User approved | User | "Your account has been approved" |
| User rejected | User | "Your registration was not approved" |
| User suspended | User | "Your account has been suspended" |
| Access requested | File owner | "{name} requested access to {file}" |
| Access approved | Requester | "{owner} approved your access to {file}" + token |
| Access rejected | Requester | "{owner} rejected your access request for {file}" |

All notifications use Laravel's `database` channel (stored in `notifications` table).

---

## Flow 11: Audit Logging

Every significant action is recorded in the `audit_logs` table:

```
AuditLogService@log(action, resource?, metadata?)
    │
    └── Creates AuditLog:
          ├── user_id     = authenticated user (from JWT)
          ├── action       = e.g., 'user.registered', 'file.uploaded'
          ├── resource_type = e.g., 'user', 'file', 'folder'
          ├── resource_id   = ID of the affected resource
          ├── metadata      = extra context (JSON)
          ├── ip_address    = client IP
          └── created_at    = timestamp
```

**Audit logs are IMMUTABLE** — the model overrides `update()` and `delete()` to throw exceptions.

**All tracked actions:**
| Action | When |
|--------|------|
| `user.registered` | New user registers |
| `user.logged_in` | Successful login |
| `user.logged_out` | User logs out |
| `user.approved` | Admin approves user |
| `user.rejected` | Admin rejects user |
| `user.suspended` | Admin suspends user |
| `folder.created` | Folder created |
| `folder.renamed` | Folder renamed |
| `folder.moved` | Folder moved |
| `folder.deleted` | Folder deleted |
| `file.uploaded` | File uploaded |
| `file.renamed` | File renamed |
| `file.moved` | File moved |
| `file.deleted` | File deleted |
| `file.downloaded` | Owner downloads own file |
| `access.requested` | User requests file access |
| `access.approved` | Owner approves access |
| `access.rejected` | Owner rejects access |
| `token.generated` | Authorization token created |
| `file.accessed_via_token` | File downloaded via token |
| `file.unauthorized_access_attempt` | Wrong user tried to use a token |

---

## Data Model Relationships

```
User
 ├── has many Folders
 ├── has many Files
 ├── has many AccessRequests (as requester)
 ├── has many AuthorizationTokens
 ├── has many AuditLogs
 └── has many Notifications (Laravel polymorphic)

Folder
 ├── belongs to User
 ├── belongs to Folder (parent — self-referencing)
 ├── has many Folders (children)
 └── has many Files

File
 ├── belongs to User
 ├── belongs to Folder (nullable)
 ├── has many AccessRequests
 └── has many AuthorizationTokens

AccessRequest
 ├── belongs to User (requester)
 ├── belongs to File
 └── has one AuthorizationToken

AuthorizationToken
 ├── belongs to AccessRequest
 ├── belongs to User
 └── belongs to File
```

---

## Security Summary

| Layer | Mechanism |
|-------|-----------|
| Authentication | JWT (stateless, 60-min TTL) |
| Authorization | Policies (owner-only for files/folders) |
| Account gating | EnsureAccountIsApproved middleware (re-checks DB on every request) |
| Role enforcement | EnsureRole middleware |
| File sharing | Time-limited, single-use authorization tokens |
| Password storage | bcrypt (via model mutator) |
| File path hiding | `storage_path` in `$hidden` — never in API responses |
| Audit trail | Immutable audit logs for every action |
| Input validation | FormRequest classes with strict rules |

---

## Active vs Commented-Out Routes

| Status | Routes |
|--------|--------|
| **Active** | register, login, check-email, logout, refresh, me |
| **Active** | admin/users (list, show, approve, reject, suspend) |
| **Active** | folders (list, show, create, rename, move, delete) |
| **Active** | files (list, upload, rename, move, delete, download) |
| Commented | audit-logs (admin) |
| Commented | browse (users, folders, files) |
| Commented | access-requests (store, outgoing, incoming, approve, reject) |
| Commented | file access via token |
| Commented | notifications |
