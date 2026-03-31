import React, { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { NAVIGATION_STRUCTURE, getEntityConfig } from '@/config/entities';

export default function NavigationSidebar({ currentRoute }) {
    const [expandedCategories, setExpandedCategories] = useState(new Set(['People', 'Academics', 'Resources', 'Assignments']));
    const { props } = usePage();
    const user = props.auth?.user;
    const userRoles = user?.roles || [];
    const userPermissions = user?.permissions || [];

    const toggleCategory = (category) => {
        const newExpanded = new Set(expandedCategories);
        if (newExpanded.has(category)) {
            newExpanded.delete(category);
        } else {
            newExpanded.add(category);
        }
        setExpandedCategories(newExpanded);
    };

    const hasPermission = (permission) => {
        // Check if user has the required permission
        if (!user) return false;
        
        // Admin has all permissions
        if (userRoles.some(role => role.name === 'admin')) {
            return true;
        }
        
        // Check specific role-based permissions
        if (userRoles.some(role => role.name === 'scheduler')) {
            const schedulerPermissions = [
                'view students', 'create students', 'edit students', 'delete students', 'import students',
                'view teachers', 'create teachers', 'edit teachers', 'delete teachers', 'import teachers',
                'view courses', 'create courses', 'edit courses', 'delete courses', 'import courses', 'import course-offerings', 'import sections',
                'view rooms', 'create rooms', 'edit rooms', 'delete rooms', 'import rooms',
                'view timeslots', 'create timeslots', 'edit timeslots', 'delete timeslots', 'import timeslots',
                'view enrollments', 'create enrollments', 'edit enrollments', 'delete enrollments', 'import enrollments', 'import section-teachers',
                'view schedules', 'create schedules', 'edit schedules', 'delete schedules',
                'import data', 'export schedule', 'generate schedule'
            ];
            return schedulerPermissions.includes(permission) || userPermissions.includes(permission);
        }
        
        if (userRoles.some(role => role.name === 'teacher')) {
            const teacherPermissions = [
                'view schedules', 'view own schedule', 'view courses', 'view rooms', 'view timeslots'
            ];
            return teacherPermissions.includes(permission) || userPermissions.includes(permission);
        }
        
        if (userRoles.some(role => role.name === 'student')) {
            const studentPermissions = [
                'view schedules', 'view own schedule', 'view courses', 'view rooms', 'view timeslots'
            ];
            return studentPermissions.includes(permission) || userPermissions.includes(permission);
        }
        
        // Check direct permissions
        return userPermissions.includes(permission);
    };

    const getIcon = (iconName) => {
        const icons = {
            Users: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            ),
            User: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            ),
            UserCheck: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            ),
            BookOpen: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            ),
            Book: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            ),
            Calendar: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            ),
            Grid: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
            ),
            MapPin: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            ),
            Home: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            ),
            Clock: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            ),
            UserPlus: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
            ),
            Dashboard: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            ),
            CalendarAlt: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            ),
            Cog: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            )
        };

        return icons[iconName] || icons.Book;
    };

    const isActive = (route) => {
        return currentRoute === route || currentRoute?.startsWith(route + '/');
    };

    return (
        <nav className="space-y-1.5">
            {/* Dashboard */}
            <Link
                href="/dashboard"
                className={`group flex items-center rounded-xl px-3 py-2 text-sm font-medium transition ${
                    isActive('/dashboard')
                        ? 'bg-vivid-orange text-rich-black shadow-sm'
                        : 'text-platinum/80 hover:bg-white/10 hover:text-white'
                }`}
            >
                {getIcon('Dashboard')}
                <span className="ml-3">Dashboard</span>
            </Link>

            {/* Schedules */}
            {(hasPermission('view schedules') || hasPermission('generate schedule')) && (
                <Link
                    href="/schedules"
                    className={`group flex items-center rounded-xl px-3 py-2 text-sm font-medium transition ${
                        isActive('/schedules')
                            ? 'bg-vivid-orange text-rich-black shadow-sm'
                            : 'text-platinum/80 hover:bg-white/10 hover:text-white'
                    }`}
                >
                    {getIcon('CalendarAlt')}
                    <span className="ml-3">Schedules</span>
                </Link>
            )}

            {/* Schedule Generation - Scheduler specific */}
            {hasPermission('generate schedule') && (
                <Link
                    href="/schedules/generate"
                    className={`group flex items-center rounded-xl px-3 py-2 text-sm font-medium transition ${
                        isActive('/schedules/generate')
                            ? 'bg-vivid-orange text-rich-black shadow-sm'
                            : 'text-platinum/80 hover:bg-white/10 hover:text-white'
                    }`}
                >
                    {getIcon('Calendar')}
                    <span className="ml-3">Generate Schedule</span>
                </Link>
            )}

            {/* Import/Export - Scheduler specific */}
            {(hasPermission('import data') || hasPermission('export schedule')) && (
                <div className="space-y-1">
                    <div className="px-3 py-1 text-xs font-semibold uppercase tracking-wider text-platinum/55">
                        Data Management
                    </div>
                    {hasPermission('import data') && (
                        <Link
                            href="/import"
                            className={`group flex items-center rounded-xl px-3 py-2 text-sm font-medium transition ${
                                isActive('/import')
                                    ? 'bg-vivid-orange text-rich-black shadow-sm'
                                    : 'text-platinum/75 hover:bg-white/10 hover:text-white'
                            }`}
                        >
                            {getIcon('BookOpen')}
                            <span className="ml-3">Import Data</span>
                        </Link>
                    )}
                    {hasPermission('export schedule') && (
                        <a
                            href="/export/schedule"
                            className={`group flex items-center rounded-xl px-3 py-2 text-sm font-medium transition ${
                                isActive('/export')
                                    ? 'bg-vivid-orange text-rich-black shadow-sm'
                                    : 'text-platinum/75 hover:bg-white/10 hover:text-white'
                            }`}
                        >
                            {getIcon('Book')}
                            <span className="ml-3">Export Schedule</span>
                        </a>
                    )}
                </div>
            )}

            {/* Entity Categories - Only show for admin */}
            {userRoles.some(role => role.name === 'admin') && NAVIGATION_STRUCTURE.map((category) => {
                const isExpanded = expandedCategories.has(category.category);
                const hasVisibleItems = category.items.some(item => {
                    const config = getEntityConfig(item.key);
                    return hasPermission(config.permissions.view);
                });

                if (!hasVisibleItems) return null;

                return (
                    <div key={category.category}>
                        <button
                            onClick={() => toggleCategory(category.category)}
                            className="w-full group flex items-center rounded-xl px-3 py-2 text-sm font-medium text-platinum/80 transition hover:bg-white/10 hover:text-white"
                        >
                            {getIcon(category.icon)}
                            <span className="ml-3">{category.category}</span>
                            <svg
                                className={`ml-auto h-4 w-4 text-gray-400 transition-transform ${
                                    isExpanded ? 'transform rotate-90' : ''
                                }`}
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                            </svg>
                        </button>

                        {isExpanded && (
                            <div className="mt-1 space-y-1">
                                {category.items.map((item) => {
                                    const config = getEntityConfig(item.key);
                                    if (!config || !config.permissions || !hasPermission(config.permissions.view)) return null;

                                    const route = `/${config.routePrefix}`;

                                    return (
                                        <Link
                                            key={item.key}
                                            href={route}
                                            className={`group flex items-center pl-10 pr-3 py-2 text-sm font-medium rounded-md ${
                                                isActive(route)
                                                    ? 'bg-vivid-orange text-rich-black shadow-sm'
                                                    : 'text-platinum/75 hover:bg-white/10 hover:text-white'
                                            }`}
                                        >
                                            {getIcon(item.icon)}
                                            <span className="ml-3">{item.name}</span>
                                        </Link>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                );
            })}

            {/* Settings */}
            <Link
                href="/settings"
                className={`group flex items-center px-3 py-2 text-sm font-medium rounded-md ${
                    isActive('/settings')
                        ? 'bg-vivid-orange text-rich-black shadow-sm'
                        : 'text-platinum/80 hover:bg-white/10 hover:text-white'
                }`}
            >
                {getIcon('Cog')}
                <span className="ml-3">Settings</span>
            </Link>
        </nav>
    );
}
