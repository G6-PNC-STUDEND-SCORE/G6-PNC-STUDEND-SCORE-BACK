# Student Score Management System - Backend

Laravel 12 REST API for managing students, classes, subjects, scores, reporting, and RBAC. Communicates with the Vue 3 frontend via Sanctum-authenticated REST APIs and stores data in MySQL.

## Tech Stack

| Technology | Version | Purpose |
|---|---|---|
| Laravel | 12.0 | Backend Framework |
| PHP | >= 8.2 | Programming Language |
| MySQL | >= 5.7 | Database |
| Laravel Sanctum | 4.3 | SPA Token Authentication |
| Eloquent ORM | - | Database ORM |
| Google API Client | 2.19 | Google Sheets OAuth2 |
| PHP Spreadsheet | 5.9 | Excel import/export |
| PDF Parser | * | PDF parsing |

### Dev Dependencies

| Technology | Purpose |
|---|---|
| Faker | Test data generation |
| Laravel Pint | Code formatting |
| PHPUnit | Testing |
| Mockery | Mocking |
| Laravel Pail | Real-time log viewer |
| Laravel Sail | Docker dev environment |

## Project Structure

```text
backend/
├── app/
│   ├── Console/                    # Artisan commands
│   ├── Http/
│   │   ├── Controllers/Api/        # 23 API controllers
│   │   │   ├── AuthController.php          # Login, logout, Google OAuth, password reset
│   │   │   ├── UserController.php          # User CRUD, bulk delete
│   │   │   ├── ProfileController.php       # Profile view/update, avatar upload
│   │   │   ├── ClassController.php         # Class CRUD
│   │   │   ├── StudentController.php       # Student CRUD, bulk import/delete
│   │   │   ├── SubjectController.php       # Subject CRUD, teacher list
│   │   │   ├── ScoreController.php         # Score CRUD, details management
│   │   │   ├── SpreadsheetController.php   # Score sheet view, Google sync, file import
│   │   │   ├── DashboardController.php     # Dashboard stats & filters
│   │   │   ├── ChartController.php         # Grade distribution, subject performance, trends
│   │   │   ├── ReportCardController.php    # Report cards & transcripts
│   │   │   ├── StudentPortalController.php # Student self-service portal
│   │   │   ├── PermissionController.php    # Role & permission management
│   │   │   ├── GoogleSheetsController.php  # Google Sheets OAuth integration
│   │   │   ├── ActivityLogController.php   # Activity log viewing
│   │   │   ├── AcademicYearController.php  # Academic year CRUD
│   │   │   ├── AssessmentTypeController.php# Assessment type CRUD
│   │   │   ├── EmailDomainRuleController.php# Email domain rules
│   │   │   ├── GenerationController.php    # Generation CRUD
│   │   │   ├── GradeBoundaryController.php # Grade boundary rules
│   │   │   ├── SubjectOfferingController.php# Subject offerings
│   │   │   ├── SubjectTermController.php   # Subject-term assignments
│   │   │   └── TermController.php          # Term CRUD
│   │   ├── Middleware/              # 3 custom middleware
│   │   │   ├── CheckPermission.php         # permission:slug
│   │   │   ├── CheckRole.php               # role:slug
│   │   │   └── LogUserActivity.php         # Activity logging
│   │   ├── Requests/API/           # 8 form request validators
│   │   │   ├── StoreClassRequest.php
│   │   │   ├── UpdateClassRequest.php
│   │   │   ├── StoreTermRequest.php
│   │   │   ├── UpdateTermRequest.php
│   │   │   ├── StoreGenerationRequest.php
│   │   │   ├── UpdateGenerationRequest.php
│   │   │   ├── StoreAssessmentTypeRequest.php
│   │   │   └── UpdateAssessmentTypeRequest.php
│   │   └── Concerns/               # Shared traits
│   │       └── ApiResponds.php
│   ├── Models/                     # 21 Eloquent models
│   │   ├── User.php
│   │   ├── RBAC/
│   │   │   ├── Role.php
│   │   │   └── Permission.php
│   │   ├── Teacher.php
│   │   ├── Student.php
│   │   ├── SchoolClass.php
│   │   ├── Subject.php
│   │   ├── SubjectOffering.php
│   │   ├── StudentSubjectEnrollment.php
│   │   ├── StudentClassHistory.php
│   │   ├── Score.php
│   │   ├── ScoreDetail.php
│   │   ├── AssessmentType.php
│   │   ├── Term.php
│   │   ├── AcademicYear.php
│   │   ├── Generation.php
│   │   ├── Department.php
│   │   ├── GradeBoundary.php
│   │   ├── ReportCard.php
│   │   ├── Transcript.php
│   │   ├── ActivityLog.php
│   │   └── EmailDomainRule.php
│   ├── Observers/                  # 7 activity logging observers
│   │   ├── UserObserver.php
│   │   ├── StudentObserver.php
│   │   ├── TeacherObserver.php
│   │   ├── SchoolClassObserver.php
│   │   ├── SubjectObserver.php
│   │   ├── ScoreObserver.php
│   │   └── RoleObserver.php
│   ├── Policies/                   # 5 authorization policies
│   │   ├── StudentPolicy.php
│   │   ├── TeacherPolicy.php
│   │   ├── SchoolClassPolicy.php
│   │   ├── SubjectPolicy.php
│   │   └── ScorePolicy.php
│   ├── Services/
│   │   ├── ActivityLogger.php      # Activity logging service
│   │   ├── ActivityLogService.php  # Log CRUD operations
│   │   ├── DashboardService.php    # Dashboard aggregation
│   │   ├── StudentNumberService.php# PNC-format student ID generation
│   │   └── Auth/
│   │       ├── GoogleClientIdTokenVerifier.php
│   │       └── GoogleIdTokenVerifierInterface.php
│   ├── Notifications/              # Email notifications
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── AuthServiceProvider.php  # Dynamic Gate registration for permissions
├── config/                         # Laravel configuration
├── database/
│   ├── migrations/                 # 39 migration files
│   ├── seeders/                    # 10 seeders
│   │   ├── DatabaseSeeder.php
│   │   ├── AdminUserSeeder.php
│   │   ├── UsersSeeder.php
│   │   ├── TeacherSeeder.php
│   │   ├── StudentSeeder.php
│   │   ├── SubjectSeeder.php
│   │   ├── SubjectTermSeeder.php
│   │   ├── AssessmentTypeSeeder.php
│   │   ├── PermissionSeeder.php
│   │   └── EmailDomainRuleSeeder.php
│   ├── factories/
│   └── SCHEMA_DESIGN.md            # Full schema documentation
├── routes/
│   └── api.php                     # All API routes (210 lines)
├── tests/                          # PHPUnit tests
├── .env.example                    # Annotated environment config
└── composer.json
```

