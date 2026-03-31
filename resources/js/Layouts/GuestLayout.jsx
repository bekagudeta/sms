import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-rich-black via-deep-jungle-green to-deep-jungle-green px-4 py-8">
            <div className="mb-6 text-center">
                <Link href="/" className="inline-flex flex-col items-center gap-3">
                    <div className="rounded-full border border-vivid-orange/60 bg-white p-3 shadow-lg">
                        <ApplicationLogo className="h-12 w-12 fill-current text-deep-jungle-green" />
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-widest text-vivid-orange">SMS</p>
                        <p className="text-sm font-medium text-platinum">Scheduling Management System</p>
                    </div>
                </Link>
            </div>

            <div className="w-full overflow-hidden rounded-2xl border border-white/10 bg-white px-6 py-5 shadow-2xl sm:max-w-md">
                {children}
            </div>
        </div>
    );
}
