# SDSS API Contract

**Base URL:** `http://localhost:8088/api`
**Auth:** JWT Bearer Token via `Authorization: Bearer <token>` header
**Content-Type:** `application/json` (except file uploads which use `multipart/form-data`)
**Accept:** `application/json`

> All responses follow the standard envelope:
> ```json
> { "success": true|false, "message": "...", "data": { ... } }
> ```

> **Swagger/OpenAPI:** After installing dependencies, generate the spec with:
> ```bash
> ./vendor/bin/openapi --output public/openapi.json app/
> ```
> Then open `public/openapi.json` in Swagger UI or import into Postman.

---

## Test Results Summary

| # | Endpoint | Method | Status | Verified |
|---|----------|--------|--------|----------|
| 1 | /api/register | POST | 201 Created | PASS |
| 2 | /api/login | POST | 200 OK / 401 / 403 | PASS |
| 3 | /api/check-email | POST | 200 OK | NEW |
| 4 | /api/logout | POST | 200 OK | PASS |
| 5 | /api/auth/refresh | POST | 200 OK | PASS |
| 6 | /api/me | GET | 200 OK | PASS |
| 7 | /api/admin/users | GET | 200 OK | PASS |
| 8 | /api/admin/users/{id} | GET | 200 OK / 404 | PASS |
| 9 | /api/admin/users/{id}/approve | PATCH | 200 OK / 422 | PASS |
| 10 | /api/admin/users/{id}/reject | PATCH | 200 OK / 422 | PASS |
| 11 | /api/admin/users/{id}/suspend | PATCH | 200 OK / 422 | PASS |
| 12 | /api/folders | GET | 200 OK | NEW |
| 13 | /api/folders/{id} | GET | 200 OK | NEW |
| 14 | /api/folders | POST | 201 Created | NEW |
| 15 | /api/folders/{id} | PATCH | 200 OK | NEW |
| 16 | /api/folders/{id}/move | PATCH | 200 OK | NEW |
| 17 | /api/folders/{id} | DELETE | 200 OK | NEW |
| 18 | /api/files | GET | 200 OK | NEW |
| 19 | /api/files | POST | 201 Created | NEW |
| 20 | /api/files/{id} | PATCH | 200 OK | NEW |
| 21 | /api/files/{id}/move | PATCH | 200 OK | NEW |
| 22 | /api/files/{id} | DELETE | 200 OK | NEW |
| 23 | /api/files/{id}/download | GET | 200 (stream) | NEW |

---

## Postman Setup Steps

### Step 1: Create Environment

Create a Postman environment called **SDSS Local** with these variables:

| Variable | Initial Value |
|----------|---------------|
| `base_url` | `http://localhost:8088/api` |
| `admin_email` | `admin@sdss.local` |
| `admin_password` | `Admin@1234` |
| `token` | *(leave empty — auto-filled on login)* |

### Step 2: Auto-save token on login

In the **Login** request, add this **Post-response Script** (Tests tab):

```javascript
if (pm.response.code === 200) {
    var data = pm.response.json();
    pm.environment.set("token", data.data.token);
}
```

### Step 3: Set auth for all requests

In the Postman **Collection** settings, set Authorization to:
- **Type:** Bearer Token
- **Token:** `{{token}}`

All requests in the collection will inherit this automatically.

---

## 1. PUBLIC ENDPOINTS

### 1.1 Register User

```
POST {{base_url}}/register
```

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Validation Rules:**
| Field | Rules |
|-------|-------|
| `first_name` | required, string, max 255 |
| `last_name` | required, string, max 255 |
| `email` | required, valid email, unique in users table |
| `password` | required, string, min 8 chars, must match confirmation |
| `password_confirmation` | required |

**Success Response (201):**
```json
{
    "success": true,
    "message": "Registration submitted. Awaiting admin approval.",
    "data": {
        "id": 3,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com"
    }
}
```

**Validation Error (422):**
```json
{
    "message": "The email has already been taken.",
    "errors": {
        "email": ["The email has already been taken."]
    }
}
```

---

### 1.2 Check Email Availability

```
POST {{base_url}}/check-email
```

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
    "email": "john@example.com"
}
```

**Validation Rules:**
| Field | Rules |
|-------|-------|
| `email` | required, valid email |

**Email Available (200):**
```json
{
    "success": true,
    "message": "Email is available.",
    "data": {
        "available": true
    }
}
```

**Email Taken (200):**
```json
{
    "success": true,
    "message": "Email is already taken.",
    "data": {
        "available": false
    }
}
```

> **Usage:** Call this before submitting the registration form to show real-time email validation.

---

### 1.3 Login

```
POST {{base_url}}/login
```

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
    "email": "admin@sdss.local",
    "password": "Admin@1234"
}
```

