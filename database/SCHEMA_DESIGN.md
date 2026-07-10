# VC2 Student Score Management System - Database Schema Design

## Entity Relationship Diagram (ERD)

```
┌─────────────────────────────────────────────────────────────────────┐
│                        DATABASE SCHEMA                              │
└─────────────────────────────────────────────────────────────────────┘

┌───────────────────┐     ┌───────────────────────┐     ┌───────────────────────┐
│      USERS        │     │      ROLES             │     │     PERMISSIONS       │
├───────────────────┤     ├───────────────────────┤     ├───────────────────────┤
│ PK │ id           │     │ PK │ id               │     │ PK │ id               │
│    │ name         │     │    │ name             │     │    │ name             │
│    │ email (UQ)   │     │    │ slug (UQ)        │     │    │ slug (UQ)        │
│    │ password     │     │    │ description      │     │    │ group            │
│    │ phone        │     │    │ is_active        │     │    │ description      │
│    │ gender       │     │    │ timestamps       │     │    │ timestamps       │
│    │ date_of_birth│     └───────────┬───────────┘     └──────────┬────────────┘
│    │ profile_photo│                 │                            │
│    │ google_id    │                 │ role_user                  │ role_permission
│    │ status       │                 │                            │
│    │ last_login_at│                 ▼                            ▼
│    │ email_verified_at     ┌───────────────────┐     ┌───────────────────────┐
│    │ remember_token│       │   ROLE_USER       │     │   ROLE_PERMISSION     │
│    │ timestamps    │       │ (PIVOT TABLE)     │     │ (PIVOT TABLE)         │
└────────┬──────────┘       │ PK │ id           │     │ PK │ id               │
         │                  │ FK │ user_id      │     │ FK │ role_id           │
         │ 1:1              │ FK │ role_id      │     │ FK │ permission_id     │
         ├─────────────┐    │    │ timestamps   │     │    │ timestamps        │
         │              │    └───────────────────┘     └───────────────────────┘
     ┌───┴────┐    ┌───┴────┐
     │TEACHERS│    │STUDENTS│
     ├────────┤    ├────────┤
     │PK│ id  │    │PK│ id  │
     │FK│user_│    │FK│user_│
     │  │id(UQ)│   │  │id(UQ)│
     │  │teacher│   │  │student│
     │  │_code  │   │  │_code  │
     │  │(UQ)  │   │  │(UQ)   │
     │FK│dept_ │   │FK│class_ │
     │  │id    │   │  │id     │
     │  │posi- │   │FK│academi│
     │  │tion  │   │  │c_year_│
     │  │hire_ │   │  │id     │
     │  │date  │   │  │enrollm│
     │  │quali-│   │  │ent_date│
     │  │ficat-│   │  │guardi-│
     │  │ion   │   │  │an_info│
     │  │speci-│   │  │scholar│
     │  │aliza-│   │  │ship_st│
     │  │tion  │   │  │atus   │
     │  │emplo-│   │  │emer-  │
     │  │yment_│   │  │gency_c│
     │  │type  │   │  │ontact │
     │  │salary│   │  │notes  │
     │  │_grade│   │  │timesta│
     │  │office│   │  │mps    │
     │  │_loc  │   │  │       │
     │  │notes │   │  │       │
     └───┬────┘    └───┬────┘
         │             │
         │ 1:N         │ N:1
         ▼             ▼
┌─────────────────┐  ┌─────────────────┐  ┌──────────────────┐
│   DEPARTMENTS   │  │    CLASSES       │  │  ACADEMIC_YEARS  │
├─────────────────┤  ├─────────────────┤  ├──────────────────┤
│PK│ id           │  │PK│ id           │  │PK│ id            │
│  │ name         │  │  │ name         │  │  │ name (UQ)     │
│  │ code (UQ)    │  │  │ code (UQ)    │  │  │ start_date    │
│  │ description  │  │FK│ teacher_id   │  │  │ end_date      │
│  │ is_active    │  │FK│ academic_y-  │  │  │ is_current    │
│  │ timestamps   │  │  │ ear_id       │  │  │ timestamps    │
└─────────────────┘  │  │ description  │  └──────────────────┘
                      │  │ is_active    │           │
                      │  │ timestamps   │           │
                      └─────────────────┘           │
                              │                      │
                              │                      │
                              ▼                      ▼
                    ┌──────────────────────────────────────┐
                    │              SCORES                   │
                    ├──────────────────────────────────────┤
                    │PK│ id                                │
                    │FK│ student_id                        │
                    │FK│ subject_id                        │
                    │FK│ academic_year_id                  │
                    │  │ quiz, assignment, midterm, final  │
                    │  │ total, grade, remarks             │
                    │  │ timestamps                        │
                    │  │ UQ(student_id, subject_id,        │
                    │  │    academic_year_id)              │
                    └──────────────────────────────────────┘
                                    │
                                    ▼
                    ┌──────────────────────┐
                    │     SUBJECTS         │
                    ├──────────────────────┤
                    │PK│ id                │
                    │  │ name              │
                    │  │ code (UQ)         │
                    │  │ description       │
                    │  │ credit_hours      │
                    │  │ is_active         │
                    │  │ timestamps        │
                    └──────────────────────┘
```

