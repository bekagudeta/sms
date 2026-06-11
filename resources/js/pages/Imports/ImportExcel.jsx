import React, { useMemo, useState } from "react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Link, useForm } from "@inertiajs/react";

const fallbackImportOptions = [
    {
        value: "departments",
        label: "Departments",
        category: "Foundation",
        description:
            "Create the department codes used by courses, teachers, and students.",
        dependencies: [],
        requiredColumns: "code, name, description (optional)",
        templateHeaders: ["code", "name", "description"],
        note: "Import this first so later files can safely reference department IDs or codes.",
    },
    {
        value: "semesters",
        label: "Semesters",
        category: "Foundation",
        description:
            "Create the term/semester records used during schedule generation.",
        dependencies: [],
        requiredColumns:
            "name, code, start_date, end_date, is_active (optional)",
        templateHeaders: [
            "name",
            "code",
            "start_date",
            "end_date",
            "is_active",
        ],
        note: "Schedule generation is semester-based, so at least one semester must exist.",
    },
    {
        value: "courses",
        label: "Courses",
        category: "Academic Structure",
        description:
            "Define the course templates, hours per week, level, and room requirements.",
        dependencies: ["departments"],
        requiredColumns:
            "course_code, course_name, description (optional), credits, hours_per_week, department_id, level, required_room_type (optional)",
        templateHeaders: [
            "course_code",
            "course_name",
            "description",
            "credits",
            "hours_per_week",
            "department_id",
            "level",
            "required_room_type",
        ],
        note: "The scheduling engine uses course hours and room requirements from this import.",
    },
    {
        value: "course-offerings",
        label: "Course Offerings",
        category: "Academic Structure",
        description:
            "Attach each course to a specific semester before sections are created.",
        dependencies: ["courses", "semesters"],
        requiredColumns:
            "course_code or course_name (or course_id), semester_code or semester_name (or semester_id), expected_students",
        templateHeaders: ["course_code", "semester_code", "expected_students"],
        note: "Courses and semesters should already exist before this step; missing references are skipped with warnings.",
    },
    {
        value: "sections",
        label: "Sections",
        category: "Academic Structure",
        description:
            "Create the actual class sections that the scheduler will place.",
        dependencies: ["course-offerings"],
        requiredColumns:
            "course_offering_id OR (course_code + semester_code/semester_id), section_name, capacity",
        templateHeaders: [
            "course_code",
            "semester_code",
            "section_name",
            "capacity",
        ],
        note: "The system generates schedules for sections, not just for courses. Keep teacher assignment in the separate Section Teachers import for clarity.",
    },
    {
        value: "teachers",
        label: "Teachers",
        category: "Resources & Constraints",
        description:
            "Create teacher records and login credentials with workload limits.",
        dependencies: ["departments"],
        requiredColumns:
            "teacher_id, first_name, last_name, email, department_id, qualification, max_hours_per_week, phone (optional)",
        templateHeaders: [
            "teacher_id",
            "first_name",
            "last_name",
            "email",
            "department_id",
            "department_name",
            "qualification",
            "max_hours_per_week",
            "phone",
        ],
        note: "A credentials file is downloaded after a successful teacher import.",
    },
    {
        value: "section-teachers",
        label: "Section Teachers",
        category: "Resources & Constraints",
        description:
            "Assign one or more teachers to each section before generation.",
        dependencies: ["sections", "teachers"],
        requiredColumns:
            "course_code, semester_code, section_name, teacher_code, append (optional: yes / no)",
        templateHeaders: [
            "course_code",
            "semester_code",
            "section_name",
            "teacher_code",
            "append",
        ],
        note: "Use the same course_code, semester_code, and section_name as your Sections sheet. teacher_code matches the teacher_id from Teachers import. Multiple teachers: one row per teacher.",
    },
    {
        value: "rooms",
        label: "Rooms",
        category: "Resources & Constraints",
        description: "Load available rooms with capacity and type information.",
        dependencies: [],
        requiredColumns:
            "room_code, building, floor, capacity, type, has_projector (optional), has_computers (optional), computer_count (optional)",
        templateHeaders: [
            "room_code",
            "building",
            "floor",
            "capacity",
            "type",
            "has_projector",
            "has_computers",
            "computer_count",
        ],
        note: "Room capacity and room type are used as hard scheduling constraints. Re-importing updates existing rooms by room_code instead of wiping the table.",
    },
    {
        value: "timeslots",
        label: "Timeslots",
        category: "Resources & Constraints",
        description:
            "Define the real timetable slots used by the scheduling engine.",
        dependencies: [],
        requiredColumns: "day_of_week, start_time, end_time, slot_code",
        templateHeaders: ["day_of_week", "start_time", "end_time", "slot_code"],
        note: "Important: `slot_code` is required by the current import logic.",
    },
    {
        value: "students",
        label: "Students",
        category: "Student Demand",
        description: "Create student records and login credentials.",
        dependencies: ["departments"],
        requiredColumns:
            "student_id, first_name, last_name, email, department_code, academic_section (cohort e.g. SE-3A)",
        templateHeaders: [
            "student_id",
            "first_name",
            "last_name",
            "email",
            "department_code",
            "academic_section",
            "student_type",
            "phone",
        ],
        note: "student_type can be regular or weekend. academic_section is the student's homeroom/cohort (SE-3A), not a course class. department_code must exist in Departments. Credentials download after import.",
    },
    {
        value: "enrollments",
        label: "Enrollments",
        category: "Student Demand",
        description:
            "Enroll students in course sections (classes) for timetable conflict detection.",
        dependencies: ["students", "sections"],
        requiredColumns:
            "student_id + course_code + semester_code + section_name (or section_code; or academic_section for whole cohort)",
        templateHeaders: [
            "student_id",
            "course_code",
            "semester_code",
            "section_name",
            "academic_section",
        ],
        note: "Course sections are classes (e.g. CS101 / F2024 / A). Leave student_id blank and set academic_section to enroll every student in that cohort.",
    },
];