**Validation Rules:**
| Field | Rules |
|-------|-------|
| `email` | required, valid email |
| `password` | required, string |

**Success Response (200):**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOi...",
        "token_type": "bearer",
        "expires_in": 3600,
        "user": {
            "id": 1,
            "first_name": "System",
            "last_name": "Admin",
            "email": "admin@sdss.local",
            "role": "admin"
        }
    }
}
```

**Invalid Credentials (401):**
```json
{
    "success": false,
    "message": "Invalid credentials"
}
```

**Account Not Approved (403):**
```json
{
    "success": false,
    "message": "Account awaiting admin approval"
}
```
> Other 403 messages: `"Account has been suspended"`, `"Registration was not approved"`

---

## 2. AUTHENTICATED ENDPOINTS

> All endpoints below require: `Authorization: Bearer {{token}}`

### 2.1 Get Current User

```
GET {{base_url}}/me
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Authenticated user",
    "data": {
        "id": 1,
        "first_name": "System",
        "last_name": "Admin",
        "email": "admin@sdss.local",
        "status": "approved",
        "role": "admin",
        "created_at": "2026-03-09T03:51:41.000000Z",
        "updated_at": "2026-03-09T03:51:41.000000Z"
    }
}
```

**Unauthenticated (401):**
```json
{
    "message": "Unauthenticated."
}
```

---

### 2.2 Refresh Token

```
POST {{base_url}}/auth/refresh
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Token refreshed",
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOi...",
        "token_type": "bearer",
        "expires_in": 3600
    }
}
```

> **Postman Tip:** Add the same post-response script as login to auto-update `{{token}}`.

---

### 2.3 Logout

```
POST {{base_url}}/logout
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Successfully logged out"
}
```

> After logout, the token is invalidated. Any subsequent request with the same token returns 401.

---

## 3. ADMIN ENDPOINTS

> Requires: `Authorization: Bearer {{token}}` where the user has `role: admin`
> URL prefix: `/api/admin/...`

### 3.1 List All Users

```
GET {{base_url}}/admin/users
```

**Query Parameters (optional):**
| Param | Type | Description |
|-------|------|-------------|
| `status` | string | Filter by status: `pending`, `approved`, `rejected`, `suspended` |
| `per_page` | integer | Results per page (default: 15) |
| `page` | integer | Page number |

**Example:** `GET {{base_url}}/admin/users?status=pending&per_page=10&page=1`

**Success Response (200):**
```json
{
    "success": true,
    "message": "Users retrieved",
    "data": [
        {
            "id": 3,
            "first_name": "Test",
            "last_name": "User",
            "email": "testuser@example.com",
            "role": "user",
            "status": "pending",
            "created_at": "2026-03-18T03:15:32.000000Z",
            "updated_at": "2026-03-18T03:15:32.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 3
    }
}
```

---

### 3.2 Get User Details

```
GET {{base_url}}/admin/users/{id}
```

**Path Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `id` | integer | User ID |

**Success Response (200):**
```json
{
    "success": true,
    "message": "User details",
    "data": {
        "id": 3,
        "first_name": "Test",
        "last_name": "User",
        "email": "testuser@example.com",
        "role": "user",
        "status": "pending",
        "created_at": "2026-03-18T03:15:32.000000Z",
        "updated_at": "2026-03-18T03:15:32.000000Z"
    }
}
```

**Not Found (404):** Standard Laravel 404 response when user ID doesn't exist.

---

### 3.3 Approve User

```
PATCH {{base_url}}/admin/users/{id}/approve
```

**Path Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `id` | integer | User ID to approve |

**No request body needed.**

**Success Response (200):**
```json
{
    "success": true,
    "message": "User approved successfully",
    "data": {
        "id": 3,
        "first_name": "Test",
        "last_name": "User",
        "email": "testuser@example.com",
        "role": "user",
        "status": "approved",
        "created_at": "2026-03-18T03:15:32.000000Z",
        "updated_at": "2026-03-18T03:26:52.000000Z"
    }
}
```

**Already Approved (422):**
```json
{
    "success": false,
    "message": "User is already approved."
}
```

**Admin Account (422):**
```json
{
    "success": false,
    "message": "Cannot change admin account status."
}
```

---

### 3.4 Reject User

```
PATCH {{base_url}}/admin/users/{id}/reject
```

**No request body needed.**

**Success Response (200):**
```json
{
    "success": true,
    "message": "User rejected",
    "data": {
        "id": 3,
        "first_name": "Test",
        "last_name": "User",
        "email": "testuser@example.com",
        "role": "user",
        "status": "rejected",
        "created_at": "2026-03-18T03:15:32.000000Z",
        "updated_at": "2026-03-18T03:26:53.000000Z"
    }
}
```

---

### 3.5 Suspend User

```
PATCH {{base_url}}/admin/users/{id}/suspend
```

**No request body needed.**

**Success Response (200):**
```json
{
    "success": true,
    "message": "User suspended",
    "data": {
        "id": 3,
        "first_name": "Test",
        "last_name": "User",
        "email": "testuser@example.com",
        "role": "user",
        "status": "suspended",
        "created_at": "2026-03-18T03:15:32.000000Z",
        "updated_at": "2026-03-18T03:26:53.000000Z"
    }
}
```

**Not Approved (422):**
```json
{
    "success": false,
    "message": "Only approved users can be suspended."
}
```

---

## 4. FOLDER MANAGEMENT

> All endpoints require: `Authorization: Bearer {{token}}`

### 4.1 List Root Folders

```
GET {{base_url}}/folders
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Folders retrieved",
    "data": [
        {
            "id": 1,
            "name": "My Documents",
            "parent_id": null,
            "created_at": "2026-03-19T10:00:00.000000Z",
            "updated_at": "2026-03-19T10:00:00.000000Z"
        }
    ]
}
```

---

### 4.2 Get Folder Contents

```
GET {{base_url}}/folders/{id}
```

**Path Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `id` | integer | Folder ID |

**Success Response (200):**
```json
{
    "success": true,
    "message": "Folder contents",
    "data": {
        "folder": {
            "id": 1,
            "name": "My Documents",
            "parent_id": null,
            "created_at": "2026-03-19T10:00:00.000000Z",
            "updated_at": "2026-03-19T10:00:00.000000Z"
        },
        "subfolders": [],
        "files": [
            {
                "id": 1,
                "name": "report.pdf",
                "original_name": "report.pdf",
                "mime_type": "application/pdf",
                "size": 204800,
                "folder_id": 1,
                "created_at": "2026-03-19T10:05:00.000000Z",
                "updated_at": "2026-03-19T10:05:00.000000Z"
            }
        ]
    }
}
```

---

### 4.3 Create Folder

```
POST {{base_url}}/folders
```

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
    "name": "My Documents",
    "parent_id": null
}
```

