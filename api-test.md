# SDSS API Test Report

**Base URL:** `https://sdss-backend-tq6g.onrender.com/api`
**Date:** 2026-03-22
**Test Users:**
- testuser7@gmail.com (ID: 7, pw: 12345678)
- testuser9@gmail.com (ID: 8, pw: 12345678)
- testuser10@gmail.com (ID: 9, pw: 12345678)
- admin@sdss.local (ID: 1, pw: Admin@1234)

---

## Summary

| Category | Pass | Fail | Total |
|---|---|---|---|
| Auth | 8 | 0 | 8 |
| Admin - User Management | 6 | 0 | 6 |
| Admin - Audit Logs | 4 | 0 | 4 |
| Folders | 3 | 4 | 7 |
| Files | 3 | 4 | 7 |
| Storage | 1 | 0 | 1 |
| Browse | 4 | 0 | 4 |
| Access Requests | 4 | 2 | 6 |
| File Access (Token) | 1 | 0 | 1 |
| Notifications | 4 | 0 | 4 |
| **TOTAL** | **38** | **10** | **48** |

---

## Auth APIs

### POST /register
**Status:** PASS (201)
```json
Request: {"first_name":"Test","last_name":"User7","email":"testuser7@gmail.com","password":"12345678","password_confirmation":"12345678"}
Response: {"success":true,"message":"Registration submitted. Awaiting admin approval.","data":{"id":7,"first_name":"Test","last_name":"User7","email":"testuser7@gmail.com"}}
```
Tested with 3 users (testuser7, testuser9, testuser10) — all returned 201.

### POST /check-email (existing)
**Status:** PASS (200)
```json
Request: {"email":"testuser7@gmail.com"}
Response: {"success":true,"message":"Email is already taken.","data":{"available":false}}
```

### POST /check-email (non-existing)
**Status:** PASS (200)
```json
Request: {"email":"nonexistent@gmail.com"}
Response: {"success":true,"message":"Email is available.","data":{"available":true}}
```

### POST /login
**Status:** PASS (200)
```json
Request: {"email":"testuser7@gmail.com","password":"12345678"}
Response: {"success":true,"message":"Login successful","data":{"token":"eyJ...","token_type":"bearer","expires_in":3600,"user":{...}}}
```

### POST /login (unapproved user)
**Status:** PASS (403)
```json
Response: {"success":false,"message":"Registration was not approved"}
```

### GET /me
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Authenticated user","data":{"id":7,"email":"testuser7@gmail.com","status":"approved","role":"user",...}}
```

### POST /auth/refresh
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Token refreshed","data":{"token":"eyJ...","token_type":"bearer","expires_in":3600}}
```

### POST /logout
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Successfully logged out"}
```
Verified: calling /me after logout returns 401 Unauthenticated.

---

## Admin - User Management APIs

### GET /admin/users
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Users retrieved","data":[...9 users...],"meta":{"current_page":1,"last_page":1,"per_page":15,"total":9}}
```

### GET /admin/users/{user}
**Status:** PASS (200)
```json
Response: {"success":true,"message":"User details","data":{"id":7,"first_name":"Test","last_name":"User7","email":"testuser7@gmail.com","role":"user","status":"approved",...}}
```

### PATCH /admin/users/{user}/approve
**Status:** PASS (200)
```json
Response: {"success":true,"message":"User approved successfully","data":{"id":7,...,"status":"approved",...}}
```

### PATCH /admin/users/{user}/reject
**Status:** PASS (200)
```json
Response: {"success":true,"message":"User rejected","data":{"id":7,...,"status":"rejected",...}}
```

### PATCH /admin/users/{user}/suspend
**Status:** PASS (422 — correct validation for already-suspended user)
```json
Response: {"success":false,"message":"Only approved users can be suspended."}
```

### GET /admin/* (non-admin)
**Status:** PASS (403)
```json
Response: {"success":false,"message":"Unauthorized. Insufficient permissions."}
```

---

## Admin - Audit Log APIs

### GET /admin/audit-logs
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Audit logs retrieved","data":[...20 entries...],"meta":{"current_page":1,"last_page":7,"per_page":20,"total":137}}
```

### GET /admin/audit-logs?user_id=7&per_page=5
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Audit logs retrieved","data":[...5 entries for user 7...],"meta":{"current_page":1,"last_page":4,"per_page":5,"total":16}}
```

