# Teamoria Frontend API Map

هذه الخريطة موجهة لفريق الفرونت اند ولأي وكيل يعمل على الواجهة. الهدف أن يكون التعامل مع الـ API موحدا: نفس الـ headers، نفس معالجة الأخطاء، ونفس سيناريوهات التوجيه بعد التسجيل أو الدخول.

## 1. إعداد عميل الـ API

Base URL:

```text
Production: https://api.teamoria.online/api/v1
Local:      http://localhost:8000/api/v1
```

Headers القياسية لكل طلب تحت `/api/v1`:

```http
Accept: application/json
x-api-key: <api_key>
Authorization: Bearer <access_token>
```

ملاحظات:

| الحالة | المطلوب |
| --- | --- |
| مسارات guest مثل login/register/otp | تحتاج `x-api-key` ولا تحتاج `Authorization` |
| مسارات authenticated | تحتاج `x-api-key` و`Authorization: Bearer ...` |
| JSON body | استخدم `Content-Type: application/json` |
| upload multipart | لا تضبط `Content-Type` يدويا، اترك المتصفح يضع boundary |
| `/api/health` | خارج `/api/v1` ولا يحتاج API key |
| `/broadcasting/auth` | خارج `/api/v1`، يحتاج Bearer token، ويرجع Pusher auth raw وليس envelope موحد |

لا يتم hardcode لأي API key أو Reverb app key داخل الكود. ضع القيم في runtime config أو env الخاص بالفرونت حسب طريقة النشر، وفي Postman يمكن وضع Reverb key في Globals.

## 2. شكل الريسبونس الموحد

كل JSON success response من التطبيق يرجع بالشكل التالي:

```json
{
  "success": true,
  "message": "Success message",
  "data": {}
}
```

كل JSON error response يرجع بالشكل التالي:

```json
{
  "success": false,
  "message": "Human readable error",
  "error_code": "VALIDATION_ERROR",
  "data": {}
}
```

خطأ validation `422` يرجع تفاصيل الحقول داخل `data`:

```json
{
  "success": false,
  "message": "Some fields contain invalid values. Please review the errors and try again.",
  "error_code": "VALIDATION_ERROR",
  "data": {
    "email": ["The email has already been taken."],
    "password": ["The password field confirmation does not match."]
  }
}
```

شكل pagination المستخدم في القوائم:

```json
{
  "current_page": 1,
  "last_page": 3,
  "per_page": 10,
  "total": 25,
  "has_more": true
}
```

## 3. معالجة الأخطاء في الفرونت

| HTTP | error_code شائع | تصرف الواجهة |
| --- | --- | --- |
| 400 | `BAD_REQUEST` | اعرض الرسالة كما هي، غالبا business rule مثل pending subscription |
| 401 | `UNAUTHENTICATED`, `MISSING_API_KEY`, `INVALID_API_KEY`, `INVALID_CREDENTIALS` | إن كان Bearer token مفقود/منتهي: logout واذهب للدخول. إن كان API key: خطأ إعدادات بيئة |
| 403 | `FORBIDDEN`, `EMAIL_NOT_VERIFIED`, `ACCOUNT_INACTIVE` | حسب الكود: verify email، blocked account، no permission، أو upgrade plan |
| 404 | `NOT_FOUND` | اعرض not found أو ارجع للقائمة |
| 405 | `METHOD_NOT_ALLOWED` | خطأ ربط endpoint في الفرونت |
| 409 | `CONFLICT` | conflict مثل إنشاء شركة ثانية لنفس owner أو payment مؤكد سابقا |
| 413 | `PAYLOAD_TOO_LARGE` | اعرض رسالة حجم الملف |
| 422 | `VALIDATION_ERROR` | اربط `data[field]` برسائل الحقول |
| 429 | `TOO_MANY_REQUESTS` | عطّل الزر مؤقتا واستخدم retry بعد مدة |
| 500/503 | `INTERNAL_ERROR`, `SERVICE_UNAVAILABLE` | اعرض رسالة عامة وسجل الخطأ في monitoring |

أكواد خاصة يجب التعامل معها في auth:

