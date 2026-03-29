import React from 'react';
import { Link } from '@inertiajs/react';
import logoSrc from '../images/logo.png';

export default function Welcome({ auth }) {
  return (
    <div className="min-h-screen bg-[#001722] text-white">
      <header className="sticky top-0 z-50 border-b border-slate-800 bg-white/80 backdrop-blur">
        <div className="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-4">
          <div className="flex items-center gap-3">
            <img src={logoSrc} alt="SMS logo" className="h-10 w-10 rounded-full border border-slate-300" />
            <div>
              <div className="text-xl font-extrabold text-slate-900">Scheduling <span className="text-cyan-500">Management</span> System</div>
              <div className="text-xs font-semibold tracking-wider text-slate-500">SMS</div>
            </div>
          </div>

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

      <section className="relative overflow-hidden bg-gradient-to-b from-[#001722] via-[#08414d] to-[#002d3d]">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,_#0b4a5a,transparent_55%)] opacity-80" />
        <div className="relative mx-auto flex max-w-7xl flex-col items-center gap-8 px-6 py-24 text-center">
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
            <Link href="/register" className="rounded-full bg-gradient-to-r from-cyan-500 to-orange-500 px-8 py-3 text-base font-extrabold text-white shadow-lg shadow-cyan-500/30 transition hover:-translate-y-0.5 hover:scale-105">
              Get Started Free
            </Link>
            <Link href="/demo" className="rounded-full border border-cyan-300/80 bg-transparent px-8 py-3 text-base font-bold text-white hover:border-cyan-100 hover:bg-white/10">
              Watch Demo
            </Link>
          </div>
        </div>
      </section>

      <section id="overview" className="mx-auto max-w-7xl px-6 py-14">
        <div className="grid gap-6 md:grid-cols-3">
          <div className="rounded-2xl border border-cyan-800/50 bg-slate-900/50 p-6">
            <p className="text-5xl font-extrabold text-cyan-300">10K+</p>
            <p className="mt-2 text-sm font-semibold uppercase tracking-widest text-slate-300">Students Managed</p>
          </div>
          <div className="rounded-2xl border border-cyan-800/50 bg-slate-900/50 p-6">
            <p className="text-5xl font-extrabold text-orange-300">500+</p>
            <p className="mt-2 text-sm font-semibold uppercase tracking-widest text-slate-300">Institutions</p>
          </div>
          <div className="rounded-2xl border border-cyan-800/50 bg-slate-900/50 p-6">
            <p className="text-5xl font-extrabold text-emerald-300">98%</p>
            <p className="mt-2 text-sm font-semibold uppercase tracking-widest text-slate-300">Satisfaction</p>
          </div>
        </div>
      </section>

      <section id="features" className="mx-auto max-w-7xl px-6 py-12">
        <h2 className="text-center text-4xl font-bold tracking-tight text-white">Import 9 Entity Types</h2>
        <p className="mt-3 text-center text-slate-300">Seamlessly import all your academic data with our intelligent Excel parser.</p>

        <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {[
            { name: 'Students', icon: '👩‍🎓', desc: 'Manage student profiles and enrollments.' },
            { name: 'Teachers', icon: '👨‍🏫', desc: 'Track teacher assignments and schedules.' },
            { name: 'Courses', icon: '📚', desc: 'Define course offerings and prerequisites.' },
            { name: 'Course Offerings', icon: '📖', desc: 'Create course cycles with room links.' },
            { name: 'Sections', icon: '📝', desc: 'Build sections with period and seat capacity.' },
            { name: 'Section Teachers', icon: '👥', desc: 'Assign teachers to sections quickly.' },
            { name: 'Timeslots', icon: '⏰', desc: 'Configure period windows and duration.' },
            { name: 'Rooms', icon: '🏫', desc: 'Set room capacity, type, and availability.' },
          ].map((feature) => (
            <div key={feature.name} className="rounded-2xl border border-cyan-600/40 bg-[#06242f] p-6 shadow-lg shadow-cyan-900/25">
              <div className="mb-3 text-4xl">{feature.icon}</div>
              <h3 className="text-xl font-bold text-white">{feature.name}</h3>
              <p className="mt-2 text-sm text-slate-300">{feature.desc}</p>
            </div>
          ))}
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

      <footer className="bg-[#001722] text-slate-300">
        <div className="mx-auto max-w-7xl px-6 py-12">
          <div className="grid gap-8 md:grid-cols-3">
            <div>
              <p className="text-2xl font-bold text-white">Scheduling Management System (SMS)</p>
              <p className="mt-2 text-sm text-slate-300">The most powerful academic scheduling platform trusted by leading institutions worldwide.</p>
            </div>
            <div>
              <p className="text-lg font-semibold text-white">Product</p>
              <ul className="mt-3 space-y-2 text-sm text-slate-300">
                <li>Features</li>
                <li>Solutions</li>
                <li>Security</li>
                <li>Roadmap</li>
              </ul>
            </div>
            <div>
              <p className="text-lg font-semibold text-white">Support</p>
              <ul className="mt-3 space-y-2 text-sm text-slate-300">
                <li>Documentation</li>
                <li>Help Center</li>
                <li>Contact Us</li>
                <li>Status</li>
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

