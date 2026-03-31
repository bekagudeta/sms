import React from 'react';
import { Link } from '@inertiajs/react';

const navLinks = [
    { href: '/dashboard', label: 'Dashboard' },
    { href: '/students', label: 'Students' },
    { href: '/teachers', label: 'Teachers' },
    { href: '/courses', label: 'Courses' },
    { href: '/schedules', label: 'Schedules' },
];

export default function AuthLayout({ children }) {
    return (
        <div className="app-shell">
            <nav className="border-b border-deep-jungle-green/10 bg-white shadow-sm">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-widest text-vivid-orange">SMS</p>
                        <h1 className="text-lg font-bold text-deep-jungle-green">School Management System</h1>
                    </div>

                    <div className="hidden items-center gap-6 sm:flex">
                        {navLinks.map((link) => (
                            <Link
                                key={link.href}
                                href={link.href}
                                className="text-sm font-medium text-deep-jungle-green/75 transition hover:text-vivid-orange"
                            >
                                {link.label}
                            </Link>
                        ))}
                    </div>
                </div>
            </nav>

            <main className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                {children}
            </main>
        </div>
    );
}