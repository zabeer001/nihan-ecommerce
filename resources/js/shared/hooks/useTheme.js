import { useEffect, useState } from 'react';

function isSharedTheme(theme) {
    return theme === 'light' || theme === 'dark';
}

function readSharedTheme() {
    return document.cookie.match(/(?:^|;\s*)nihan-theme=(light|dark)(?:;|$)/)?.[1];
}

function readStoredTheme() {
    try {
        return localStorage.getItem('theme');
    } catch {
        return null;
    }
}

function writeSharedTheme(theme) {
    const cookie = isSharedTheme(theme) ? `${theme}; Max-Age=31536000` : '; Max-Age=0';
    document.cookie = `nihan-theme=${cookie}; Path=/; SameSite=Lax`;
}

function linkedTheme(url) {
    const theme = url.searchParams.get('storefront_theme');
    return url.pathname === '/sign-in' && isSharedTheme(theme) ? theme : null;
}

export default function useTheme() {
    const [theme, setTheme] = useState(() =>
        linkedTheme(new URL(window.location.href)) || readSharedTheme() || readStoredTheme() || 'forest'
    );

    useEffect(() => {
        const url = new URL(window.location.href);
        const handoff = linkedTheme(url);
        if (handoff) {
            writeSharedTheme(handoff);
            url.searchParams.delete('storefront_theme');
            window.history.replaceState(window.history.state, '', url);
        }

        function syncTheme() {
            const sharedTheme = readSharedTheme() || readStoredTheme();
            if (sharedTheme) setTheme(sharedTheme);
        }

        function onStorage(event) {
            if (event.key === 'theme' || event.key === null) syncTheme();
        }

        window.addEventListener('focus', syncTheme);
        window.addEventListener('storage', onStorage);
        return () => {
            window.removeEventListener('focus', syncTheme);
            window.removeEventListener('storage', onStorage);
        };
    }, []);

    useEffect(() => {
        try {
            localStorage.setItem('theme', theme);
        } catch {
            // The displayed theme still works if storage is disabled.
        }
    }, [theme]);

    function chooseTheme(next) {
        writeSharedTheme(next);
        setTheme(next);
    }

    return [theme, chooseTheme];
}
