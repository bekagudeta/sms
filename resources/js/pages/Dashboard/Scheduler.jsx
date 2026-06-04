import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import ScheduleTable from '@/Components/ScheduleTable';

function ActionIcon({ children, className = '' }) {
    return (
        <span
            className={`inline-flex h-12 w-12 items-center justify-center rounded-xl bg-white/15 ${className}`}
            aria-hidden
        >
            {children}
        </span>
    );
}

const QUICK_ACTIONS = [
    {
        permission: 'generate schedule',
        href: () => route('schedules.generate.show'),
        title: 'Generate Schedule',
        description: 'Run the auto-scheduler for the active semester',
        className: 'app-primary-btn flex-col !h-auto !w-full !px-6 !py-6 text-center',
        icon: (
            <svg className="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
        ),
    },
    {
        permission: 'import data',
        href: () => route('import.index'),
        title: 'Import Data',
        description: 'Upload Excel templates for students, sections, and more',
        className: 'app-secondary-btn flex-col !h-auto !w-full !px-6 !py-6 text-center',
        icon: (
            <svg className="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
        ),
        linkComponent: Link,
    },
    {
        permission: 'export schedule',
        href: () => route('export.schedule'),
        title: 'Export Schedule',
        description: 'Download the current timetable as Excel',
        className: 'app-secondary-btn flex-col !h-auto !w-full !px-6 !py-6 text-center',
        icon: (
            <svg className="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
        ),
        linkComponent: 'a',
    },
    {
        permission: 'view schedule',
        altPermission: 'view schedules',
        href: () => '/schedules',
        title: 'View Schedules',
        description: 'Browse and review all generated class schedules',
        className: 'app-secondary-btn flex-col !h-auto !w-full !px-6 !py-6 text-center',
        icon: (
            <svg className="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
        ),
        linkComponent: Link,
    },
];

export default function SchedulerDashboard({ stats = {}, recentSchedules = [], activeSemester = null }) {
    const permissions = usePage().props.auth?.permissions ?? [];

    const can = (name, alt) => {
        if (permissions.includes(name)) return true;
        if (alt && permissions.includes(alt)) return true;
        return false;
    };

    const visibleActions = QUICK_ACTIONS.filter((action) =>
        can(action.permission, action.altPermission)
    );

    const statCards = [
        {
            label: 'Total Schedules',
            value: stats.total_schedules ?? 0,
            hint: 'Assigned class meetings',
        },
        {
            label: 'Class Sections',
            value: stats.total_sections ?? 0,
            hint: 'Sections in the system',
        },
        {
            label: 'Needs Scheduling',
            value: stats.unscheduled_sections ?? 0,
            hint: 'Sections without a timetable slot',
        },
    ];

    return (
        <div className="space-y-8">
            <div className="app-panel overflow-hidden">
                <div className="border-b border-deep-jungle-green/10 bg-deep-jungle-green px-8 py-7 text-platinum">
                    <p className="mb-2 text-xs font-semibold uppercase tracking-widest text-vivid-orange">
                        Scheduling workspace
                    </p>
                    <h2 className="text-3xl font-bold">Scheduler Dashboard</h2>
                    <p className="mt-2 text-platinum/80">
                        Generate timetables, import preparation data, and export results
                        {activeSemester
                            ? ` — ${activeSemester.name}${activeSemester.academic_year ? ` (${activeSemester.academic_year})` : ''}`
                            : ''}
                        .
                    </p>
                </div>

                <div className="grid gap-4 border-b border-deep-jungle-green/10 bg-platinum/30 p-8 sm:grid-cols-3">
                    {statCards.map((card) => (
                        <div key={card.label} className="app-panel-muted p-5">
                            <p className="text-xs font-semibold uppercase tracking-wider text-deep-jungle-green/60">
                                {card.label}
                            </p>
                            <p className="mt-2 text-3xl font-bold text-deep-jungle-green">{card.value}</p>
                            <p className="mt-1 text-sm text-deep-jungle-green/70">{card.hint}</p>
                        </div>
                    ))}
                </div>

                {visibleActions.length > 0 && (
                    <div className="border-b border-deep-jungle-green/10 px-8 py-6">
                        <h3 className="text-lg font-bold text-deep-jungle-green">Quick actions</h3>
                        <p className="mt-1 text-sm text-deep-jungle-green/70">
                            Scheduler tools — import, generate, export, and review timetables.
                        </p>
                        <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            {visibleActions.map((action) => {
                                const LinkTag = action.linkComponent === 'a' ? 'a' : Link;
                                const linkProps =
                                    action.linkComponent === 'a'
                                        ? { href: action.href() }
                                        : { href: action.href() };

                                return (
                                    <LinkTag
                                        key={action.title}
                                        {...linkProps}
                                        className={`${action.className} gap-3 no-underline`}
                                    >
                                        <ActionIcon>{action.icon}</ActionIcon>
                                        <span className="text-base font-semibold">{action.title}</span>
                                        <span className="text-xs font-normal opacity-80">{action.description}</span>
                                    </LinkTag>
                                );
                            })}
                        </div>
                    </div>
                )}

                <div className="p-8">
                    <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 className="text-2xl font-bold text-deep-jungle-green">Recent Schedules</h3>
                            <p className="mt-1 text-sm text-deep-jungle-green/70">
                                Latest timetable entries — open the full list to manage them.
                            </p>
                        </div>
                        {(can('view schedule', 'view schedules') || can('generate schedule')) && (
                            <Link href="/schedules" className="app-secondary-btn">
                                View all schedules
                            </Link>
                        )}
                    </div>

                    {recentSchedules.length > 0 ? (
                        <ScheduleTable
                            schedules={recentSchedules}
                            compact
                            actionColumn={
                                can('view schedule', 'view schedules') || can('generate schedule')
                                    ? (schedule) => (
                                          <Link
                                              href={`/schedules/${schedule.id}`}
                                              className="text-sm font-semibold text-deep-jungle-green underline-offset-2 hover:underline"
                                          >
                                              View
                                          </Link>
                                      )
                                    : null
                            }
                        />
                    ) : (
                        <div className="app-panel-muted px-6 py-10 text-center">
                            <p className="text-deep-jungle-green/80">No schedules yet.</p>
                            {can('generate schedule') && (
                                <Link
                                    href={route('schedules.generate.show')}
                                    className="app-primary-btn mt-4 inline-flex"
                                >
                                    Generate your first schedule
                                </Link>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