const setupSteps = [
    {
        key: "foundation",
        title: "Step 1 - Foundation",
        description:
            "Create academic master data used by the rest of the system.",
        items: ["departments", "semesters"],
    },
    {
        key: "structure",
        title: "Step 2 - Academic Structure",
        description: "Define what should be offered and which sections exist.",
        items: ["courses", "course-offerings", "sections"],
    },
    {
        key: "resources",
        title: "Step 3 - Resources & Constraints",
        description:
            "Load teachers, rooms, timeslots, and section-teacher assignments.",
        items: ["teachers", "section-teachers", "rooms", "timeslots"],
    },
    {
        key: "students",
        title: "Step 4 - Student Demand",
        description: "Load students and enrollments for conflict prevention.",
        items: ["students", "enrollments"],
    },
];

function getStepState(step, counts) {
    const completed = step.items.filter(
        (item) => (counts[item] ?? 0) > 0,
    ).length;

    if (completed === step.items.length) {
        return { state: "complete", label: "Complete", tone: "green" };
    }

    if (completed > 0) {
        return { state: "partial", label: "In progress", tone: "yellow" };
    }

    return { state: "pending", label: "Pending", tone: "gray" };
}

function normalizeHeader(value = "") {
    return String(value).trim().toLowerCase().replace(/\s+/g, "_");
}

function parseCsvRow(line = "") {
    const values = [];
    let current = "";
    let inQuotes = false;

    for (let index = 0; index < line.length; index += 1) {
        const char = line[index];

        if (char === '"') {
            if (inQuotes && line[index + 1] === '"') {
                current += '"';
                index += 1;
            } else {
                inQuotes = !inQuotes;
            }
        } else if (char === "," && !inQuotes) {
            values.push(current.trim());
            current = "";
        } else {
            current += char;
        }
    }

    values.push(current.trim());
    return values.map((value) => value.replace(/^"|"$/g, "").trim());
}

function formatFileSize(bytes = 0) {
    if (!bytes) return "0 KB";
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta?.getAttribute("content")) {
        return String(meta.getAttribute("content"));
    }

    const cookieMatch = document.cookie.match(/(^|; )XSRF-TOKEN=([^;]+)/);
    if (cookieMatch) {
        return decodeURIComponent(cookieMatch[2]);
    }

    return null;
}

