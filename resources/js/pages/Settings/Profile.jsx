import React from 'react';
import { Head } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import UpdateProfileInformationForm from '../Profile/Partials/UpdateProfileInformationForm';
import UpdatePasswordForm from '../Profile/Partials/UpdatePasswordForm';

export default function SettingsProfile({ mustVerifyEmail, status }) {
    return (
        <DashboardLayout>
            <Head title="Profile Settings" />

            <div className="py-8">
                <div className="mx-auto max-w-6xl sm:px-6 lg:px-8 space-y-6">
                    <div className="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h1 className="text-2xl font-bold text-gray-900">Profile Settings</h1>
                        <p className="mt-2 text-gray-600">Update your name, email, and password settings securely.</p>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="bg-white p-6 rounded-xl shadow-sm">
                            <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                        </div>
                        <div className="bg-white p-6 rounded-xl shadow-sm">
                            <UpdatePasswordForm />
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