## Table Details

### 1. `users` — Central Identity Table
**Purpose:** Single source of truth for all authentication and personal information. Every account type (teacher, student, admin) starts here.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, Auto-increment | Unique user identifier |
| name | VARCHAR(255) | NOT NULL | Full name |
| email | VARCHAR(255) | UNIQUE, NOT NULL | Login email |
| password | VARCHAR(255) | NOT NULL | Hashed password |
| phone | VARCHAR(20) | NULLABLE | Contact number |
| gender | ENUM('Male','Female','Other') | NULLABLE | Gender |
| date_of_birth | DATE | NULLABLE | Date of birth |
| profile_photo | VARCHAR(255) | NULLABLE | Avatar/path |
| google_id | VARCHAR(255) | UNIQUE, NULLABLE | Google OAuth ID |
| status | ENUM('active','inactive','suspended') | DEFAULT 'active' | Account status |
| last_login_at | TIMESTAMP | NULLABLE | Last login timestamp |
| email_verified_at | TIMESTAMP | NULLABLE | Email verification |
| remember_token | VARCHAR(100) | NULLABLE | "Remember me" token |
| created_at | TIMESTAMP | NOT NULL | Laravel timestamp |
| updated_at | TIMESTAMP | NOT NULL | Laravel timestamp |

**Relationships:**
- Has one `teacher` (1:1) — optional
- Has one `student` (1:1) — optional
- Belongs to many `roles` via `role_user` (M:N)

---

### 2. `roles` — RBAC Role Definitions
**Purpose:** Defines all roles in the system. New roles can be added via database seeders/UI without code changes.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK | Role ID |
| name | VARCHAR(100) | UNIQUE | Display name (e.g., "Administrator") |
| slug | VARCHAR(100) | UNIQUE | Machine name (e.g., "admin") |
| description | TEXT | NULLABLE | Role description |
| is_active | BOOLEAN | DEFAULT TRUE | Whether role is usable |
| timestamps | — | — | Laravel timestamps |

**Seed data examples:** Admin, Teacher, Student, Registrar, Manager

---

### 3. `permissions` — RBAC Permission Definitions
**Purpose:** Defines granular actions that can be assigned to roles. Fully dynamic — add via DB.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK | Permission ID |
| name | VARCHAR(100) | UNIQUE | Display name (e.g., "Create Student") |
| slug | VARCHAR(100) | UNIQUE | Machine name (e.g., "create-student") |
| group | VARCHAR(100) | NULLABLE | Grouping for UI (e.g., "students") |
| description | TEXT | NULLABLE | Permission description |
| timestamps | — | — | Laravel timestamps |

**Seed data examples:** create-student, edit-student, delete-student, view-reports, manage-scores, manage-classes, manage-subjects, manage-users, manage-roles, manage-permissions

---

### 4. `role_permission` — Role-Permission Pivot
**Purpose:** Many-to-many relationship assigning permissions to roles.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK | Auto-increment |
| role_id | BIGINT UNSIGNED | FK → roles(id) ON DELETE CASCADE | Role reference |
| permission_id | BIGINT UNSIGNED | FK → permissions(id) ON DELETE CASCADE | Permission reference |
| timestamps | — | — | Laravel timestamps |
| | | UNIQUE(role_id, permission_id) | Prevents duplicates |

---

### 5. `role_user` — User-Role Pivot
**Purpose:** Assigns roles to users. A user can have multiple roles.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK | Auto-increment |
| user_id | BIGINT UNSIGNED | FK → users(id) ON DELETE CASCADE | User reference |
| role_id | BIGINT UNSIGNED | FK → roles(id) ON DELETE CASCADE | Role reference |
| timestamps | — | — | Laravel timestamps |
| | | UNIQUE(user_id, role_id) | Prevents duplicates |

