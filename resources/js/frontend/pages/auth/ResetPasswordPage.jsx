import React, { useMemo, useState } from 'react';
import Link from '@/shared/CachedLink';
import { apiRequest } from '../../../shared/apiClient';

function ResetPasswordPage({ token }) {
    const emailFromQuery = useMemo(
        () => new URLSearchParams(window.location.search).get('email') || '',
        []
    );
    const [email, setEmail] = useState(emailFromQuery);
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [message, setMessage] = useState('');
    const [isSuccess, setIsSuccess] = useState(false);

    const handleSubmit = async (event) => {
        event.preventDefault();
        setIsLoading(true);
        setMessage('');
        setIsSuccess(false);

        try {
            const payload = await apiRequest('/api/reset-password', {
                method: 'POST',
                body: {
                    token,
                    email,
                    password,
                    password_confirmation: passwordConfirmation,
                },
            });

            setMessage(payload?.message || 'Password reset successfully.');
            setIsSuccess(true);
            setPassword('');
            setPasswordConfirmation('');
        } catch (error) {
            if (error?.status === 422 && error?.payload?.errors) {
                const firstError = Object.values(error.payload.errors)?.[0]?.[0];
                setMessage(firstError || 'Validation failed.');
            } else {
                setMessage(error?.message || 'Could not reset your password.');
            }
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <section className="mx-auto mt-[10%] mb-[12%] max-w-md">
            <div className="card border border-base-300 bg-base-100 shadow-2xl">
                <div className="card-body p-8 sm:p-10">
                    <div className="mb-4 text-center">
                        <div className="badge badge-secondary badge-outline mb-4 p-4 text-xs font-medium">
                            Set new password
                        </div>
                        <h1 className="text-secondary text-3xl font-extrabold tracking-tight">
                            Reset Password
                        </h1>
                        <p className="mt-2 text-base-content/70">
                            Choose a new password for your account
                        </p>
                    </div>

                    <form className="space-y-5" onSubmit={handleSubmit}>
                        {message ? (
                            <div className={`alert ${isSuccess ? 'alert-success' : 'alert-error'} text-sm`} role="alert">
                                <span>{message}</span>
                            </div>
                        ) : null}

                        <div className="form-control">
                            <label htmlFor="email" className="label">
                                <span className="label-text font-medium">Email address</span>
                            </label>
                            <input
                                id="email"
                                type="email"
                                autoComplete="email"
                                required
                                value={email}
                                onChange={(event) => setEmail(event.target.value)}
                                disabled={isLoading}
                                className="input input-bordered w-full"
                            />
                        </div>

                        <div className="form-control">
                            <label htmlFor="password" className="label">
                                <span className="label-text font-medium">New password</span>
                            </label>
                            <input
                                id="password"
                                type="password"
                                placeholder="At least 8 characters"
                                autoComplete="new-password"
                                required
                                value={password}
                                onChange={(event) => setPassword(event.target.value)}
                                disabled={isLoading}
                                className="input input-bordered w-full"
                            />
                        </div>

                        <div className="form-control">
                            <label htmlFor="password_confirmation" className="label">
                                <span className="label-text font-medium">Confirm password</span>
                            </label>
                            <input
                                id="password_confirmation"
                                type="password"
                                placeholder="Repeat your password"
                                autoComplete="new-password"
                                required
                                value={passwordConfirmation}
                                onChange={(event) => setPasswordConfirmation(event.target.value)}
                                disabled={isLoading}
                                className="input input-bordered w-full"
                            />
                        </div>

                        <button type="submit" disabled={isLoading} className="btn btn-secondary w-full">
                            {isLoading ? 'Resetting Password...' : 'Reset Password'}
                        </button>
                    </form>

                    <div className="mt-6 text-center text-sm text-base-content/70">
                        Back to{' '}
                        <Link href="/sign-in" className="link link-hover font-semibold text-secondary">
                            sign in
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}

export default ResetPasswordPage;
