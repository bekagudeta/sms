import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Link } from '@inertiajs/react';

// role-specific dashboard fragments
import AdminDashboard from '@/Pages/Dashboard/Admin';
import SchedulerDashboard from '@/Pages/Dashboard/Scheduler';
import TeacherDashboard from '@/Pages/Dashboard/Teacher';
import StudentDashboard from '@/Pages/Dashboard/Student';

export default function Dashboard({ stats, recentSchedules, role }) {
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
            content = <StudentDashboard recentSchedules={recentSchedules} />;
            break;
    }

    return <DashboardLayout>{content}</DashboardLayout>;
}