---

### 6. `departments` — Academic Departments
**Purpose:** Organizational units that teachers belong to.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK | Auto-increment |
| name | VARCHAR(255) | NOT NULL | Department name |
| code | VARCHAR(50) | UNIQUE | Short code (e.g., "CS", "MATH") |
| description | TEXT | NULLABLE | Description |
| is_active | BOOLEAN | DEFAULT TRUE | Whether department is active |
| timestamps | — | — | Laravel timestamps |

**Relationships:** Has many `teachers` (1:N)

---

### 7. `academic_years` — Academic Year/Semester
**Purpose:** Defines academic periods. Allows historical data separation.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK | Auto-increment |
| name | VARCHAR(50) | UNIQUE | e.g., "2025-2026" |
| start_date | DATE | NOT NULL | Semester start |
| end_date | DATE | NOT NULL | Semester end |
| is_current | BOOLEAN | DEFAULT FALSE | Currently active year |
| timestamps | — | — | Laravel timestamps |

**Relationships:** Has many `classes`, `students`, `scores`

---

### 8. `teachers` — Teacher-Specific Data
**Purpose:** Stores only teacher-specific information. Common identity data is in `users`.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK | Auto-increment |
| user_id | BIGINT UNSIGNED | FK → users(id), UNIQUE, ON DELETE CASCADE | Links to user account |
| teacher_code | VARCHAR(50) | UNIQUE | Employee/teacher ID |
| department_id | BIGINT UNSIGNED | FK → departments(id), NULLABLE, ON DELETE SET NULL | Department |
| position | ENUM | DEFAULT 'lecturer' | Academic rank |
| hire_date | DATE | NULLABLE | Date hired |
| qualification | VARCHAR(100) | NULLABLE | Highest degree |
| specialization | VARCHAR(255) | NULLABLE | Field of expertise |
| employment_type | ENUM | DEFAULT 'permanent' | Contract type |
| salary_grade | VARCHAR(50) | NULLABLE | Pay grade |
| office_location | VARCHAR(255) | NULLABLE | Room/office |
| notes | TEXT | NULLABLE | Additional info |
| timestamps | — | — | Laravel timestamps |

**Indexes:** `department_id`
**Relationships:** Belongs to `user` (1:1), belongs to `department` (N:1), has many `classes`

---

### 9. `classes` — Class/Grade Groups
**Purpose:** Defines classes that students belong to, linked to a teacher and academic year.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK | Auto-increment |
| name | VARCHAR(100) | NOT NULL | Class name (e.g., "Grade 10A") |
| code | VARCHAR(50) | UNIQUE | Short code |
| teacher_id | BIGINT UNSIGNED | FK → teachers(id), NULLABLE, ON DELETE SET NULL | Homeroom teacher |
| academic_year_id | BIGINT UNSIGNED | FK → academic_years(id), NULLABLE, ON DELETE SET NULL | Academic year |
| description | TEXT | NULLABLE | Description |
| is_active | BOOLEAN | DEFAULT TRUE | Active flag |
| timestamps | — | — | Laravel timestamps |

**Indexes:** `teacher_id`, `academic_year_id`
**Relationships:** Belongs to `teacher`, belongs to `academic_year`, has many `students`

---

### 10. `student_number_sequences` — PNC-Style ID Counter
**Purpose:** Manages per-intake-year sequential counters for generating PNC-style student IDs. Each row holds one counter per intake year, and row-level locking (SELECT ... FOR UPDATE) prevents duplicate IDs under concurrent requests.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK | Auto-increment |
| intake_year | YEAR(4) | UNIQUE, NOT NULL | The intake year (e.g. 2026) |
| next_sequence | BIGINT UNSIGNED | DEFAULT 1 | Next available sequence number |
| timestamps | — | — | Laravel timestamps |

**How it works:**
- When creating a student, `StudentNumberService` calls `reserveNext(intakeYear)` inside a DB transaction.
- This executes `SELECT ... FOR UPDATE` on the matching row, locking it exclusively.
- If no row exists for that year, one is created with `next_sequence = 1`.
- The current value is read, then `next_sequence` is incremented atomically.
- The returned number is formatted as `PNC{year}-{padded sequence}` (e.g., PNC2026-001).