export default function ImportExcel({
    entityCounts = {},
    importOptions: backendImportOptions = [],
}) {
    const importOptions = useMemo(() => {
        const sourceOptions =
            Array.isArray(backendImportOptions) && backendImportOptions.length > 0
                ? backendImportOptions
                : fallbackImportOptions;

        return sourceOptions.map((option) => ({
            ...option,
            requiredColumnList:
                option.requiredColumnList ??
                String(option.requiredColumns || "")
                    .split(",")
                    .map((column) => column.trim())
                    .filter(Boolean),
            optionalColumnList:
                option.optionalColumnList ??
                String(option.optionalColumns || "")
                    .split(",")
                    .map((column) => column.trim())
                    .filter(Boolean),
        }));
    }, [backendImportOptions]);

    const [importType, setImportType] = useState("");
    const [errorMessage, setErrorMessage] = useState(null);
    const [successMessage, setSuccessMessage] = useState(null);
    const [isDownloading, setIsDownloading] = useState(false);
    const [filePreview, setFilePreview] = useState({
        fileName: "",
        fileSize: 0,
        isCsv: false,
        notice: "",
        headers: [],
        rows: [],
    });

    const { data, setData, processing, errors, reset } = useForm({
        file: null,
    });

    const counts = useMemo(() => {
        return importOptions.reduce((acc, option) => {
            acc[option.value] = Number(entityCounts?.[option.value] ?? 0);
            return acc;
        }, {});
    }, [entityCounts]);

    const selectedOption = useMemo(
        () =>
            importOptions.find((option) => option.value === importType) ?? null,
        [importOptions, importType],
    );

    const dependencyIssues = useMemo(() => {
        if (!selectedOption) return [];

        return selectedOption.dependencies
            .filter((dependency) => (counts[dependency] ?? 0) === 0)
            .map(
                (dependency) =>
                    importOptions.find((option) => option.value === dependency)
                        ?.label ?? dependency,
            );
    }, [counts, importOptions, selectedOption]);

    const expectedHeaders = selectedOption?.templateHeaders ?? [];
    const requiredHeaders = selectedOption?.requiredColumnList ?? expectedHeaders;
    const normalizedPreviewHeaders = useMemo(
        () => filePreview.headers.map((header) => normalizeHeader(header)),
        [filePreview.headers],
    );

    const missingHeaders = useMemo(() => {
        if (
            !selectedOption ||
            !filePreview.isCsv ||
            filePreview.headers.length === 0
        ) {
            return [];
        }

        const hasAnyHeader = (headers) =>
            headers.some((header) =>
                normalizedPreviewHeaders.includes(normalizeHeader(header)),
            );

        let missing = requiredHeaders.filter(
            (header) =>
                !normalizedPreviewHeaders.includes(normalizeHeader(header)),
        );

        if (
            ["courses", "teachers", "students"].includes(
                selectedOption.value,
            ) &&
            !hasAnyHeader([
                "department_id",
                "department_code",
                "department_name",
            ])
        ) {
            missing.push("department_id, department_code, or department_name");
        }

        if (selectedOption.value === "course-offerings") {
            if (hasAnyHeader(["course_code", "course_id"])) {
                missing = missing.filter(
                    (header) => normalizeHeader(header) !== "course_code",
                );
            }

            if (hasAnyHeader(["semester_code", "semester_id"])) {
                missing = missing.filter(
                    (header) => normalizeHeader(header) !== "semester_code",
                );
            }
        }

        if (selectedOption.value === "sections") {
            if (hasAnyHeader(["course_offering_id"])) {
                missing = missing.filter(
                    (header) =>
                        !["course_code", "semester_code"].includes(
                            normalizeHeader(header),
                        ),
                );
            } else if (hasAnyHeader(["semester_code", "semester_id"])) {
                missing = missing.filter(
                    (header) => normalizeHeader(header) !== "semester_code",
                );
            }
        }

        if (selectedOption.value === "section-teachers") {
            const hasTeacherIdentifier =
                normalizedPreviewHeaders.includes("teacher_code") ||
                normalizedPreviewHeaders.includes("teacher_id") ||
                normalizedPreviewHeaders.includes("teacher_ids");
            if (hasTeacherIdentifier) {
                missing = missing.filter(
                    (header) => normalizeHeader(header) !== "teacher_code",
                );
            }
        }

        if (selectedOption.value === "students") {
            if (
                hasAnyHeader(["academic_section", "section"])
            ) {
                missing = missing.filter((header) => {
                    const key = normalizeHeader(header);
                    return !["academic_section", "section"].includes(key);
                });
            }
        }

        if (selectedOption.value === "enrollments") {
            const hasCourseSection =
                hasAnyHeader(["course_code"]) &&
                hasAnyHeader(["semester_code"]) &&
                hasAnyHeader(["section_name"]);

            if (hasCourseSection) {
                missing = missing.filter((header) => {
                    const key = normalizeHeader(header);
                    return !["course_code", "semester_code", "section_name", "section_code"].includes(
                        key,
                    );
                });
            } else if (hasAnyHeader(["section_code"])) {
                missing = missing.filter(
                    (header) => normalizeHeader(header) !== "section_code",
                );
            }

            if (hasAnyHeader(["academic_section"])) {
                missing = missing.filter(
                    (header) => normalizeHeader(header) !== "student_id",
                );
            }
        }

        return missing;
    }, [
        expectedHeaders,
        filePreview.headers.length,
        filePreview.isCsv,
        normalizedPreviewHeaders,
        requiredHeaders,
        selectedOption,
    ]);

    const extraHeaders = useMemo(() => {
        if (
            !selectedOption ||
            !filePreview.isCsv ||
            filePreview.headers.length === 0
        ) {
            return [];
        }

        return filePreview.headers.filter((header) => {
            const normalized = normalizeHeader(header);
            if (
                selectedOption.value === "section-teachers" &&
                (normalized === "teacher_id" || normalized === "teacher_ids")
            ) {
                return false;
            }
            return !expectedHeaders.some(
                (expected) => normalizeHeader(expected) === normalized,
            );
        });
    }, [
        expectedHeaders,
        filePreview.headers,
        filePreview.isCsv,
        selectedOption,
    ]);

    const canSubmitImport =
        Boolean(selectedOption && data.file) &&
        dependencyIssues.length === 0 &&
        (!filePreview.isCsv || missingHeaders.length === 0);

    const completedSteps = setupSteps.filter(
        (step) => getStepState(step, counts).state === "complete",
    ).length;

    const readinessChecks = [
        {
            label: "Foundation ready",
            ok: counts.departments > 0 && counts.semesters > 0,
            detail: "Departments and semesters are loaded.",
        },
        {
            label: "Academic structure ready",
            ok:
                counts.courses > 0 &&
                counts["course-offerings"] > 0 &&
                counts.sections > 0,
            detail: "Courses, offerings, and sections are available.",
        },
        {
            label: "Resources ready",
            ok:
                counts.teachers > 0 &&
                counts["section-teachers"] > 0 &&
                counts.rooms > 0 &&
                counts.timeslots > 0,
            detail: "Teachers, room assignments, rooms, and timeslots exist.",
        },
        {
            label: "Student conflict data loaded",
            ok: counts.students > 0 && counts.enrollments > 0,
            detail: "Recommended for student conflict detection.",
            recommended: true,
        },
    ];

    const criticalReady = readinessChecks
        .filter((check) => !check.recommended)
        .every((check) => check.ok);

    const downloadFile = async (url, formData, defaultFilename) => {
        const token = getCsrfToken();

        const formDataWithToken = new FormData();
        for (const [key, value] of formData.entries()) {
            formDataWithToken.append(key, value);
        }
        if (token && !formDataWithToken.has("_token")) {
            formDataWithToken.append("_token", token);
        }

        const response = await fetch(url, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": token || "",
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
            body: formDataWithToken,
            credentials: "same-origin",
        });

        if (!response.ok) {
            if (response.status === 419) {
                throw new Error(
                    "Session expired. Please reload the page and log in again.",
                );
            }
            if (response.status === 401) {
                throw new Error("Unauthorized. Please log in again.");
            }

            const text = await response.text();
            let json;
            try {
                json = JSON.parse(text);
            } catch {
                json = null;
            }

            if (json?.message) {
                throw new Error(json.message);
            }
            if (json?.error) {
                throw new Error(json.error);
            }

            if (text.trim().startsWith("<")) {
                console.error("Import error response:", text);
                const parser = new DOMParser();
                const doc = parser.parseFromString(text, "text/html");
                const heading = doc.querySelector("h1")?.textContent?.trim();
                const paragraph = doc.querySelector("p")?.textContent?.trim();
                throw new Error(
                    heading ||
                        paragraph ||
                        "Import failed (check console for details)",
                );
            }

            throw new Error(text || "Upload failed");
        }

        const blob = await response.blob();
        const contentDisposition = response.headers.get("content-disposition");
        let filename = defaultFilename;
        if (contentDisposition) {
            const match = /filename="?([^";]+)"?/.exec(contentDisposition);
            if (match && match[1]) filename = match[1];
        }

        const urlObject = window.URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = urlObject;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(urlObject);
    };

    const loadFilePreview = (file) => {
        if (!file) {
            setFilePreview({
                fileName: "",
                fileSize: 0,
                isCsv: false,
                notice: "",
                headers: [],
                rows: [],
            });
            return;
        }

        const isCsv = file.name.toLowerCase().endsWith(".csv");
        setFilePreview({
            fileName: file.name,
            fileSize: file.size,
            isCsv,
            notice: isCsv
                ? "Reading CSV preview..."
                : "Preview is available for CSV files. Excel files can still be uploaded normally.",
            headers: [],
            rows: [],
        });

        if (!isCsv) {
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            const text = String(event.target?.result ?? "");
            const lines = text
                .split(/\r?\n/)
                .filter((line) => line.trim() !== "");

            if (lines.length === 0) {
                setFilePreview({
                    fileName: file.name,
                    fileSize: file.size,
                    isCsv: true,
                    notice: "This CSV file looks empty.",
                    headers: [],
                    rows: [],
                });
                return;
            }

            const headers = parseCsvRow(lines[0]);
            const rows = lines.slice(1, 6).map((line) => parseCsvRow(line));

            setFilePreview({
                fileName: file.name,
                fileSize: file.size,
                isCsv: true,
                notice: `Previewing the first ${rows.length} data row(s) from your CSV file.`,
                headers,
                rows,
            });
        };

        reader.onerror = () => {
            setFilePreview({
                fileName: file.name,
                fileSize: file.size,
                isCsv: true,
                notice: "The file could not be previewed, but you can still try importing it.",
                headers: [],
                rows: [],
            });
        };

        reader.readAsText(file);
    };

    const handleFileChange = (e) => {
        const file = e.target.files[0] ?? null;
        setData("file", file);
        setErrorMessage(null);
        setSuccessMessage(null);
        loadFilePreview(file);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!importType) {
            setErrorMessage("Please choose which entity you want to import.");
            return;
        }

        if (!data.file) {
            setErrorMessage("Please choose an Excel or CSV file.");
            return;
        }

        if (dependencyIssues.length > 0) {
            setErrorMessage(
                `Complete these dependencies first: ${dependencyIssues.join(", ")}.`,
            );
            return;
        }

        if (filePreview.isCsv && missingHeaders.length > 0) {
            setErrorMessage(
                `Your CSV file is missing required column(s): ${missingHeaders.join(", ")}.`,
            );
            return;
        }

        setSuccessMessage(null);
        const routeUrl = route(`import.${importType}`);
        const formData = new FormData();
        formData.append("file", data.file);

        const csrfToken = getCsrfToken();
        if (csrfToken) {
            formData.append("_token", csrfToken);
        }

        if (importType === "students" || importType === "teachers") {
            try {
                setIsDownloading(true);
                await downloadFile(
                    routeUrl,
                    formData,
                    `${importType}_credentials.xlsx`,
                );
                reset();
                setImportType("");
                setErrorMessage(null);
                setSuccessMessage(
                    `${selectedOption?.label ?? "Records"} imported successfully. The credentials download should start automatically.`,
                );
                setTimeout(() => window.location.reload(), 900);
            } catch (err) {
                console.error(err);
                setErrorMessage(err?.message || "Import failed.");
            } finally {
                setIsDownloading(false);
            }

            return;
        }

        try {
            setIsDownloading(true);
            const token = getCsrfToken();

            const response = await fetch(routeUrl, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": token || "",
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "application/json",
                },
                body: formData,
                credentials: "include",
            });

            if (!response.ok) {
                const text = await response.text();
                let msg = "Import failed.";

                try {
                    const json = JSON.parse(text);
                    msg = json?.message || json?.error || msg;
                    if (json?.errors && Array.isArray(json.errors)) {
                        msg +=
                            "\n\nErrors:\n" +
                            json.errors.slice(0, 5).join("\n");
                        if (json.errors.length > 5) {
                            msg += `\n...and ${json.errors.length - 5} more errors`;
                        }
                    }
                } catch {
                    if (text.trim().startsWith("<")) {
                        console.error("Import error response:", text);
                        msg = "Import failed (check console for details)";
                    } else {
                        msg = text || msg;
                    }
                }

                throw new Error(msg);
            }

            const result = await response.json();
            const warnings = Array.isArray(result?.errors) ? result.errors : [];
            const processedCount = result?.count
                ? ` (${result.count} rows processed)`
                : "";
            const warningSuffix =
                warnings.length > 0
                    ? ` ${warnings.length} warning(s) were reported.`
                    : "";

            setSuccessMessage(
                `${result?.message || `${selectedOption?.label ?? "Records"} imported successfully.`}${processedCount}.${warningSuffix}`.replace(
                    "..",
                    ".",
                ),
            );
            setTimeout(() => window.location.reload(), 900);
        } catch (err) {
            console.error(err);
            setErrorMessage(err?.message || "Import failed.");
        } finally {
            setIsDownloading(false);
        }
    };

    return (
        <DashboardLayout>
            <div className="space-y-6">
                <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div className="p-6 bg-white border-b border-gray-200">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900">
                                    Scheduling Setup Wizard
                                </h2>
                                <p className="mt-2 text-sm text-gray-600">
                                    Import your scheduling data one step at a
                                    time, in the correct order, before
                                    generating a timetable.
                                </p>
                            </div>

                            <div className="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                                <p className="font-semibold">Progress</p>
                                <p>
                                    {completedSteps} / {setupSteps.length} setup
                                    steps completed
                                </p>
                            </div>
                        </div>

                        <div className="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-4">
                            {setupSteps.map((step) => {
                                const status = getStepState(step, counts);
                                const badgeClass =
                                    status.tone === "green"
                                        ? "bg-green-100 text-green-700"
                                        : status.tone === "yellow"
                                          ? "bg-yellow-100 text-yellow-700"
                                          : "bg-gray-100 text-gray-700";

                                return (
                                    <div
                                        key={step.key}
                                        className="rounded-lg border border-gray-200 bg-gray-50 p-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <h3 className="text-sm font-semibold text-gray-900">
                                                {step.title}
                                            </h3>
                                            <span
                                                className={`rounded-full px-2 py-1 text-xs font-medium ${badgeClass}`}
                                            >
                                                {status.label}
                                            </span>
                                        </div>
                                        <p className="mt-2 text-xs text-gray-600">
                                            {step.description}
                                        </p>
                                        <ul className="mt-3 space-y-1 text-xs text-gray-700">
                                            {step.items.map((item) => {
                                                const itemConfig =
                                                    importOptions.find(
                                                        (option) =>
                                                            option.value ===
                                                            item,
                                                    );
                                                const count = counts[item] ?? 0;
                                                return (
                                                    <li
                                                        key={item}
                                                        className="flex items-center justify-between gap-2 rounded bg-white px-2 py-1"
                                                    >
                                                        <span>
                                                            {itemConfig?.label}
                                                        </span>
                                                        <span
                                                            className={`font-semibold ${count > 0 ? "text-green-600" : "text-gray-400"}`}
                                                        >
                                                            {count}
                                                        </span>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-5">
                    <div className="xl:col-span-3 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 bg-white border-b border-gray-200">
                            <h3 className="text-lg font-semibold text-gray-900">
                                1. Choose what to import
                            </h3>
                            <p className="mt-1 text-sm text-gray-600">
                                Pick the next entity in the recommended flow
                                below and upload its Excel or CSV file.
                            </p>

                            <div className="mt-4 space-y-4">
                                {[
                                    "Foundation",
                                    "Academic Structure",
                                    "Resources & Constraints",
                                    "Student Demand",
                                ].map((category) => (
                                    <div key={category}>
                                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            {category}
                                        </p>
                                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            {importOptions
                                                .filter(
                                                    (option) =>
                                                        option.category ===
                                                        category,
                                                )
                                                .map((option) => {
                                                    const isSelected =
                                                        importType ===
                                                        option.value;
                                                    return (
                                                        <button
                                                            key={option.value}
                                                            type="button"
                                                            onClick={() => {
                                                                setImportType(
                                                                    option.value,
                                                                );
                                                                setErrorMessage(
                                                                    null,
                                                                );
                                                                setSuccessMessage(
                                                                    null,
                                                                );
                                                                setData(
                                                                    "file",
                                                                    null,
                                                                );
                                                                setFilePreview({
                                                                    fileName:
                                                                        "",
                                                                    fileSize: 0,
                                                                    isCsv: false,
                                                                    notice: "",
                                                                    headers: [],
                                                                    rows: [],
                                                                });
                                                            }}
                                                            className={`rounded-lg border px-3 py-3 text-left transition ${
                                                                isSelected
                                                                    ? "border-indigo-500 bg-indigo-50 shadow-sm"
                                                                    : "border-gray-200 bg-gray-50 hover:border-indigo-300 hover:bg-indigo-50/40"
                                                            }`}
                                                        >
                                                            <div className="flex items-center justify-between gap-3">
                                                                <span className="font-medium text-gray-900">
                                                                    {
                                                                        option.label
                                                                    }
                                                                </span>
                                                                <span
                                                                    className={`rounded-full px-2 py-1 text-xs font-semibold ${(counts[option.value] ?? 0) > 0 ? "bg-green-100 text-green-700" : "bg-gray-200 text-gray-600"}`}
                                                                >
                                                                    {counts[
                                                                        option
                                                                            .value
                                                                    ] ?? 0}{" "}
                                                                    records
                                                                </span>
                                                            </div>
                                                            <p className="mt-1 text-xs text-gray-600">
                                                                {
                                                                    option.description
                                                                }
                                                            </p>
                                                        </button>
                                                    );
                                                })}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <h4 className="text-sm font-semibold text-gray-900">
                                    2. Upload file
                                </h4>

                                {!selectedOption ? (
                                    <p className="mt-2 text-sm text-gray-600">
                                        Select an entity above to see its
                                        required columns and dependencies.
                                    </p>
                                ) : (
                                    <div className="mt-3 space-y-4">
                                        <div className="rounded-md border border-blue-200 bg-blue-50 p-3">
                                            <div className="flex items-center justify-between gap-3">
                                                <p className="font-semibold text-blue-900">
                                                    {selectedOption.label}
                                                </p>
                                                <span className="text-xs font-medium text-blue-700">
                                                    {selectedOption.category}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-sm text-blue-800">
                                                {selectedOption.note}
                                            </p>
                                        </div>

                                        {selectedOption.dependencies.length >
                                            0 && (
                                            <div>
                                                <p className="text-sm font-semibold text-gray-900">
                                                    Import dependencies
                                                </p>
                                                <div className="mt-2 space-y-2">
                                                    {selectedOption.dependencies.map(
                                                        (dependency) => {
                                                            const dependencyOption =
                                                                importOptions.find(
                                                                    (option) =>
                                                                        option.value ===
                                                                        dependency,
                                                                );
                                                            const dependencyCount =
                                                                counts[
                                                                    dependency
                                                                ] ?? 0;
                                                            return (
                                                                <div
                                                                    key={
                                                                        dependency
                                                                    }
                                                                    className="flex items-center justify-between rounded-md border border-gray-200 bg-white px-3 py-2 text-sm"
                                                                >
                                                                    <span>
                                                                        {
                                                                            dependencyOption?.label
                                                                        }
                                                                    </span>
                                                                    <span
                                                                        className={`font-semibold ${dependencyCount > 0 ? "text-green-600" : "text-red-500"}`}
                                                                    >
                                                                        {dependencyCount >
                                                                        0
                                                                            ? `${dependencyCount} found`
                                                                            : "Missing"}
                                                                    </span>
                                                                </div>
                                                            );
                                                        },
                                                    )}
                                                </div>
                                            </div>
                                        )}

                                        <div>
                                            <p className="text-sm font-semibold text-gray-900">
                                                Required columns
                                            </p>
                                            <p className="mt-1 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
                                                {selectedOption.requiredColumns}
                                            </p>
                                        </div>

                                        <div className="rounded-md border border-indigo-200 bg-indigo-50 p-3">
                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <p className="text-sm font-semibold text-indigo-900">
                                                        Starter template
                                                    </p>
                                                    <p className="text-xs text-indigo-800">
                                                        Download a sample CSV
                                                        with the correct headers
                                                        and one example row,
                                                        then replace the sample
                                                        values with your real
                                                        data.
                                                    </p>
                                                </div>
                                                <a
                                                    href={route(
                                                        "import.template",
                                                        {
                                                            type: selectedOption.value,
                                                        },
                                                    )}
                                                    className="inline-flex items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                                                >
                                                    Download CSV Template
                                                </a>
                                            </div>
                                        </div>

                                        {(importType === "enrollments" ||
                                            importType ===
                                                "section-teachers") && (
                                            <div className="rounded-md border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800">
                                                {importType === "enrollments"
                                                    ? "Enroll students in course sections (classes), not academic cohorts. Import students with academic_section (e.g. SE-3A) first, then course sections, then enrollments."
                                                    : "Assign teachers to sections before generating schedules. Use append=yes if you want to add teachers without replacing current assignments."}
                                            </div>
                                        )}

                                        {(importType === "students" ||
                                            importType === "teachers") && (
                                            <div className="rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-800">
                                                A credentials file will be
                                                downloaded after a successful
                                                import.
                                            </div>
                                        )}

                                        <form
                                            onSubmit={handleSubmit}
                                            className="space-y-4"
                                        >
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                                    Choose Excel / CSV file
                                                </label>
                                                <input
                                                    type="file"
                                                    accept=".xlsx,.xls,.csv"
                                                    onChange={handleFileChange}
                                                    className="w-full rounded-md border border-gray-300 bg-white px-3 py-2"
                                                    required
                                                />
                                                {errors.file && (
                                                    <p className="mt-1 text-sm text-red-500">
                                                        {errors.file}
                                                    </p>
                                                )}
                                                {errorMessage && (
                                                    <p className="mt-1 whitespace-pre-line text-sm text-red-500">
                                                        {errorMessage}
                                                    </p>
                                                )}
                                                {successMessage && (
                                                    <p className="mt-1 whitespace-pre-line text-sm text-green-600">
                                                        {successMessage}
                                                    </p>
                                                )}
                                            </div>

                                            {(data.file ||
                                                filePreview.fileName) && (
                                                <div className="rounded-lg border border-gray-200 bg-white p-4">
                                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                        <div>
                                                            <p className="text-sm font-semibold text-gray-900">
                                                                Selected file
                                                            </p>
                                                            <p className="text-sm text-gray-700">
                                                                {filePreview.fileName ||
                                                                    data.file
                                                                        ?.name}
                                                            </p>
                                                        </div>
                                                        <div className="text-xs text-gray-500">
                                                            {formatFileSize(
                                                                filePreview.fileSize ||
                                                                    data.file
                                                                        ?.size ||
                                                                    0,
                                                            )}
                                                        </div>
                                                    </div>

                                                    {filePreview.notice && (
                                                        <p className="mt-2 text-xs text-gray-600">
                                                            {filePreview.notice}
                                                        </p>
                                                    )}

                                                    {dependencyIssues.length >
                                                        0 && (
                                                        <div className="mt-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                                                            Complete these
                                                            dependencies first:{" "}
                                                            <span className="font-semibold">
                                                                {dependencyIssues.join(
                                                                    ", ",
                                                                )}
                                                            </span>
                                                            .
                                                        </div>
                                                    )}

                                                    {filePreview.isCsv &&
                                                        filePreview.headers
                                                            .length > 0 && (
                                                            <div className="mt-3 space-y-3">
                                                                <div>
                                                                    <p className="text-sm font-semibold text-gray-900">
                                                                        Header
                                                                        validation
                                                                    </p>
                                                                    <div className="mt-2 flex flex-wrap gap-2">
                                                                        {filePreview.headers.map(
                                                                            (
                                                                                header,
                                                                            ) => {
                                                                                const isExpected =
                                                                                    expectedHeaders.some(
                                                                                        (
                                                                                            expected,
                                                                                        ) =>
                                                                                            normalizeHeader(
                                                                                                expected,
                                                                                            ) ===
                                                                                            normalizeHeader(
                                                                                                header,
                                                                                            ),
                                                                                    );
                                                                                return (
                                                                                    <span
                                                                                        key={
                                                                                            header
                                                                                        }
                                                                                        className={`rounded-full px-2 py-1 text-xs font-medium ${isExpected ? "bg-green-100 text-green-700" : "bg-gray-100 text-gray-600"}`}
                                                                                    >
                                                                                        {
                                                                                            header
                                                                                        }
                                                                                    </span>
                                                                                );
                                                                            },
                                                                        )}
                                                                    </div>
                                                                </div>

                                                                {missingHeaders.length >
                                                                0 ? (
                                                                    <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                                                                        Missing
                                                                        required
                                                                        column(s):{" "}
                                                                        <span className="font-semibold">
                                                                            {missingHeaders.join(
                                                                                ", ",
                                                                            )}
                                                                        </span>
                                                                    </div>
                                                                ) : (
                                                                    <div className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                                                                        CSV
                                                                        headers
                                                                        match
                                                                        the
                                                                        expected
                                                                        template
                                                                        for{" "}
                                                                        {
                                                                            selectedOption.label
                                                                        }
                                                                        .
                                                                    </div>
                                                                )}

                                                                {extraHeaders.length >
                                                                    0 && (
                                                                    <div className="rounded-md border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800">
                                                                        Extra
                                                                        column(s)
                                                                        detected:{" "}
                                                                        <span className="font-semibold">
                                                                            {extraHeaders.join(
                                                                                ", ",
                                                                            )}
                                                                        </span>
                                                                    </div>
                                                                )}

                                                                {filePreview
                                                                    .rows
                                                                    .length >
                                                                    0 && (
                                                                    <div>
                                                                        <p className="text-sm font-semibold text-gray-900">
                                                                            First
                                                                            few
                                                                            data
                                                                            rows
                                                                        </p>
                                                                        <div className="mt-2 overflow-x-auto rounded-md border border-gray-200">
                                                                            <table className="min-w-full text-xs text-gray-700">
                                                                                <thead className="bg-gray-50">
                                                                                    <tr>
                                                                                        {filePreview.headers.map(
                                                                                            (
                                                                                                header,
                                                                                            ) => (
                                                                                                <th
                                                                                                    key={
                                                                                                        header
                                                                                                    }
                                                                                                    className="border px-2 py-2 text-left font-semibold text-gray-700"
                                                                                                >
                                                                                                    {
                                                                                                        header
                                                                                                    }
                                                                                                </th>
                                                                                            ),
                                                                                        )}
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    {filePreview.rows.map(
                                                                                        (
                                                                                            row,
                                                                                            rowIndex,
                                                                                        ) => (
                                                                                            <tr
                                                                                                key={`${rowIndex}-${row.join("-")}`}
                                                                                            >
                                                                                                {filePreview.headers.map(
                                                                                                    (
                                                                                                        header,
                                                                                                        columnIndex,
                                                                                                    ) => (
                                                                                                        <td
                                                                                                            key={`${header}-${rowIndex}`}
                                                                                                            className="border px-2 py-2 align-top"
                                                                                                        >
                                                                                                            {row[
                                                                                                                columnIndex
                                                                                                            ] ||
                                                                                                                "-"}
                                                                                                        </td>
                                                                                                    ),
                                                                                                )}
                                                                                            </tr>
                                                                                        ),
                                                                                    )}
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        )}
                                                </div>
                                            )}

                                            <button
                                                type="submit"
                                                disabled={
                                                    processing ||
                                                    isDownloading ||
                                                    !canSubmitImport
                                                }
                                                className="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                {processing || isDownloading
                                                    ? "Importing..."
                                                    : dependencyIssues.length >
                                                        0
                                                      ? "Complete dependencies first"
                                                      : `Import ${selectedOption.label}`}
                                            </button>
                                        </form>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="xl:col-span-2 space-y-6">
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6 bg-white border-b border-gray-200">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    Schedule readiness
                                </h3>
                                <p className="mt-1 text-sm text-gray-600">
                                    These checks show whether the system is
                                    ready for schedule generation.
                                </p>

                                <div className="mt-4 space-y-3">
                                    {readinessChecks.map((check) => (
                                        <div
                                            key={check.label}
                                            className="rounded-lg border border-gray-200 bg-gray-50 p-3"
                                        >
                                            <div className="flex items-center justify-between gap-3">
                                                <p className="text-sm font-medium text-gray-900">
                                                    {check.label}
                                                </p>
                                                <span
                                                    className={`rounded-full px-2 py-1 text-xs font-semibold ${check.ok ? "bg-green-100 text-green-700" : check.recommended ? "bg-yellow-100 text-yellow-700" : "bg-red-100 text-red-700"}`}
                                                >
                                                    {check.ok
                                                        ? "Ready"
                                                        : check.recommended
                                                          ? "Recommended"
                                                          : "Missing"}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-xs text-gray-600">
                                                {check.detail}
                                            </p>
                                        </div>
                                    ))}
                                </div>

                                <div
                                    className={`mt-4 rounded-lg border p-4 ${criticalReady ? "border-green-200 bg-green-50" : "border-yellow-200 bg-yellow-50"}`}
                                >
                                    <p
                                        className={`text-sm font-semibold ${criticalReady ? "text-green-800" : "text-yellow-800"}`}
                                    >
                                        {criticalReady
                                            ? "Ready for schedule generation"
                                            : "More setup is still required"}
                                    </p>
                                    <p
                                        className={`mt-1 text-xs ${criticalReady ? "text-green-700" : "text-yellow-700"}`}
                                    >
                                        {criticalReady
                                            ? "Core schedule data is complete. You can open the schedule generator now."
                                            : "Finish the missing foundation, academic, and resource steps before generating."}
                                    </p>

                                    <div className="mt-3">
                                        <Link
                                            href={route(
                                                "schedules.generate.show",
                                            )}
                                            className={`inline-flex items-center rounded-md px-3 py-2 text-sm font-medium ${criticalReady ? "bg-green-600 text-white hover:bg-green-700" : "bg-gray-300 text-gray-600 cursor-not-allowed pointer-events-none"}`}
                                        >
                                            Open Schedule Generator
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6 bg-white border-b border-gray-200">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    Recommended one-by-one flow
                                </h3>
                                <ol className="mt-3 space-y-3 text-sm text-gray-700">
                                    <li>
                                        <span className="font-semibold">
                                            1.
                                        </span>{" "}
                                        Import{" "}
                                        <span className="font-semibold">
                                            Departments
                                        </span>{" "}
                                        and{" "}
                                        <span className="font-semibold">
                                            Semesters
                                        </span>
                                        .
                                    </li>
                                    <li>
                                        <span className="font-semibold">
                                            2.
                                        </span>{" "}
                                        Import{" "}
                                        <span className="font-semibold">
                                            Courses
                                        </span>
                                        , then{" "}
                                        <span className="font-semibold">
                                            Course Offerings
                                        </span>
                                        .
                                    </li>
                                    <li>
                                        <span className="font-semibold">
                                            3.
                                        </span>{" "}
                                        Import{" "}
                                        <span className="font-semibold">
                                            Sections
                                        </span>{" "}
                                        for each offering.
                                    </li>
                                    <li>
                                        <span className="font-semibold">
                                            4.
                                        </span>{" "}
                                        Import{" "}
                                        <span className="font-semibold">
                                            Teachers
                                        </span>
                                        ,{" "}
                                        <span className="font-semibold">
                                            Rooms
                                        </span>
                                        , and{" "}
                                        <span className="font-semibold">
                                            Timeslots
                                        </span>
                                        .
                                    </li>
                                    <li>
                                        <span className="font-semibold">
                                            5.
                                        </span>{" "}
                                        Import{" "}
                                        <span className="font-semibold">
                                            Section Teachers
                                        </span>{" "}
                                        so every section has an instructor.
                                    </li>
                                    <li>
                                        <span className="font-semibold">
                                            6.
                                        </span>{" "}
                                        Import{" "}
                                        <span className="font-semibold">
                                            Students
                                        </span>{" "}
                                        and{" "}
                                        <span className="font-semibold">
                                            Enrollments
                                        </span>{" "}
                                        for conflict checking.
                                    </li>
                                    <li>
                                        <span className="font-semibold">
                                            7.
                                        </span>{" "}
                                        Generate the schedule and review any
                                        remaining conflicts manually.
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