| error_code | متى يحدث | تصرف الواجهة |
| --- | --- | --- |
| `INVALID_CREDENTIALS` | login email/password خطأ | اعرض خطأ في نموذج الدخول |
| `EMAIL_NOT_VERIFIED` | الحساب موجود لكن البريد غير مفعل | وجه المستخدم إلى صفحة OTP مع `type=register` أو `verify-email` |
| `ACCOUNT_INACTIVE` | المستخدم suspended/inactive | اعرض رسالة تواصل مع الدعم |

## 4. سيناريو التسجيل لأول مرة

المسار المتوقع لصاحب شركة جديد:

1. المستخدم يملأ صفحة التسجيل.
2. frontend يرسل `POST /auth/register`.
3. backend ينشئ user بدور `company_owner` وحالة `pending` ويرسل OTP.
4. الواجهة تنتقل إلى صفحة OTP.
5. الواجهة ترسل `POST /otp/verify` مع `type=register`.
6. إذا نجح التحقق يصبح المستخدم `active`.
7. الواجهة تعرض login أو تعمل auto-login إذا كانت كلمة المرور محفوظة مؤقتا في memory فقط.
8. بعد login وحفظ token، وجه المستخدم إلى صفحة إنشاء الشركة.
9. الواجهة ترسل `POST /company/register`.
10. بعد نجاح إنشاء الشركة، وجه المستخدم إلى company dashboard.

مهم: لا تخزن كلمة المرور في `localStorage` أو `sessionStorage`. إذا أردت auto-login بعد OTP، احتفظ بها فقط في memory أثناء نفس الجلسة.

مثال التسجيل:

```http
POST /auth/register
```