**Validation Rules:**
| Field | Rules |
|-------|-------|
| `name` | required, string, max 255 |
| `parent_id` | nullable, integer, must exist in folders table |

**Success Response (201):**
```json
{
    "success": true,
    "message": "Folder created",
    "data": {
        "id": 1,
        "name": "My Documents",
        "parent_id": null,
        "created_at": "2026-03-19T10:00:00.000000Z",
        "updated_at": "2026-03-19T10:00:00.000000Z"
    }
}
```

**Duplicate Name (422):**
```json
{
    "success": false,
    "message": "A folder with this name already exists at this level."
}
```

---

### 4.4 Rename Folder

```
PATCH {{base_url}}/folders/{id}
```

**Request Body:**
```json
{
    "name": "Renamed Folder"
}
```

---

### 4.5 Move Folder

```
PATCH {{base_url}}/folders/{id}/move
```

**Request Body:**
```json
{
    "parent_id": 2
}
```

> Set `parent_id` to `null` to move folder to root.

**Error Cases:**
- Moving folder into itself → 422
- Moving folder into one of its own subfolders (circular ref) → 422
- Duplicate name at target level → 422

---

### 4.6 Delete Folder

```
DELETE {{base_url}}/folders/{id}
```

**No request body needed.** Cascades to all subfolders and files via FK.

**Success Response (200):**
```json
{
    "success": true,
    "message": "Folder deleted"
}
```

---

## 5. FILE MANAGEMENT

> All endpoints require: `Authorization: Bearer {{token}}`

### 5.1 List Root Files

```
GET {{base_url}}/files
```

Returns files not in any folder (root level).