## Database Schema

### Core Tables

#### users
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | bigint | PK | User ID |
| name | varchar(255) | NOT NULL | Full name |
| email | varchar(255) | UNIQUE, NOT NULL | Login email |
| password | varchar(255) | NOT NULL | Hashed password |
| phone | varchar(20) | NULLABLE | Contact number |
| gender | enum('Male','Female','Other') | NULLABLE | Gender |
| date_of_birth | date | NULLABLE | Date of birth |
| profile_photo | varchar(255) | NULLABLE | Avatar path |
| google_id | varchar(255) | UNIQUE, NULLABLE | Google OAuth ID |
| google_refresh_token | varchar(255) | NULLABLE | Google refresh token |
| status | enum('active','inactive','suspended') | DEFAULT 'active' | Account status |
| last_login_at | timestamp | NULLABLE | Last login |
| email_verified_at | timestamp | NULLABLE | Email verification |
| created_at / updated_at | timestamp | | Laravel timestamps |

#### roles (RBAC)
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| name | varchar(100) | UNIQUE |
| slug | varchar(100) | UNIQUE |
| description | text | NULLABLE |
| is_active | boolean | DEFAULT TRUE |

**Seed data:** Admin, Teacher, Student, Registrar, Manager

#### permissions (RBAC)
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| name | varchar(100) | UNIQUE |
| slug | varchar(100) | UNIQUE |
| group | varchar(100) | NULLABLE |
| description | text | NULLABLE |

