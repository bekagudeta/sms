import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import logoSrc from '../images/logo.png';

export default function Welcome({ auth }) {
  const [mobileOpen, setMobileOpen] = useState(false);
  return (
    <div className="min-h-screen bg-gradient-to-b from-[#001722] via-[#084A48] to-[#6BCFCB] text-white">
      <header className="sticky top-0 z-50 border-b border-[#084A48] bg-[#001722]/75 backdrop-blur-lg">
        <div className="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-4">
          <div className="flex items-center gap-3">
            <img src={logoSrc} alt="SMS logo" className="h-10 w-10 rounded-full border border-slate-300" />
            <div>
              <div className="text-xl font-extrabold text-slate-900">Scheduling <span className="text-cyan-500">Management</span> System</div>
              <div className="text-xs font-semibold tracking-wider text-slate-500">SMS</div>
            </div>
          </div>

          <button
            aria-label="Menu"
            onClick={() => setMobileOpen((open) => !open)}
            className="md:hidden rounded-lg border border-cyan-500/40 bg-[#001722]/70 px-3 py-2 text-xl text-cyan-200 hover:bg-[#001722]/90"
          >
            {mobileOpen ? '✕' : '☰'}
          </button>

          <nav className="hidden items-center gap-8 text-base font-medium text-slate-700 md:flex">
            <Link href="#dashboard" className="hover:text-cyan-600">Dashboard</Link>
            <Link href="#features" className="hover:text-cyan-600">Features</Link>
            <Link href="#about" className="hover:text-cyan-600">About</Link>
            <Link href="#contact" className="hover:text-cyan-600">Contact</Link>
          </nav>

          <div>
            {auth?.user ? (
              <Link href="/dashboard" className="rounded-full border-2 border-cyan-600 bg-white px-6 py-2 text-sm font-bold text-cyan-700 hover:bg-cyan-50">
                Dashboard
              </Link>
            ) : (
              <Link href="/login" className="rounded-full border-2 border-cyan-600 bg-white px-6 py-2 text-sm font-bold text-cyan-700 hover:bg-cyan-50">
                Login
              </Link>
            )}
          </div>
        </div>
      </header>

      {mobileOpen && (
        <div className="md:hidden fixed inset-x-0 top-16 z-40 bg-[#001722]/95 border-b border-cyan-500/30 backdrop-blur-md">
          <div className="space-y-2 px-6 py-4">
            <Link href="#dashboard" className="block rounded-lg px-3 py-2 text-base font-semibold text-cyan-200 hover:bg-[#001722]/80" onClick={() => setMobileOpen(false)}>Dashboard</Link>
            <Link href="#features" className="block rounded-lg px-3 py-2 text-base font-semibold text-cyan-200 hover:bg-[#001722]/80" onClick={() => setMobileOpen(false)}>Features</Link>
            <Link href="#about" className="block rounded-lg px-3 py-2 text-base font-semibold text-cyan-200 hover:bg-[#001722]/80" onClick={() => setMobileOpen(false)}>About</Link>
            <Link href="#contact" className="block rounded-lg px-3 py-2 text-base font-semibold text-cyan-200 hover:bg-[#001722]/80" onClick={() => setMobileOpen(false)}>Contact</Link>
          </div>
        </div>
      )}

      <div className="fixed bottom-4 left-1/2 z-40 hidden -translate-x-1/2 md:flex items-center gap-3 rounded-full bg-[#001722]/95 px-5 py-3 text-sm text-white shadow-xl shadow-[#001722]/50 backdrop-blur-md">
        <span className="font-semibold text-cyan-300">Need help fast?</span>
        <Link href="#help" className="rounded-full border border-cyan-500/40 bg-cyan-500/20 px-3 py-1 text-cyan-100 transition hover:bg-cyan-500/40">Visit Help Center</Link>
        <Link href="/login" className="rounded-full border border-[#FE580B] bg-[#FE580B]/20 px-3 py-1 font-semibold text-[#FE580B] transition hover:bg-[#FE580B]/30">Sign in</Link>
      </div>

      <section id="dashboard" className="relative overflow-hidden min-h-[85vh] pt-20 md:pt-24">
        <div className="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1696197018935-fe03c621838f?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1920')] bg-cover bg-center" />
        <div className="absolute inset-0 bg-gradient-to-b from-[#001722]/80 via-[#084A48]/60 to-[#6BCFCB]/30" />
        <div className="relative mx-auto flex w-full max-w-7xl flex-col items-center gap-8 px-6 py-20 text-center sm:py-24 xl:py-28">
          <p className="rounded-full border border-cyan-300/80 bg-cyan-600/20 px-4 py-2 text-sm font-semibold uppercase tracking-wider text-cyan-200">
            Smart Academic Scheduling Platform
          </p>
          <h1 className="text-5xl font-bold leading-tight tracking-tight text-white sm:text-6xl lg:text-7xl">
            Streamline Your <span className="text-cyan-300">Academic Scheduling</span> with Ease
          </h1>
          <p className="max-w-3xl text-base text-slate-200 sm:text-lg lg:text-xl">
            Manage students, teachers, courses, sections, and enrollments all in one powerful platform. Import data seamlessly and create optimized schedules in minutes.
          </p>

          <div className="flex flex-wrap justify-center gap-4">
            <Link href="/register" className="rounded-full bg-gradient-to-r from-[#6BCFCB] to-[#FE580B] px-8 py-3 text-base font-extrabold text-[#001722] shadow-lg shadow-[#6BCFCB]/30 transition hover:-translate-y-0.5 hover:scale-105">
              Get Started Free
            </Link>
            <Link href="/demo" className="rounded-full border border-[#6BCFCB]/80 bg-white/15 px-8 py-3 text-base font-bold text-white hover:border-[#6BCFCB] hover:bg-white/30">
              Watch Demo
            </Link>
          </div>
        </div>
      </section>

      <section id="features" className="mx-auto max-w-7xl px-6 py-16 bg-white/95 backdrop-blur rounded-3xl shadow-2xl my-10 border border-[#6BCFCB]/30">
        <div className="text-center">
          <h2 className="text-4xl font-bold tracking-tight text-[#001722] sm:text-5xl">Everything You Need</h2>
          <h3 className="mt-2 text-4xl font-bold tracking-tight text-[#084A48] sm:text-5xl">
            in <span className="text-[#6BCFCB]">One</span> <span className="text-[#FE580B]">Platform</span>
          </h3>
          <p className="mx-auto mt-4 max-w-3xl text-base text-slate-500 sm:text-lg">Powerful features designed to simplify academic scheduling</p>
        </div>

        <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {[
            { title: 'Bulk Import', desc: 'Import 9 entity types from Excel with drag & drop simplicity.', icon: '⬆️' },
            { title: 'Smart Editor', desc: 'Edit students, teachers, and courses with an intuitive interface.', icon: '✏️' },
            { title: 'Auto-Scheduling', desc: 'AI-powered schedule optimization that saves hours of work.', icon: '🗓️' },
            { title: 'People Management', desc: 'Manage all users centrally with role-based permissions.', icon: '👥' },
            { title: 'Resource Allocation', desc: 'Smart room and timeslot management for optimal utilization.', icon: '🏫' },
            { title: 'Analytics Dashboard', desc: 'Real-time insights into enrollment, capacity, and performance.', icon: '📊' },
          ].map((item, index) => {
            const colorClasses = [
              'from-[#FE580B] to-[#6BCFCB] border-[#FE580B]/40 text-[#001722] bg-gradient-to-br',
              'from-[#6BCFCB] to-[#084A48] border-[#6BCFCB]/40 text-[#001722] bg-gradient-to-br',
              'from-[#084A48] to-[#001722] border-[#084A48]/40 text-white bg-gradient-to-br',
              'from-[#001722] to-[#FE580B] border-[#001722]/40 text-white bg-gradient-to-br',
            ];
            const style = colorClasses[index % colorClasses.length];

            return (
              <div key={item.title} className={`group rounded-2xl border p-7 shadow-lg transition transform duration-300 hover:-translate-y-1 hover:shadow-[0_25px_50px_-12px_rgba(15,23,42,0.3)] ${style}`}>
                <div className="mb-6 inline-flex h-14 w-14 items-center justify-center rounded-xl bg-white/20 text-2xl shadow-md text-white transition duration-300 group-hover:scale-110">
                  {item.icon}
                </div>
                <h3 className="text-2xl font-bold">{item.title}</h3>
                <p className="mt-3 text-sm leading-relaxed">{item.desc}</p>
                <span className="mt-5 inline-flex items-center text-sm font-semibold underline decoration-white/60 transition-colors duration-300 group-hover:text-white">
                  Learn more <span className="ml-1">→</span>
                </span>
              </div>
            );
          })}
        </div>
      </section>

      <section id="how-it-works" className="mx-auto max-w-7xl px-6 py-12">
        <h2 className="text-center text-4xl font-bold text-white">How it Works</h2>
        <p className="mx-auto mt-2 max-w-xl text-center text-slate-300">Three simple steps to perfect schedules</p>

        <div className="mt-10 grid gap-6 sm:grid-cols-3">
          {[
            { num: '01', title: 'Upload', details: 'Drag & drop your files, or connect SIS automatically.' },
            { num: '02', title: 'Validate & Map', details: 'Auto-detect conflicts, set priorities, and update rules.' },
            { num: '03', title: 'Generate', details: 'Deliver fully optimized, conflict-free schedules fast.' },
          ].map((item) => (
            <div key={item.num} className="rounded-2xl border border-cyan-500/40 bg-[#072f3f] p-8 text-center">
              <div className="text-7xl font-black text-cyan-400/80">{item.num}</div>
              <h3 className="mt-4 text-2xl font-bold text-white">{item.title}</h3>
              <p className="mt-2 text-sm text-slate-300">{item.details}</p>
            </div>
          ))}
        </div>
      </section>

      <section id="testimonials" className="mx-auto max-w-7xl px-6 py-12">
        <h2 className="text-center text-4xl font-bold text-white">Loved by Educators</h2>
        <p className="mx-auto mt-2 max-w-xl text-center text-slate-300">See what academic leaders are saying</p>

        <div className="mt-8 grid gap-6 lg:grid-cols-3">
          {[
            { quote: 'ScheduleMaster Pro transformed our scheduling process. What used to take weeks now takes just hours.', author: 'District Principal' },
            { quote: 'The bulk import feature saved us 15,000 records in minutes. Outstanding speed and accuracy.', author: 'Operations Director' },
            { quote: 'Resource allocation works flawlessly and keeps classrooms at optimal capacity.', author: 'Academic Coordinator' },
          ].map((item, idx) => (
            <div key={idx} className="rounded-2xl border border-cyan-600/40 bg-[#072d3b] p-6">
              <div className="mb-3 flex gap-1 text-amber-400">★★★★★</div>
              <p className="text-sm text-slate-200">“{item.quote}”</p>
              <p className="mt-4 text-xs font-semibold text-cyan-200">- {item.author}</p>
            </div>
          ))}
        </div>
      </section>

      <section id="security" className="mx-auto max-w-7xl px-6 py-12 mb-12 sm:mb-16 rounded-3xl border border-[#6BCFCB]/30 bg-gradient-to-r from-[#001722]/90 via-[#084A48]/85 to-[#6BCFCB]/30">
        <div className="mx-auto max-w-4xl">
          <h2 className="text-center text-4xl font-bold text-[#6BCFCB]">Enterprise-Grade Security</h2>
          <p className="mx-auto mt-3 max-w-3xl text-center text-[#C8F9FF]">Role-based permissions, audit logs, and encrypted data ensure your institution stays compliant and secure, with military-grade AES-256 encryption and granular access controls.</p>
          <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {[
              { title: 'Access Control', desc: 'RBAC and SSO with multi-factor authentication', icon: '🔐' },
              { title: 'Data Encryption', desc: 'AES-256 at rest and TLS in transit', icon: '🛡️' },
              { title: 'Audit Logs', desc: 'Immutable audit trails for compliance', icon: '📜' },
            ].map((item) => (
              <div key={item.title} className="rounded-2xl border border-cyan-300/30 bg-[#04272f]/80 p-5">
                <div className="text-3xl">{item.icon}</div>
                <h3 className="mt-2 text-lg font-bold text-[#6BCFCB]">{item.title}</h3>
                <p className="mt-1 text-sm text-[#D5FCFF]">{item.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section id="roadmap" className="mx-auto max-w-7xl px-6 py-12 rounded-3xl border border-[#6BCFCB]/25 bg-[#082530]/90">
        <h2 className="text-center text-4xl font-bold text-[#6BCFCB]">Product Roadmap</h2>
        <p className="mx-auto mt-3 max-w-3xl text-center text-[#C8F9FF]">Next priority: advanced timetable conflict resolution, bulk schedule publish, and custom role workflows to expand the platform without feature speculation.</p>
      </section>

      <section id="faq" className="mx-auto max-w-7xl px-6 py-12 rounded-3xl border border-[#6BCFCB]/25 bg-[#082530]/90 mt-8">
        <h2 className="text-center text-4xl font-bold text-[#6BCFCB]">FAQ</h2>
        <p className="mx-auto mt-3 max-w-3xl text-center text-[#C8F9FF]">Common questions about importing entities, generating schedules, and role-based access are answered here, with library links for deep dive.</p>
      </section>

      <section id="help" className="mx-auto max-w-7xl px-6 py-12 rounded-3xl border border-[#6BCFCB]/25 bg-[#082530]/90 mt-8">
        <h2 className="text-center text-4xl font-bold text-[#6BCFCB]">Help Center</h2>
        <p className="mx-auto mt-3 max-w-3xl text-center text-[#C8F9FF]">Documentation, how-to resources, and support escalation for administrators and scheduling teams are available when you need them.</p>
      </section>

      <section id="status" className="mx-auto max-w-7xl px-6 py-12 rounded-3xl border border-[#6BCFCB]/25 bg-[#082530]/90 mt-8 mb-16">
        <h2 className="text-center text-4xl font-bold text-[#6BCFCB]">System Status</h2>
        <p className="mx-auto mt-3 max-w-3xl text-center text-[#C8F9FF]">Live status data display for imports, schedules, and system health is available from the dashboard backend in real deployments.</p>
      </section>

      <footer className="bg-[#001722] text-slate-300 mt-12 sm:mt-16 lg:mt-20">
        <div className="mx-auto max-w-7xl px-6 py-16 sm:py-18 lg:py-20">
          <div className="grid gap-8 md:grid-cols-3">
            <div>
              <p className="text-2xl font-bold text-white">Scheduling Management System (SMS)</p>
              <p className="mt-2 text-sm text-slate-300">The most powerful academic scheduling platform trusted by leading institutions worldwide.</p>
            </div>
            <div>
              <p className="text-lg font-semibold text-white">Product</p>
              <ul className="mt-3 space-y-2 text-sm text-slate-200">
                <li><Link href="#features" className="hover:text-cyan-300">Features</Link></li>
                <li><Link href="#how-it-works" className="hover:text-cyan-300">Solutions</Link></li>
                <li><Link href="#security" className="hover:text-cyan-300">Security</Link></li>
                <li><Link href="#roadmap" className="hover:text-cyan-300">Roadmap</Link></li>
              </ul>
            </div>
            <div>
              <p className="text-lg font-semibold text-white">Support</p>
              <ul className="mt-3 space-y-2 text-sm text-slate-200">
                <li><Link href="#faq" className="hover:text-cyan-300">Documentation</Link></li>
                <li><Link href="#help" className="hover:text-cyan-300">Help Center</Link></li>
                <li><Link href="#contact" className="hover:text-cyan-300">Contact Us</Link></li>
                <li><Link href="#status" className="hover:text-cyan-300">Status</Link></li>
              </ul>
            </div>
          </div>

          <div className="mt-10 rounded-2xl border border-cyan-500/20 bg-[#02151f] p-6">
            <p className="text-lg font-bold text-white">Subscribe to our newsletter</p>
            <p className="mt-2 text-sm text-slate-300">Get the latest updates and tips delivered to your inbox.</p>
            <div className="mt-4 flex flex-wrap gap-3">
              <input type="email" placeholder="Enter your email" className="min-w-[240px] rounded-xl border border-cyan-500/20 bg-[#031a29] px-4 py-2 text-sm text-white outline-none focus:border-cyan-400" />
              <button className="rounded-xl bg-gradient-to-r from-cyan-500 to-orange-500 px-6 py-2 font-bold text-white hover:brightness-110">Subscribe</button>
            </div>
          </div>

          <p className="mt-10 text-center text-xs text-slate-400">© {new Date().getFullYear()} Scheduling Management System (SMS). All rights reserved.</p>
        </div>
      </footer>
    </div>
  );
}