```json
{
  "name": "Mohammed Owner",
  "email": "owner@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Success:

```json
{
  "success": true,
  "message": "User registered successfully. Please verify your email.",
  "data": {
    "type": "register",
    "expires_in": 10
  }
}
```

مثال OTP:

```http
POST /otp/verify
```

```json
{
  "email": "owner@example.com",
  "code": 123456,
  "type": "register"
}
```

Success:

```json
{
  "success": true,
  "message": "Email verified successfully.",
  "data": {
    "email_verified": "2026-07-10T12:00:00.000000Z"
  }
}
```

مثال إنشاء الشركة:

```http
POST /company/register
Authorization: Bearer <token>
```

```json
{
  "name": "Teamoria Demo",
  "industry": "Software",
  "website": "https://teamoria.online",
  "address": "Ramallah",
  "logo_path": null,
  "status": "active"
}
```

إذا حاول owner إنشاء شركة مرة ثانية يرجع `409 CONFLICT`.

## 5. سيناريو الدخول والتوجيه

`POST /auth/login` يرجع token فقط:

```json
{
  "success": true,
  "message": "Logged in successfully.",
  "data": {
    "token": "plain-text-sanctum-token"
  }
}
```

لا يوجد حاليا endpoint عام مثل `/me`. لذلك بعد login استخدم أحد الأساليب التالية:

| الأسلوب | الخطوات |
| --- | --- |
| مسار التسجيل المعروف | إذا المستخدم جاء من register/OTP، فهو `company_owner` جديد، وجهه مباشرة إلى `/company/register` |
| جلسة سابقة مع profile محفوظ | استخدم profile المحفوظ ثم اعمل refresh من endpoint المناسب |
| login عام | جرب `GET /admin/profile`. إذا نجح فهو admin. إذا رجع 403، جرب `GET /company/profile`. إذا رجع 403 برسالة `assigned to a company` وجهه إلى صفحة إنشاء الشركة |

اقتراح تحسين لاحق للباك اند: إضافة `GET /api/v1/me` يرجع UserResource مع company لتبسيط routing في الواجهة.

## 6. Guards في الواجهة

| Guard | الشرط | عند الفشل |
| --- | --- | --- |
| Guest only | لا يوجد token | إن وجد token اذهب للداشبورد المناسب |
| Authenticated | token موجود | اذهب إلى login |
| Admin | `GET /admin/profile` ينجح | اعرض forbidden أو رجوع |
| Company ready | `GET /company/profile` ينجح | إن كان user بلا company اذهب إلى create company |
| Company owner | `role=company_owner` | اخف staff management عن غير owner |
| Project manager actions | admin أو company_owner أو project pivot role `manager` | اخف edit/delete/manage buttons |
| Subscription features | API لا يرجع 403 limit | اعرض upgrade CTA عند رسائل plan limit |

## 7. Auth و OTP

| Method | Endpoint | Auth | Body | Response data |
| --- | --- | --- | --- | --- |
| POST | `/auth/register` | API key | `name`, `email`, `password`, `password_confirmation` | `type`, `expires_in`, و`code` فقط في debug |
| POST | `/auth/login` | API key | `email`, `password` | `token` |
| POST | `/auth/logout` | Bearer | لا يوجد | `[]` |
| POST | `/auth/reset-password` | Bearer | `old_password`, `new_password`, `new_password_confirmation` | `[]` |
| POST | `/otp/send` | API key | `email`, `type` | `email`, `type`, و`code` فقط في debug |
| POST | `/otp/verify` | API key | `email`, `code`, `type`, `new_password?` | email verification أو empty |
| GET | `/auth/google/redirect` | API key | لا يوجد | `redirect_url` |
| POST | `/auth/google` | API key | `provider_token` | `user`, `token` |

OTP types:

```text
register
forgot-password
verify-email
```

`new_password` مطلوب فقط عندما `type=forgot-password`.

## 8. Company workspace

كل المسارات هنا تحتاج Bearer token و`x-api-key`.

| Method | Endpoint | Roles | Body/Query | Response data |
| --- | --- | --- | --- | --- |
| POST | `/company/register` | `company_owner` بلا company | Company fields | CompanyResource |
| GET | `/company/dashboard` | user لديه company | لا يوجد | `totals`, `project_statuses`, `task_statuses`, `recent_projects`, `upcoming_tasks` |
| GET | `/company/profile` | user لديه company | لا يوجد | UserResource |
| PATCH | `/company/profile` | user لديه company | `name?`, `email?`, `password?`, `password_confirmation?`, `phone?`, `timezone?` | UserResource |
| GET | `/company/staff` | `company_owner` | `page?`, `archived?`, `roles[]?`, `statuses[]?` | `users`, `company`, `pagination` |
| POST | `/company/staff` | `company_owner` | Staff fields | UserResource |
| GET | `/company/staff/{id}` | `company_owner` | لا يوجد | UserResource |
| PUT | `/company/staff/{id}` | `company_owner` | Staff update fields | UserResource |
| DELETE | `/company/staff/{id}` | `company_owner` | لا يوجد | `null` |
| PATCH | `/company/staff/{id}/restore` | `company_owner` | لا يوجد | UserResource |
| DELETE | `/company/staff/{id}/force-delete` | `company_owner` | لا يوجد | `null` |

Staff create fields:

```json
{
  "name": "Company Manager",
  "email": "manager@example.com",
  "password": "password",
  "password_confirmation": "password",
  "role": "company_manager",
  "status": "active"
}
```

الأدوار المسموحة في staff create/update:

```text
company_manager
company_member
```

## 9. Admin workspace

كل المسارات هنا تحتاج user بدور `admin`.

| Method | Endpoint | Body/Query | Response data |
| --- | --- | --- | --- |
| GET | `/admin/dashboard` | لا يوجد | dashboard blocks |
| GET | `/admin/profile` | لا يوجد | UserResource |
| PATCH | `/admin/profile` | Profile fields | UserResource |
| GET | `/admin/users` | `page?`, `archived?` | `users`, `pagination` |
| POST | `/admin/users` | Admin user fields | UserResource |
| GET | `/admin/users/{id}` | لا يوجد | UserResource |
| PUT | `/admin/users/{id}` | User update fields | UserResource |
| DELETE | `/admin/users/{id}` | لا يوجد | `null` |
| PATCH | `/admin/users/{id}/restore` | لا يوجد | UserResource |
| DELETE | `/admin/users/{id}/force-delete` | لا يوجد | `null` |
| GET | `/admin/companies` | `page?`, `archived?` | `companies`, `pagination` |
| POST | `/admin/companies` | Company fields | CompanyResource |
| GET | `/admin/companies/{id}` | لا يوجد | CompanyResource |
| PUT | `/admin/companies/{id}` | Company update fields | CompanyResource |
| DELETE | `/admin/companies/{id}` | لا يوجد | `null` |
| PATCH | `/admin/companies/{id}/restore` | لا يوجد | CompanyResource |
| DELETE | `/admin/companies/{id}/force-delete` | لا يوجد | `null` |

Admin user create fields:

```json
{
  "name": "User",
  "email": "user@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "company_member",
  "phone": "+970000000",
  "status": "active",
  "timezone": "Asia/Jerusalem",
  "company_id": "uuid"
}
```

Company fields:

```json
{
  "name": "Company Name",
  "industry": "Software",
  "website": "https://example.com",
  "address": "Address",
  "logo_path": "optional/path.png",
  "status": "active"
}
```

## 10. Projects

Admin paths تبدأ بـ `/admin/projects`. Company paths تبدأ بـ `/company/projects`. نفس controller تقريبا مع اختلاف الصلاحيات والـ company scoping.

| Method | Endpoint | Body/Query | Response data |
| --- | --- | --- | --- |
| GET | `/admin/projects` أو `/company/projects` | `page?`, `archived?` | `projects`, `pagination` |
| POST | `/admin/projects` | Project fields مع `company_id` مطلوب | ProjectResource |
| POST | `/company/projects` | Project fields بدون `company_id` | ProjectResource |
| GET | `/{scope}/projects/{id}` | لا يوجد | ProjectResource |
| PUT | `/{scope}/projects/{id}` | Project update fields | ProjectResource |
| DELETE | `/{scope}/projects/{id}` | لا يوجد | `null` |
| PATCH | `/{scope}/projects/{id}/restore` | لا يوجد | ProjectResource |
| DELETE | `/{scope}/projects/{id}/force-delete` | لا يوجد | `null` |
| POST | `/{scope}/projects/{id}/members` | `user_ids[]`, `role?` | ProjectResource |
| DELETE | `/{scope}/projects/{id}/members/{userId}` | لا يوجد | ProjectResource |

Project create fields:

```json
{
  "company_id": "uuid-for-admin-only",
  "name": "Product Launch",
  "description": "Project description",
  "status": "active",
  "progress": 0,
  "start_date": "2026-07-01",
  "end_date": "2026-08-31"
}
```

قواعد مهمة:

| الحالة | القاعدة |
| --- | --- |
| Admin create | `company_id` مطلوب |
| Company create | `company_id` ممنوع والباك اند يأخذه من المستخدم |
| `company_member` | لا يستطيع إنشاء project |
| تعديل/حذف project | admin أو company_owner أو project manager |
| إضافة أعضاء | المستخدمون يجب أن يكونوا من نفس الشركة، ويفضل من أعضاء المشروع الحاليين حسب السياق |

## 11. Tasks

Admin paths تبدأ بـ `/admin/tasks`. Company paths تبدأ بـ `/company/tasks`.

| Method | Endpoint | Body/Query | Response data |
| --- | --- | --- | --- |
| GET | `/{scope}/tasks` | filters | `tasks`, `pagination` |
| POST | `/{scope}/tasks` | Task create fields | TaskResource |
| GET | `/{scope}/tasks/{id}` | لا يوجد | TaskResource |
| PUT | `/{scope}/tasks/{id}` | Task update fields | TaskResource |
| PATCH | `/{scope}/tasks/{id}/status` | `status` | TaskResource |
| PATCH | `/{scope}/tasks/{id}/progress` | `seen?`, `completed?` | TaskResource |
| DELETE | `/{scope}/tasks/{id}` | لا يوجد | `null` |
| PATCH | `/{scope}/tasks/{id}/restore` | لا يوجد | TaskResource |
| DELETE | `/{scope}/tasks/{id}/force-delete` | لا يوجد | `null` |
| POST | `/{scope}/tasks/{id}/assignees` | `user_ids[]` | TaskResource |
| DELETE | `/{scope}/tasks/{id}/assignees/{userId}` | لا يوجد | TaskResource |
| POST | `/{scope}/tasks/{id}/dependencies` | `dependency_ids[]` | TaskResource |
| DELETE | `/{scope}/tasks/{id}/dependencies/{dependencyId}` | لا يوجد | TaskResource |
| POST | `/{scope}/tasks/{id}/notes` | `content` | TaskNoteResource |
| DELETE | `/{scope}/tasks/{id}/notes/{noteId}` | لا يوجد | `null` |

Task list filters:

```text
project_id
statuses[]
priorities[]
assignee_id
due_from
due_to
archived
per_page
page
```

Task create fields:

```json
{
  "project_id": "uuid",
  "title": "Prepare proposal",
  "description": "Optional description",
  "status": "todo",
  "priority": "high",
  "due_date": "2026-12-31",
  "assignee_ids": ["uuid"],
  "dependency_ids": ["uuid"]
}
```

قواعد مهمة:

| الحالة | القاعدة |
| --- | --- |
| create/update/delete task | admin أو company_owner أو project manager |
| update status | assignee أو manager |
| update progress | assignee فقط |
| completed=true | إذا لم ترسل `seen`، الباك اند يضبط seen تلقائيا |
| dependencies | ممنوع self dependency أو circular dependency أو task من project مختلف |
| notes | admin/company_owner/project manager/project member يمكنهم الإضافة، viewer لا |

## 12. Uploads

كل مسارات upload تحتاج Bearer token.

| Method | Endpoint | Body/Query | Response data |
| --- | --- | --- | --- |
| POST | `/uploads` | multipart fields | `files` |
| GET | `/uploads` | upload filters | `files`, `pagination` |
| GET | `/uploads/list` | نفس `/uploads` | `files`, `pagination` |
| GET | `/uploads/mine` | upload filters | `files`, `pagination` |
| GET | `/uploads/{project}/list` | upload filters | `files`, `pagination` |
| GET | `/uploads/{upload}` | لا يوجد | UploadResource |
| GET | `/uploads/{upload}/status` | لا يوجد | `processing_status`, و`processing_error` عند الفشل |
| GET | `/uploads/{upload}/download` | لا يوجد | file stream، ليس JSON envelope |
| POST | `/uploads/{upload}/permissions` | `user_ids[]`, `access_level` | UploadResource |
| DELETE | `/uploads/{upload}/permissions/{user}` | لا يوجد | UploadResource |
| DELETE | `/uploads/{upload}` | لا يوجد | `null` |

Upload multipart fields:

```text
files[]
scope
visibility?
company_id?
project_id?
task_id?
shared_with_user_ids[]?
```

Upload rules:

| field | القاعدة |
| --- | --- |
| `files[]` | مطلوب، واحد أو أكثر |
| `scope` | `company`, `project`, `task`, `personal` |
| admin + scope company | `company_id` مطلوب |
| غير admin | `company_id` ممنوع |
| scope project/task | `project_id` مطلوب |
| scope task | `task_id` مطلوب |
| visibility selected | `shared_with_user_ids[]` مطلوب |
| personal + members | ممنوع |
| project/task sharing | المشاركون يجب أن يكونوا من نفس الشركة وأعضاء في المشروع |
| max size | default 20MB، الفيديو 100MB |

File categories/statuses في response:

```text
category: video | image | audio | document
status: pending | uploading | success | failed
processing_status: queued | processing | processed | failed
visibility: private | members | selected
access_level: view | manage
```

## 13. AI Chat و Realtime

Chat endpoints:

| Method | Endpoint | Body/Query | Response data |
| --- | --- | --- | --- |
| POST | `/chat/messages` | `session_id?`, `project_id?`, `message_content` | `session_id`, `message_id`, `status=processing` |
| GET | `/chat/sessions` | لا يوجد | array of sessions |
| GET | `/chat/sessions/{session}/messages` | cursor pagination | Laravel cursor paginator |

`/chat/messages` يحتاج خطة فيها AI. إذا لم تكن الخطة تسمح يرجع `403` مع رسالة:

```text
Upgrade your plan to use AI chat.
```

WebSocket Laravel Reverb:

الباك اند يستخدم Laravel Reverb كسيرفر WebSocket، وهو متوافق مع Pusher protocol. في الفرونت استخدم Laravel Echo مع Pusher driver أو raw Pusher protocol عند الاختبار في Postman.

```text
wss://api.teamoria.online/app/<reverb_app_key>?protocol=7&client=js&version=8.4.0
```

ملاحظات مهمة:

| جزء | القيمة |
| --- | --- |
| auth endpoint | `POST https://api.teamoria.online/broadcasting/auth` |
| auth headers | `Authorization: Bearer <token>`, `Accept: application/json` |
| channel في Echo | `chat.<authenticated_user_id>` |
| channel في raw Pusher subscribe | `private-chat.<authenticated_user_id>` |
| event name في Echo listener | `.ai.message.received` |
| auth response | `{ "auth": "..." }` وليس envelope موحد |