#### Pivot Tables
- **role_user** -- User-Role assignment (M:N)
- **role_permission** -- Role-Permission assignment (M:N)

#### teachers
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| user_id | bigint | FK -> users.id, UNIQUE, CASCADE |
| teacher_code | varchar(50) | UNIQUE |
| department_id | bigint | FK -> departments.id, NULLABLE, SET NULL |
| position | enum | DEFAULT 'lecturer' |
| hire_date | date | NULLABLE |
| qualification | varchar(100) | NULLABLE |
| specialization | varchar(255) | NULLABLE |
| employment_type | enum | DEFAULT 'permanent' |
| salary_grade | varchar(50) | NULLABLE |
| office_location | varchar(255) | NULLABLE |
| notes | text | NULLABLE |

#### students
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| user_id | bigint | FK -> users.id, UNIQUE, CASCADE |
| student_number | varchar(20) | UNIQUE, NOT NULL (format: PNC{year}-{seq}) |
| intake_year | year(4) | NOT NULL |
| sequence_number | int unsigned | NOT NULL |
| class_id | bigint | FK -> classes.id, NULLABLE, SET NULL |
| academic_year_id | bigint | FK -> academic_years.id, NULLABLE, SET NULL |
| enrollment_date | date | NULLABLE |
| guardian_name | varchar(255) | NULLABLE |
| guardian_phone | varchar(20) | NULLABLE |
| guardian_email | varchar(100) | NULLABLE |
| parent_address | text | NULLABLE |
| current_address | text | NULLABLE |
| scholarship_status | enum('none','partial','full') | DEFAULT 'none' |
| emergency_contact | varchar(100) | NULLABLE |
| notes | text | NULLABLE |
| is_placeholder | boolean | DEFAULT FALSE |

#### classes
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| name | varchar(100) | NOT NULL |
| teacher_id | bigint | FK -> teachers.id, NULLABLE, SET NULL |
| room | varchar(100) | NULLABLE |
| deleted_at | timestamp | NULLABLE (soft deletes) |

#### subjects
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| name | varchar(100) | NOT NULL |
| code | varchar(50) | UNIQUE |
| description | text | NULLABLE |
| credit_hours | integer | DEFAULT 0 |
| is_active | boolean | DEFAULT TRUE |

#### subject_offerings
Links subjects to teachers, classes, terms, and generations for historical tracking.

| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| subject_id | bigint | FK -> subjects.id, CASCADE |
| teacher_id | bigint | FK -> teachers.id, NULLABLE, SET NULL |
| class_id | bigint | FK -> classes.id, CASCADE |
| generation_id | bigint | FK -> generations.id, CASCADE |
| term_id | bigint | FK -> terms.id, CASCADE |
| status | enum('active','inactive') | DEFAULT 'active' |

#### terms
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| name | varchar(50) | NOT NULL |
| academic_year_id | bigint | FK -> academic_years.id, CASCADE |

#### academic_years
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| name | varchar(50) | UNIQUE |
| start_date | date | NOT NULL |
| end_date | date | NOT NULL |
| is_current | boolean | DEFAULT FALSE |

#### generations
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| name | varchar(50) | NOT NULL |

#### student_subject_enrollments
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| student_id | bigint | FK -> students.id, CASCADE |
| subject_offering_id | bigint | FK -> subject_offerings.id, NULLABLE, SET NULL |
| generation_id | bigint | FK -> generations.id, NULLABLE, SET NULL |
| term_id | bigint | FK -> terms.id, NULLABLE, SET NULL |

