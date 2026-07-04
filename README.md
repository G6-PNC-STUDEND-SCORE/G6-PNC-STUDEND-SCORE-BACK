# 🎓 Student Score Management System - Backend

## 📖 Overview

This repository contains the backend API for the **Student Score Management System**.

The backend is developed using **Laravel 12** and follows the RESTful API architecture. It provides secure authentication, role-based authorization, student score management, automatic grade calculation, reporting, and dashboard statistics.

The backend communicates with the Vue 3 frontend through REST APIs and stores data in a MySQL database.

---

# 🚀 Features

## Authentication

* User Login
* User Logout
* Password Reset
* Profile Update
* Avatar Upload
* Laravel Sanctum Authentication

## User Management

* Admin Management
* Teacher Management
* Role-Based Authorization

## Class Management

* Create Class
* Update Class
* Delete Class
* Assign Teacher

## Subject Management

* Create Subject
* Update Subject
* Delete Subject

## Student Management

* Create Student
* Update Student
* Delete Student
* Assign Student to Class

## Score Management

* Quiz Score
* Assignment Score
* Midterm Score
* Final Score
* Automatic Grade Calculation

## Reports

* Student Report Card
* Class Performance
* Student Ranking
* Dashboard Statistics

---

# 🛠 Technology Stack

| Technology      | Purpose              |
| --------------- | -------------------- |
| Laravel 12      | Backend Framework    |
| PHP 8.3+        | Programming Language |
| MySQL           | Database             |
| Laravel Sanctum | Authentication       |
| Eloquent ORM    | Database ORM         |
| REST API        | API Architecture     |

---

# 📁 Project Structure

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── API/
│   │       ├── AuthController.php
│   │       ├── UserController.php
│   │       ├── ClassController.php
│   │       ├── SubjectController.php
│   │       ├── StudentController.php
│   │       ├── ScoreController.php
│   │       └── ReportController.php
│   │
│   ├── Requests/
│   │   └── API/
│   │
│   └── Middleware/
│
├── Models/
│
├── Services/
│   └── GradeService.php
│
├── Policies/
│
├── Providers/
│
routes/
├── api.php
└── web.php

database/
├── migrations/
├── seeders/
└── factories/
```

---

# 🗄 Database Tables

## users

| Column     | Type                 | Key      |
| ---------- | -------------------- | -------- |
| id         | bigint               | PK       |
| name       | string               |          |
| email      | string               | Unique   |
| password   | string               |          |
| role       | enum(admin, teacher) |          |
| avatar     | string               | Nullable |
| created_at | timestamp            |          |
| updated_at | timestamp            |          |

---

## classes

| Column     | Type      | Key           |
| ---------- | --------- | ------------- |
| id         | bigint    | PK            |
| name       | string    |               |
| teacher_id | bigint    | FK → users.id |
| created_at | timestamp |               |
| updated_at | timestamp |               |

---

## subjects

| Column     | Type      | Key |
| ---------- | --------- | --- |
| id         | bigint    | PK  |
| name       | string    |     |
| created_at | timestamp |     |
| updated_at | timestamp |     |

---

## students

| Column     | Type               | Key             |
| ---------- | ------------------ | --------------- |
| id         | bigint             | PK              |
| class_id   | bigint             | FK → classes.id |
| name       | string             |                 |
| photo      | string             | Nullable        |
| gender     | enum(Male, Female) |                 |
| created_at | timestamp          |                 |
| updated_at | timestamp          |                 |

---

## scores

| Column     | Type      | Key              |
| ---------- | --------- | ---------------- |
| id         | bigint    | PK               |
| student_id | bigint    | FK → students.id |
| subject_id | bigint    | FK → subjects.id |
| quiz       | decimal   |                  |
| assignment | decimal   |                  |
| midterm    | decimal   |                  |
| final      | decimal   |                  |
| total      | decimal   |                  |
| grade      | string    |                  |
| created_at | timestamp |                  |
| updated_at | timestamp |                  |

---

## grade_rules

| Column    | Type    | Key |
| --------- | ------- | --- |
| id        | bigint  | PK  |
| min_score | decimal |     |
| max_score | decimal |     |
| grade     | string  |     |

---

# 🔑 Primary Keys

| Table       | Primary Key |
| ----------- | ----------- |
| users       | id          |
| classes     | id          |
| subjects    | id          |
| students    | id          |
| scores      | id          |
| grade_rules | id          |

---

# 🔗 Foreign Keys

| Table    | Foreign Key | References  |
| -------- | ----------- | ----------- |
| classes  | teacher_id  | users.id    |
| students | class_id    | classes.id  |
| scores   | student_id  | students.id |
| scores   | subject_id  | subjects.id |

---

# 📊 Entity Relationship Diagram (ERD)

```text
                    USERS
                  +---------+
                  | id (PK) |
                  +---------+
                       |
                       | 1
                       |
                       | N
                  +------------+
                  |  CLASSES   |
                  |------------|
                  | id (PK)    |
                  | teacher_id |
                  +------------+
                       |
                       | 1
                       |
                       | N
                  +------------+
                  | STUDENTS   |
                  |------------|
                  | id (PK)    |
                  | class_id   |
                  +------------+
                       |
                       | 1
                       |
                       | N
                  +------------+
                  |  SCORES    |
                  |------------|
                  | id (PK)    |
                  | student_id |
                  | subject_id |
                  +------------+
                       ^
                       |
                       | N
                       |
                  +------------+
                  | SUBJECTS   |
                  |------------|
                  | id (PK)    |
                  +------------+
