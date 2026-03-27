import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import logo from '../images/logo.png';
import NavigationSidebar from '@/components/NavigationSidebar';

export default function DashboardLayout({ children }) {
    const { props } = usePage();
    const user = props.auth.user || {};
    const perms = props.auth.permissions || [];
    const roles = props.auth.roles || [];
    const flash = props.flash || {};

    const currentRoute = route().current();

    return (
        <div className="min-h-screen bg-gray-100 flex">
            {/* Sidebar */}
            <div className="flex flex-shrink-0">
                <div className="flex flex-col w-64">
                    <div className="flex flex-col h-0 flex-1 bg-gray-800">
                        <div className="flex items-center h-16 flex-shrink-0 px-4 bg-gray-900">
                            <Link href="/dashboard" className="flex items-center space-x-2">
                                <img 
                                    src={logo} 
                                    alt="Logo" 
                                    className="h-8 w-auto object-contain"
                                />
                                <span className="text-white font-semibold">SMS</span>
                            </Link>
                        </div>
                        <div className="flex-1 flex flex-col overflow-y-auto">
                            <nav className="flex-1 px-2 py-4 space-y-1">
                                <NavigationSidebar currentRoute={currentRoute} />
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            {/* Main content */}
            <div className="flex flex-col w-0 flex-1 overflow-hidden">
                {/* Top bar */}
                <header className="bg-white shadow">
                    <div className="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
                        <div className="flex items-center">
                            <h1 className="text-lg font-semibold text-gray-900">Schedule Management System</h1>
                        </div>
                        <div className="flex items-center space-x-4">
                            <span className="text-sm text-gray-700">
                                {user.name}
                            </span>
                            <Link
                                href={route('logout')}
                                method="post"
                                replace
                                as="button"
                                className="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            >
                                Logout
                            </Link>
                        </div>
                    </div>
                </header>

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

                {/* Page content */}
                <main className="flex-1 overflow-y-auto">
                    <div className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}