#### scores
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| student_id | bigint | FK -> students.id, CASCADE |
| subject_id | bigint | FK -> subjects.id, CASCADE |
| generation_id | bigint | FK -> generations.id, NULLABLE, SET NULL |
| term_id | bigint | FK -> terms.id, NULLABLE, SET NULL |
| total | decimal(5,2) | DEFAULT 0 |
| grade | varchar(10) | NULLABLE |
| remarks | text | NULLABLE |

#### score_details
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| score_id | bigint | FK -> scores.id, CASCADE |
| type | varchar(50) | NOT NULL (quiz, assignment, midterm, final) |
| label | varchar(100) | NULLABLE |
| mark | decimal(5,2) | NOT NULL |
| max_score | integer | NULLABLE |
| order_number | integer | NULLABLE |

#### assessment_types
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| name | varchar(50) | NOT NULL |
| weight | decimal(5,2) | NOT NULL |

#### grade_boundaries
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| min_score | decimal | NOT NULL |
| max_score | decimal | NOT NULL |
| grade | varchar(10) | NOT NULL |
| description | text | NULLABLE |

#### report_cards
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| student_id | bigint | FK -> students.id, CASCADE |
| generation_id | bigint | FK -> generations.id, NULLABLE, SET NULL |
| term_id | bigint | FK -> terms.id, CASCADE |
| total_score | decimal | |
| average_score | decimal | |
| grade | varchar(10) | |
| rank | integer | |
| status | varchar(20) | |

#### transcripts
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| student_id | bigint | FK -> students.id, CASCADE |
| generation_id | bigint | FK -> generations.id |
| term_id | bigint | FK -> terms.id, CASCADE |

#### activity_logs
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| user_id | bigint | FK -> users.id, CASCADE |
| action | varchar(50) | NOT NULL, INDEXED |
| module | varchar(50) | NOT NULL, INDEXED |
| description | text | NOT NULL |
| model_type | varchar(100) | NULLABLE, INDEXED |
| model_id | bigint | NULLABLE, INDEXED |
| old_values | json | NULLABLE |
| new_values | json | NULLABLE |
| ip_address | varchar(45) | NULLABLE |
| user_agent | text | NULLABLE |
| created_at | timestamp | INDEXED |

#### email_domain_rules
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| domain | varchar(255) | UNIQUE |
| role_slug | varchar(100) | NOT NULL |
| is_active | boolean | DEFAULT TRUE |

#### student_class_histories
Tracks student class transfers across generations.

| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| student_id | bigint | FK -> students.id, CASCADE |
| class_id | bigint | FK -> classes.id, CASCADE |
| generation_id | bigint | FK -> generations.id, CASCADE |
| start_date | date | NOT NULL |
| end_date | date | NULLABLE |
| status | enum('active','completed','transferred') | DEFAULT 'active' |

#### departments
| Column | Type | Constraints |
|---|---|---|
| id | bigint | PK |
| name | varchar(255) | NOT NULL |
| code | varchar(50) | UNIQUE |
| description | text | NULLABLE |
| is_active | boolean | DEFAULT TRUE |

### Relationships

| Relationship | Type | Description |
|---|---|---|
| User -> Teacher | 1:1 (optional) | One user may be a teacher |
| User -> Student | 1:1 (optional) | One user may be a student |
| User -> Roles | M:N | Via role_user pivot |
| Role -> Permissions | M:N | Via role_permission pivot |
| Teacher -> Classes | 1:N | Homeroom teacher |
| Class -> Students | 1:N | Students in class |
| Subject -> SubjectOfferings | 1:N | Subject taught per term/class |
| SubjectOffering -> Enrollments | 1:N | Student enrollments |
| Student -> Scores | 1:N | Student scores |
| Subject -> Scores | 1:N | Subject scores |
| Score -> ScoreDetails | 1:N | Individual assessment items |
| User -> ActivityLogs | 1:N | Audit trail |

### Cascade Rules