Payload الحدث:

```json
{
  "message": {
    "id": "uuid",
    "chat_session_id": "uuid",
    "role": "ai",
    "content": "AI answer",
    "created_at": "2026-07-10T12:00:00.000000Z",
    "updated_at": "2026-07-10T12:00:00.000000Z"
  }
}
```

سيناريو chat في الواجهة:

1. أرسل `POST /chat/messages`.
2. اعرض رسالة المستخدم محليا وحالة assistant loading.
3. احتفظ بـ `session_id` و`message_id`.
4. استمع على private channel `chat.<user_id>`.
5. عند event `.ai.message.received` أضف رسالة AI إلى conversation.
6. إذا لم يصل الحدث خلال مدة مناسبة، اعمل fallback polling على `/chat/sessions/{session}/messages`.

## 14. Notifications

| Method | Endpoint | Body/Query | Response data |
| --- | --- | --- | --- |
| GET | `/notifications` | `status=all|read|unread`, `per_page?`, `page?` | `notifications`, `unread_count`, `pagination` |
| GET | `/notifications/unread-count` | لا يوجد | `unread_count` |
| PATCH | `/notifications/{notification}/read` | لا يوجد | NotificationResource |
| PATCH | `/notifications/read-all` | لا يوجد | `unread_count=0` |
| DELETE | `/notifications/{notification}` | لا يوجد | `null` |

