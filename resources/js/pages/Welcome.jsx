import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import logoSrc from '../images/logo.png';
import heroBg from '../images/hero-campus.jpg';

const CURRENT_YEAR = new Date().getFullYear();

const FEATURES = [
  {
    icon: (
      <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
      </svg>
    ),
    title: 'Bulk Excel Import',
    desc: 'Import 11 academic entity types—from departments and semesters to enrollments—with guided templates and validation.',
  },
  {
    icon: (
      <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
      </svg>
    ),
    title: 'Automated Timetables',
    desc: 'Generate course schedules that assign teachers, rooms, and timeslots while respecting capacity and availability.',
  },
  {
    icon: (
      <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
      </svg>
    ),
    title: 'Role-Based Access',
    desc: 'Separate experiences for admins, schedulers, teachers, and students—each sees only what their role allows.',
  },
  {
    icon: (
      <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
      </svg>
    ),
    title: 'Exports & Insights',
    desc: 'Export schedules and credentials to Excel, and monitor students, courses, and assignments from one dashboard.',
  },
];

const STEPS = [
  {
    num: '1',
    title: 'Import your data',
    details: 'Upload Excel files for departments, courses, teachers, rooms, students, and more using built-in templates.',
  },
  {
    num: '2',
    title: 'Configure resources',
    details: 'Set semesters, sections, timeslots, and teacher assignments so the scheduler has everything it needs.',
  },
  {
    num: '3',
    title: 'Generate schedules',
    details: 'Run schedule generation, review assignments, and export timetables for your institution.',
  },
];

const ROLE_HIGHLIGHTS = [
  {
    role: 'For registrars',
    initials: 'AD',
    title: 'One source of truth',
    desc: 'Manage students, teachers, courses, and rooms in one place instead of spreadsheets that drift out of sync.',
  },
  {
    role: 'For schedulers',
    initials: 'SC',
    title: 'Conflict-aware timetables',
    desc: 'Guided imports validate your data, then schedule generation assigns teachers, rooms, and timeslots automatically.',
  },
  {
    role: 'For teachers & students',
    initials: 'TS',
    title: 'The right view per role',
    desc: 'Teachers see their own sections and students; students see their own schedules—no extra IT overhead.',
  },
];

const FAQ_ITEMS = [
  {
    q: 'What can I import into SMS?',
    a: 'Eleven entity types: departments, semesters, courses, course offerings, sections, teachers, section teachers, rooms, timeslots, students, and enrollments—each with documented Excel columns.',
  },
  {
    q: 'Who can use the platform?',
    a: 'Admins manage all data and users. Schedulers generate timetables. Teachers view schedules and their students. Students view their own schedules.',
  },
  {
    q: 'Do I need a credit card to start?',
    a: 'No. Create a free account, sign in, and explore the dashboard. Your institution can add data through imports when ready.',
  },
];

