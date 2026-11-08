import React from 'react';
import Link from '@/shared/CachedLink';
import { apiRequest, clearSession } from '../../shared/apiClient';
import useAuth from '../../shared/hooks/useAuth';

function FrontendNavbar({ themes, theme, setTheme }) {
    const { isAuthenticated } = useAuth();
    const authHref = isAuthenticated ? '/dashboard' : '/sign-in';
    const authLabel = isAuthenticated ? 'Dashboard' : 'Login';
    const isSignInPage = typeof window !== 'undefined' && window.location.pathname === '/sign-in';

    const signOut = async () => {
        try {
            await apiRequest('/api/signout', { method: 'POST', auth: true });
        } catch (error) {
            console.error('Sign out request failed:', error);
        } finally {
            clearSession();
            window.location.href = '/';
        }
    };

    return (
        <header className="navbar relative z-[100] rounded-2xl border border-base-300/50 bg-base-100/70 px-3 shadow-lg backdrop-blur-xl sm:px-6">
            <div className="navbar-start">
                <Link href="/" className="inline-flex items-center" aria-label="Go to homepage">
                    <span className="text-lg font-bold text-primary sm:text-xl">
                        Management Software
                    </span>
                </Link>
            </div>

            <div className="navbar-end gap-1 sm:gap-2">
                {!isSignInPage ? (
                    <>
                        <Link href="/site-info" className="btn btn-info btn-sm hidden sm:inline-flex">
                            Site Info
                        </Link>
                        <Link href="/stats" className="btn btn-success btn-sm hidden sm:inline-flex">
                            Stats
                        </Link>
                    </>
                ) : null}
                {!isSignInPage ? (
                    <>
                        <Link
                            href={authHref}
                            className={`btn btn-sm hidden sm:inline-flex ${theme === 'light' ? 'btn-neutral text-neutral-content' : 'btn-primary'}`}
                        >
                            {authLabel}
                        </Link>
                        {isAuthenticated ? (
                            <button
                                type="button"
                                className="btn btn-error btn-sm hidden sm:inline-flex"
                                onClick={signOut}
                            >
                                Sign Out
                            </button>
                        ) : null}
                    </>
                ) : null}

                <div className="dropdown dropdown-end sm:hidden">
                    <button
                        type="button"
                        tabIndex={0}
                        className="btn btn-square btn-sm btn-ghost"
                        aria-label="Open navigation menu"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                    </button>
                    <ul
                        tabIndex={0}
                        className="menu menu-sm dropdown-content z-[9999] mt-2 w-48 rounded-box border border-base-300 bg-base-100 p-2 shadow-2xl"
                    >
                     
                        {!isSignInPage ? (
                            <>
                                <li>
                                    <Link href={authHref}>{authLabel}</Link>
                                </li>
                                {isAuthenticated ? (
                                    <li>
                                        <button type="button" onClick={signOut}>
                                            Sign Out
                                        </button>
                                    </li>
                                ) : null}
                            </>
                        ) : null}
                    </ul>
                </div>

                <div className="dropdown dropdown-end">
                    <button
                        type="button"
                        tabIndex={0}
                        className="btn btn-sm btn-ghost w-9 px-0 sm:w-auto sm:gap-1 sm:px-2"
                        aria-label="Change theme"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="3" width="18" height="18" rx="5" className="fill-base-200 stroke-base-300" strokeWidth="1.2" />
                            <circle cx="9" cy="9" r="1.6" fill="#7c3aed" />
                            <circle cx="15" cy="9" r="1.6" fill="#0ea5e9" />
                            <circle cx="9" cy="15" r="1.6" fill="#ec4899" />
                            <circle cx="15" cy="15" r="1.6" fill="#22c55e" />
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" className="hidden h-4 w-4 opacity-70 sm:block" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.12l3.71-3.89a.75.75 0 1 1 1.08 1.04l-4.25 4.46a.75.75 0 0 1-1.08 0L5.21 8.27a.75.75 0 0 1 .02-1.06Z" clipRule="evenodd" />
                        </svg>
                    </button>
                    <ul
                        tabIndex={0}
                        className="dropdown-content z-[9999] mt-2 max-h-80 w-56 overflow-y-auto rounded-box border border-base-300 bg-base-100 p-2 shadow-2xl"
                    >
                        {themes.map((name) => (
                            <li key={name}>
                                <button
                                    type="button"
                                    className={`btn btn-sm btn-ghost btn-block justify-start capitalize ${
                                        theme === name ? 'btn-active' : ''
                                    }`}
                                    onClick={() => setTheme(name)}
                                >
                                    {name}
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </header>
    );
}

export default FrontendNavbar;
