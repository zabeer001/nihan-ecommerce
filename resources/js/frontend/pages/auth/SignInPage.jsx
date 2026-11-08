import React, { useRef, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { apiRequest, setAccessToken, setRole } from '../../../shared/apiClient';
import useAuth from '../../../shared/hooks/useAuth';

function getPostSignInRedirectUrl() {
    return '/dashboard';
}

function SignInPage() {
    const { isAuthenticated, isCheckingAuth } = useAuth();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [rememberMe, setRememberMe] = useState(false);
    const [message, setMessage] = useState('');
    const hasHandledRedirectRef = useRef(false);

    useQuery({
        queryKey: ['sign-in-auth-redirect', isAuthenticated],
        queryFn: () => {
            if (hasHandledRedirectRef.current) {
                return false;
            }

            hasHandledRedirectRef.current = true;
            window.location.href = getPostSignInRedirectUrl();
            return true;
        },
        enabled: isAuthenticated && !isCheckingAuth,
        retry: false,
        staleTime: Infinity,
    });

    const signInMutation = useMutation({
        mutationFn: () => apiRequest('/api/signin', {
            method: 'POST',
            body: {
                email,
                password,
                remember_me: rememberMe,
            },
        }),
        onSuccess: (payload) => {
            const accessToken = payload?.data?.access_token;
            const user = payload?.data?.user;

            setAccessToken(accessToken);
            setRole(user?.role);

            setMessage(payload?.message || 'Signed in successfully.');
            hasHandledRedirectRef.current = true;
            window.location.href = getPostSignInRedirectUrl();
        },
        onError: (error) => {
            if (error?.status === 422 && error?.payload?.errors) {
                const firstError = Object.values(error.payload.errors)?.[0]?.[0];
                setMessage(firstError || 'Validation failed.');
            } else if (error?.status === 401 || error?.status === 400 || error?.status === 403) {
                setMessage(error?.message || 'Authentication failed. Please check your credentials.');
            } else {
                setMessage('Could not connect to the server. Please try again.');
            }

            setPassword('');
        },
    });

    const handleSubmit = async (e) => {
        e.preventDefault();
        setMessage('');
        signInMutation.mutate();
    };

    const isLoading = signInMutation.isPending;
    const isSuccess = message.toLowerCase().includes('success');

    if (isCheckingAuth) {
        return null;
    }

    return (
        <section className="mx-auto max-w-md mt-[5%] mb-[19%]">
                <div className="card border border-base-300 bg-base-100 shadow-2xl">
                    <div className="card-body p-8 sm:p-10">
                        <div className="mb-4 text-center">
                            <div className="badge badge-primary badge-outline mb-4 p-4 text-xs font-medium">
                                Secure access
                            </div>
                            <h1 className="text-primary text-3xl font-extrabold tracking-tight">
                                Welcome Back
                            </h1>
                            <p className="mt-2 text-base-content/70">Sign in to your account to continue</p>
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
                                    onChange={(e) => setEmail(e.target.value)}
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
                                    placeholder="••••••••"
                                    autoComplete="current-password"
                                    required
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    disabled={isLoading}
                                    className="input input-bordered w-full"
                                />
                            </div>

                            <div className="flex items-center justify-between text-sm">
                                <label className="label cursor-pointer gap-2 p-0">
                                    <input
                                        type="checkbox"
                                        checked={rememberMe}
                                        onChange={(e) => setRememberMe(e.target.checked)}
                                        disabled={isLoading}
                                        className="checkbox checkbox-sm checkbox-primary"
                                    />
                                    <span className="label-text text-base-content/80">Remember me</span>
                                </label>
                                {/* <a
                                    href="/forgot-password"
                                    className={`link link-hover text-base-content/80 ${isLoading ? 'pointer-events-none opacity-50' : ''}`}
                                >
                                    Forgot password?
                                </a> */}
                            </div>

                            <button
                                type="submit"
                                disabled={isLoading}
                                className="btn btn-primary w-full"
                            >
                                {isLoading ? 'Signing In...' : 'Sign In'}
                            </button>
                        </form>

                        {/* <div className="mt-6 text-center text-sm text-base-content/70">
                            Don&apos;t have an account?{' '}
                            <a
                                href="/register"
                                className={`link link-hover font-semibold text-primary ${isLoading ? 'pointer-events-none opacity-50' : ''}`}
                            >
                                Sign up
                            </a>
                        </div> */}
                    </div>
                </div>
        </section>
    );
}

export default SignInPage;