function DashboardPreview() {
  const rows = [
    { course: 'CS101', teacher: 'Dr. Chen', room: 'Lab A', day: 'Mon', time: '09:00–10:30' },
    { course: 'MATH201', teacher: 'Prof. Ali', room: 'R-204', day: 'Tue', time: '11:00–12:30' },
    { course: 'ENG110', teacher: 'Ms. Park', room: 'R-101', day: 'Wed', time: '14:00–15:30' },
  ];

  return (
    <div className="w-full max-w-4xl overflow-hidden rounded-2xl border border-white/25 bg-white shadow-2xl shadow-black/30">
      <div className="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3">
        <span className="h-3 w-3 rounded-full bg-red-400" />
        <span className="h-3 w-3 rounded-full bg-amber-400" />
        <span className="h-3 w-3 rounded-full bg-emerald-400" />
        <span className="ml-3 text-xs font-medium text-gray-500">SMS Dashboard — Schedule overview</span>
      </div>
      <div className="grid gap-3 bg-white p-4 sm:grid-cols-4 sm:p-5">
        {[
          { label: 'Students', value: '1,248', color: 'from-primary to-primary-dark' },
          { label: 'Teachers', value: '86', color: 'from-success to-success-dark' },
          { label: 'Courses', value: '142', color: 'from-primary-accent to-primary' },
          { label: 'Schedules', value: '318', color: 'from-primary-dark to-primary' },
        ].map((stat) => (
          <div key={stat.label} className={`rounded-xl bg-gradient-to-br ${stat.color} p-3 text-white`}>
            <p className="text-[10px] font-semibold uppercase tracking-wide text-white/80">{stat.label}</p>
            <p className="text-xl font-bold">{stat.value}</p>
          </div>
        ))}
      </div>
      <div className="border-t border-gray-100 px-4 pb-4 sm:px-5">
        <table className="w-full text-left text-xs text-gray-600">
          <thead>
            <tr className="border-b border-gray-100 text-[10px] font-semibold uppercase tracking-wide text-gray-400">
              <th className="py-2 pr-2">Course</th>
              <th className="py-2 pr-2">Teacher</th>
              <th className="py-2 pr-2">Room</th>
              <th className="hidden py-2 pr-2 sm:table-cell">Day</th>
              <th className="py-2">Time</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.course} className="border-b border-gray-50 last:border-0">
                <td className="py-2.5 pr-2 font-semibold text-primary">{row.course}</td>
                <td className="py-2.5 pr-2">{row.teacher}</td>
                <td className="py-2.5 pr-2">{row.room}</td>
                <td className="hidden py-2.5 pr-2 sm:table-cell">{row.day}</td>
                <td className="py-2.5 text-gray-500">{row.time}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default function Welcome({ auth }) {
  const [mobileOpen, setMobileOpen] = useState(false);
  const [openFaq, setOpenFaq] = useState(0);

  const primaryCta = auth?.user ? (
    <Link href="/dashboard" className="app-primary-btn rounded-full px-8 py-3 text-base shadow-lg shadow-primary/30">
      Go to Dashboard
    </Link>
  ) : (
    <Link
      href="/login"
      className="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-success to-primary px-8 py-3 text-base font-bold text-white shadow-lg shadow-success/25 transition hover:-translate-y-0.5 hover:brightness-105"
    >
      Sign in
    </Link>
  );

  const secondaryCta = auth?.user ? (
    <Link href="/schedules" className="rounded-full border-2 border-white/60 bg-white/10 px-8 py-3 text-base font-semibold text-white backdrop-blur transition hover:bg-white/20">
      View schedules
    </Link>
  ) : (
    <Link href="/login" className="rounded-full border-2 border-white/60 bg-white/10 px-8 py-3 text-base font-semibold text-white backdrop-blur transition hover:bg-white/20">
      Sign in
    </Link>
  );

  return (
    <div className="min-h-screen bg-gray-50 text-primary">
      {/* Header */}
      <header className="sticky top-0 z-50 border-b border-primary/10 bg-white/90 backdrop-blur-lg">
        <div className="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-4">
          <Link href="/" className="flex items-center gap-3">
            <img src={logoSrc} alt="SMS logo" className="h-10 w-10 rounded-full border border-primary/20 shadow-sm" />
            <div>
              <p className="text-lg font-bold leading-tight text-primary">Scheduling Management System</p>
              <p className="text-xs font-semibold uppercase tracking-widest text-success">SMS</p>
            </div>
          </Link>

          <button
            type="button"
            aria-label="Toggle menu"
            onClick={() => setMobileOpen((open) => !open)}
            className="rounded-lg border border-primary/20 px-3 py-2 text-primary md:hidden"
          >
            {mobileOpen ? '✕' : '☰'}
          </button>

          <nav className="hidden items-center gap-8 text-sm font-medium text-primary/80 md:flex">
            <a href="#features" className="transition hover:text-primary">Features</a>
            <a href="#how-it-works" className="transition hover:text-primary">How it works</a>
            <a href="#testimonials" className="transition hover:text-primary">For teams</a>
            <a href="#faq" className="transition hover:text-primary">FAQ</a>
          </nav>

          <div className="hidden md:block">
            {auth?.user ? (
              <Link href="/dashboard" className="app-primary-btn rounded-full px-6 py-2">
                Dashboard
              </Link>
            ) : (
              <Link href="/login" className="app-secondary-btn rounded-full px-6 py-2">
                Login
              </Link>
            )}
          </div>
        </div>

        {mobileOpen && (
          <div className="border-t border-primary/10 bg-white px-6 py-4 md:hidden">
            <div className="space-y-1">
              {[
                { href: '#features', label: 'Features' },
                { href: '#how-it-works', label: 'How it works' },
                { href: '#testimonials', label: 'For teams' },
                { href: '#faq', label: 'FAQ' },
              ].map(({ href, label }) => (
                <a
                  key={href}
                  href={href}
                  className="block rounded-lg px-3 py-2 font-medium text-primary hover:bg-gray-50"
                  onClick={() => setMobileOpen(false)}
                >
                  {label}
                </a>
              ))}
              <Link
                href={auth?.user ? '/dashboard' : '/login'}
                className="mt-2 block rounded-lg bg-primary px-3 py-2 text-center font-semibold text-white"
                onClick={() => setMobileOpen(false)}
              >
                {auth?.user ? 'Dashboard' : 'Login'}
              </Link>
            </div>
          </div>
        )}
      </header>

      {/* Hero */}
      <section id="hero" className="relative min-h-[85vh] overflow-hidden text-white">
        <div
          className="absolute inset-0 bg-cover bg-center bg-no-repeat"
          style={{ backgroundImage: `url(${heroBg})` }}
          aria-hidden="true"
        />
        <div className="absolute inset-0 bg-gradient-to-br from-primary/90 via-primary-dark/85 to-primary-bright/75" aria-hidden="true" />
        <div
          className="absolute inset-0 opacity-30"
          style={{
            backgroundImage:
              'radial-gradient(circle at 20% 80%, #82d3ee 0%, transparent 50%), radial-gradient(circle at 80% 20%, #20ae4d 0%, transparent 40%)',
          }}
          aria-hidden="true"
        />
        <div className="relative z-10 mx-auto flex w-full max-w-7xl flex-col items-center gap-10 px-6 py-16 lg:flex-row lg:items-center lg:py-24">
          <div className="flex-1 text-center lg:text-left">
            <p className="inline-flex rounded-full border border-white/30 bg-white/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-light-bg">
              Academic scheduling & administration
            </p>
            <h1 className="mt-5 text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl lg:text-6xl">
              Run your school schedules with less chaos.
            </h1>
            <p className="mt-5 max-w-xl text-base leading-relaxed text-white/90 sm:text-lg">
              SMS helps registrars and scheduling teams manage students, teachers, courses, rooms, and timetables in one
              place—from bulk Excel imports to automated schedule generation.
            </p>
            <div className="mt-8 flex flex-wrap justify-center gap-4 lg:justify-start">
              {primaryCta}
              {secondaryCta}
            </div>
            <p className="mt-4 text-sm text-white/70">Free to get started · No credit card required</p>
          </div>
          <div className="flex w-full flex-1 justify-center lg:justify-end">
            <DashboardPreview />
          </div>
        </div>
      </section>

      {/* Social proof */}
      <section className="border-b border-primary/10 bg-white py-8">
        <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 px-6 sm:flex-row">
          <div className="flex flex-wrap items-center justify-center gap-x-10 gap-y-3 text-center sm:justify-start">
            <div>
              <p className="text-2xl font-bold text-primary">11</p>
              <p className="text-xs font-medium text-primary/60">Import entity types</p>
            </div>
            <div>
              <p className="text-2xl font-bold text-primary">4</p>
              <p className="text-xs font-medium text-primary/60">User roles</p>
            </div>
            <div>
              <p className="text-2xl font-bold text-primary">1</p>
              <p className="text-xs font-medium text-primary/60">Unified dashboard</p>
            </div>
          </div>
          <div className="flex items-center gap-3 text-center sm:text-right">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-success/10 text-success" aria-hidden="true">
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <p className="max-w-xs text-sm text-primary/70">
              <span className="font-semibold text-primary">Built for schools and colleges</span> that need reliable
              timetables without juggling dozens of spreadsheets.
            </p>
          </div>
        </div>
      </section>

      {/* Problem → Solution */}
      <section className="mx-auto max-w-7xl px-6 py-16">
        <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
          <div>
            <h2 className="text-3xl font-bold text-primary sm:text-4xl">Sound familiar?</h2>
            <ul className="mt-6 space-y-4">
              {[
                'Student and course data scattered across spreadsheets that never stay in sync.',
                'Manual timetabling that takes weeks and still produces room or teacher conflicts.',
                'No clear way for teachers and students to see schedules after they are published.',
              ].map((pain) => (
                <li key={pain} className="flex gap-3 text-primary/80">
                  <span className="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-100 text-sm text-red-600">
                    ✕
                  </span>
                  <span>{pain}</span>
                </li>
              ))}
            </ul>
          </div>
          <div className="rounded-2xl border border-primary/10 bg-gradient-to-br from-white to-light-bg/30 p-8 shadow-lg">
            <p className="text-sm font-semibold uppercase tracking-wide text-success">That is why we built SMS</p>
            <h3 className="mt-2 text-2xl font-bold text-primary">One platform for your academic operations</h3>
            <p className="mt-4 leading-relaxed text-primary/75">
              Import your institutional data in the right order, assign teachers and rooms, generate conflict-aware
              schedules, and give every role a tailored view—from admin dashboards to teacher and student portals.
            </p>
            <Link href={auth?.user ? '/import' : '/login'} className="app-primary-btn mt-6 inline-flex">
              {auth?.user ? 'Open import center' : 'Sign in'}
            </Link>
          </div>
        </div>
      </section>

      {/* Features */}
      <section id="features" className="bg-white py-16">
        <div className="mx-auto max-w-7xl px-6">
          <div className="text-center">
            <h2 className="text-3xl font-bold text-primary sm:text-4xl">Everything you need to schedule smarter</h2>
            <p className="mx-auto mt-3 max-w-2xl text-primary/65">
              Purpose-built tools for registrars, schedulers, and academic administrators—not generic project management.
            </p>
          </div>
          <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {FEATURES.map((item) => (
              <div
                key={item.title}
                className="group rounded-2xl border border-primary/10 bg-gray-50 p-6 transition hover:border-primary/25 hover:bg-white hover:shadow-lg"
              >
                <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary transition group-hover:bg-primary group-hover:text-white">
                  {item.icon}
                </div>
                <h3 className="text-lg font-bold text-primary">{item.title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-primary/70">{item.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* How it works */}
      <section id="how-it-works" className="border-y border-primary/10 bg-gradient-to-b from-gray-50 to-white py-16">
        <div className="mx-auto max-w-7xl px-6">
          <div className="text-center">
            <h2 className="text-3xl font-bold text-primary sm:text-4xl">How it works</h2>
            <p className="mt-2 text-primary/65">Three straightforward steps—no complex setup wizard</p>
          </div>
          <div className="mt-12 grid gap-8 md:grid-cols-3">
            {STEPS.map((item) => (
              <div key={item.num} className="relative rounded-2xl border border-primary/10 bg-white p-8 text-center shadow-sm">
                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-primary to-success text-2xl font-black text-white">
                  {item.num}
                </div>
                <h3 className="mt-5 text-xl font-bold text-primary">{item.title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-primary/70">{item.details}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Built for every role */}
      <section id="testimonials" className="py-16">
        <div className="mx-auto max-w-7xl px-6">
          <div className="text-center">
            <h2 className="text-3xl font-bold text-primary sm:text-4xl">Built for every role on campus</h2>
            <p className="mt-2 text-primary/65">One platform, tailored to how each team actually works</p>
          </div>
          <div className="mt-10 grid gap-6 lg:grid-cols-3">
            {ROLE_HIGHLIGHTS.map((item) => (
              <div
                key={item.title}
                className="flex flex-col rounded-2xl border border-primary/10 bg-white p-6 shadow-sm"
              >
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary">
                    {item.initials}
                  </div>
                  <p className="text-xs font-semibold uppercase tracking-wide text-success">{item.role}</p>
                </div>
                <h3 className="mt-4 text-lg font-bold text-primary">{item.title}</h3>
                <p className="mt-2 flex-1 text-sm leading-relaxed text-primary/75">{item.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Security (honest) */}
      <section className="mx-auto max-w-7xl px-6 pb-16">
        <div className="rounded-2xl bg-gradient-to-r from-primary to-primary-dark p-8 text-white sm:p-10">
          <div className="max-w-3xl">
            <h2 className="text-2xl font-bold sm:text-3xl">Secure access for your institution</h2>
            <p className="mt-3 text-white/85">
              SMS uses Laravel authentication with role-based permissions so admins, schedulers, teachers, and students
              each access only the data and actions appropriate to their role.
            </p>
          </div>
          <div className="mt-8 grid gap-4 sm:grid-cols-3">
            {[
              { title: 'Role-based permissions', desc: 'Admin, scheduler, teacher, and student roles with granular capabilities.' },
              { title: 'Authenticated sessions', desc: 'Secure login, registration, and password reset flows built in.' },
              { title: 'Audit-friendly workflows', desc: 'Structured imports and exports keep data changes traceable.' },
            ].map((item) => (
              <div key={item.title} className="rounded-xl border border-white/20 bg-white/10 p-5 backdrop-blur">
                <h3 className="font-semibold">{item.title}</h3>
                <p className="mt-1 text-sm text-white/80">{item.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Pricing teaser */}
      <section className="border-t border-primary/10 bg-light-bg/20 py-12">
        <div className="mx-auto max-w-3xl px-6 text-center">
          <h2 className="text-2xl font-bold text-primary">Managed access</h2>
          <p className="mt-3 text-primary/70">
            Accounts are provisioned by your administrator or via bulk import—sign in to explore the dashboard and
            manage your institution's data.
          </p>
          {!auth?.user && (
            <Link href="/login" className="app-primary-btn mt-6 inline-flex rounded-full px-8">
              Sign in
            </Link>
          )}
        </div>
      </section>

      {/* FAQ */}
      <section id="faq" className="py-16">
        <div className="mx-auto max-w-3xl px-6">
          <h2 className="text-center text-3xl font-bold text-primary">Frequently asked questions</h2>
          <div className="mt-8 space-y-3">
            {FAQ_ITEMS.map((item, index) => (
              <div key={item.q} className="overflow-hidden rounded-xl border border-primary/10 bg-white">
                <button
                  type="button"
                  className="flex w-full items-center justify-between px-5 py-4 text-left font-semibold text-primary"
                  onClick={() => setOpenFaq(openFaq === index ? -1 : index)}
                  aria-expanded={openFaq === index}
                >
                  {item.q}
                  <span className="text-primary/50">{openFaq === index ? '−' : '+'}</span>
                </button>
                {openFaq === index && (
                  <p className="border-t border-gray-100 px-5 pb-4 text-sm leading-relaxed text-primary/75">{item.a}</p>
                )}
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Final CTA */}
      <section className="bg-gradient-to-r from-primary via-primary-dark to-success py-16 text-white">
        <div className="mx-auto max-w-3xl px-6 text-center">
          <h2 className="text-3xl font-bold sm:text-4xl">Ready to take control of your academic schedule?</h2>
          <p className="mt-4 text-white/90">
            Join institutions that centralize students, courses, and timetables in SMS—starting takes minutes.
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-4">
            {primaryCta}
            <a href="#features" className="rounded-full border-2 border-white/50 px-8 py-3 font-semibold transition hover:bg-white/10">
              Explore features
            </a>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-primary/10 bg-primary text-white">
        <div className="mx-auto max-w-7xl px-6 py-14">
          <div className="grid gap-10 md:grid-cols-4">
            <div className="md:col-span-2">
              <div className="flex items-center gap-3">
                <img src={logoSrc} alt="" className="h-9 w-9 rounded-full border border-white/20" />
                <div>
                  <p className="font-bold">School Management System</p>
                  <p className="text-xs uppercase tracking-widest text-light-bg">SMS</p>
                </div>
              </div>
              <p className="mt-4 max-w-md text-sm text-white/75">
                Scheduling, imports, and role-based dashboards for schools and colleges that want clarity—not
                spreadsheet sprawl.
              </p>
            </div>
            <div>
              <p className="font-semibold">Product</p>
              <ul className="mt-3 space-y-2 text-sm text-white/75">
                <li>
                  <a href="#features" className="hover:text-white">
                    Features
                  </a>
                </li>
                <li>
                  <a href="#how-it-works" className="hover:text-white">
                    How it works
                  </a>
                </li>
                <li>
                  <Link href={auth?.user ? '/schedules/generate' : '/login'} className="hover:text-white">
                    Schedule generator
                  </Link>
                </li>
              </ul>
            </div>
            <div>
              <p className="font-semibold">Account</p>
              <ul className="mt-3 space-y-2 text-sm text-white/75">
                <li>
                  <Link href="/login" className="hover:text-white">
                    Sign in
                  </Link>
                </li>
                <li>
                  <Link href="/forgot-password" className="hover:text-white">
                    Forgot password
                  </Link>
                </li>
                <li>
                  <a href="#faq" className="hover:text-white">
                    FAQ
                  </a>
                </li>
                <li>
                  <a href="#testimonials" className="hover:text-white">
                    For teams
                  </a>
                </li>
              </ul>
            </div>
          </div>
          <p className="mt-12 border-t border-white/15 pt-8 text-center text-xs text-white/50">
            © {CURRENT_YEAR} Scheduling Management System (SMS). All rights reserved.
          </p>
        </div>
      </footer>
    </div>
  );
}