| Foreign Key | ON DELETE | Rationale |
|---|---|---|
| teachers.user_id -> users.id | CASCADE | User deleted = teacher record removed |
| students.user_id -> users.id | CASCADE | User deleted = student record removed |
| role_permission.* | CASCADE | Role/permission deleted = assignments removed |
| role_user.* | CASCADE | User/role deleted = assignments removed |
| scores.student_id -> students.id | CASCADE | Student deleted = scores removed |
| scores.subject_id -> subjects.id | CASCADE | Subject deleted = scores removed |
| teachers.department_id -> departments.id | SET NULL | Department deleted doesn't delete teachers |
| students.class_id -> classes.id | SET NULL | Class deleted doesn't delete students |
| classes.teacher_id -> teachers.id | SET NULL | Teacher deleted doesn't delete classes |

## Grade Calculation

Assessment types have configurable weights. Default:

```
Final Score = (Quiz x 20%) + (Assignment x 10%) + (Midterm x 30%) + (Final x 40%)
```

Score details use **weighted averages** (not simple averages):

```
Quiz 1: 8/10, Quiz 2: 18/20
Weighted average: (8 + 18) / (10 + 20) * 100 = 85% (correct)
Simple average: (8 + 18) / 2 = 13 -> 90% (incorrect)
```

Grades are determined by `grade_boundaries` table rules.

## Authentication & Authorization

### Authentication
- Laravel Sanctum SPA token authentication
- Email/password login
- Google OAuth2 login (ID token verification)
- Password hashing with bcrypt (12 rounds)
- Password reset via email

### Authorization (RBAC)
Custom role-based access control (not Spatie):

- **Admin** -- Bypasses all checks, full access
- **Teacher** -- Permissions assigned to teacher role
- **Student** -- Read-only permissions

**Components:**

| Component | Purpose |
|---|---|
| `AuthServiceProvider` | Dynamically registers a Gate for every permission slug. Cached 1 hour. |
| `CheckPermission` middleware | `permission:slug` -- Blocks users without the required Gate. Admin bypasses. |
| `CheckRole` middleware | `role:slug` -- Blocks users without the required role. |
| `User::hasPermission()` | Checks if user has permission via roles. Admins always return true. |
| Policies | `StudentPolicy`, `TeacherPolicy`, `SchoolClassPolicy`, `SubjectPolicy`, `ScorePolicy` |

**Permission flow:**
```
User -> Assigned Role(s) -> Role has Permissions -> User inherits all permissions
```

**Clearing permission cache:**
```php
\AuthServiceProvider::clearPermissionsCache();
```

## Activity Logging

Automatic audit trail via model observers:

- `UserObserver` -- User CRUD events
- `StudentObserver` -- Student CRUD events
- `TeacherObserver` -- Teacher CRUD events
- `SchoolClassObserver` -- Class CRUD events
- `SubjectObserver` -- Subject CRUD events
- `ScoreObserver` -- Score CRUD events
- `RoleObserver` -- Role CRUD events

Each observer calls `ActivityLogService::logCreate/logUpdate/logDelete()`. The service checks if the user has admin or teacher role before logging. Login/logout events are logged via controller logic.

**Actions tracked:** Create, Update, Delete, Login, Logout, Export, Import, ResetPassword

**Modules tracked:** Students, Teachers, Classes, Subjects, Scores, Users, Roles, Permissions, Reports, Auth, System

## Student ID Generation

PNC-format sequential IDs with per-intake-year counters:

- Format: `PNC{year}-{padded sequence}` (e.g., `PNC2026-001`)
- Uses `student_number_sequences` table with row-level locking (`SELECT ... FOR UPDATE`)
- Guarantees uniqueness even under concurrent requests
- Sequence resets automatically per intake year

## API Endpoints

