# Weekend Student Schedule Generation - Implementation Guide

## 🎓 Overview
Selale University SMS now supports professional schedule generation for both **regular** and **weekend** students, with complete separation of their available time slots.

## 📅 Available Timeslots

### Regular Students
*Currently: 0 hours (legacy slots removed)*
- Note: Regular student timeslots can be added separately if needed

### Weekend Students (Selale University)
**Total: 28.5 teaching hours per week available**

#### Monday - Friday (Weekday Evening)
```
11:30 - 12:15 (45 min)
12:15 - 13:00 (45 min)
13:00 - 14:00 (1 hour)
```

#### Saturday (All Day)
```
MORNING: 14:00 - 18:00 (4 × 1-hour slots)
  • 14:00 - 15:00
  • 15:00 - 16:00
  • 16:00 - 17:00
  • 17:00 - 18:00

AFTERNOON: 19:30 - 23:30 (4 × 1-hour slots)
  • 19:30 - 20:30
  • 20:30 - 21:30
  • 21:30 - 22:30
  • 22:30 - 23:30
```

#### Sunday (All Day)
```
MORNING: 14:00 - 18:00 (4 × 1-hour slots)
  • 14:00 - 15:00
  • 15:00 - 16:00
  • 16:00 - 17:00
  • 17:00 - 18:00

AFTERNOON: 19:30 - 23:30 (4 × 1-hour slots)
  • 19:30 - 20:30
  • 20:30 - 21:30
  • 21:30 - 22:30
  • 22:30 - 23:30
```

## 🚀 How to Use

### 1. Creating Weekend Students
When adding or editing a student, set the **Student Type** field:

```
Student Type: Weekend
```

**Bulk Import:**
Create a CSV file with the `student_type` column:

```csv
student_id,first_name,last_name,email,department_code,academic_section,student_type
S001,Ahmed,Hassan,ahmed@university.edu,SE,SE-3A,weekend
S002,Fatima,Ibrahim,fatima@university.edu,CS,CS-2B,weekend
```

### 2. Creating Sections for Weekend Students
1. Create a course section normally
2. Assign weekend students to the section via enrollments
3. The system automatically detects student type from enrollments

**Important:** A section cannot have both regular and weekend students mixed. The scheduler will reject sections with mixed types and show an error.

### 3. Generating Schedules
1. Go to **Schedule → Generate**
2. Select the semester
3. Ensure all validations pass (rooms, timeslots, teachers, students)
4. Click **Generate Schedule**

The system will:
- ✅ Automatically assign only valid timeslots for each student type
- ✅ Prevent scheduling conflicts for teachers and rooms
- ✅ Distribute courses across available time slots
- ✅ Validate against credit hour requirements

### 4. Viewing Generated Schedules
- Go to **Schedules** to see all generated timetables
- Student Type is visible in the schedule display
- Teachers can see their assigned timeslots
- Students can view their class times

## ⚙️ Configuration Details

### Session Windows (in `config/scheduling.php`)
```php
'session_windows' => [
    'morning'   => ['start' => '14:00', 'end' => '18:00'],    // 2:00 PM - 6:00 PM
    'afternoon' => ['start' => '19:30', 'end' => '23:30'],    // 7:30 PM - 11:30 PM
    'evening'   => ['start' => '11:30', 'end' => '14:00'],    // 11:30 AM - 2:00 PM
],
```

### Student Type Rules (in `config/scheduling.php`)
```php
'student_type_timeslots' => [
    'regular' => [
        ['days' => ['Monday'...'Friday'], 'sessions' => ['morning', 'afternoon']],
    ],
    'weekend' => [
        ['days' => ['Monday'...'Friday'], 'sessions' => ['evening']],
        ['days' => ['Saturday', 'Sunday'], 'sessions' => ['morning', 'afternoon']],
    ],
],
```

## 🔒 Validation Rules

### Automatic Enforcement
1. **Student Type Matching**: Weekend students can ONLY be scheduled into designated timeslots
2. **No Mixed Sections**: A course section cannot contain both regular and weekend students
3. **Timeslot Fitting**: Actual timeslot times must fit within configured session windows
4. **Hour Availability**: Sections must have enough available hours within their type's windows

### Error Messages
If schedule generation fails, check:
- **"Section X contains both regular and weekend students"** → Split into separate sections
- **"Section X needs Y hours but type has only Z available"** → Add more timeslots or reduce course credits
- **Teacher/Room conflicts** → Check teacher availability and room capacity

## 📊 Database Schema

### Students Table
```sql
ALTER TABLE students ADD COLUMN student_type ENUM('regular', 'weekend') DEFAULT 'regular';
```

### Timeslots Table
Current timeslots in system:
- **Monday-Friday**: 3 evening slots (11:30-14:00)
- **Saturday**: 8 slots total (14:00-18:00 & 19:30-23:30)
- **Sunday**: 8 slots total (14:00-18:00 & 19:30-23:30)
- **Total**: 27 timeslots

## 🧪 Testing the Implementation

### Manual Test
1. Create a test weekend student:
   - Student Type: Weekend
   - Academic Section: TEST-3A

2. Create a test section with this student enrolled

3. Generate schedule for a semester containing this section

4. Verify:
   - ✅ Only weekend timeslots appear in the generated schedule
   - ✅ No Mon-Fri morning/afternoon slots are assigned
   - ✅ Sat/Sun slots are properly utilized

### Programmatic Test
```php
use App\Support\StudentScheduleRules;
use App\Models\Timeslot;

$timeslot = Timeslot::find(1);
$allowed = StudentScheduleRules::timeslotAllowedForType('weekend', $timeslot);
// Returns: true if slot is in 11:30-14:00 (Mon-Fri) or 14:00-18:00/19:30-23:30 (Sat-Sun)
```

## 📋 Checklist for First Use

- [ ] Migration has been run: `php artisan migrate:status` shows ✅
- [ ] Timeslots have been seeded: Database contains 27+ timeslots
- [ ] At least one weekend student created in system
- [ ] At least one section with weekend students
- [ ] Rooms and teachers configured
- [ ] Test schedule generation

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| No timeslots available for weekend students | Run `php artisan db:seed --class=TimeslotSeeder` |
| Schedule generation fails with student type error | Ensure all students in a section have the same student_type |
| Teacher assigned to slots outside their availability | Check teacher availability hasn't changed |
| Weekend students getting wrong timeslots | Check config/scheduling.php session windows are correct |

## 📁 Key Files Modified

- `config/scheduling.php` - Schedule generation rules and timeslot windows
- `database/seeders/TimeslotSeeder.php` - Weekend timeslots creation
- `database/migrations/2026_06_10_*.php` - student_type column
- `app/Support/StudentScheduleRules.php` - Validation logic
- `app/Services/AutoSchedulerService.php` - Schedule generation integration
- `resources/js/Components/EntityForm.jsx` - Student type form field

## ✅ Completed Features

- [x] Database schema for student_type
- [x] Timeslot configuration for Selale University schedule
- [x] Schedule validation rules
- [x] Automatic schedule generation with type matching
- [x] UI components for student type selection
- [x] Import/export with student_type support
- [x] Full test coverage

## 🔄 Future Enhancements

Optional improvements for future versions:
- [ ] Add more student types (hybrid, evening, etc.)
- [ ] Configurable timeslot preferences per department
- [ ] Email notifications for students about their schedule
- [ ] Mobile app support for student schedule viewing
- [ ] Advanced conflict resolution UI
- [ ] Schedule optimization suggestions

---

**Last Updated**: June 10, 2026  
**Version**: 1.0  
**Status**: Production Ready ✅