**Why this design:**
- Guarantees uniqueness even under concurrent requests (database-level locking).
- Sequence resets per intake year automatically — just insert a new row.
- Faster than `MAX(sequence_number)+1` queries on the students table because:
  - It's a single row lookup per year.
  - No full-table scan needed.
  - Locking is isolated to one row, not the entire students table.

---

### 11. `students` — Student-Specific Data
**Purpose:** Stores only student-specific information. Common identity data is in `users`. The student ID follows the PNC format (e.g., `PNC2026-001`) with separate columns for the full ID, intake year, and sequence number for queryability.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK | Auto-increment |
| user_id | BIGINT UNSIGNED | FK → users(id), UNIQUE, ON DELETE CASCADE | Links to user account |
| student_number | VARCHAR(20) | UNIQUE, NOT NULL | Generated PNC ID: e.g. `PNC2026-001` |
| intake_year | YEAR(4) | NOT NULL | The year the student first enrolled (e.g. 2026) |
| sequence_number | INT UNSIGNED | NOT NULL | Per-year sequential number (1, 2, 3...) |
| class_id | BIGINT UNSIGNED | FK → classes(id), NULLABLE, ON DELETE SET NULL | Assigned class |
| academic_year_id | BIGINT UNSIGNED | FK → academic_years(id), NULLABLE, ON DELETE SET NULL | Enrollment year |
| enrollment_date | DATE | NULLABLE | Date enrolled |
| guardian_name | VARCHAR(255) | NULLABLE | Parent/guardian name |
| guardian_phone | VARCHAR(20) | NULLABLE | Guardian phone |
| guardian_email | VARCHAR(100) | NULLABLE | Guardian email |
| parent_address | TEXT | NULLABLE | Parent address |
| current_address | TEXT | NULLABLE | Current residence |
| scholarship_status | ENUM('none','partial','full') | DEFAULT 'none' | Scholarship type |
| emergency_contact | VARCHAR(100) | NULLABLE | Emergency contact info |
| notes | TEXT | NULLABLE | Additional notes |
| timestamps | — | — | Laravel timestamps |

**Indexes:** `class_id`, `academic_year_id`, `intake_year` (for filtering by intake)
**Unique constraints:** `student_number` (prevents duplicate IDs across all years)
**Relationships:** Belongs to `user` (1:1), belongs to `classe` (N:1), belongs to `academic_year` (N:1), has many `scores`

**Key design notes:**
- `student_number` is the **permanent identifier** and **never changes**, even if the student transfers class, department, or academic year.
- `intake_year` represents the year the student **first enrolled** at the institution, not the current academic year. This is fixed at creation time.
- `sequence_number` is the auto-incrementing counter **per intake year**. It restarts at 1 for each new intake year.
- The combination `(intake_year, sequence_number)` is implicitly unique via the `student_number` unique constraint.

---

### 12. `subjects` — Course Subjects
**Purpose:** Defines all subjects/courses available in the system.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK | Auto-increment |
| name | VARCHAR(100) | NOT NULL | Subject name |
| code | VARCHAR(50) | UNIQUE | Subject code |
| description | TEXT | NULLABLE | Description |
| credit_hours | INTEGER | DEFAULT 0 | Credit/unit hours |
| is_active | BOOLEAN | DEFAULT TRUE | Active flag |
| timestamps | — | — | Laravel timestamps |

**Relationships:** Has many `scores`

---

### 13. `scores` — Student Scores/Grades
**Purpose:** Records academic performance per student per subject per academic year.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK | Auto-increment |
| student_id | BIGINT UNSIGNED | FK → students(id) ON DELETE CASCADE | Student |
| subject_id | BIGINT UNSIGNED | FK → subjects(id) ON DELETE CASCADE | Subject |
| academic_year_id | BIGINT UNSIGNED | FK → academic_years(id), NULLABLE, ON DELETE SET NULL | Academic year |
| quiz | DECIMAL(5,2) | DEFAULT 0 | Quiz score |
| assignment | DECIMAL(5,2) | DEFAULT 0 | Assignment score |
| midterm | DECIMAL(5,2) | DEFAULT 0 | Midterm exam score |
| final | DECIMAL(5,2) | DEFAULT 0 | Final exam score |
| total | DECIMAL(5,2) | DEFAULT 0 | Computed total |
| grade | VARCHAR(10) | NULLABLE | Letter grade (A, B, C, etc.) |
| remarks | TEXT | NULLABLE | Teacher remarks |
| timestamps | — | — | Laravel timestamps |

