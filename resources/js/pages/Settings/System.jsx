import React from 'react';
import { Head } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function SettingsSystem() {
    return (
        <DashboardLayout>
            <Head title="System Preferences" />

            <div className="py-8">
                <div className="mx-auto max-w-6xl sm:px-6 lg:px-8 space-y-6">
                    <div className="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h1 className="text-2xl font-bold text-gray-900">System Preferences</h1>
                        <p className="mt-2 text-gray-600">Set default theme, notifications and dashboard defaults.</p>
                    </div>

                    <div className="bg-white p-6 rounded-xl shadow-sm">
                        <h2 className="text-lg font-semibold text-gray-900">Coming Soon</h2>
                        <p className="mt-2 text-gray-600">System preference controls will appear here (timezone, language, notifications).</p>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
