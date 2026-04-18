import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head } from '@inertiajs/react';

export default function MinimalLayout({ children, title }) {
    return (
        <div className="min-h-screen bg-gradient-to-br from-rich-black via-deep-jungle-green to-deep-jungle-green flex items-center justify-center px-4 py-8">
            <Head title={title || "Change Password"} />
            
            <div className="w-full max-w-md">
                {/* Header with logo and title */}
                <div className="text-center mb-8">
                    <div className="inline-flex items-center justify-center w-20 h-20 bg-white rounded-full shadow-lg mb-4">
                        <ApplicationLogo className="h-12 w-12 fill-current text-deep-jungle-green" />
                    </div>
                    <h1 className="text-2xl font-bold text-white mb-2">Password Required</h1>
                    <p className="text-platinum/80 text-sm">For security, you must set a new password before continuing</p>
                </div>

                {/* Main content card */}
                <div className="bg-white rounded-2xl shadow-2xl p-8">
                    {children}
                </div>

                {/* Footer */}
                <div className="text-center mt-6">
                    <p className="text-platinum/60 text-xs">
                        Need help? Contact your administrator if you're stuck.
                    </p>
                </div>
            </div>
        </div>
    );
}