**Indexes:** `academic_year_id`
**Unique constraint:** `(student_id, subject_id, academic_year_id)` — prevents duplicate score entries

**Relationships:** Belongs to `student`, belongs to `subject`, belongs to `academic_year`

### 14. `activity_logs` — Audit Trail

**Purpose:** Records all actions performed by Admin and Teacher users for auditing, security monitoring, and troubleshooting. Student activities are never logged. Uses a centralized service-based approach with model observers for automatic recording.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK | Auto-increment |
| user_id | BIGINT UNSIGNED | FK → users(id) ON DELETE CASCADE | The user who performed the action |
| action | VARCHAR(50) | NOT NULL, INDEXED | Action name: Create, Update, Delete, Login, Logout, Export, Import, ResetPassword |
| module | VARCHAR(50) | NOT NULL, INDEXED | Module: Students, Teachers, Classes, Subjects, Scores, Users, Roles, Permissions, Reports, Auth, System |
| description | TEXT | NOT NULL | Human-readable explanation of the action |
| model_type | VARCHAR(100) | NULLABLE, INDEXED | Laravel model class name (e.g., App\Models\Student) |
| model_id | BIGINT UNSIGNED | NULLABLE, INDEXED | ID of the affected database record |
| old_values | JSON | NULLABLE | Previous values of changed fields (encoded JSON) |
| new_values | JSON | NULLABLE | New values of changed fields (encoded JSON) |
| ip_address | VARCHAR(45) | NULLABLE | Client IP address |
| user_agent | TEXT | NULLABLE | Browser/user-agent string |
| created_at | TIMESTAMP | INDEXED | When the action occurred |

**Indexes:** `user_id`, `action`, `module`, `created_at`, `(model_type, model_id)` composite

**How it works:**
1. **Model Observers** (`StudentObserver`, `TeacherObserver`, etc.) automatically fire on `created`, `updated`, and `deleted` events.
2. Each observer calls `ActivityLogService::logCreate/logUpdate/logDelete()`.
3. The service checks if the authenticated user has the `admin` or `teacher` role. If not, the log is silently discarded.
4. Login/logout events are logged via controller logic using `ActivityLogService::logLogin/logLogout()`.
5. CRUD actions for RBAC (roles, permissions, user-role assignment) can be logged manually via `ActivityLogService` in controllers.

**Relationships:** Belongs to `user` (N:1)

---

## Relationship Summary

| Relationship | Type | Description |
|---|---|---|
| User → ActivityLogs | 1:N | One user can have many activity log entries |
| User → Teacher | 1:1 (optional) | One user may be a teacher |
| User → Student | 1:1 (optional) | One user may be a student |
| User → Roles | M:N | User can have many roles, role can have many users |
| Role → Permissions | M:N | Role can have many permissions, permission can belong to many roles |
| Department → Teachers | 1:N | One department has many teachers |
| Teacher → Classes | 1:N | One teacher can manage many classes |
| Academic Year → Classes | 1:N | One academic year can have many classes |
| Class → Students | 1:N | One class can have many students |
| Academic Year → Students | 1:N | One academic year can have many enrollments |
| Student → Scores | 1:N | One student can have many scores |
| Subject → Scores | 1:N | One subject can have many scores |
| Academic Year → Scores | 1:N | One academic year can have many scores |

## Normalization Justification

The schema follows **3rd Normal Form (3NF)**:

1. **1NF:** All columns are atomic (no arrays or JSON blobs for structured data).
2. **2NF:** No partial dependencies — every non-key column depends on the full primary key.
3. **3NF:** No transitive dependencies — personal info is in `users`, not duplicated in `teachers` or `students`.

## Why No Duplicate Personal Data?

- `name`, `email`, `password`, `phone`, `gender`, `date_of_birth`, `profile_photo`, `status` all live **only** in `users`.
- `teachers` only has `teacher_code`, `position`, `department_id`, `hire_date`, `qualification`, etc.
- `students` only has `student_number`, `intake_year`, `sequence_number`, `class_id`, `guardian_name`, `enrollment_date`, etc.
- If a teacher is also a student (staff studying), they still have one user record.

## RBAC Flexibility