NotificationResource:

```json
{
  "id": "uuid",
  "type": "App\\Notifications\\TaskAssignedNotification",
  "data": {},
  "read_at": null,
  "is_read": false,
  "created_at": "date",
  "updated_at": "date"
}
```

## 15. Billing و subscriptions

Company-facing:

| Method | Endpoint | Body/Query | Response data |
| --- | --- | --- | --- |
| GET | `/billing/plans` | لا يوجد | `plans` |
| GET | `/company/subscription` | لا يوجد | `subscription` أو `null` |
| POST | `/company/subscription` | `plan_id`, `billing_cycle`, `reference_number?` | `payment` |

Subscribe body:

```json
{
  "plan_id": "uuid",
  "billing_cycle": "monthly",
  "reference_number": "BANK-TRANSFER-REF"
}
```

Admin billing:

| Method | Endpoint | Body/Query | Response data |
| --- | --- | --- | --- |
| GET | `/admin/plans` | `page?` | `plans`, `pagination` |
| POST | `/admin/plans` | Plan fields | PlanResource |
| GET | `/admin/plans/{plan}` | لا يوجد | PlanResource |
| PUT/PATCH | `/admin/plans/{plan}` | Plan update fields | PlanResource |
| DELETE | `/admin/plans/{plan}` | لا يوجد | PlanResource with `status=archived` |
| GET | `/admin/subscriptions` | `status?`, `per_page?`, `page?` | `subscriptions`, `pagination` |
| PATCH | `/admin/subscriptions/{subscription}/cancel` | لا يوجد | SubscriptionResource |
| GET | `/admin/payments` | لا يوجد | pending `payments` |
| PATCH | `/admin/payments/{payment}/confirm` | لا يوجد | `payment` |

