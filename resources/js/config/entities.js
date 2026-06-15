export const ENTITY_CONFIG = {
  students: {
    title: "Students",
    singular: "Student",
    icon: "Users",
    category: "People",
    columns: [
      { key: "id", label: "ID", sortable: true },
      { key: "student_id", label: "Student ID", sortable: true, searchable: true },
      { key: "first_name", label: "First Name", sortable: true, searchable: true },
      { key: "last_name", label: "Last Name", sortable: true, searchable: true },
      { key: "email", label: "Email", sortable: true, searchable: true },
      { key: "department.name", label: "Department", sortable: true, searchable: true },
      { key: "level", label: "Level", sortable: true },
      { key: "academic_section", label: "Academic Section", sortable: true, searchable: true },
      { key: "student_type", label: "Student Type", sortable: true, filterable: true },
      { key: "status", label: "Status", sortable: true, filterable: true },
      { key: "enrollment_date", label: "Enrolled", sortable: true },
      { key: "phone", label: "Phone", sortable: true }
    ],
    requiredColumns: ["student_id", "first_name", "last_name", "email", "department_code", "academic_section"],
    optionalColumns: ["student_type", "level", "phone", "enrollment_date", "department_id", "status"],
    // Fields for the manual Add/Edit form (decoupled from import columns).
    // These mirror the server validation rules in EntityController::getValidationRules('students').
    formFields: [
      { name: "student_id", label: "Student ID", type: "text", required: true, maxLength: 50, placeholder: "e.g. S001" },
      { name: "first_name", label: "First Name", type: "text", required: true, maxLength: 100 },
      { name: "last_name", label: "Last Name", type: "text", required: true, maxLength: 100 },
      { name: "email", label: "Email Address", type: "email", required: true, maxLength: 255, placeholder: "name@student.university.edu" },
      { name: "department_id", label: "Department", type: "select", required: true, optionsKey: "departments" },
      { name: "academic_section", label: "Academic Section", type: "text", required: true, maxLength: 50, placeholder: "Cohort, e.g. SE-3A" },
      { name: "level", label: "Level", type: "select", required: false, optionsKey: "levels", options: [
        { value: "bachelor", label: "Bachelor" },
        { value: "master", label: "Master" },
        { value: "phd", label: "PhD" },
        { value: "diploma", label: "Diploma" },
        { value: "certificate", label: "Certificate" },
      ] },
      { name: "student_type", label: "Student Type", type: "select", required: false, options: [
        { value: "regular", label: "Regular" },
        { value: "weekend", label: "Weekend" },
      ] },
      { name: "status", label: "Status", type: "select", required: false, options: [
        { value: "active", label: "Active" },
        { value: "inactive", label: "Inactive" },
        { value: "pending", label: "Pending" },
        { value: "graduated", label: "Graduated" },
        { value: "suspended", label: "Suspended" },
      ] },
      { name: "phone", label: "Phone", type: "tel", required: false, maxLength: 20, pattern: "^[0-9+()\\-\\s]{7,20}$", patternMessage: "Enter a valid phone number" },
      { name: "enrollment_date", label: "Enrollment Date", type: "date", required: false },
    ],
    apiEndpoint: "/api/students",
    routePrefix: "students",
    permissions: {
      view: "view students",
      create: "create students", 
      edit: "edit students",
      delete: "delete students",
      import: "import students"
    }
  },
  teachers: {
    title: "Teachers",
    singular: "Teacher", 
    icon: "UserCheck",
    category: "People",
    columns: [
      { key: "id", label: "ID", sortable: true },
      { key: "teacher_id", label: "Teacher ID", sortable: true, searchable: true },
      { key: "first_name", label: "First Name", sortable: true, searchable: true },
      { key: "last_name", label: "Last Name", sortable: true, searchable: true },
      { key: "email", label: "Email", sortable: true, searchable: true },
      { key: "department.name", label: "Department", sortable: true, searchable: true },
      { key: "qualification", label: "Qualification", sortable: true },
      { key: "specialization", label: "Specialization", sortable: true, searchable: true },
      { key: "max_hours_per_week", label: "Max Hours", sortable: true },
      { key: "phone", label: "Phone", sortable: true }
    ],
    requiredColumns: ["teacher_id", "first_name", "last_name", "email", "department_id"],
    optionalColumns: ["qualification", "specialization", "phone", "max_hours_per_week"],
    // Manual Add/Edit form fields — mirror EntityController::getValidationRules('teachers').
    formFields: [
      { name: "teacher_id", label: "Teacher ID", type: "text", required: true, maxLength: 50, placeholder: "e.g. T001" },
      { name: "first_name", label: "First Name", type: "text", required: true, maxLength: 100 },
      { name: "last_name", label: "Last Name", type: "text", required: true, maxLength: 100 },
      { name: "email", label: "Email Address", type: "email", required: true, maxLength: 255, placeholder: "name@university.edu" },
      { name: "department_id", label: "Department", type: "select", required: true, optionsKey: "departments" },
      { name: "max_hours_per_week", label: "Max Hours per Week", type: "number", required: true, min: 1, max: 38, integer: true },
      { name: "qualification", label: "Qualification", type: "select", required: false, options: [
        { value: "BSc", label: "BSc" },
        { value: "MSc", label: "MSc" },
        { value: "PhD", label: "PhD" },
        { value: "MBA", label: "MBA" },
        { value: "MA", label: "MA" },
        { value: "EdD", label: "EdD" },
        { value: "Other", label: "Other" },
      ] },
      { name: "specialization", label: "Specialization", type: "text", required: false, maxLength: 255 },
      { name: "phone", label: "Phone", type: "tel", required: false, maxLength: 20, pattern: "^[0-9+()\\-\\s]{7,20}$", patternMessage: "Enter a valid phone number" },
    ],
    apiEndpoint: "/api/teachers",
    routePrefix: "teachers",
    permissions: {
      view: "view teachers",
      create: "create teachers",
      edit: "edit teachers", 
      delete: "delete teachers",
      import: "import teachers"
    }
  },
  courses: {
    title: "Courses",
    singular: "Course",
    icon: "BookOpen",
    category: "Academics",
    columns: [
      { key: "id", label: "ID", sortable: true },
      { key: "course_code", label: "Code", sortable: true, searchable: true },
      { key: "course_name", label: "Name", sortable: true, searchable: true },
      { key: "credits", label: "Credits", sortable: true },
      { key: "hours_per_week", label: "Hours / Week", sortable: true },
      { key: "level", label: "Level", sortable: true, filterable: true },
      { key: "required_room_type", label: "Room Type", sortable: true, filterable: true },
      { key: "department.name", label: "Department", sortable: true, filterable: true }
    ],
    requiredColumns: ["course_code", "course_name", "credits", "hours_per_week"],
    optionalColumns: ["description", "department_id", "department_code", "department_name", "level", "required_room_type"],
    // Manual Add/Edit form fields — mirror EntityController::getValidationRules('courses').
    formFields: [
      { name: "course_code", label: "Course Code", type: "text", required: true, maxLength: 50, placeholder: "e.g. CS101" },
      { name: "course_name", label: "Course Name", type: "text", required: true, maxLength: 255 },
      { name: "credits", label: "Credits", type: "number", required: true, min: 1, max: 38, integer: true },
      { name: "hours_per_week", label: "Hours per Week", type: "number", required: true, min: 1, max: 38, integer: true },
      { name: "department_id", label: "Department", type: "select", required: false, optionsKey: "departments" },
      { name: "level", label: "Level", type: "select", required: false, optionsKey: "levels", options: [
        { value: "undergraduate", label: "Undergraduate" },
        { value: "graduate", label: "Graduate" },
        { value: "certificate", label: "Certificate" },
        { value: "professional", label: "Professional" },
      ] },
      { name: "required_room_type", label: "Required Room Type", type: "select", required: false, optionsKey: "roomTypes", options: [
        { value: "lecture", label: "Lecture" },
        { value: "lab", label: "Laboratory" },
        { value: "seminar", label: "Seminar" },
        { value: "conference", label: "Conference" },
        { value: "studio", label: "Studio" },
      ] },
      { name: "description", label: "Description", type: "textarea", required: false },
    ],
    apiEndpoint: "/api/courses",
    routePrefix: "courses",
    permissions: {
      view: "view courses",
      create: "create courses",
      edit: "edit courses",
      delete: "delete courses",
      import: "import courses"
    }
  },
  departments: {
    title: "Departments",
    singular: "Department",
    icon: "Archive",
    category: "Academics",
    columns: [
      { key: "id", label: "ID", sortable: true },
      { key: "code", label: "Code", sortable: true, searchable: true },
      { key: "name", label: "Name", sortable: true, searchable: true },
      { key: "description", label: "Description", sortable: false }
    ],
    requiredColumns: ["code", "name"],
    optionalColumns: ["description"],
    // Manual Add/Edit form fields — mirror EntityController::getValidationRules('departments').
    formFields: [
      { name: "code", label: "Code", type: "text", required: true, maxLength: 50, placeholder: "e.g. CS" },
      { name: "name", label: "Name", type: "text", required: true, maxLength: 255 },
      { name: "description", label: "Description", type: "textarea", required: false },
    ],
    apiEndpoint: "/api/departments",
    routePrefix: "departments",
    permissions: {
      view: "view departments",
      create: "create departments",
      edit: "edit departments",
      delete: "delete departments",
      import: "import departments"
    }
  },
  semesters: {
    title: "Semesters",
    singular: "Semester",
    icon: "Calendar",
    category: "Academics",
    columns: [
      { key: "id", label: "ID", sortable: true },
      { key: "name", label: "Name", sortable: true, searchable: true },
      { key: "code", label: "Code", sortable: true, searchable: true },
      {
        key: "academic_year",
        label: "Academic Year",
        sortable: true,
        searchable: true,
        render: (value, item) => value || item.resolved_academic_year || "—",
      },
      { key: "start_date", label: "Start Date", sortable: true },
      { key: "end_date", label: "End Date", sortable: true },
      { key: "is_active", label: "Active", sortable: true }
    ],
    requiredColumns: ["name", "code"],
    optionalColumns: ["academic_year", "start_date", "end_date", "is_active"],
    // Manual Add/Edit form fields — mirror EntityController::getValidationRules('semesters').
    formFields: [
      { name: "name", label: "Name", type: "text", required: true, maxLength: 255, placeholder: "e.g. Fall 2024" },
      { name: "code", label: "Code", type: "text", required: true, maxLength: 50, placeholder: "e.g. FA24" },
      { name: "academic_year", label: "Academic Year", type: "text", required: false, maxLength: 20, placeholder: "e.g. 2024-2025 or 2024" },
      { name: "start_date", label: "Start Date", type: "date", required: false },
      { name: "end_date", label: "End Date", type: "date", required: false },
      { name: "is_active", label: "Is Active", type: "checkbox", required: false },
    ],
    apiEndpoint: "/api/semesters",
    routePrefix: "semesters",
    permissions: {
      view: "view semesters",
      create: "create semesters",
      edit: "edit semesters",
      delete: "delete semesters",
      import: "import semesters"
    }
  },
  "course-offerings": {
    title: "Course Offerings",
    singular: "Offering",
    icon: "Calendar",
    category: "Academics", 
    columns: [
      { key: "id", label: "ID", sortable: true },
      { key: "course.course_code", label: "Course", sortable: true, searchable: true },
      { key: "semester.name", label: "Semester", sortable: true, searchable: true },
      {
        key: "semester.academic_year",
        label: "Academic Year",
        sortable: true,
        render: (value, item) =>
          value || item.semester?.resolved_academic_year || "—",
      },
      { key: "expected_students", label: "Expected Students", sortable: true },
      { key: "created_at", label: "Created", sortable: true }
    ],
    requiredColumns: ["course_code", "semester_code", "expected_students"],
    optionalColumns: ["course_id", "semester_id"] ,
    // Manual Add/Edit form fields — mirror EntityController::getValidationRules('course-offerings').
    formFields: [
      { name: "course_id", label: "Course", type: "select", required: true, optionsKey: "courses", labelField: "course_code" },
      { name: "semester_id", label: "Semester", type: "select", required: true, optionsKey: "semesters", labelField: "label" },
      { name: "expected_students", label: "Expected Students", type: "number", required: true, min: 0, integer: true },
    ],
    apiEndpoint: "/api/course-offerings",
    routePrefix: "course-offerings",
    permissions: {
      view: "view courses",
      create: "create courses",
      edit: "edit courses",
      delete: "delete courses",
      import: "import course-offerings"
    }
  },
  sections: {
    title: "Sections",
    singular: "Section",
    icon: "Grid",
    category: "Academics",
    columns: [
      { key: "id", label: "ID", sortable: true },
      { key: "section_name", label: "Section", sortable: true, searchable: true },
      { key: "course_offering.course.course_code", label: "Course Code", sortable: true, searchable: true },
      { key: "course_name", label: "Course", sortable: true, searchable: true },
      { key: "course_offering.semester.name", label: "Semester", sortable: true, searchable: true },
      { key: "capacity", label: "Capacity", sortable: true },
      { key: "enrolled_count", label: "Enrolled", sortable: true },
      { key: "created_at", label: "Created", sortable: true }
    ],
    requiredColumns: ["course_code", "semester_code", "section_name", "capacity"],
    optionalColumns: ["course_offering_id"] ,
    // Manual Add/Edit form fields — mirror EntityController::getValidationRules('sections').
    formFields: [
      { name: "course_offering_id", label: "Course Offering", type: "select", required: true, optionsKey: "courseOfferings", labelField: "label" },
      { name: "section_name", label: "Section Name", type: "text", required: true, maxLength: 255, placeholder: "e.g. A" },
      { name: "capacity", label: "Capacity", type: "number", required: true, min: 1, integer: true },
    ],
    apiEndpoint: "/api/sections",
    routePrefix: "sections",
    permissions: {
      view: "view courses",
      create: "create courses",
      edit: "edit courses",
      delete: "delete courses",
      import: "import sections"
    }
  },
  rooms: {
    title: "Rooms",
    singular: "Room",
    icon: "MapPin",
    category: "Resources",
    columns: [
      { key: "id", label: "ID", sortable: true },
      { key: "room_code", label: "Room Code", sortable: true, searchable: true },
      { key: "building", label: "Building", sortable: true, filterable: true },
      { key: "floor", label: "Floor", sortable: true },
      { key: "capacity", label: "Capacity", sortable: true },
      { key: "type", label: "Type", sortable: true, filterable: true },
      { key: "has_projector", label: "Projector", sortable: true },
      { key: "has_computers", label: "Computers", sortable: true },
      { key: "computer_count", label: "Computers #", sortable: true }
    ],
    requiredColumns: ["room_code", "building", "floor", "capacity", "type"],
    optionalColumns: ["has_projector", "has_computers", "computer_count"],
    // Manual Add/Edit form fields — mirror EntityController::getValidationRules('rooms').
    formFields: [
      { name: "room_code", label: "Room Code", type: "text", required: true, maxLength: 50, placeholder: "e.g. B101" },
      { name: "building", label: "Building", type: "text", required: true, maxLength: 100 },
      { name: "floor", label: "Floor", type: "number", required: true, min: 0, integer: true },
      { name: "capacity", label: "Capacity", type: "number", required: true, min: 1, integer: true },
      { name: "type", label: "Type", type: "select", required: true, options: [
        { value: "lecture", label: "Lecture" },
        { value: "lab", label: "Laboratory" },
        { value: "seminar", label: "Seminar" },
        { value: "conference", label: "Conference" },
      ] },
      { name: "has_projector", label: "Has Projector", type: "checkbox", required: false },
      { name: "has_computers", label: "Has Computers", type: "checkbox", required: false },
      { name: "computer_count", label: "Computer Count", type: "number", required: false, min: 0, integer: true },
    ],
    apiEndpoint: "/api/rooms",
    routePrefix: "rooms",
    permissions: {
      view: "view rooms",
      create: "create rooms",
      edit: "edit rooms",
      delete: "delete rooms",
      import: "import rooms"
    }
  },
  timeslots: {
    title: "Timeslots",
    singular: "Timeslot",
    icon: "Clock",
    category: "Resources",
    columns: [
      { key: "id", label: "ID", sortable: true },
      { key: "day_of_week", label: "Day", sortable: true, filterable: true },
      { key: "start_time", label: "Start Time", sortable: true },
      { key: "end_time", label: "End Time", sortable: true },
      { key: "slot_code", label: "Slot Code", sortable: true, searchable: true },
      { key: "student_type", label: "Student Type", sortable: true, filterable: true }
    ],
    requiredColumns: ["day_of_week", "start_time", "end_time", "slot_code"],
    optionalColumns: ["student_type"],
    // Manual Add/Edit form fields — mirror EntityController::getValidationRules('timeslots').
    // Note: start_time/end_time post as H:i; EntityManager normalizes before submit.
    formFields: [
      { name: "day_of_week", label: "Day of Week", type: "select", required: true, options: [
        { value: "Monday", label: "Monday" },
        { value: "Tuesday", label: "Tuesday" },
        { value: "Wednesday", label: "Wednesday" },
        { value: "Thursday", label: "Thursday" },
        { value: "Friday", label: "Friday" },
        { value: "Saturday", label: "Saturday" },
        { value: "Sunday", label: "Sunday" },
      ] },
      { name: "start_time", label: "Start Time", type: "time", required: true },
      { name: "end_time", label: "End Time", type: "time", required: true },
      { name: "slot_code", label: "Slot Code", type: "text", required: false, maxLength: 100 },
      { name: "student_type", label: "Student Type", type: "select", required: false, options: [
        { value: "", label: "All students" },
        { value: "regular", label: "Regular" },
        { value: "weekend", label: "Weekend" },
      ] },
    ],
    apiEndpoint: "/api/timeslots",
    routePrefix: "timeslots",
    permissions: {
      view: "view timeslots",
      create: "create timeslots",
      edit: "edit timeslots",
      delete: "delete timeslots",
      import: "import timeslots"
    }
  },
  enrollments: {
    title: "Enrollments",
    singular: "Enrollment",
    icon: "UserPlus",
    category: "Assignments",
    columns: [
      { key: "id", label: "ID", sortable: true },
      { key: "student.full_name", label: "Student", sortable: true, searchable: true },
      { key: "student_code_value", label: "Student Code", sortable: true, searchable: true },
      { key: "section.section_name", label: "Section", sortable: true, searchable: true },
      { key: "section.course_offering.course.course_code", label: "Course", sortable: true },
      { key: "section.course_offering.semester.name", label: "Semester", sortable: true },
      { key: "enrolled_at", label: "Enrolled Date", sortable: true }
    ],
    requiredColumns: ["student_id", "course_code", "semester_code", "section_name"],
    optionalColumns: ["section_code", "academic_section", "enrolled_at", "student_code_value"],
    // Manual Add/Edit form fields — mirror EntityController::getValidationRules('enrollments').
    // Here student_id / section_id are DB ids (the form selects), not the import codes.
    formFields: [
      { name: "student_id", label: "Student", type: "select", required: true, optionsKey: "students", labelField: "label" },
      { name: "section_id", label: "Course Section", type: "select", required: true, optionsKey: "sections", labelField: "label" },
      { name: "enrolled_at", label: "Enrolled At", type: "date", required: false },
      { name: "student_code_value", label: "Student Code Value", type: "text", required: false, maxLength: 255 },
    ],
    apiEndpoint: "/api/enrollments",
    routePrefix: "enrollments",
    permissions: {
      view: "view enrollments",
      create: "create enrollments",
      edit: "edit enrollments",
      delete: "delete enrollments",
      import: "import enrollments"
    }
  },
  "section-teachers": {
    title: "Section Teachers",
    singular: "Assignment",
    icon: "Users",
    category: "Assignments",
    columns: [
      { key: "id", label: "ID", sortable: true },
      { key: "section.section_name", label: "Section", sortable: true, searchable: true },
      { key: "section.course_offering.course.course_code", label: "Course", sortable: true },
      { key: "section.course_offering.semester.name", label: "Semester", sortable: true },
      { key: "teacher.full_name", label: "Teacher", sortable: true, searchable: true },
      { key: "teacher.teacher_id", label: "Teacher ID", sortable: true, searchable: true }
    ],
    requiredColumns: ["course_code", "semester_code", "section_name", "teacher_code"],
    optionalColumns: ["append"],
    // Manual Add/Edit form fields — mirror EntityController::getValidationRules('section-teachers').
    formFields: [
      { name: "section_id", label: "Section", type: "select", required: true, optionsKey: "sections", labelField: "label" },
      { name: "teacher_id", label: "Teacher", type: "select", required: true, optionsKey: "teachers", labelField: "label" },
    ],
    apiEndpoint: "/api/section-teachers",
    routePrefix: "section-teachers",
    permissions: {
      view: "view section-teachers",
      create: "create section-teachers",
      edit: "edit section-teachers",
      delete: "delete section-teachers",
      import: "import section-teachers"
    }
  },
  schedulers: {
    title: "Schedulers",
    singular: "Scheduler",
    icon: "UserCheck",
    category: "System",
    columns: [
      { key: "id", label: "ID", sortable: true },
      { key: "name", label: "Name", sortable: true, searchable: true },
      { key: "email", label: "Email", sortable: true, searchable: true },
      { key: "created_at", label: "Created At", sortable: true }
    ],
    requiredColumns: ["name", "email", "password"],
    optionalColumns: [],
    // Manual Add/Edit form fields — mirror EntityController::getValidationRules('schedulers').
    // Password is required only when creating; on edit, leave blank to keep current.
    formFields: [
      { name: "name", label: "Name", type: "text", required: true, maxLength: 255 },
      { name: "email", label: "Email Address", type: "email", required: true, maxLength: 255 },
      { name: "password", label: "Password", type: "password", requiredOnCreate: true, minLength: 8, placeholder: "Min. 8 characters", helpOnEdit: "Leave blank to keep the current password" },
    ],
    apiEndpoint: "/api/schedulers",
    routePrefix: "schedulers",
    permissions: {
      view: "view schedulers",
      create: "create schedulers",
      edit: "edit schedulers",
      delete: "delete schedulers",
      import: "import schedulers"
    }
  }
};

