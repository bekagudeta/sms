import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';

export default function Settings() {
    return (
        <DashboardLayout>
            <Head title="Settings" />

            <div className="py-8">
                <div className="mx-auto max-w-6xl space-y-8 sm:px-6 lg:px-8">
                    <div className="rounded-2xl border border-blue-100 bg-gradient-to-r from-blue-500 to-purple-500 p-6 shadow-xl text-white">
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-3xl font-bold">Platform Settings</h1>
                                <p className="mt-2 text-sm opacity-90">
                                    Manage app preferences, user profile, and security all in one place.
                                </p>
                            </div>
                            <span className="inline-flex items-center rounded-full bg-white/20 px-3 py-1 text-xs font-semibold text-white/90">
                                v1.0
                            </span>
                        </div>
                    </div>

                    <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        <Link
                            href={route('settings.profile')}
                            className="group block rounded-xl border border-gray-200 bg-white p-5 shadow-md transition hover:-translate-y-0.5 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-400"
                        >
                            <div className="flex items-center space-x-3">
                                <div className="rounded-full bg-blue-600 p-2 text-white">
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.695 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <h2 className="text-lg font-semibold text-gray-900">Profile Settings</h2>
                            </div>
                            <p className="mt-3 text-sm text-gray-500">Update your name, email, and password settings securely.</p>
                        </Link>

                        <Link
                            href={route('settings.system')}
                            className="group block rounded-xl border border-gray-200 bg-white p-5 shadow-md transition hover:-translate-y-0.5 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-green-400"
                        >
                            <div className="flex items-center space-x-3">
                                <div className="rounded-full bg-green-500 p-2 text-white">
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h2 className="text-lg font-semibold text-gray-900">System Preferences</h2>
                            </div>
                            <p className="mt-3 text-sm text-gray-500">Set default theme, notifications and dashboard defaults.</p>
                        </Link>

                        <Link
                            href={route('settings.security')}
                            className="group block rounded-xl border border-gray-200 bg-white p-5 shadow-md transition hover:-translate-y-0.5 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-400"
                        >
                            <div className="flex items-center space-x-3">
                                <div className="rounded-full bg-red-500 p-2 text-white">
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 11c1.657 0 3 1.343 3 3v1a8 8 0 11-16 0v-1c0-1.657 1.343-3 3-3h10z" />
                                    </svg>
                                </div>
                                <h2 className="text-lg font-semibold text-gray-900">Security</h2>
                            </div>
                            <p className="mt-3 text-sm text-gray-500">Manage access control and authentication settings.</p>
                        </Link>
                    </div>

                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-md">
                        <h3 className="text-lg font-bold text-gray-900">Starting steps</h3>
                        <p className="mt-1 text-sm text-gray-500">Complete these configuration steps for a professional setup.</p>
                        <ul className="mt-4 list-disc space-y-2 pl-5 text-sm text-gray-600">
                            <li>Complete your profile and contact information.</li>
                            <li>Set your default time zone and preferred language.</li>
                            <li>Enable 2FA and strong password rules.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
