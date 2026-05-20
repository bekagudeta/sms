import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import logo from '../images/logo.png';
import NavigationSidebar from '@/Components/NavigationSidebar';

export default function DashboardLayout({ children }) {
    const { props } = usePage();
    const user = props.auth.user || {};
    const flash = props.flash || {};
    const currentRoute = route().current();

    return (
        <div className="app-shell lg:flex">
            <aside className="w-full shrink-0 bg-primary text-white shadow-2xl lg:min-h-screen lg:w-72">
                <div className="flex items-center gap-3 border-b border-white/10 bg-primary-dark px-5 py-4">
                    <Link href="/dashboard" className="flex items-center gap-3">
                        <img src={logo} alt="Logo" className="h-10 w-auto object-contain" />
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-widest text-success-light">SMS</p>
                            <p className="text-sm font-semibold text-white">Schedule Management</p>
                        </div>
                    </Link>
                </div>

                <div className="px-3 py-4">
                    <NavigationSidebar currentRoute={currentRoute} />
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-30 border-b border-primary/10 bg-white/95 backdrop-blur">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-widest text-primary">Dashboard</p>
                            <h1 className="text-lg font-bold text-primary">Schedule Management System</h1>
                        </div>

                        <div className="flex items-center gap-3">
                            <div className="hidden text-right sm:block">
                                <p className="text-sm font-semibold text-primary">{user.name}</p>
                                <p className="text-xs text-primary/60">Signed in</p>
                            </div>
                            <Link
                                href={route('logout')}
                                method="post"
                                replace
                                as="button"
                                className="app-primary-btn !px-3 !py-2"
                            >
                                Logout
                            </Link>
                        </div>
                    </div>
                </header>

                <main className="flex-1 overflow-y-auto px-4 py-6 sm:px-6 lg:px-8">
                    <div className="mx-auto max-w-7xl space-y-4">
                        {flash.success && (
                            <div className="rounded-2xl border border-success/30 bg-white px-4 py-3 text-sm text-primary shadow-sm">
                                <span className="mr-2 font-semibold text-success">Success:</span>
                                {flash.success}
                            </div>
                        )}

                        {flash.error && (
                            <div className="rounded-2xl border border-primary/20 bg-white px-4 py-3 text-sm text-primary shadow-sm">
                                <span className="mr-2 font-semibold text-primary-dark">Notice:</span>
                                {flash.error}
                            </div>
                        )}

                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