export const NAVIGATION_STRUCTURE = [
  {
    category: "People",
    icon: "Users",
    items: [
      { key: "students", name: "Students", icon: "User" },
      { key: "teachers", name: "Teachers", icon: "UserCheck" }
    ]
  },
  {
    category: "Academics",
    icon: "BookOpen",
    items: [
      { key: "departments", name: "Departments", icon: "Archive" },
      { key: "semesters", name: "Semesters", icon: "Calendar" },
      { key: "courses", name: "Courses", icon: "Book" },
      { key: "course-offerings", name: "Course Offerings", icon: "Calendar" },
      { key: "sections", name: "Sections", icon: "Grid" }
    ]
  },
  {
    category: "Resources",
    icon: "MapPin",
    items: [
      { key: "rooms", name: "Rooms", icon: "Home" },
      { key: "timeslots", name: "Timeslots", icon: "Clock" }
    ]
  },
  {
    category: "Assignments",
    icon: "UserPlus",
    items: [
      { key: "enrollments", name: "Enrollments", icon: "UserPlus" },
      { key: "section-teachers", name: "Section Teachers", icon: "Users" }
    ]
  },
  {
    category: "System",
    icon: "Settings",
    items: [
      { key: "schedulers", name: "Schedulers", icon: "UserCheck" }
    ]
  }
];

export const getEntityConfig = (entityKey) => {
  return ENTITY_CONFIG[entityKey];
};

export const getAllEntities = () => {
  return Object.keys(ENTITY_CONFIG);
};

export const getEntitiesByCategory = (category) => {
  return Object.entries(ENTITY_CONFIG)
    .filter(([key, config]) => config.category === category)
    .map(([key]) => key);
};
