# Weekend Student Scheduling System - Complete Documentation

## Table of Contents
1. [Overview](#overview)
2. [API Reference](#api-reference)
3. [Admin Dashboard](#admin-dashboard)
4. [Bulk Operations](#bulk-operations)
5. [Database Schema](#database-schema)
6. [Backup & Recovery](#backup--recovery)
7. [Troubleshooting](#troubleshooting)

---

## Overview

The Weekend Student Scheduling System is a professional, production-grade solution for managing students who take classes during weekday evenings and weekends. This system integrates seamlessly with the existing course management system.

### Key Features
- ✅ Two student types: **Regular** and **Weekend**
- ✅ Automatic schedule generation with type-specific timeslots
- ✅ Comprehensive validation and error handling
- ✅ RESTful API for integration
- ✅ Role-based access control
- ✅ Audit logging for compliance
- ✅ Backup and recovery tools
- ✅ Professional export formats

### Student Type Definitions

| Aspect | Regular | Weekend |
|--------|---------|---------|
| **Available Days** | Monday - Friday | Weekday Evenings + Saturday + Sunday |
| **Timeslots** | 11:30 AM - 2:00 PM | Mon-Fri 11:30-14:00 + Sat-Sun 14:00-23:30 |
| **Weekly Hours** | 0 (for schedule validation) | 28.5 hours max |
| **Session Windows** | Evening (11:30-14:00) | Evening + Morning + Afternoon |

---

## API Reference

### Authentication
All API endpoints require Bearer token authentication:
```bash
Authorization: Bearer {token}
```

### Base URL
```
/api/students
```

### Endpoints

#### 1. List Students
```http
GET /api/students
```

**Parameters:**
- `type` (optional): Filter by type (regular|weekend)
- `department` (optional): Filter by department ID
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 50)

**Response:**
```json
{
  "success": true,
  "message": "Students retrieved successfully",
  "data": [
    {
      "id": 1,
      "student_id": "S001",
      "first_name": "John",
      "last_name": "Doe",
      "student_type": "weekend",
      "academic_section": "SE-3A",
      "schedules_count": 5,
      "enrollments_count": 3
    }
  ],
  "pagination": {
    "total": 150,
    "per_page": 50,
    "current_page": 1,
    "last_page": 3
  }
}
```

#### 2. Get Student Details
```http
GET /api/students/{student_id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "student_id": "S001",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "student_type": "weekend",
    "schedules_count": 5,
    "enrollments_count": 3
  }
}
```

#### 3. Update Student Type
```http
PUT /api/students/{student_id}/type
```

**Request Body:**
```json
{
  "student_type": "weekend",
  "force": false
}
```

**Response:**
```json
{
  "success": true,
  "message": "Student type updated successfully",
  "data": {
    "student_id": "S001",
    "old_type": "regular",
    "new_type": "weekend"
  },
  "warnings": []
}
```

**Error Responses (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": [
    "Cannot change student type: Student S001 has existing schedules"
  ],
  "warnings": []
}
```

#### 4. Bulk Import Students
```http
POST /api/students/bulk/import
```

**Request Body (Dry Run):**
```json
{
  "students": [
    {"student_id": "S001", "student_type": "weekend"},
    {"student_id": "S002", "student_type": "regular"},
    {"student_id": "S003", "student_type": "weekend"}
  ],
  "dry_run": true
}
```

**Dry Run Response:**
```json
{
  "success": true,
  "message": "Bulk import validation completed (dry run)",
  "dry_run": true,
  "validation_results": {
    "total": 3,
    "valid": 2,
    "invalid": 1,
    "warnings": 0,
    "errors": [
      {
        "student_id": "INVALID",
        "error": "Student not found in system"
      }
    ]
  }
}
```

**Request Body (Execute):**
```json
{
  "students": [...],
  "dry_run": false
}
```

#### 5. Get Statistics
```http
GET /api/students/admin/statistics
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_students": 450,
    "regular_students": 320,
    "weekend_students": 130,
    "by_department": [
      {
        "department_id": "SE",
        "total": 150,
        "regular": 100,
        "weekend": 50
      }
    ]
  }
}
```

#### 6. Get Available Timeslots for Type
```http
GET /api/students/timeslots/{student_type}
```

**Response:**
```json
{
  "success": true,
  "student_type": "weekend",
  "available_timeslots": 27,
  "total_timeslots": 27,
  "timeslots": [
    {
      "id": 1,
      "day": "Monday",
      "session": "evening",
      "start_time": "11:30",
      "end_time": "12:15",
      "duration_minutes": 45
    }
  ]
}
```

---

## Admin Dashboard

### Accessing the Dashboard
Navigate to `/admin/student-types` in the web application.

### Features

#### Overview Tab
- **Statistics Cards**: Show total, regular, and weekend student counts
- **Department Distribution**: Table showing student type breakdown by department
- **Level Distribution**: Breakdown by academic level

#### Manage Students Tab
- **Search & Filter**: Find students by ID, name, type, or department
- **Edit Individual**: Change a single student's type with validation
- **Export**: Download student list with type information
- **Bulk Update**: Access bulk import/export features

#### Bulk Operations Tab

**Workflow:**
1. Enter student data in CSV format (student_id, type)
2. Click "Validate (Dry Run)" to check for errors
3. Review validation results
4. Click "Confirm & Execute" to apply changes

**CSV Format Example:**
```
S001, weekend
S002, regular
S003, weekend
S004, regular
```

**Validation Results Display:**
- Total: Number of records submitted
- Valid: Successfully validated students
- Invalid: Students with errors
- Warnings: Students with potential issues

---

## Bulk Operations

### Command Line Bulk Migration

#### Migrate all regular students to weekend
```bash
php artisan students:migrate-type --from=regular --to=weekend --dry-run
php artisan students:migrate-type --from=regular --to=weekend --force
```

#### Migrate specific section
```bash
php artisan students:migrate-type --section=SE-3A --to=weekend --dry-run
php artisan students:migrate-type --section=SE-3A --to=weekend --force
```

#### Migrate department with filter
```bash
php artisan students:migrate-type --department=SE --to=weekend --filter="level=300" --dry-run
```

#### Options
- `--from`: Current type (regular|weekend)
- `--to`: Target type (regular|weekend) - default: weekend
- `--section`: Specific academic section
- `--department`: Department code
- `--filter`: Additional where clause (e.g., "level=300")
- `--dry-run`: Preview changes without executing
- `--force`: Skip confirmation prompts

---

## Database Schema

### Key Tables

#### Students Table
```sql
CREATE TABLE students (
  id BIGINT PRIMARY KEY,
  student_id VARCHAR(255) UNIQUE NOT NULL,
  first_name VARCHAR(255),
  last_name VARCHAR(255),
  email VARCHAR(255),
  phone VARCHAR(20),
  department_id BIGINT,
  academic_section VARCHAR(255),
  student_type ENUM('regular', 'weekend') DEFAULT 'regular',
  level INT,
  status VARCHAR(255),
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  INDEX idx_student_type (student_type),
  INDEX idx_student_type_section (student_type, academic_section),
  INDEX idx_dept_student_type (department_id, student_type)
);
```

#### Timeslots Table
```sql
CREATE TABLE timeslots (
  id BIGINT PRIMARY KEY,
  day VARCHAR(50),
  session VARCHAR(50), -- evening, morning, afternoon
  start_time TIME,
  end_time TIME,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  INDEX idx_timeslot_day (day),
  INDEX idx_timeslot_session (session)
);
```

#### Audit Logs Table
```sql
CREATE TABLE audit_logs (
  id BIGINT PRIMARY KEY,
  user_id BIGINT,
  action VARCHAR(255), -- created, updated, deleted
  model VARCHAR(255),
  model_id BIGINT,
  changes JSON,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_model (model, model_id),
  INDEX idx_user_id (user_id),
  INDEX idx_created_at (created_at)
);
```

### Indexes for Performance
- `idx_student_type`: Fast filtering by type
- `idx_student_type_section`: Combined queries
- `idx_dept_student_type`: Department-level reporting
- `idx_schedule_section`: Schedule lookups
- `idx_timeslot_day`: Timeslot availability

---

## Backup & Recovery

### Backup Operations

#### Create a full backup
```bash
php artisan backup:create --name=pre-migration-backup
```

#### List available backups
```bash
php artisan backup:list
```

#### Restore from backup (dry run)
```bash
php artisan backup:restore --backup=pre-migration-backup --dry-run
```

#### Restore from backup (execute)
```bash
php artisan backup:restore --backup=pre-migration-backup --force
```

#### Recover to point in time
```bash
php artisan backup:recover-pit --timestamp="2026-06-10 14:30:00"
```

#### Get audit trail for a student
```bash
php artisan backup:audit-trail --student-id=1
```

### Via API (Admin Only)

#### Create backup
```http
POST /api/admin/backups
```

#### List backups
```http
GET /api/admin/backups
```

#### Restore backup
```http
POST /api/admin/backups/{backup_name}/restore
```

---

## Troubleshooting

### Common Issues

#### Error: "Cannot change student type: Student has existing schedules"
**Cause**: Student already has scheduled classes.
**Solution**: 
1. Remove the student's existing schedules first
2. Or use `--force` flag with migration command to override

#### Error: "Invalid student type: [type]"
**Cause**: Student type is not 'regular' or 'weekend'.
**Solution**: Check the input data for typos.

#### Bulk import shows "Student not found"
**Cause**: Student ID doesn't exist in the system.
**Solution**: 
1. Verify student ID spelling
2. Check if student has been deleted
3. Ensure student exists before import

#### Schedule generation fails for weekend students
**Cause**: No timeslots configured for weekend.
**Solution**:
1. Verify timeslots were created: `php artisan tinker`
2. Run: `Timeslot::count()` 
3. Should return 27 timeslots
4. If missing, re-run migration: `php artisan migrate --step`

### Debug Commands

#### Check migration status
```bash
php artisan migrate:status
```

#### Run tinker to inspect data
```bash
php artisan tinker

# Check student types
Student::value('student_type')->groupBy('student_type')->count()

# Check timeslots
Timeslot::count()

# Check schedules
Schedule::count()
```

#### Validate all students
```bash
php artisan students:validate-types
```

#### View audit logs
```bash
php artisan audit:view --model=Student --limit=20
```

---

## Permissions

### Required Permissions by Role

#### Admin
- ✅ View all students and types
- ✅ Modify student types
- ✅ Bulk operations
- ✅ View audit logs
- ✅ Create/restore backups

#### Registrar
- ✅ View all students
- ✅ Modify student types
- ✅ Bulk operations
- ✅ Export data
- ⛔ Create backups (admin only)

#### Academic Coordinator
- ✅ View students in department
- ✅ View schedules
- ✅ Export department data
- ⛔ Modify types

#### Teacher
- ✅ View enrolled students
- ✅ View student types
- ✅ View schedules
- ⛔ Modify types

---

## Support & Maintenance

### Regular Maintenance Tasks

1. **Weekly**: Review audit logs for anomalies
2. **Monthly**: Create backup for archives
3. **Quarterly**: Review and optimize database indexes
4. **Annually**: Audit user permissions and access

### Performance Monitoring

Monitor query performance:
```bash
php artisan command:monitor-queries --threshold=1000ms
```

View slow queries:
```bash
php artisan logs:slow-queries --limit=20
```

---

## FAQ

**Q: Can I change a student's type after they're scheduled?**
A: The system prevents this by default to avoid conflicts. Use `--force` flag carefully.

**Q: What happens to existing schedules if I migrate a student type?**
A: Schedules are preserved. The student's type is updated, but schedule validity may need re-validation.

**Q: How many weekend slots are available per week?**
A: Weekend students have access to 28.5 hours per week across 27 timeslots.

**Q: Can regular and weekend students be in the same section?**
A: The system validates against mixed types in sections and will reject schedule generation for mixed sections.

---

**Version**: 1.0.0  
**Last Updated**: 2026-06-10  
**Status**: Production Ready
