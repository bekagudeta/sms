export const ENTITY_CONFIG = {
  students: {
    title: "Students",
    singular: "Student",
    icon: "Users",
    category: "People",
    columns: [
      { key: "id", label: "ID", sortable: true },
      { key: "name", label: "Name", sortable: true, searchable: true },
      { key: "email", label: "Email", sortable: true, searchable: true },
      { key: "grade", label: "Grade", sortable: true },
      { key: "status", label: "Status", sortable: true, filterable: true }
    ],
    requiredColumns: ["student_id", "name", "email"],
    optionalColumns: ["grade", "status", "phone", "address"],
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
      { key: "name", label: "Name", sortable: true, searchable: true },
      { key: "email", label: "Email", sortable: true, searchable: true },
      { key: "department", label: "Department", sortable: true, filterable: true },
      { key: "status", label: "Status", sortable: true, filterable: true }
    ],
    requiredColumns: ["teacher_id", "name", "email"],
    optionalColumns: ["department", "status", "phone", "address", "max_hours_per_week"],
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
      { key: "department", label: "Department", sortable: true, filterable: true }
    ],
    requiredColumns: ["course_code", "course_name", "credits"],
    optionalColumns: ["department", "description", "level", "prerequisites"],
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
  courseOfferings: {
    title: "Course Offerings",
    singular: "Offering",
    icon: "Calendar",
    category: "Academics", 
    columns: [
      { key: "id", label: "ID", sortable: true },
      { key: "course.course_code", label: "Course", sortable: true, searchable: true },
      { key: "semester.name", label: "Semester", sortable: true, filterable: true },
      { key: "year", label: "Year", sortable: true },
      { key: "expected_students", label: "Expected Students", sortable: true },
      { key: "status", label: "Status", sortable: true, filterable: true }
    ],
    requiredColumns: ["course_id", "semester_id", "year"],
    optionalColumns: ["expected_students", "status", "description"],
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
      { key: "courseOffering.course.course_name", label: "Course", sortable: true, searchable: true },
      { key: "capacity", label: "Capacity", sortable: true },
      { key: "enrolled_count", label: "Enrolled", sortable: true },
      { key: "status", label: "Status", sortable: true, filterable: true }
    ],
    requiredColumns: ["course_offering_id", "section_name", "capacity"],
    optionalColumns: ["status", "description", "room_id"],
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
      { key: "capacity", label: "Capacity", sortable: true },
      { key: "type", label: "Type", sortable: true, filterable: true },
      { key: "status", label: "Status", sortable: true, filterable: true }
    ],
    requiredColumns: ["room_code", "capacity", "type"],
    optionalColumns: ["building", "status", "description", "equipment"],
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
      { key: "type", label: "Type", sortable: true, filterable: true }
    ],
    requiredColumns: ["day_of_week", "start_time", "end_time"],
    optionalColumns: ["slot_code", "type", "description"],
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
      { key: "enrollment_date", label: "Enrolled Date", sortable: true },
      { key: "status", label: "Status", sortable: true, filterable: true }
    ],
    requiredColumns: ["student_id", "section_id"],
    optionalColumns: ["enrollment_date", "status", "grade"],
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
  sectionTeachers: {
    title: "Section Teachers",
    singular: "Assignment",
    icon: "Users",
    category: "Assignments",
    columns: [
      { key: "id", label: "ID", sortable: true },
      { key: "section.section_name", label: "Section", sortable: true, searchable: true },
      { key: "teacher.name", label: "Teacher", sortable: true, searchable: true },
      { key: "role", label: "Role", sortable: true, filterable: true },
      { key: "assigned_date", label: "Assigned Date", sortable: true },
      { key: "status", label: "Status", sortable: true, filterable: true }
    ],
    requiredColumns: ["section_id", "teacher_id"],
    optionalColumns: ["role", "assigned_date", "status"],
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
