# Quick Start Guide - Weekend Student System

## 🚀 Getting Started (5 Minutes)

### Step 1: Apply Database Migrations
```bash
cd c:\xampp\htdocs\dashboard\sms
php artisan migrate
```

Expected output:
```
Migration table created successfully.
Migrated: 2026_06_10_000001_add_student_type_to_students_table
Migrated: 2026_06_10_000005_add_performance_indexes
...
```

### Step 2: Access the Admin Dashboard
1. Open your browser
2. Navigate to: `http://localhost/dashboard/sms/admin/student-types`
3. Log in with Admin account

### Step 3: Try the API
```bash
# Get all students
curl -X GET http://localhost/api/students \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get student details
curl -X GET http://localhost/api/students/S001 \
  -H "Authorization: Bearer YOUR_TOKEN"

# Update student type
curl -X PUT http://localhost/api/students/S001/type \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"student_type": "weekend"}'
```

---

## 📊 Common Tasks

### Bulk Import Students from CSV

**Method 1: Admin Dashboard** (Recommended for UI-based users)
1. Go to Admin Dashboard → Bulk Operations tab
2. Paste CSV data:
   ```
   S001, weekend
   S002, regular
   S003, weekend
   ```
3. Click "Validate (Dry Run)"
4. Review results
5. Click "Confirm & Execute"

**Method 2: CLI Command** (Recommended for developers)
```bash
php artisan students:migrate-type \
  --from=regular \
  --to=weekend \
  --dry-run

# Execute
php artisan students:migrate-type \
  --from=regular \
  --to=weekend \
  --force
```

**Method 3: API**
```bash
curl -X POST http://localhost/api/students/bulk/import \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "students": [
      {"student_id": "S001", "student_type": "weekend"},
      {"student_id": "S002", "student_type": "regular"}
    ],
    "dry_run": false
  }'
```

### Change a Single Student's Type

**Method 1: Admin Dashboard**
1. Go to Manage Students tab
2. Find the student
3. Click Edit button
4. Select new type
5. Confirm

**Method 2: API**
```bash
curl -X PUT http://localhost/api/students/S001/type \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"student_type": "weekend"}'
```

### View Statistics

**Via Dashboard:**
- Go to Overview tab
- See real-time stats cards

**Via API:**
```bash
curl -X GET http://localhost/api/students/admin/statistics \
  -H "Authorization: Bearer TOKEN"
```

**Via Tinker (Developer Console):**
```bash
php artisan tinker
> Student::where('student_type', 'weekend')->count()
```

---

## 🔍 Verification & Testing

### Verify Installation
```bash
# Check migrations
php artisan migrate:status

# Should show all migrations as "Ran"
```

### Run Test Suite
```bash
# Run all tests
php artisan test

# Run only weekend student tests
php artisan test tests/Feature/WeekendStudentTest.php

# Run specific test
php artisan test tests/Feature/WeekendStudentTest.php --filter=test_weekend_student_type_validation
```

### Check Database Data
```bash
php artisan tinker

# Count students
Student::count()

# Count by type
Student::groupBy('student_type')->count()

# View weekend students
Student::where('student_type', 'weekend')->get()

# View timeslots
Timeslot::count()
```

---

## 📁 Files & Locations

### Backend Files
```
app/
├── Validation/StudentTypeValidator.php
├── Policies/StudentPolicy.php
├── Http/Controllers/Api/StudentTypeController.php
├── Services/
│   ├── ProfessionalScheduleExporter.php
│   └── BackupRecoveryService.php
└── Console/Commands/MigrateStudentTypeCommand.php
```

### Frontend Files
```
resources/js/pages/Admin/
└── StudentTypeAdminDashboard.jsx
```

### Configuration
```
routes/api.php          # API routes
config/imports.php      # Import templates
config/scheduling.php   # Scheduling config
```

### Documentation
```
PROFESSIONAL_DOCUMENTATION.md  # Complete guide
IMPLEMENTATION_SUMMARY.md       # What was built
QUICK_START_GUIDE.md           # This file
```

---

## 🔐 Permissions & Access

### Required Roles
- **Admin**: Full access
- **Registrar**: Can manage students and types
- **Coordinator**: Can view only
- **Teacher**: Can view enrolled students

### Grant Permissions
```bash
php artisan tinker

# Give user admin role
$user = User::find(1);
$user->assignRole('admin');

# Give specific permission
$user->givePermissionTo('modify student types');
```