### Public Routes

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/login` | User login |
| POST | `/api/google-login` | Google OAuth login |
| POST | `/api/forgot-password` | Send reset email |
| POST | `/api/reset-password` | Reset password |
| GET | `/api/chart/grade-distribution` | Grade distribution chart (public) |
| GET | `/api/chart/subject-performance` | Subject performance chart (public) |
| GET | `/api/chart/summary` | Summary chart (public) |
| GET | `/api/chart/trends` | Trends chart (public) |

### Authenticated Routes

| Method | Endpoint | Middleware | Description |
|---|---|---|---|
| POST | `/api/logout` | auth:sanctum | Logout |
| GET | `/api/user` | auth:sanctum | Current user |
| PATCH | `/api/change-password` | auth:sanctum | Change password |
| GET | `/api/profile` | auth:sanctum | View profile |
| PUT | `/api/profile` | auth:sanctum | Update profile |
| POST | `/api/profile/avatar` | auth:sanctum | Upload avatar |

### Student Portal (role:student)

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/student/portal` | Portal dashboard |
| GET | `/api/student/scores` | Student scores |
| GET | `/api/student/transcript` | Transcript data |
| GET | `/api/student/transcript/download` | Download transcript PDF |

### User Management (role:admin)

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/users` | List users |
| GET | `/api/users/roles` | List roles |
| GET | `/api/users/{user}` | Show user |
| POST | `/api/users` | Create user |
| PUT | `/api/users/{user}` | Update user |
| DELETE | `/api/users/{user}` | Delete user |
| POST | `/api/users/bulk-delete` | Bulk delete users |

### Permission & Role Management (role:admin)

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/permissions` | List permissions |
| GET | `/api/roles` | List roles |
| POST | `/api/roles` | Create role |
| PUT | `/api/roles/{role}` | Update role |
| DELETE | `/api/roles/{role}` | Delete role |
| GET | `/api/roles/{role}/permissions` | Get role permissions |
| PUT | `/api/roles/{role}/permissions` | Sync role permissions |
| POST | `/api/roles/{role}/permissions/{permission}` | Grant permission |
| DELETE | `/api/roles/{role}/permissions/{permission}` | Revoke permission |