```

---

# 🔄 Database Relationships

| Relationship    | Type        |
| --------------- | ----------- |
| User → Class    | One to Many |
| Class → Student | One to Many |
| Student → Score | One to Many |
| Subject → Score | One to Many |

---

# 🧮 Grade Calculation

```text
Final Score =
(Quiz × 20%)
+ (Assignment × 10%)
+ (Midterm × 30%)
+ (Final × 40%)
```

The backend automatically calculates:

* Total Score
* Average Score
* Grade (A–F)
* Pass / Fail

using the `GradeService`.

---

# 🔐 Authentication

* Laravel Sanctum
* Role-Based Authorization
* API Token Authentication
* Password Hashing
* Request Validation
* Protected API Routes

---

# 📡 Main API Endpoints

| Method | Endpoint             | Description    |
| ------ | -------------------- | -------------- |
| POST   | /api/login           | User Login     |
| POST   | /api/logout          | User Logout    |
| GET    | /api/classes         | Get Classes    |
| POST   | /api/classes         | Create Class   |
| GET    | /api/students        | Get Students   |
| POST   | /api/students        | Create Student |
| GET    | /api/subjects        | Get Subjects   |
| POST   | /api/scores          | Create Score   |
| PUT    | /api/scores/{id}     | Update Score   |
| DELETE | /api/scores/{id}     | Delete Score   |
| GET    | /api/reports/{class} | Class Report   |

---

# ⚙️ Installation

Clone the repository

```bash
git clone https://github.com/G6-PNC-STUDEND-SCORE/G6-PNC-STUDEND-SCORE-BACK.git
```

Install dependencies

```bash
composer install
```

Create environment file

```bash
cp .env.example .env
```

Generate application key

```bash
php artisan key:generate
```

Configure your MySQL database in the `.env` file.

Run migrations

```bash
php artisan migrate
```

Start the development server

```bash
php artisan serve
```

---

# 🌿 Git Workflow

Main branches

* `master`
* `develop`

Feature workflow

```bash
git checkout develop
git pull origin develop

git checkout -b feature/feature-name

git add .
git commit -m "Add feature"

git push -u origin feature/feature-name
```

Create a Pull Request from **feature/** → **develop**.

---

# 👥 Team

**Project:** Student Score Management System

**Backend Repository**

* Framework: Laravel 12
* Database: MySQL
* Authentication: Laravel Sanctum
* API: RESTful API
* Methodology: Agile Scrum
