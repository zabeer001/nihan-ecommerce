import React, { useEffect, useState } from 'react';
import FrontendFooter from './FrontendFooter';
import FrontendNavbar from './FrontendNavbar';
import themeOptions from '../../shared/themeOptions';

function FrontendLayout({ children }) {
    const [theme, setTheme] = useState(() => localStorage.getItem('theme') || 'forest');

    useEffect(() => {
        localStorage.setItem('theme', theme);
    }, [theme]);

    return (
        <main
            data-theme={theme}
            className="min-h-screen bg-gradient-to-br from-base-100 via-base-200 to-base-300 text-base-content transition-colors duration-300"
        >
            <div
                className="pointer-events-none absolute inset-0 opacity-45
                [background:radial-gradient(circle_at_20%_25%,rgba(34,197,94,.6)_0%,transparent_36%),
                radial-gradient(circle_at_82%_18%,rgba(16,185,129,.42)_0%,transparent_42%)]"
            />

            <div className="relative w-full px-4 sm:px-6 md:px-8 pb-0 pt-6">
                <FrontendNavbar themes={themeOptions} theme={theme} setTheme={setTheme} />
                {children}
                <FrontendFooter />
            </div>
        </main>
    );
}

export default FrontendLayout;
