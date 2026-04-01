import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, useForm } from '@inertiajs/react';
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
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Change Password
                </h2>
            }
        >
            <Head title="Change Password" />

            <div className="py-12">
                <div className="mx-auto max-w-2xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow sm:rounded-lg">
                        <div className="mb-4">
                            <p className="text-sm text-gray-600">
                                For security, you must set a new password before continuing.
                            </p>
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
                                    onChange={(e) =>
                                        setData('password_confirmation', e.target.value)
                                    }
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                    className="mt-2"
                                />
                            </div>

                            <div className="text-sm text-gray-600">
                                {data.password && data.password_confirmation ? (
                                    passwordsMatch ? (
                                        <p className="text-green-600">Passwords match</p>
                                    ) : (
                                        <p className="text-red-600">Passwords do not match</p>
                                    )
                                ) : (
                                    <p>Passwords must match and be at least 8 characters.</p>
                                )}
                            </div>

                            <div className="flex items-center justify-end">
                                <PrimaryButton
                                    disabled={processing || !passwordsMatch || !data.password}
                                >
                                    Set Password
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
