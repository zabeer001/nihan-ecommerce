import React, { useRef, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { consumeAuthRedirectIntent } from '../../../shared/authRedirect';
import { apiRequest, setAccessToken, setRole } from '../../../shared/apiClient';
import useAuth from '../../../shared/hooks/useAuth';

function getPostRegisterRedirectUrl() {
    const redirectIntent = consumeAuthRedirectIntent();
    return redirectIntent?.url || '/dashboard/tapes/create';
}

function RegisterPage() {
    const { isAuthenticated, isCheckingAuth } = useAuth();
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [message, setMessage] = useState('');
    const hasHandledRedirectRef = useRef(false);

    useQuery({
        queryKey: ['register-auth-redirect', isAuthenticated],
        queryFn: () => {
            if (hasHandledRedirectRef.current) {
                return false;
            }

            hasHandledRedirectRef.current = true;
            window.location.href = getPostRegisterRedirectUrl();
            return true;
        },
        enabled: isAuthenticated && !isCheckingAuth,
        retry: false,
        staleTime: Infinity,
    });

    const registerMutation = useMutation({
        mutationFn: () => apiRequest('/api/register', {
            method: 'POST',
            body: {
                name,
                email,
                password,
                password_confirmation: passwordConfirmation,
            },
        }),
        onSuccess: (payload) => {
            const accessToken = payload?.data?.access_token;
            const user = payload?.data?.user;

            setAccessToken(accessToken);
            setRole(user?.role);

            setMessage(payload?.message || 'Registered successfully.');
            hasHandledRedirectRef.current = true;
            window.location.href = getPostRegisterRedirectUrl();
        },
        onError: (error) => {
            if (error?.status === 422 && error?.payload?.errors) {
                const firstError = Object.values(error.payload.errors)?.[0]?.[0];
                setMessage(firstError || 'Validation failed.');
            } else {
                setMessage(error?.message || 'Could not create your account right now.');
            }

            setPassword('');
            setPasswordConfirmation('');
        },
    });

    const handleSubmit = async (event) => {
        event.preventDefault();
        setMessage('');
        registerMutation.mutate();
    };

    const isLoading = registerMutation.isPending;
    const isSuccess = message.toLowerCase().includes('success');

    if (isCheckingAuth) {
        return null;
    }

    return (
        <section className="mx-auto mt-[10%] mb-[12%] max-w-md">
            <div className="card border border-base-300 bg-base-100 shadow-2xl">
                <div className="card-body p-8 sm:p-10">
                    <div className="mb-4 text-center">
                        <div className="badge badge-secondary badge-outline mb-4 p-4 text-xs font-medium">
                            Join the archive
                        </div>
                        <h1 className="text-secondary text-3xl font-extrabold tracking-tight">
                            Create Account
                        </h1>
                        <p className="mt-2 text-base-content/70">
                            Register with your email to add tapes to the database
                        </p>
                    </div>

                    <form className="space-y-5" onSubmit={handleSubmit}>
                        {message ? (
                            <div className={`alert ${isSuccess ? 'alert-success' : 'alert-error'} text-sm`} role="alert">
                                <span>{message}</span>
                            </div>
                        ) : null}

                        <div className="form-control">
                            <label htmlFor="name" className="label">
                                <span className="label-text font-medium">Full Name</span>
                            </label>
                            <input
                                id="name"
                                type="text"
                                placeholder="Your name"
                                autoComplete="name"
                                required
                                value={name}
                                onChange={(event) => setName(event.target.value)}
                                disabled={isLoading}
                                className="input input-bordered w-full"
                            />
                        </div>

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

                        <div className="form-control">
                            <label htmlFor="password" className="label">
                                <span className="label-text font-medium">Password</span>
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
                            {isLoading ? 'Creating Account...' : 'Create Account'}
                        </button>
                    </form>

                    <div className="mt-6 text-center text-sm text-base-content/70">
                        Already have an account?{' '}
                        <a
                            href="/sign-in"
                            className={`link link-hover font-semibold text-secondary ${isLoading ? 'pointer-events-none opacity-50' : ''}`}
                        >
                            Sign in
                        </a>
                    </div>
                </div>
            </div>
        </section>
    );
}

export default RegisterPage;