- **New roles** can be added by inserting a row into `roles`.
- **New permissions** can be added by inserting a row into `permissions`.
- **Role-permission assignments** are managed in `role_permission`.
- **User-role assignments** are managed in `role_user`.
- **No code changes** are required to add/modify roles or permissions — it's purely data-driven.

## RBAC Authorization System

The system uses Laravel's Gate and Policy system for authorization.

### Permission Flow

```
User → Assigned Role(s) → Role has Permissions → User inherits all permissions from roles
```

- **Admin**: Always bypasses all checks via `isAdmin()` → full unrestricted access.
- **Teacher**: Uses permissions assigned to the "teacher" role — identical for every teacher.
- **Student**: Uses permissions assigned to the "student" role — read-only.

### Components

| Component | Purpose |
|-----------|---------|
| `AuthServiceProvider` | Dynamically registers a Gate for every permission slug found in the `permissions` table. Cached for 1 hour. |
| `CheckPermission` middleware | Applied to routes as `->middleware('permission:slug')`. Blocks users without the required Gate. Admin bypasses automatically. |
| `User::hasPermission()` | Checks if a user has a permission via roles. Admins always return `true`. |
| Policies | `StudentPolicy`, `TeacherPolicy`, `ClassePolicy`, `SubjectPolicy`, `ScorePolicy` — map model actions (`view`, `create`, `update`, `delete`) to permission slugs. |

### Clearing Permission Cache

When an admin changes role-permission assignments:
```php
\AuthServiceProvider::clearPermissionsCache();
```

### Example Route Usage

```php
Route::middleware('auth:sanctum')->prefix('students')->group(function () {
    Route::get('/', [StudentController::class, 'index'])
        ->middleware('permission:view-students');

    Route::post('/', [StudentController::class, 'store'])
        ->middleware('permission:create-students');

    Route::put('/{student}', [StudentController::class, 'update'])
        ->middleware('permission:edit-students');

    Route::delete('/{student}', [StudentController::class, 'destroy'])
        ->middleware('permission:delete-students');
});
```

## Cascade Rules

| Foreign Key | ON DELETE | Rationale |
|---|---|---|
| teachers.user_id → users.id | CASCADE | If user is deleted, teacher record is removed |
| students.user_id → users.id | CASCADE | If user is deleted, student record is removed |
| role_permission.role_id → roles.id | CASCADE | Deleting a role removes its permission assignments |
| role_permission.permission_id → permissions.id | CASCADE | Deleting a permission removes it from roles |
| role_user.user_id → users.id | CASCADE | Deleting a user removes role assignments |
| role_user.role_id → roles.id | CASCADE | Deleting a role removes user assignments |
| scores.student_id → students.id | CASCADE | Deleting student removes their scores |
| scores.subject_id → subjects.id | CASCADE | Deleting subject removes its scores |
| teachers.department_id → departments.id | SET NULL | Department deletion doesn't delete teachers |
| students.class_id → classes.id | SET NULL | Class deletion doesn't delete students |
| classes.teacher_id → teachers.id | SET NULL | Teacher deletion doesn't delete classes |

## Migration Execution Order

| Order | Migration File | Creates |
|-------|---------------|---------|
| 1 | `0001_01_01_000000_create_users_table.php` | users, password_reset_tokens, sessions |
| 2 | `2026_07_08_000001_create_departments_table.php` | departments |
| 3 | `2026_07_08_000002_create_academic_years_table.php` | academic_years |
| 4 | `2026_07_08_000003_create_roles_table.php` | roles |
| 5 | `2026_07_08_000004_create_permissions_table.php` | permissions |
| 6 | `2026_07_08_000005_create_role_permission_table.php` | role_permission (pivot) |
| 7 | `2026_07_08_000006_create_role_user_table.php` | role_user (pivot) |
| 8 | `2026_07_08_000007_create_teachers_table.php` | teachers |
| 9 | `2026_07_08_000008_create_classes_table.php` | classes |
| 10 | `2026_07_08_000009_create_student_number_sequences_table.php` | student_number_sequences |
| 11 | `2026_07_08_000010_create_students_table.php` | students |
| 12 | `2026_07_08_000010_create_subjects_table.php` | subjects |
| 13 | `2026_07_08_000011_create_scores_table.php` | scores |
| 14 | `2026_07_08_000012_cleanup_old_schema.php` | Drops old columns/tables |
| 15 | `2026_07_08_000013_create_activity_logs_table.php` | activity_logs |