Plan fields:

```json
{
  "name": "Business",
  "description": "Optional",
  "price_monthly": 49,
  "price_yearly": 499,
  "max_projects": 20,
  "max_members": 50,
  "max_storage_mb": 10240,
  "has_ai_features": true
}
```

## 16. Resource shapes المختصرة

UserResource:

```text
id, company?, name, email, phone, role, status, task_progress?, timezone,
last_login_at, is_email_verified, created_at, updated_at
```

CompanyResource:

```text
id, name, industry, website, address, logo_path, status,
created_at, updated_at, deleted_at
```

ProjectResource:

```text
id, name, description, status, progress, start_date, end_date,
company?, users?, created_at, updated_at, deleted_at
```

TaskResource:

```text
id, title, description, status, priority, due_date, project?,
assignees?, dependencies?, dependent_tasks?, notes?,
created_at, updated_at, deleted_at
```

UploadResource:

```text
id, original_name, company_id, project_id, task_id, scope, visibility,
file_name, file_type, category, file_size, status, processing_status,
processing_error, upload_date, uploaded_by?, shared_with?, source_type?,
summary?, decisions?, decision_items?, tasks?, task_items?, download_url,
created_at, updated_at
```

PlanResource:

```text
id, name, description, price_monthly, price_yearly, max_projects,
max_members, max_storage_mb, has_ai_features, status, created_at, updated_at
```

