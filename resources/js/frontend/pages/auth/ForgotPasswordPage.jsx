import React, { useState } from 'react';
import Link from '@/shared/CachedLink';
import { apiRequest } from '../../../shared/apiClient';

function ForgotPasswordPage() {
    const [email, setEmail] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [message, setMessage] = useState('');
    const [isSuccess, setIsSuccess] = useState(false);

    const handleSubmit = async (event) => {
        event.preventDefault();
        setIsLoading(true);
        setMessage('');
        setIsSuccess(false);

        try {
            const payload = await apiRequest('/api/forgot-password', {
                method: 'POST',
                body: { email },
            });

            setMessage(payload?.message || 'Password reset link sent.');
            setIsSuccess(true);
        } catch (error) {
            if (error?.status === 422 && error?.payload?.errors) {
                const firstError = Object.values(error.payload.errors)?.[0]?.[0];
                setMessage(firstError || 'Validation failed.');
            } else {
                setMessage(error?.message || 'Could not send the reset link right now.');
            }
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <section className="mx-auto mt-[15%] mb-[19%] max-w-md">
            <div className="card border border-base-300 bg-base-100 shadow-2xl">
                <div className="card-body p-8 sm:p-10">
                    <div className="mb-4 text-center">
                        <div className="badge badge-primary badge-outline mb-4 p-4 text-xs font-medium">
                            Account recovery
                        </div>
                        <h1 className="text-primary text-3xl font-extrabold tracking-tight">
                            Forgot Password
                        </h1>
                        <p className="mt-2 text-base-content/70">
                            Enter your email to receive a password reset link
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
                                placeholder="you@example.com"
                                autoComplete="email"
                                required
                                value={email}
                                onChange={(event) => setEmail(event.target.value)}
                                disabled={isLoading}
                                className="input input-bordered w-full"
                            />
                        </div>

                        <button type="submit" disabled={isLoading} className="btn btn-primary w-full">
                            {isLoading ? 'Sending Link...' : 'Send New Password Setup Link'}
                        </button>
                    </form>

                    <div className="mt-6 text-center text-sm text-base-content/70">
                        Remembered your password?{' '}
                        <Link href="/sign-in" className="link link-hover font-semibold text-primary">
                            Sign in
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}

export default ForgotPasswordPage;
