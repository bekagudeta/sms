import React from 'react';
import { Head } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function SettingsSecurity() {
    return (
        <DashboardLayout>
            <Head title="Security Settings" />

            <div className="py-8">
                <div className="mx-auto max-w-6xl sm:px-6 lg:px-8 space-y-6">
                    <div className="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h1 className="text-2xl font-bold text-gray-900">Security</h1>
                        <p className="mt-2 text-gray-600">Manage access control, authentication, and session policies.</p>
                    </div>

                    <div className="bg-white p-6 rounded-xl shadow-sm">
                        <h2 className="text-lg font-semibold text-gray-900">Coming Soon</h2>
                        <p className="mt-2 text-gray-600">Security options like 2FA, sessions, and password rules will be available here.</p>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