### GET /admin/audit-logs/{auditLog}
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Audit log entry","data":{"id":1,"user_id":1,"user":{...},"action":"user.logged_in","resource_type":"user","resource_id":1,...}}
```

### GET /admin/audit-logs (non-admin)
**Status:** PASS (403)
```json
Response: {"success":false,"message":"Unauthorized. Insufficient permissions."}
```

---

## Folder APIs

### GET /folders (list root folders)
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Folders retrieved","data":[{"id":10,"name":"Documents","parent_id":null,...},{"id":11,"name":"Photos","parent_id":null,...}]}
```

### POST /folders (create folder)
**Status:** PASS (201)
```json
Request: {"name":"Documents"}
Response: {"success":true,"message":"Folder created","data":{"id":10,"name":"Documents","parent_id":null,...}}
```

### POST /folders (create subfolder)
**Status:** PASS (201)
```json
Request: {"name":"Work","parent_id":10}
Response: {"success":true,"message":"Folder created","data":{"id":12,"name":"Work","parent_id":10,...}}
```

### GET /folders/{folder} (show folder contents)
**Status:** FAIL (500)
```json
Response: {"message": "Server Error"}
```
**Error:** 500 Internal Server Error. Likely an issue with route model binding + policy authorization on the production server. The `$this->authorize('view', $folder)` call in FolderController@show may fail due to the auth guard not resolving the user correctly.

### PATCH /folders/{folder} (rename folder)
**Status:** FAIL (500)
```json
Request: {"name":"Vacation"}
Response: {"message": "Server Error"}
```
**Error:** Same 500 pattern as show. Policy authorization issue.

### PATCH /folders/{folder}/move (move folder)
**Status:** FAIL (500)
```json
Request: {"parent_id":11}
Response: {"message": "Server Error"}
```
**Error:** Same 500 pattern. Policy authorization issue.

### DELETE /folders/{folder} (delete folder)
**Status:** FAIL (500)
```json
Response: {"message": "Server Error"}
```
**Error:** Same 500 pattern. Policy authorization issue.

---

## File APIs

### GET /files (list root files)
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Files retrieved","data":[{"id":10,"name":"testfile.txt","mime_type":"text/plain","size":22,"folder_id":null,...}]}
```

### POST /files (upload file to root)
**Status:** PASS (201)
```json
Response: {"success":true,"message":"File uploaded","data":{"id":10,"name":"testfile.txt","original_name":"testfile.txt","mime_type":"text/plain","size":22,"folder_id":null,...}}
```

### POST /files (upload file to folder)
**Status:** PASS (201)
```json
Response: {"success":true,"message":"File uploaded","data":{"id":11,"name":"doc.txt","original_name":"doc.txt","mime_type":"text/plain","size":17,"folder_id":10,...}}
```

### PATCH /files/{file} (rename file)
**Status:** FAIL (500)
```json
Request: {"name":"renamedtest"}
Response: {"message": "Server Error"}
```
**Error:** Same 500 pattern as folder operations. Policy authorization issue with route model binding.

**Additional Note:** Filenames with dots (e.g., "renamed.txt") are rejected with 422 due to overly strict regex validation: `regex:/^[^\/\\\\\.\.]+$/` — this blocks dots entirely, preventing any filename with an extension.

### PATCH /files/{file}/move (move file)
**Status:** FAIL (500)
```json
Request: {"folder_id":10}
Response: {"message": "Server Error"}
```
**Error:** Same 500 pattern.

### GET /files/{file}/download
**Status:** FAIL (500)
```json
Response: {"message": "Server Error"}
```
**Error:** Same 500 pattern.

### DELETE /files/{file}
**Status:** FAIL (500)
```json
Response: {"message": "Server Error"}
```
**Error:** Same 500 pattern.

---

## Storage API

### GET /my-storage
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Storage retrieved","data":{"tree":[...nested folder/file tree...],"recent":[...],"stats":{"total_files":2,"total_folders":3,"total_size":39}}}
```

---

## Browse APIs

### GET /browse/users
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Users retrieved","data":[{"id":2,"first_name":"Prabhab","last_name":"Khanal"},{"id":3,...},{"id":6,...},{"id":5,...},{"id":9,...},{"id":7,...}]}
```
Note: Correctly excludes self (user 8/testuser9) and admin (user 1).

### GET /browse/users/{user}/folders
**Status:** PASS (200)
```json
Response: {"success":true,"message":"User folders retrieved","data":[{"id":10,"name":"Documents",...},{"id":11,"name":"Photos",...}]}
```

### GET /browse/users/{user}/folders/{folder}
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Folder contents retrieved","data":{"folder":{"id":10,"name":"Documents",...},"subfolders":[{"id":12,"name":"Work",...}],"files":[{"id":11,"name":"doc.txt",...}]}}
```

