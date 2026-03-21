import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import logo from '../images/logo.png';


export default function DashboardLayout({ children }) {
    const { props } = usePage();
    const user = props.auth.user || {};
    const perms = props.auth.permissions || [];
    const roles = props.auth.roles || [];
    const flash = props.flash || {};

    const can = (permission) => perms.includes(permission);

    return (
        <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
            <nav className="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-700 shadow-lg">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16">
                        <div className="flex items-center">
                            <div className="shrink-0 flex items-center">
                                <Link href="/dashboard" className="flex items-center space-x-2">
                                    <img 
                                        src={logo} 
                                        alt="Logo" 
                                        className="h-12 w-auto object-contain shadow-lg rounded-full"
                                    />
                                </Link>
                            </div>
                            <div className="hidden space-x-6 sm:-my-px sm:ml-10 sm:flex">
                                <Link
                                    href="/dashboard"
                                    className="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-white hover:bg-white hover:bg-opacity-20 transition duration-200"
                                >
                                    🏠 Dashboard
                                </Link>
                                {can('manage students') && (
                                    <Link
                                        href="/students"
                                        className="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-white hover:bg-white hover:bg-opacity-20 transition duration-200"
                                    >
                                        👨‍🎓 Students
                                    </Link>
                                )}
                                {can('manage teachers') && (
                                    <Link
                                        href="/teachers"
                                        className="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-white hover:bg-white hover:bg-opacity-20 transition duration-200"
                                    >
                                        👨‍🏫 Teachers
                                    </Link>
                                )}
                                {can('manage courses') && (
                                    <Link
                                        href="/courses"
                                        className="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-white hover:bg-white hover:bg-opacity-20 transition duration-200"
                                    >
                                        📚 Courses
                                    </Link>
                                )}
                                {can('view schedule') && (
                                    <Link
                                        href="/schedules"
                                        className="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-white hover:bg-white hover:bg-opacity-20 transition duration-200"
                                    >
                                        📅 Schedules
                                    </Link>
                                )}
                                {can('generate schedule') && (
                                    <Link
                                        href="/schedules/generate"
                                        className="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-white hover:bg-white hover:bg-opacity-20 transition duration-200"
                                    >
                                        🔄 Generate
                                    </Link>
                                )}
                                {can('export schedule') && (
                                    <a
                                        href="/export/schedule"
                                        className="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-white hover:bg-white hover:bg-opacity-20 transition duration-200"
                                    >
                                        📤 Export
                                    </a>
                                )}
                                {can('import data') && (
                                    <Link
                                        href="/import"
                                        className="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-white hover:bg-white hover:bg-opacity-20 transition duration-200"
                                    >
                                        📥 Import
                                    </Link>
                                )}
                            </div>
                        </div>
                        <div className="hidden sm:flex sm:items-center sm:ml-6">
                            <Link
                                href={route('logout')}
                                method="post"
                                replace
                                as="button"
                                className="inline-flex items-center px-4 py-2 bg-red-500 border border-transparent rounded-lg font-semibold text-sm text-white uppercase tracking-widest hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-md"
                            >
                                🚪 Logout
                            </Link>
                        </div>
                        {/* Mobile menu button */}
                        <div className="sm:hidden">
                            <button className="text-white hover:text-gray-200 focus:outline-none">
                                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            {/* Flash Messages */}
            <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-6">
                {flash.success && (
                    <div className="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-md">
                        {flash.success}
                    </div>
                )}

                {flash.error && (
                    <div className="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-md">
                        {flash.error}
                    </div>
                )}
            </div>

            <main className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {children}
                </div>
            </main>
        </div>
    );
}