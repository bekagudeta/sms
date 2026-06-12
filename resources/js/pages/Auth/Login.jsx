import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Login({ canResetPassword }) {
    const { props } = usePage();
    const url = new URL(window.location.href);
    const sessionExpired = url.searchParams.get('session') === 'expired';

    const [showPassword, setShowPassword] = useState(false);
    const [pwStrength, setPwStrength] = useState(0);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const togglePasswordVisibility = () => {
        setShowPassword(!showPassword);
    };

    const handlePasswordChange = (e) => {
        const val = e.target.value;
        setData('password', val);
        let strength = 0;
        if (val.length >= 8) strength += 33;
        if (/[A-Z]/.test(val) && /[a-z]/.test(val)) strength += 34;
        if (/[0-9!@#$%^&*]/.test(val)) strength += 33;
        setPwStrength(val.length ? Math.max(strength, 10) : 0);
    };

    const strengthColor =
        pwStrength < 40 ? '#c0392b' : pwStrength < 75 ? '#e67e22' : '#0f6e56';

    return (
        <GuestLayout>
            <Head title="Sign In — Salale University" />

            <style>{`
                .su-card {
                    background: #ffffff;
                    border-radius: 16px;
                    border: 0.5px solid #b0dce5;
                    overflow: hidden;
                    box-shadow: 0 4px 28px rgba(26,143,168,0.10);
                    width: 100%;
                    max-width: 420px;
                    margin: 0 auto;
                    font-family: 'Segoe UI', system-ui, sans-serif;
                }
                .su-header {
                    background: linear-gradient(135deg, #0f6e56 0%, #1a8fa8 60%, #2bb5c8 100%);
                    padding: 2rem 2rem 1.6rem;
                    text-align: center;
                }
                .su-logo-ring {
                    width: 68px; height: 68px;
                    border-radius: 50%;
                    background: rgba(255,255,255,0.15);
                    border: 2px solid rgba(255,255,255,0.4);
                    margin: 0 auto 1rem;
                    display: flex; align-items: center; justify-content: center;
                }
                .su-header h1 {
                    color: #fff;
                    font-size: 18px;
                    font-weight: 600;
                    margin: 0 0 3px;
                    letter-spacing: 0.3px;
                }
                .su-header p {
                    color: rgba(255,255,255,0.75);
                    font-size: 13px;
                    margin: 0;
                }
                .su-body { padding: 1.75rem 2rem 1.5rem; }
                .su-label {
                    display: block;
                    font-size: 13px;
                    font-weight: 500;
                    color: #0d3d4f;
                    margin-bottom: 6px;
                }
                .su-input-wrap { position: relative; }
                .su-input-icon {
                    position: absolute; left: 11px; top: 50%;
                    transform: translateY(-50%);
                    color: #5a8a96;
                    pointer-events: none;
                    display: flex; align-items: center;
                }
                .su-input {
                    width: 100% !important;
                    height: 42px;
                    padding: 0 42px 0 38px !important;
                    border: 1px solid #b0dce5 !important;
                    border-radius: 8px !important;
                    background: #f4fbfd !important;
                    font-size: 14px;
                    color: #0d3d4f;
                    transition: border-color 0.2s, box-shadow 0.2s;
                }
                .su-input:focus {
                    border-color: #1a8fa8 !important;
                    box-shadow: 0 0 0 3px rgba(26,143,168,0.12) !important;
                    background: #fff !important;
                    outline: none;
                }
                .su-toggle-pw {
                    position: absolute; right: 11px; top: 50%;
                    transform: translateY(-50%);
                    background: none; border: none; cursor: pointer;
                    color: #5a8a96;
                    display: flex; align-items: center;
                    padding: 2px;
                    transition: color 0.15s;
                }
                .su-toggle-pw:hover { color: #1a8fa8; }
                .su-pw-strength {
                    height: 3px;
                    border-radius: 2px;
                    background: #b0dce5;
                    margin-top: 6px;
                    overflow: hidden;
                }
                .su-pw-bar {
                    height: 100%;
                    border-radius: 2px;
                    transition: width 0.3s, background 0.3s;
                }
                .su-remember {
                    display: flex; align-items: center; gap: 8px;
                    font-size: 13px; color: #0d3d4f;
                }
                .su-forgot {
                    font-size: 13px;
                    color: #1a8fa8;
                    text-decoration: none;
                    font-weight: 500;
                }
                .su-forgot:hover { color: #0f6e56; text-decoration: underline; }
                .su-btn {
                    width: 100%;
                    height: 44px;
                    background: linear-gradient(135deg, #0f6e56, #1a8fa8) !important;
                    color: #fff !important;
                    border: none !important;
                    border-radius: 8px !important;
                    font-size: 15px;
                    font-weight: 600;
                    cursor: pointer;
                    display: flex; align-items: center; justify-content: center; gap: 8px;
                    transition: opacity 0.2s, transform 0.15s;
                    letter-spacing: 0.2px;
                }
                .su-btn:hover:not(:disabled) { opacity: 0.92; }
                .su-btn:active:not(:disabled) { transform: scale(0.98); }
                .su-btn:disabled { opacity: 0.55; cursor: not-allowed; }
                .su-divider {
                    border: none;
                    border-top: 0.5px solid #b0dce5;
                    margin: 1.25rem 0 1rem;
                }
                .su-register {
                    text-align: center;
                    font-size: 13px;
                    color: #5a8a96;
                }
                .su-register a {
                    color: #1a8fa8;
                    font-weight: 500;
                    text-decoration: none;
                }
                .su-register a:hover { text-decoration: underline; }
                .su-secure {
                    display: flex; align-items: center; justify-content: center;
                    gap: 5px; margin-top: 1.1rem;
                    font-size: 11.5px; color: #5a8a96;
                }
            `}</style>

            <div className="su-card">
                {/* Header */}
                <div className="su-header">
                    <div className="su-logo-ring">
                        <svg width="38" height="38" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="19" cy="19" r="17" stroke="rgba(255,255,255,0.5)" strokeWidth="1"/>
                            <path d="M9 24h20M12 20l7-10 7 10" stroke="#fff" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                            <rect x="14" y="24" width="10" height="6" rx="1" stroke="#fff" strokeWidth="1.5"/>
                            <line x1="16" y1="24" x2="16" y2="30" stroke="#fff" strokeWidth="1"/>
                            <line x1="22" y1="24" x2="22" y2="30" stroke="#fff" strokeWidth="1"/>
                        </svg>
                    </div>
                    <h1>Salale University</h1>
                    <p>Academic Management System</p>
                </div>

                {/* Body */}
                <div className="su-body">
                    {(sessionExpired || props.flash?.status) && (
                        <div style={{ background: '#fdf3f2', border: '0.5px solid #f5c6c2', color: '#c0392b', borderRadius: 8, padding: '10px 14px', fontSize: 13, marginBottom: '1.25rem' }}>
                            {sessionExpired ? 'Your session has expired. Please sign in again.' : props.flash?.status}
                        </div>
                    )}

                    <form onSubmit={submit}>
                        {/* Email */}
                        <div style={{ marginBottom: '1.1rem' }}>
                            <InputLabel htmlFor="email" value="Email address" className="su-label" />
                            <div className="su-input-wrap">
                                <span className="su-input-icon">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                        <polyline points="22,6 12,13 2,6"/>
                                    </svg>
                                </span>
                                <TextInput
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    className="su-input"
                                    autoComplete="username"
                                    isFocused={true}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="you@example.com"
                                />
                            </div>
                            <InputError message={errors.email} className="mt-2" />
                        </div>

                        {/* Password */}
                        <div style={{ marginBottom: '0.5rem' }}>
                            <InputLabel htmlFor="password" value="Password" className="su-label" />
                            <div className="su-input-wrap">
                                <span className="su-input-icon">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                </span>
                                <TextInput
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    name="password"
                                    value={data.password}
                                    className="su-input"
                                    autoComplete="current-password"
                                    onChange={handlePasswordChange}
                                    placeholder="Enter your password"
                                />
                                <button
                                    type="button"
                                    onClick={togglePasswordVisibility}
                                    className="su-toggle-pw"
                                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                                >
                                    {showPassword ? (
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                            <line x1="1" y1="1" x2="23" y2="23"/>
                                        </svg>
                                    ) : (
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    )}
                                </button>
                            </div>
                            {/* Password strength bar */}
                            <div className="su-pw-strength">
                                <div
                                    className="su-pw-bar"
                                    style={{ width: `${pwStrength}%`, background: strengthColor }}
                                />
                            </div>
                            <InputError message={errors.password} className="mt-2" />
                        </div>

                        {/* Remember + Forgot */}
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', margin: '1rem 0 1.25rem' }}>
                            <label className="su-remember">
                                <Checkbox
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                />
                                <span>Remember me</span>
                            </label>
                            {canResetPassword && (
                                <Link href={route('password.request')} className="su-forgot">
                                    Forgot password?
                                </Link>
                            )}
                        </div>

                        {/* Submit */}
                        <button type="submit" className="su-btn" disabled={processing}>
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                                <polyline points="10 17 15 12 10 7"/>
                                <line x1="15" y1="12" x2="3" y2="12"/>
                            </svg>
                            {processing ? 'Signing in…' : 'Sign in'}
                        </button>
                    </form>

                    <hr className="su-divider" />

                    <div className="su-register">
                        New to the system?{' '}
                        <Link href={route('register')}>Create an account</Link>
                    </div>

                    <div className="su-secure">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        Secured connection — Salale University © 2015
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}