import MinimalLayout from '@/Layouts/MinimalLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

/*
 * Colour palette — Deep Teal + Warm Amber (matches Login.jsx)
 *
 * teal-900  #0a3d40  →  logo bg, button bg
 * teal-700  #0d5c63  →  focus border, button hover
 * teal-050  #d6eeef  →  focus ring glow
 * teal-025  #edf7f7  →  success banner bg
 * teal-border #a8d5d8 → success banner border
 *
 * sand-050  #faf9f5  →  card surface
 * sand-100  #f2efe8  →  input resting bg
 * sand-200  #e0dbd0  →  border default
 * sand-300  #c8c2b5  →  border hover
 * sand-ph   #b0a898  →  placeholder / icon resting
 *
 * ink-900   #1c1a14  →  title, input text
 * ink-600   #4a4538  →  labels
 * ink-400   #7a7060  →  subtitle, hint text
 *
 * red-600   #c0392b  →  validation errors (semantic)
 * red-bg    #fdf3f2  →  mismatch banner bg
 * red-border #f5c6c2 →  mismatch banner border
 */

export default function ChangePassword() {
    const [showPassword, setShowPassword]     = useState(false);
    const [showConfirm,  setShowConfirm]      = useState(false);

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
            <style>{`
                .cp-card {
                    background: #faf9f5;
                    border-radius: 20px;
                    padding: 2.5rem;
                    box-shadow: 0 4px 6px -1px rgba(10,61,64,0.06),
                                0 20px 40px -8px rgba(10,61,64,0.10);
                    border: 1px solid #e0dbd0;
                    width: 100%;
                    max-width: 420px;
                }

                .cp-header {
                    text-align: center;
                    margin-bottom: 2rem;
                }

                .cp-logo {
                    width: 44px;
                    height: 44px;
                    background: #0a3d40;
                    border-radius: 12px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 1.25rem;
                }

                .cp-title {
                    font-family: 'Georgia', 'Times New Roman', serif;
                    font-size: 1.5rem;
                    font-weight: 700;
                    color: #1c1a14;
                    letter-spacing: -0.03em;
                    margin: 0 0 0.375rem;
                }

                .cp-subtitle {
                    font-family: 'system-ui', sans-serif;
                    font-size: 0.875rem;
                    color: #7a7060;
                    margin: 0;
                }

                .field-group {
                    margin-bottom: 1.25rem;
                }

                .field-label {
                    display: block;
                    font-family: 'system-ui', sans-serif;
                    font-size: 0.8125rem;
                    font-weight: 600;
                    color: #4a4538;
                    margin-bottom: 0.4rem;
                    letter-spacing: 0.01em;
                }

                .input-wrapper {
                    position: relative;
                }

                .field-input {
                    width: 100%;
                    padding: 0.65rem 0.875rem;
                    font-family: 'system-ui', sans-serif;
                    font-size: 0.9375rem;
                    color: #1c1a14;
                    background: #f2efe8;
                    border: 1.5px solid #e0dbd0;
                    border-radius: 10px;
                    outline: none;
                    transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
                    box-sizing: border-box;
                }

                .field-input::placeholder { color: #b0a898; }
                .field-input:hover        { border-color: #c8c2b5; }

                .field-input:focus {
                    border-color: #0d5c63;
                    background: #ffffff;
                    box-shadow: 0 0 0 3px #d6eeef;
                }

                .field-input.has-toggle { padding-right: 2.75rem; }

                .toggle-btn {
                    position: absolute;
                    right: 0; top: 0; bottom: 0;
                    width: 2.75rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: none;
                    border: none;
                    cursor: pointer;
                    color: #b0a898;
                    border-radius: 0 10px 10px 0;
                    transition: color 0.15s;
                }

                .toggle-btn:hover { color: #0d5c63; }
                .toggle-btn:focus { outline: none; color: #0d5c63; }

                .field-error {
                    font-size: 0.8rem;
                    color: #c0392b;
                    margin-top: 0.35rem;
                    display: flex;
                    align-items: center;
                    gap: 4px;
                }

                /* Password strength hint */
                .pw-hint {
                    font-family: 'system-ui', sans-serif;
                    font-size: 0.8rem;
                    color: #7a7060;
                    margin-top: 0.75rem;
                    text-align: center;
                }

                /* Match / mismatch banners */
                .pw-match {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.4rem;
                    font-family: 'system-ui', sans-serif;
                    font-size: 0.8125rem;
                    font-weight: 500;
                    padding: 0.5rem 0.875rem;
                    border-radius: 8px;
                    margin-top: 0.75rem;
                    background: #edf7f7;
                    border: 1px solid #a8d5d8;
                    color: #0a3d40;
                }

                .pw-mismatch {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.4rem;
                    font-family: 'system-ui', sans-serif;
                    font-size: 0.8125rem;
                    font-weight: 500;
                    padding: 0.5rem 0.875rem;
                    border-radius: 8px;
                    margin-top: 0.75rem;
                    background: #fdf3f2;
                    border: 1px solid #f5c6c2;
                    color: #c0392b;
                }

                /* Divider */
                .cp-divider {
                    height: 1px;
                    background: #e0dbd0;
                    margin: 1.5rem 0;
                }

                /* Submit button */
                .submit-btn {
                    width: 100%;
                    padding: 0.75rem 1.5rem;
                    background: #0a3d40;
                    color: #ffffff;
                    font-family: 'system-ui', sans-serif;
                    font-size: 0.9375rem;
                    font-weight: 600;
                    letter-spacing: 0.01em;
                    border: none;
                    border-radius: 10px;
                    cursor: pointer;
                    transition: background 0.15s, transform 0.1s, box-shadow 0.15s;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                    margin-top: 1.5rem;
                }

                .submit-btn:hover:not(:disabled) {
                    background: #0d5c63;
                    box-shadow: 0 4px 14px rgba(10,61,64,0.30);
                    transform: translateY(-1px);
                }

                .submit-btn:active:not(:disabled) {
                    background: #083336;
                    transform: translateY(0);
                }

                .submit-btn:disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }

                .spinner {
                    width: 16px; height: 16px;
                    border: 2px solid rgba(255,255,255,0.3);
                    border-top-color: #ffffff;
                    border-radius: 50%;
                    animation: spin 0.7s linear infinite;
                }

                @keyframes spin { to { transform: rotate(360deg); } }
            `}</style>

            <div className="cp-card">

                {/* Header */}
                <div className="cp-header">
                    <div className="cp-logo">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="5" y="11" width="14" height="10" rx="2" stroke="#ffffff" strokeWidth="1.8" fill="none"/>
                            <path d="M8 11V7a4 4 0 1 1 8 0v4" stroke="#ffffff" strokeWidth="1.8" strokeLinecap="round" fill="none"/>
                            <circle cx="12" cy="16" r="1.2" fill="#ffffff"/>
                        </svg>
                    </div>
                    <h1 className="cp-title">Set New Password</h1>
                    <p className="cp-subtitle">Choose a secure password for your account</p>
                </div>

                <form onSubmit={submit}>

                    {/* New password */}
                    <div className="field-group">
                        <InputLabel htmlFor="password" value="New Password" className="field-label" />
                        <div className="input-wrapper">
                            <TextInput
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                name="password"
                                value={data.password}
                                className="field-input has-toggle"
                                autoComplete="new-password"
                                placeholder="Enter your new password"
                                onChange={(e) => setData('password', e.target.value)}
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword(!showPassword)}
                                className="toggle-btn"
                                aria-label={showPassword ? 'Hide password' : 'Show password'}
                            >
                                {showPassword ? (
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                        <line x1="1" y1="1" x2="23" y2="23"/>
                                    </svg>
                                ) : (
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                )}
                            </button>
                        </div>
                        <InputError message={errors.password} className="field-error" />
                    </div>

                    {/* Confirm password */}
                    <div className="field-group">
                        <InputLabel htmlFor="password_confirmation" value="Confirm Password" className="field-label" />
                        <div className="input-wrapper">
                            <TextInput
                                id="password_confirmation"
                                type={showConfirm ? 'text' : 'password'}
                                name="password_confirmation"
                                value={data.password_confirmation}
                                className="field-input has-toggle"
                                autoComplete="new-password"
                                placeholder="Confirm your new password"
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                            />
                            <button
                                type="button"
                                onClick={() => setShowConfirm(!showConfirm)}
                                className="toggle-btn"
                                aria-label={showConfirm ? 'Hide password' : 'Show password'}
                            >
                                {showConfirm ? (
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                        <line x1="1" y1="1" x2="23" y2="23"/>
                                    </svg>
                                ) : (
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                )}
                            </button>
                        </div>
                        <InputError message={errors.password_confirmation} className="field-error" />
                    </div>

                    {/* Match indicator / hint */}
                    {data.password && data.password_confirmation ? (
                        passwordsMatch ? (
                            <div className="pw-match">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                Passwords match
                            </div>
                        ) : (
                            <div className="pw-mismatch">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                                Passwords do not match
                            </div>
                        )
                    ) : (
                        <p className="pw-hint">Password must be at least 8 characters</p>
                    )}

                    {/* Submit */}
                    <button
                        type="submit"
                        className="submit-btn"
                        disabled={processing || !passwordsMatch || !data.password}
                    >
                        {processing && <span className="spinner" />}
                        {processing ? 'Setting Password…' : 'Set Password & Continue'}
                    </button>

                </form>
            </div>
        </MinimalLayout>
    );
}