import MinimalLayout from '@/Layouts/MinimalLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { useMemo } from 'react';

export default function ChangePassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
        password_confirmation: '',
    });

    const passwordsMatch = useMemo(
        () => data.password && data.password === data.password_confirmation,
        [data.password, data.password_confirmation],
    );

    const submit = (e) => {
        e.preventDefault();

        post(route('change.password.update'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <MinimalLayout title="Change Password">
            <div className="text-center mb-6">
                <h2 className="text-xl font-semibold text-gray-900 mb-2">Set Your New Password</h2>
                <p className="text-sm text-gray-600">Choose a secure password for your account</p>
            </div>

            <form onSubmit={submit} className="space-y-6">
                <div>
                    <InputLabel htmlFor="password" value="New Password" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        placeholder="Enter your new password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Confirm Password"
                    />

                    <TextInput
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        placeholder="Confirm your new password"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />

                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

                <div className="text-sm">
                    {data.password && data.password_confirmation ? (
                        passwordsMatch ? (
                            <p className="text-green-600 flex items-center justify-center">
                                <svg className="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                </svg>
                                Passwords match
                            </p>
                        ) : (
                            <p className="text-red-600 flex items-center justify-center">
                                <svg className="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                </svg>
                                Passwords do not match
                            </p>
                        )
                    ) : (
                        <p className="text-gray-500 text-center">Password must be at least 8 characters</p>
                    )}
                </div>

                <div className="flex items-center justify-end">
                    <PrimaryButton
                        disabled={processing || !passwordsMatch || !data.password}
                        className="w-full"
                    >
                        {processing ? 'Setting Password...' : 'Set Password & Continue'}
                    </PrimaryButton>
                </div>
            </form>
        </MinimalLayout>
    );
}
