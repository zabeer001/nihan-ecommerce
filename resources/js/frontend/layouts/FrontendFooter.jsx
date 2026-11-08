import React from 'react';
import Link from '@/shared/CachedLink';
import useAuth from '../../shared/hooks/useAuth';

function FrontendFooter() {
    const { isAuthenticated } = useAuth();
    const authHref = isAuthenticated ? '/dashboard' : '/sign-in';
    const authLabel = isAuthenticated ? 'Dashboard' : 'Login';

    return (
        <footer className="relative mt-16 -mx-4 overflow-hidden border-t border-white/10 bg-neutral-950 text-neutral-content sm:-mx-6 md:-mx-8">
            {/* soft background texture */}
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.06),transparent_28%),radial-gradient(circle_at_bottom_right,rgba(255,255,255,0.04),transparent_30%)]" />

            {/* colored glow accents */}
            <div className="pointer-events-none absolute -top-24 left-0 h-72 w-72 rounded-full bg-primary/10 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-24 right-0 h-72 w-72 rounded-full bg-secondary/10 blur-3xl" />

            {/* subtle overlay for depth */}
            <div className="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/[0.03] via-transparent to-black/20" />

            <div className="relative mx-auto max-w-6xl px-6 py-10 sm:px-8 sm:py-12">
                <div className="grid gap-8 sm:grid-cols-2">
                    <div className="text-center sm:text-left">
                        <p className="font-semibold uppercase tracking-wider text-white">Pages</p>
                        <ul className="mt-3 flex flex-col items-center gap-2 sm:items-start">
                            <li>
                                <Link href="/" className="text-white/75 transition-colors duration-200 hover:text-white">
                                    Home
                                </Link>
                            </li>
                            <li>
                                <Link href="/site-info" className="text-white/75 transition-colors duration-200 hover:text-white">
                                    Site Info
                                </Link>
                            </li>
                            <li>
                                <Link href="/stats" className="text-white/75 transition-colors duration-200 hover:text-white">
                                    Stats
                                </Link>
                            </li>
                            <li>
                                <Link href={authHref} className="text-white/75 transition-colors duration-200 hover:text-white">
                                    {authLabel}
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <div className="text-center sm:text-right">
                        <p className="font-semibold uppercase tracking-wider text-white">Contact</p>
                        <a
                            className="mt-3 inline-block text-base font-medium text-white transition-opacity hover:opacity-80"
                            href="mailto:example@gmail.com"
                        >
                            example@gmail.com 
                        </a>
                    </div>
                </div>

                <div className="mt-8 border-t border-white/10 pt-4 text-center text-xs text-white/50">
                    © {new Date().getFullYear()} All rights reserved by Reqto.
                </div>
            </div>
        </footer>
    );
}

export default FrontendFooter;