SubscriptionResource:

```text
id, company?, plan?, billing_cycle, status, trial_ends_at,
starts_at, ends_at, created_at, updated_at
```

PaymentResource:

```text
id, company?, subscription?, amount, method, status, reference_number,
notes, paid_at, confirmed_at, created_at, updated_at
```

## 17. Enums

```text
UserRole: admin | company_owner | company_manager | company_member
UserStatus: pending | active | suspended | inactive
CompanyStatus: active | inactive | suspended
ProjectStatus: active | pending | paused | completed | cancelled
ProjectRole: manager | member | viewer
TaskStatus: todo | in_progress | on_hold | blocked | review | done
TaskPriority: low | medium | high | emergency
UploadScope: company | project | task | personal
UploadVisibility: private | members | selected
UploadAccessLevel: view | manage
UploadStatus: pending | uploading | success | failed
ProcessingStatus: queued | processing | processed | failed
BillingCycle: monthly | yearly
SubscriptionStatus: trialing | active | past_due | canceled
PlanStatus: active | archived
PaymentStatus: pending | completed | rejected
PaymentMethod: bank_transfer | cash
MessageRole: user | ai
OtpType: register | forgot-password | verify-email
```

## 18. Frontend implementation notes

API client:

| نقطة | تنفيذ |
| --- | --- |
| token storage | استخدم storage آمن حسب التطبيق. للويب، تجنب تعريضه أكثر من اللازم وامسحه عند 401 |
| response parsing | لا تعتمد على HTTP 200 فقط، افحص `success` في JSON |
| field errors | عند `422`, اعرض أول رسالة لكل field من `data[field][0]` |
| list cache keys | اجعل query params جزءا من cache key |
| delete actions | بعد success احذف العنصر من cache أو refetch القائمة |
| download | استخدم blob/stream ولا تنتظر JSON |
| retry | لا تعمل retry تلقائي على POST create إلا إذا كان idempotency مضمون من الشاشة |
| dates | أرسل dates بصيغة ISO أو `YYYY-MM-DD` حسب الحقل |

توجيه مقترح للصفحات:

```text
/login
/register
/verify-otp
/forgot-password
/onboarding/company
/admin/dashboard
/admin/users
/admin/companies
/admin/projects
/admin/tasks
/admin/billing/plans
/admin/billing/payments
/company/dashboard
/company/staff
/company/projects
/company/tasks
/company/uploads
/company/chat
/company/billing
/notifications
/profile
```

Checklist قبل تسليم الواجهة:

| بند | مطلوب |
| --- | --- |
| register flow | بعد OTP، توجيه create company لأول owner |
| login errors | معالجة `EMAIL_NOT_VERIFIED` و`ACCOUNT_INACTIVE` |
| company missing | 403 assigned-to-company يؤدي إلى onboarding |
| role UI | اخفاء admin/company_owner/project manager actions حسب الدور |
| validation | ربط `data` برسائل الحقول |
| pagination | دعم `has_more` و`page` |
| uploads | multipart صحيح وdownload كـ blob |
| websocket | auth endpoint صحيح، channel صحيح، event listener صحيح |
| no secrets | لا توجد قيم فعلية لمفاتيح API/Reverb داخل الكود أو docs |