### GET /browse/users/{user}/files
**Status:** PASS (200)
```json
Response: {"success":true,"message":"User files retrieved","data":[{"id":10,"name":"testfile.txt","mime_type":"text/plain","size":22,...}]}
```

---

## Access Request APIs

### POST /files/{file}/access-requests (create request)
**Status:** PASS (201)
```json
Request: {"message":"I need this file for my project"}
Response: {"success":true,"message":"Access request submitted","data":{"id":5,"file":{"id":10,"name":"testfile.txt"},"requester":{"id":8,"first_name":"Test","last_name":"User9"},"status":"pending","message":"I need this file for my project",...}}
```

### GET /access-requests/outgoing
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Outgoing requests retrieved","data":[{"id":5,"file":{"id":10,"name":"testfile.txt","owner":{"id":7,...}},"status":"pending","message":"I need this file for my project",...}],"meta":{...}}
```

### GET /access-requests/incoming
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Incoming requests retrieved","data":[{"id":5,"file":{"id":10,"name":"testfile.txt"},"requester":{"id":8,"first_name":"Test","last_name":"User9"},"status":"pending",...}],"meta":{...}}
```

### PATCH /access-requests/{accessRequest}/approve
**Status:** FAIL (500)
```json
Response: {"message": "Server Error"}
```
**Error:** Same 500 pattern as other route-model-bound endpoints. Cannot approve access requests.

### PATCH /access-requests/{accessRequest}/reject
**Status:** FAIL (500)
```json
Request: {"rejection_reason":"This file is confidential"}
Response: {"message": "Server Error"}
```
**Error:** Same 500 pattern.

### GET /access-requests/outgoing?status=pending
**Status:** PASS (200)
Filter by status works correctly.

---

## File Access (Token) API

### GET /files/access/{token} (invalid token)
**Status:** PASS (404)
```json
Response: {"message": "Authorization token not found."}
```
Note: Could not test with a valid token because access request approval (which generates the token) returns 500.

---

## Notification APIs

### GET /notifications
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Notifications retrieved","data":[{"id":"81a681cc-...","type":"AccessRequested","data":{"message":"Test User10 requested access to testfile.txt",...},"read_at":null,...},{"id":"a4a1135d-...","type":"AccessRequested",...},{"id":"13ebe404-...","type":"AccountApproved",...},{"id":"a68a97a1-...","type":"AccountRejected",...},{"id":"07ca5e6f-...","type":"AccountApproved",...}],"meta":{...}}
```

### GET /notifications/unread-count
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Unread count","data":{"count":5}}
```

### PATCH /notifications/{notification}/read
**Status:** PASS (200)
```json
Response: {"success":true,"message":"Notification marked as read"}
```

### POST /notifications/read-all
**Status:** PASS (200)
```json
Response: {"success":true,"message":"All notifications marked as read"}
```
Verified: unread-count returns 0 after mark-all.

---

## Critical Issues Found

### 1. 500 Server Error on ALL route-model-bound endpoints with policies (10 endpoints)

**Affected endpoints:**
- `GET /folders/{folder}` — show folder
- `PATCH /folders/{folder}` — rename folder
- `PATCH /folders/{folder}/move` — move folder
- `DELETE /folders/{folder}` — delete folder
- `PATCH /files/{file}` — rename file
- `PATCH /files/{file}/move` — move file
- `GET /files/{file}/download` — download file
- `DELETE /files/{file}` — delete file
- `PATCH /access-requests/{accessRequest}/approve` — approve request
- `PATCH /access-requests/{accessRequest}/reject` — reject request

**Root Cause Hypothesis:** All these endpoints use `$this->authorize()` (Laravel policies) combined with route model binding. The likely cause is that the `auth('api')` guard used in policies resolves `null` for the user during policy evaluation, causing a server error. This could be a JWT middleware or AuthServiceProvider configuration issue specific to the production deployment.

**Impact:** Users cannot view/rename/move/delete folders or files, download files, or approve/reject access requests. Only creation and listing operations work.

### 2. File rename regex blocks dots in filenames

**File:** `app/Http/Requests/File/UpdateFileRequest.php`
**Regex:** `regex:/^[^\/\\\\\.\.]+$/`

This regex rejects any filename containing a dot (`.`), which means files cannot be renamed to include an extension (e.g., `report.pdf`). The regex intends to block `..` (path traversal) but also blocks single dots.

**Suggested fix:** Change regex to `regex:/^(?!.*\.\.)(?!.*[\/\\\\])[^\/\\\\]+$/` to allow dots but block `..` sequences.