### Email Domain Rules (role:admin)

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/email-domain-rules` | List rules |
| POST | `/api/email-domain-rules` | Create rule |
| PUT | `/api/email-domain-rules/{rule}` | Update rule |
| DELETE | `/api/email-domain-rules/{rule}` | Delete rule |

### Students (permission-based)

| Method | Endpoint | Permission | Description |
|---|---|---|---|
| GET | `/api/students` | view-students | List students |
| GET | `/api/students/{student}` | view-students | Show student |
| GET | `/api/students/{student}/scores` | view-scores | Student scores |
| POST | `/api/students` | create-students | Create student |
| PUT | `/api/students/{student}` | update-students | Update student |
| PUT | `/api/students/{student}/assign-class` | update-students | Assign class |
| DELETE | `/api/students/{student}` | delete-students | Delete student |
| POST | `/api/students/import` | create-students | Bulk import |
| POST | `/api/students/bulk-delete` | delete-students | Bulk delete |

### Classes (permission-based)

| Method | Endpoint | Permission | Description |
|---|---|---|---|
| GET | `/api/classes` | view-classes | List classes |
| POST | `/api/classes` | create-classes | Create class |
| PUT | `/api/classes/{class}` | update-classes | Update class |
| DELETE | `/api/classes/{class}` | delete-classes | Delete class |

### Subjects (permission-based)

| Method | Endpoint | Permission | Description |
|---|---|---|---|
| GET | `/api/subjects` | view-subjects | List subjects |
| GET | `/api/subjects/{subject}` | view-subjects | Show subject |
| POST | `/api/subjects` | create-subjects | Create subject |
| PUT | `/api/subjects/{subject}` | update-subjects | Update subject |
| DELETE | `/api/subjects/{subject}` | delete-subjects | Delete subject |
| GET | `/api/teachers` | view-teachers | List teachers |

### Subject Terms & Offerings (permission-based)

| Method | Endpoint | Permission | Description |
|---|---|---|---|
| GET | `/api/subject-terms` | view-subjects | List subject-term assignments |
| POST | `/api/subject-terms/sync` | update-subjects | Batch sync |
| PUT | `/api/subject-terms/{subject}` | update-subjects | Sync single subject |
| GET | `/api/subject-offerings` | view-subjects | List offerings |
| GET | `/api/subject-offerings/{offering}/enrollments` | view-scores | Enrollments by offering |

### Scores (permission-based)

| Method | Endpoint | Permission | Description |
|---|---|---|---|
| GET | `/api/scores` | view-scores | List scores |
| GET | `/api/scores/{score}` | view-scores | Show score |
| GET | `/api/scores/by-enrollment/{enrollment}` | view-scores | Score by enrollment |
| POST | `/api/scores` | create-scores | Create score |
| DELETE | `/api/scores/{score}` | delete-scores | Delete score |
| POST | `/api/scores/{score}/details` | create-scores | Add detail |
| PUT | `/api/scores/{score}/details/{detail}` | update-scores | Update detail |
| DELETE | `/api/scores/{score}/details/{detail}` | delete-scores | Delete detail |

### Spreadsheet (Score Sheet) (permission-based)

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/spreadsheet/subjects` | List subjects with offerings |
| GET | `/api/spreadsheet/subject/{subject}/term/{term}` | Score sheet data |
| PUT | `/api/spreadsheet/subject/{subject}/term/{term}/details/{detail}` | Update cell |
| PATCH | `/api/spreadsheet/subject/{subject}/term/{term}/details/{detail}/rename` | Rename column |
| POST | `/api/spreadsheet/subject/{subject}/term/{term}/details` | Add column |
| DELETE | `/api/spreadsheet/subject/{subject}/term/{term}/details/{detail}` | Delete column |
| PATCH | `/api/spreadsheet/subject/{subject}/term/{term}/details/change-type` | Change column type |
| POST | `/api/spreadsheet/subject/{subject}/term/{term}/reorder` | Reorder columns |
| POST | `/api/spreadsheet/subject/{subject}/term/{term}/sync-google` | Push to Google Sheets |
| POST | `/api/spreadsheet/subject/{subject}/term/{term}/import-google` | Import from Google Sheets |
| POST | `/api/spreadsheet/subject/{subject}/term/{term}/import-file` | Import from file |
| PUT | `/api/spreadsheet/weights` | Update assessment weights |
| GET | `/api/spreadsheet/student-numbers` | Student numbers |
| POST | `/api/spreadsheet/subject/{subject}/term/{term}/enrollments` | Add enrollment |
| PUT | `/api/spreadsheet/subject/{subject}/term/{term}/enrollments/{enrollment}` | Update enrollment |
| DELETE | `/api/spreadsheet/subject/{subject}/term/{term}/enrollments/{enrollment}` | Delete enrollment |

### Other Routes

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/dashboard` | Dashboard stats |
| GET | `/api/dashboard/filters` | Dashboard filter options |
| GET | `/api/activity-logs` | Activity logs (admin, teacher) |
| GET | `/api/chart/recent-activity` | Recent activity chart |
| GET | `/api/academic-years` | List academic years |
| GET | `/api/terms` | List terms |
| POST | `/api/terms` | Create term (admin) |
| PUT | `/api/terms/{term}` | Update term (admin) |
| DELETE | `/api/terms/{term}` | Delete term (admin) |
| GET | `/api/generations` | List generations |
| POST | `/api/generations` | Create generation (admin) |
| PUT | `/api/generations/{generation}` | Update generation (admin) |
| DELETE | `/api/generations/{generation}` | Delete generation (admin) |
| GET | `/api/assessment-types` | List assessment types |
| POST | `/api/assessment-types` | Create (admin) |
| PUT | `/api/assessment-types/{type}` | Update (admin) |
| DELETE | `/api/assessment-types/{type}` | Delete (admin) |
| GET | `/api/grade-boundaries` | List grade boundaries |
| PUT | `/api/grade-boundaries/{boundary}` | Update (admin) |
| GET | `/api/report-cards` | List report cards |
| GET | `/api/report-cards/{reportCard}` | Show report card |
| POST | `/api/subject-offerings/{offering}/generate-report-cards` | Generate report cards |
| GET | `/api/transcripts` | List transcripts |
| POST | `/api/students/{student}/generate-transcript` | Generate transcript |

### Google Sheets Integration

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/google-sheets/config` | OAuth config |
| GET | `/api/google-sheets/status` | Connection status |
| POST | `/api/google-sheets/token` | Exchange auth code |
| POST | `/api/google-sheets/refresh` | Refresh token |
| POST | `/api/google-sheets/disconnect` | Disconnect |
| POST | `/api/google-sheets/create` | Create spreadsheet |
| POST | `/api/google-sheets/push` | Push data to Sheets |
| POST | `/api/google-sheets/import` | Import from Sheets |
| POST | `/api/google-sheets/ensure-shared` | Ensure sheet is shared |

