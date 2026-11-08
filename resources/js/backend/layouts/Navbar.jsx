import React from 'react';

function Navbar({ theme, themes, setTheme, signOut, currentUser }) {
    const avatarUrl = currentUser?.avatar_url || '';
    const userName = currentUser?.name || 'Signed User';
    const roleLabel = currentUser?.role || 'user';
    const fallbackInitial = userName.charAt(0).toUpperCase();

    return (
        <header className="navbar relative z-[100] border-b border-base-300 bg-base-100 px-4 sm:px-6">
            <div className="flex-none lg:hidden">
                <label htmlFor="admin-drawer" className="btn btn-square btn-ghost">
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h7" />
                    </svg>
                </label>
            </div>

            <div className="flex-1">
                <h1 className="text-xl font-bold tracking-wide">Admin Panel</h1>
            </div>

            <div className="flex items-center gap-2">
                <div className="hidden items-center gap-2 rounded-xl border border-base-300 bg-base-200 px-2 py-1 sm:flex">
                    <div className="avatar">
                        <div className="h-9 w-9 rounded-xl bg-base-300">
                            {avatarUrl ? (
                                <img src={avatarUrl} alt="Signed in user" className="h-full w-full object-cover" />
                            ) : (
                                <span className="flex h-full w-full items-center justify-center text-xs font-bold">
                                    {fallbackInitial}
                                </span>
                            )}
                        </div>
                    </div>
                    <div className="leading-tight">
                        <p className="text-xs font-semibold">{userName}</p>
                        <p className="text-[10px] uppercase text-base-content/60">{roleLabel}</p>
                    </div>
                </div>

                <div className="dropdown dropdown-end">
                    <button
                        type="button"
                        tabIndex={0}
                        className="btn btn-sm btn-ghost gap-1 px-2"
                        aria-label="Change theme"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="3" width="18" height="18" rx="5" className="fill-base-200 stroke-base-300" strokeWidth="1.2" />
                            <circle cx="9" cy="9" r="1.6" fill="#7c3aed" />
                            <circle cx="15" cy="9" r="1.6" fill="#0ea5e9" />
                            <circle cx="9" cy="15" r="1.6" fill="#ec4899" />
                            <circle cx="15" cy="15" r="1.6" fill="#22c55e" />
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 opacity-70" viewBox="0 0 20 20" fill="currentColor">
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
                <button type="button" className="btn btn-sm btn-error btn-outline" onClick={signOut}>
                    Sign out
                </button>
            </div>
        </header>
    );
}

export default Navbar;
