import React, { useEffect, useState } from 'react';
import { clearSession, apiRequest } from '../../shared/apiClient';
import useAuth from '../../shared/hooks/useAuth';
import Footer from './Footer';
import Navbar from './Navbar';
import Sidebar from './Sidebar';
import themeOptions from '../../shared/themeOptions';
import DownloadPagePdfButton from '../shared/DownloadPagePdfButton';

function isDashboardShowPage(pathname) {
    return /^\/dashboard\/(?:[^/]+)\/\d+\/?$/.test(pathname);
}

function BackendLayout({ children }) {
    const [theme, setTheme] = useState(() => localStorage.getItem('theme') || 'forest');
    const [currentUser, setCurrentUser] = useState(null);
    const { isAuthenticated, role } = useAuth();
    const showPdfDownload = isDashboardShowPage(window.location.pathname);

    useEffect(() => {
        localStorage.setItem('theme', theme);
    }, [theme]);

    useEffect(() => {
        const loadProfile = async () => {
            try {
                const payload = await apiRequest('/api/profile', { auth: true });
                setCurrentUser(payload?.data || null);
            } catch (error) {
                setCurrentUser(null);
            }
        };

        loadProfile();
        window.addEventListener('focus', loadProfile);

        return () => {
            window.removeEventListener('focus', loadProfile);
        };
    }, []);

    const signOut = async () => {
        try {
            await apiRequest('/api/signout', { method: 'POST', auth: true });
        } catch (error) {
            console.error('Sign out request failed:', error);
        } finally {
            clearSession();
            window.location.href = '/sign-in';
        }
    };

    if (!isAuthenticated) {
        window.location.replace('/sign-in');
        return null;
    }

    return (
        <main
            data-theme={theme}
            className="min-h-screen bg-base-200 text-base-content"
        >
            <div className="drawer lg:drawer-open">
                <input id="admin-drawer" type="checkbox" className="drawer-toggle" />

                <div className="drawer-content flex min-h-screen flex-col">
                    <Navbar
                        theme={theme}
                        themes={themeOptions}
                        setTheme={setTheme}
                        signOut={signOut}
                        currentUser={currentUser}
                    />

                    <div className="flex-1 p-4 sm:p-6 lg:p-8">
                        {showPdfDownload ? <DownloadPagePdfButton /> : null}
                        <div id="dashboard-page-content">{children}</div>
                    </div>

                    <Footer role={role} />
                </div>

                <Sidebar />
            </div>
        </main>
    );
}

export default BackendLayout;