## Installation

```bash
git clone https://github.com/G6-PNC-STUDEND-SCORE/G6-PNC-STUDEND-SCORE-BACK.git
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve        # Runs at http://localhost:8000
```

## Available Scripts

| Command | Description |
|---|---|
| `php artisan serve` | Start development server |
| `php artisan migrate` | Run database migrations |
| `php artisan migrate --seed` | Migrate + seed sample data |
| `php artisan db:seed` | Run seeders only |
| `php artisan queue:work` | Process queued jobs |
| `php artisan test` | Run PHPUnit tests |
| `composer dev` | Start all services (server, queue, logs, vite) concurrently |

## Environment Variables

Key variables from `.env.example`:

| Variable | Description | Default |
|---|---|---|
| `APP_NAME` | Application name | G6-PNC-Student-Score |
| `APP_ENV` | Environment | local |
| `APP_DEBUG` | Debug mode | true |
| `FRONTEND_URL` | Frontend URL for CORS/Sanctum | http://localhost:5173 |
| `DB_CONNECTION` | Database driver | mysql |
| `DB_HOST` | Database host | 127.0.0.1 |
| `DB_PORT` | Database port | 3306 |
| `DB_DATABASE` | Database name | student_score_db |
| `DB_USERNAME` | Database user | root |
| `SANCTUM_STATEFUL_DOMAINS` | Sanctum SPA domains | localhost,127.0.0.1,localhost:5173 |
| `CORS_ALLOWED_ORIGINS` | CORS origins | http://localhost:5173,... |
| `SESSION_DRIVER` | Session driver | database |
| `QUEUE_CONNECTION` | Queue driver | database |
| `CACHE_STORE` | Cache driver | database |
| `GOOGLE_CLIENT_ID` | Google OAuth client ID | - |
| `GOOGLE_CLIENT_SECRET` | Google OAuth client secret | - |
| `GOOGLE_REDIRECT_URI` | OAuth redirect URI | - |

## Database Improvements

### Historical Data Tracking

- **student_class_histories** -- Tracks student class transfers across generations
- **subject_offerings** -- Tracks subject-teacher-class assignments per term (replaces direct teacher/class on subjects)
- **student_subject_enrollments** -- Links enrollment to specific offering, inheriting subject/teacher/class/generation/term
- **score_details.max_score** -- Enables proper weighted average calculation
- **score_details.order_number** -- Controls display order for quiz/assignment items
- **scores.generation_id** -- Distinguishes scores across academic years

### Score Calculation Update

Before (simple average):
```
Quiz 1: 8/10, Quiz 2: 18/20 -> (8 + 18) / 2 = 13 -> 90% (incorrect)
```

After (weighted average):
```
Quiz 1: 8/10, Quiz 2: 18/20 -> (8 + 18) / (10 + 20) * 100 = 85% (correct)
```

## Git Workflow

Main branches:
- `master` -- Stable production branch
- `develop` -- Main development branch

```bash
git checkout develop
git pull origin develop
git checkout -b feature/feature-name
# ... work ...
git add . && git commit -m "Add feature"
git push -u origin feature/feature-name
```

Create a Pull Request from **feature/** -> **develop**.
