import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Link } from '@inertiajs/react';

// role-specific dashboard fragments
import AdminDashboard from '@/pages/Dashboard/Admin';
import SchedulerDashboard from '@/pages/Dashboard/Scheduler';
import TeacherDashboard from '@/pages/Dashboard/Teacher';
import StudentDashboard from '@/pages/Dashboard/Student';

export default function Dashboard({ stats, recentSchedules, role, studentProfile, enrolledCourses }) {
    // choose sub-view based on role passed from controller
    let content;

    switch (role) {
        case 'admin':
            content = <AdminDashboard stats={stats} recentSchedules={recentSchedules} />;
            break;
        case 'scheduler':
            content = <SchedulerDashboard stats={stats} recentSchedules={recentSchedules} />;
            break;
        case 'teacher':
            content = <TeacherDashboard recentSchedules={recentSchedules} />;
            break;
        default:
            content = (
                <StudentDashboard
                    recentSchedules={recentSchedules}
                    studentProfile={studentProfile}
                    enrolledCourses={enrolledCourses}
                />
            );
            break;
    }

    return <DashboardLayout>{content}</DashboardLayout>;
}
