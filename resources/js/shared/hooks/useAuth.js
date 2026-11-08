import { useEffect, useState } from 'react';
import {
    AUTH_STATE_CHANGED_EVENT,
    getStoredRole,
    hasStoredSession,
} from '../apiClient';

export default function useAuth() {
    const [isAuthenticated, setIsAuthenticated] = useState(hasStoredSession);
    const [role, setRole] = useState(getStoredRole);
    const [isCheckingAuth, setIsCheckingAuth] = useState(false);

    useEffect(() => {
        const syncAuthState = () => {
            setIsAuthenticated(hasStoredSession());
            setRole(getStoredRole());
            setIsCheckingAuth(false);
        };

        syncAuthState();
        window.addEventListener('storage', syncAuthState);
        window.addEventListener('focus', syncAuthState);
        window.addEventListener(AUTH_STATE_CHANGED_EVENT, syncAuthState);

        return () => {
            window.removeEventListener('storage', syncAuthState);
            window.removeEventListener('focus', syncAuthState);
            window.removeEventListener(AUTH_STATE_CHANGED_EVENT, syncAuthState);
        };
    }, []);

    return {
        isAuthenticated,
        isCheckingAuth,
        role,
        isAdminOrSuperadmin: role === 'admin' || role === 'superadmin',
    };
}