**Success Response (200):**
```json
{
    "success": true,
    "message": "Files retrieved",
    "data": [
        {
            "id": 1,
            "name": "document.pdf",
            "original_name": "document.pdf",
            "mime_type": "application/pdf",
            "size": 102400,
            "folder_id": null,
            "created_at": "2026-03-19T10:00:00.000000Z",
            "updated_at": "2026-03-19T10:00:00.000000Z"
        }
    ]
}
```

---

### 5.2 Upload File

```
POST {{base_url}}/files
```

> **IMPORTANT: This is a `multipart/form-data` request, NOT JSON.**

**Validation Rules:**
| Field | Rules |
|-------|-------|
| `file` | required, file, max 50MB (51200 KB) |
| `folder_id` | nullable, integer, must exist in folders table |

**Success Response (201):**
```json
{
    "success": true,
    "message": "File uploaded",
    "data": {
        "id": 1,
        "name": "document.pdf",
        "original_name": "document.pdf",
        "mime_type": "application/pdf",
        "size": 102400,
        "folder_id": null,
        "created_at": "2026-03-19T10:00:00.000000Z",
        "updated_at": "2026-03-19T10:00:00.000000Z"
    }
}
```

#### How to Upload in Postman

1. Set method to **POST** and URL to `{{base_url}}/files`
2. Go to **Body** tab → select **form-data**
3. Add a row:
   - **Key:** `file` — click the dropdown on the right side of the key field and change from "Text" to **"File"**
   - **Value:** Click "Select Files" and choose your file
4. (Optional) Add another row:
   - **Key:** `folder_id` (keep as "Text")
   - **Value:** the folder ID number (e.g., `1`), or leave empty for root
5. Make sure **Authorization** is set to Bearer Token with `{{token}}`
6. Hit **Send**

#### Upload via cURL

```bash
curl -X POST http://localhost:8088/api/files \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -F "file=@/path/to/your/document.pdf" \
  -F "folder_id=1"
```

---

### 5.3 Rename File

```
PATCH {{base_url}}/files/{id}
```

**Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
    "name": "new-name.pdf"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "File renamed",
    "data": { ... }
}
```

---

### 5.4 Move File

```
PATCH {{base_url}}/files/{id}/move
```

**Request Body:**
```json
{
    "folder_id": 2
}
```

> Set `folder_id` to `null` to move file to root.

---

### 5.5 Delete File

```
DELETE {{base_url}}/files/{id}
```

**No request body needed.** Also revokes active authorization tokens and rejects pending access requests for the file.

**Success Response (200):**
```json
{
    "success": true,
    "message": "File deleted"
}
```

---

### 5.6 Download File

```
GET {{base_url}}/files/{id}/download
```

Returns the file as a streamed download. In Postman, click **"Save Response"** → **"Save to a file"** after hitting Send.

---

## Postman Testing Workflow

Follow this order to test the full flow:

1. **Login as Admin** — `POST /api/login` with admin credentials (token auto-saved)
2. **Verify Auth** — `GET /api/me` to confirm you're logged in
3. **Check Email** — `POST /api/check-email` with `{"email": "newuser@test.com"}` → should be available
4. **Register New User** — `POST /api/register` with first_name, last_name, email, password
5. **Check Email Again** — same email → should now be taken
6. **List Pending Users** — `GET /api/admin/users?status=pending`
7. **Approve User** — `PATCH /api/admin/users/{id}/approve`
8. **Create Folder** — `POST /api/folders` with `{"name": "Test Folder"}`
9. **Upload File to Folder** — `POST /api/files` (form-data) with file + folder_id
10. **Upload File to Root** — `POST /api/files` (form-data) with file only
11. **List Root Folders** — `GET /api/folders`
12. **View Folder Contents** — `GET /api/folders/{id}`
13. **List Root Files** — `GET /api/files`
14. **Rename File** — `PATCH /api/files/{id}` with `{"name": "renamed.pdf"}`
15. **Move File** — `PATCH /api/files/{id}/move` with `{"folder_id": 1}`
16. **Download File** — `GET /api/files/{id}/download`
17. **Delete File** — `DELETE /api/files/{id}`
18. **Delete Folder** — `DELETE /api/folders/{id}`
19. **Logout** — `POST /api/logout`

---

## Seed Data (Pre-loaded)

| ID | Email | Password | Role | Status | First Name | Last Name |
|----|-------|----------|------|--------|------------|-----------|
| 1 | `admin@sdss.local` | `Admin@1234` | admin | approved | System | Admin |
