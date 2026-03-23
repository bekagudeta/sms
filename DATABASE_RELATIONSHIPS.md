## Database Schema Overview

### Users Table
**Attributes**: 
- id (PK)
- name
- email (Unique)
- email_verified_at (Nullable)
- password
- role (Enum: admin, scheduler, teacher, student)
- remember_token
- created_at, updated_at

**Relations**: 
- → Teachers (1:1 via user_id)
- → Students (1:1 via user_id)

### Teachers Table
**Attributes**: 
- id (PK)
- teacher_id (Unique)
- first_name
- last_name
- email (Unique)
- phone (Nullable)
- department_id (FK → departments.id)
- qualification (Nullable)
- max_hours_per_week (Default: 20)
- created_at, updated_at

**Relations**: 
- ← User (1:1)
- ← Department (Many:1)
- ↔ Sections (Many:Many via section_teachers)

### Students Table
**Attributes**: 
- id (PK)
- student_id (Unique)
- first_name
- last_name
- email (Unique)
- phone (Nullable)
- department_id (FK → departments.id)
- semester
- enrollment_date
- created_at, updated_at

**Relations**: 
- ← User (1:1)
- ← Department (Many:1)
- ↔ Sections (Many:Many via enrollments)

### Departments Table
**Attributes**: 
- id (PK)
- code (Unique)
- name
- description (Nullable)
- created_at, updated_at

**Relations**: 
- → Teachers (1:Many)
- → Students (1:Many)
- → Courses (1:Many)

### Courses Table
**Attributes**: 
- id (PK)
- course_code (Unique)
- course_name
- description (Nullable)
- credits
- hours_per_week
- department_id (FK → departments.id)
- semester_id (FK → semesters.id)
- teacher_id (FK → teachers.id, Nullable)
- level (Enum: undergraduate, graduate, diploma)
- created_at, updated_at

**Relations**: 
- → CourseOfferings (1:Many)
- ← Department (Many:1)
- ← Semester (Many:1)
- ← Teacher (Many:1, Nullable)

### Semesters Table
**Attributes**: 
- id (PK)
- name
- code (Unique)
- start_date
- end_date
- is_active (Default: false)
- created_at, updated_at

**Relations**: 
- → CourseOfferings (1:Many)
- → Courses (1:Many)

### CourseOfferings Table
**Attributes**: 
- id (PK)
- course_id (FK → courses.id, Cascade)
- semester_id (FK → semesters.id, Cascade)
- expected_students
- created_at, updated_at

**Constraints**: 
- Unique: [course_id, semester_id]

**Relations**: 
- → Sections (1:Many)
- ← Course (Many:1)
- ← Semester (Many:1)

### Sections Table
**Attributes**: 
- id (PK)
- course_offering_id (FK → course_offerings.id, Cascade)
- section_name
- capacity
- created_at, updated_at

**Constraints**: 
- Unique: [course_offering_id, section_name]

**Relations**: 
- ↔ Teachers (Many:Many via section_teachers)
- ↔ Students (Many:Many via enrollments)
- → Schedules (1:Many)
- ← CourseOffering (Many:1)

### Rooms Table
**Attributes**: 
- id (PK)
- room_code (Unique)
- building
- floor
- capacity
- type (Enum: lecture, lab, seminar, conference)
- has_projector (Default: false)
- has_computers (Default: false)
- computer_count (Default: 0)
- created_at, updated_at

**Relations**: 
- → Schedules (1:Many)

### Timeslots Table
**Attributes**: 
- id (PK)
- day_of_week
- start_time
- end_time
- slot_code (Unique)
- created_at, updated_at

**Relations**: 
- → Schedules (1:Many)

### Schedules Table
**Attributes**: 
- id (PK)
- section_id (FK → sections.id, Cascade)
- room_id (FK → rooms.id, Cascade)
- timeslot_id (FK → timeslots.id, Cascade)
- created_at, updated_at

**Constraints**: 
- Unique: [room_id, timeslot_id]

**Relations**: 
- ← Section (Many:1)
- ← Room (Many:1)
- ← Timeslot (Many:1)

## Pivot Tables

### section_teachers
**Attributes**: 
- id (PK)
- section_id (FK → sections.id, Cascade)
- teacher_id (FK → teachers.id, Cascade)
- created_at, updated_at

**Constraints**: 
- Unique: [section_id, teacher_id]

**Purpose**: Teachers ↔ Sections (Many:Many)

### enrollments
**Attributes**: 
- id (PK)
- student_id (FK → students.id, Cascade)
- section_id (FK → sections.id, Cascade)
- enrolled_at
- created_at, updated_at

**Constraints**: 
- Unique: [student_id, section_id]

**Purpose**: Students ↔ Sections (Many:Many)