---

## 🛠 Troubleshooting Quick Fixes

### Issue: API returns 403 Forbidden
```bash
# Check user role
$user->getRoleNames()

# Assign admin role
$user->assignRole('admin')

# Verify permissions
$user->can('modify student types')
```

### Issue: Bulk import fails
```bash
# Verify student exists
Student::where('student_id', 'S001')->exists()

# Check for existing schedules
Schedule::where('section_id', $section->id)->count()

# Remove schedules if needed
Schedule::where('section_id', $section->id)->delete()
```

### Issue: Timeslots not found
```bash
# Check if timeslots exist
Timeslot::count()  # Should be 27

# If missing, reseed
php artisan db:seed --class=TimeslotSeeder

# Or run migration again
php artisan migrate --step
```

### Issue: Slow queries
```bash
# Check indexes
php artisan db:show --table=students

# Optimize
php artisan optimize

# Clear cache
php artisan cache:clear
```

---

## 📈 Monitoring & Maintenance

### Daily
```bash
# Check errors
tail -f storage/logs/laravel.log

# Monitor API
curl http://localhost/api/students/admin/statistics
```

### Weekly
```bash
# Create backup
php artisan backup:create --name=weekly-$(date +%Y-%m-%d)

# View backups
php artisan backup:list
```

### Monthly
```bash
# Check audit logs
php artisan tinker
> AuditLog::latest()->limit(50)->get()

# Clean old logs
php artisan logs:clear
```

---

## 💡 Common Scenarios

### Scenario 1: Convert All SE Department to Weekend
```bash
php artisan students:migrate-type \
  --department=SE \
  --to=weekend \
  --dry-run

# Review results, then execute
php artisan students:migrate-type \
  --department=SE \
  --to=weekend \
  --force
```

### Scenario 2: Add New Weekend Student
```bash
# Via Dashboard
1. Admin → Manage Students
2. Add new student
3. Set type to "weekend"
4. Save

# Via API
curl -X POST http://localhost/api/students \
  -d '{"student_type": "weekend", ...}'
```

### Scenario 3: Generate Schedule for Weekend Students
```bash
php artisan scheduling:generate \
  --type=weekend \
  --dry-run

# Execute
php artisan scheduling:generate --type=weekend
```

### Scenario 4: Export Schedule with Student Types
```bash
# Via API
curl http://localhost/api/schedules/export \
  -H "Authorization: Bearer TOKEN"

# Returns Excel file with student_type column
```

---

## 🎓 Learning Resources

### Read These First
1. `IMPLEMENTATION_SUMMARY.md` - High-level overview
2. `QUICK_START_GUIDE.md` - This file
3. `PROFESSIONAL_DOCUMENTATION.md` - Detailed reference

### Explore the Code
- `tests/Feature/WeekendStudentTest.php` - Examples and best practices
- `app/Validation/StudentTypeValidator.php` - Validation patterns
- `app/Http/Controllers/Api/StudentTypeController.php` - API usage

### Try These Commands
```bash
# List all available commands
php artisan list

# Get help on specific command
php artisan students:migrate-type --help

# Run tinker for exploration
php artisan tinker

# Run tests with verbose output
php artisan test --verbose
```

---

## 🚀 Next Steps

1. ✅ Run migrations (`php artisan migrate`)
2. ✅ Access admin dashboard
3. ✅ Import some test students
4. ✅ Generate schedules
5. ✅ Export and verify student_type appears
6. ✅ Run test suite
7. ✅ Create a backup
8. ✅ Review documentation
9. ✅ Train team members
10. ✅ Deploy to production

---

## 📞 Support

### If Something Breaks
1. Check logs: `tail -f storage/logs/laravel.log`
2. Review troubleshooting section above
3. Check test cases for examples
4. Review documentation
5. Run validation: `php artisan tinker`

### For Detailed Help
- **API Reference**: See `PROFESSIONAL_DOCUMENTATION.md`
- **Database Schema**: See `PROFESSIONAL_DOCUMENTATION.md#Database Schema`
- **Command Line**: Run `php artisan {command} --help`
- **Test Examples**: Check `tests/Feature/WeekendStudentTest.php`

---

**Version**: 1.0.0  
**Quick Start Time**: ~5 minutes  
**Status**: Production Ready ✅
