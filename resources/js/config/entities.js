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
      { key: "level", label: "Level", sortable: true },
      { key: "section", label: "Section", sortable: true },
      { key: "phone", label: "Phone", sortable: true }
    ],
    requiredColumns: ["student_id", "first_name", "last_name", "email"],
    optionalColumns: ["level", "section", "phone", "department_id"],
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
      { key: "qualification", label: "Qualification", sortable: true },
      { key: "max_hours_per_week", label: "Max Hours", sortable: true }
    ],
    requiredColumns: ["teacher_id", "first_name", "last_name", "email", "department_id"],
    optionalColumns: ["qualification", "phone", "max_hours_per_week"],
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
      { key: "department.name", label: "Department", sortable: true, filterable: true }
    ],
    requiredColumns: ["course_code", "course_name", "credits", "hours_per_week", "department_id"],
    optionalColumns: ["description", "level", "required_room_type"],
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
      { key: "start_date", label: "Start Date", sortable: true },
      { key: "end_date", label: "End Date", sortable: true },
      { key: "is_active", label: "Active", sortable: true }
    ],
    requiredColumns: ["name", "code"],
    optionalColumns: ["start_date", "end_date", "is_active"],
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
      { key: "expected_students", label: "Expected Students", sortable: true },
      { key: "created_at", label: "Created", sortable: true }
    ],
    requiredColumns: ["course_id", "semester_id", "expected_students"],
    optionalColumns: [] ,
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
      { key: "course_name", label: "Course", sortable: true, searchable: true },
      { key: "capacity", label: "Capacity", sortable: true },
      { key: "enrolled_count", label: "Enrolled", sortable: true },
      { key: "created_at", label: "Created", sortable: true }
    ],
    requiredColumns: ["course_offering_id", "section_name", "capacity"],
    optionalColumns: [] ,
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
      { key: "slot_code", label: "Slot Code", sortable: true, searchable: true }
    ],
    requiredColumns: ["day_of_week", "start_time", "end_time"],
    optionalColumns: ["slot_code"],
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
      { key: "student.name", label: "Student", sortable: true, searchable: true },
      { key: "section.section_name", label: "Section", sortable: true, searchable: true },
      { key: "enrolled_at", label: "Enrolled Date", sortable: true },
      { key: "student_code_value", label: "Student Code", sortable: true }
    ],
    requiredColumns: ["student_id", "section_id"],
    optionalColumns: ["enrolled_at", "student_code_value"],
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
      { key: "teacher.full_name", label: "Teacher", sortable: true, searchable: true }
    ],
    requiredColumns: ["section_id", "teacher_id", "teacher_ids"],
    optionalColumns: ["append"],
    apiEndpoint: "/api/section-teachers",
    routePrefix: "section-teachers",
    permissions: {
      view: "view enrollments",
      create: "create enrollments",
      edit: "edit enrollments",
      delete: "delete enrollments",
      import: "import section-teachers"
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
