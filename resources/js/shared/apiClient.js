export const AUTH_STATE_CHANGED_EVENT = 'auth-state-changed';

function dispatchAuthStateChanged() {
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event(AUTH_STATE_CHANGED_EVENT));
    }
}

export function getAccessToken() {
    return localStorage.getItem('access_token');
}

export function hasStoredSession() {
    return Boolean(getAccessToken());
}

export function getStoredRole() {
    return (localStorage.getItem('role') || 'user').toLowerCase();
}

export function setAccessToken(token) {
    if (token) {
        localStorage.setItem('access_token', token);
        dispatchAuthStateChanged();
    }
}

export function setRole(role) {
    if (role) {
        localStorage.setItem('role', role);
        dispatchAuthStateChanged();
    }
}

export function clearSession() {
    localStorage.removeItem('access_token');
    localStorage.removeItem('role');
    dispatchAuthStateChanged();
}

function buildHeaders({ auth = false, headers = {}, body, token }) {
    const merged = {
        Accept: 'application/json',
        ...headers,
    };

    if (!(body instanceof FormData)) {
        merged['Content-Type'] = merged['Content-Type'] || 'application/json';
    }

    if (auth) {
        if (token) {
            merged.Authorization = `Bearer ${token}`;
        }
    }

    return merged;
}

let tokenRefreshPromise = null;

async function responsePayload(response) {
    return (response.headers.get('content-type') || '').includes('application/json')
        ? response.json()
        : response.text();
}

function requestError(response, payload) {
    const validationMessages = payload?.errors && typeof payload.errors === 'object'
        ? Object.values(payload.errors).flat().map(String).filter(Boolean)
        : [];
    const message = validationMessages.length
        ? [...new Set(validationMessages)].join(' ')
        : (payload?.message || payload?.error || 'Request failed.');
    const error = new Error(message);
    error.status = response.status;
    error.payload = payload;
    error.validationErrors = payload?.errors || {};
    return error;
}

async function refreshAccessToken(token) {
    // Share one refresh across concurrent API requests.
    if (!tokenRefreshPromise) {
        tokenRefreshPromise = (async () => {
            const response = await fetch('/api/refresh', {
                method: 'POST',
                headers: buildHeaders({ auth: true, token }),
                credentials: 'same-origin',
            });
            const payload = await responsePayload(response);
            if (!response.ok) throw requestError(response, payload);
            if (!payload?.data?.access_token) {
                throw requestError({ status: 401 }, { message: 'No refreshed access token returned.' });
            }
            // Do not restore a session that was signed out while refresh was running.
            if (getAccessToken() === token) setAccessToken(payload.data.access_token);
            return payload;
        })().finally(() => { tokenRefreshPromise = null; });
    }
    return tokenRefreshPromise;
}

export async function apiRequest(url, options = {}, retryAfterRefresh = true) {
    const { method = 'GET', body, auth = false, headers = {} } = options;
    const requestToken = auth ? getAccessToken() : null;
    const response = await fetch(url, {
        method,
        headers: buildHeaders({ auth, headers, body, token: requestToken }),
        body: body instanceof FormData ? body : body ? JSON.stringify(body) : undefined,
        credentials: 'same-origin',
    });
    const payload = await responsePayload(response);
    if (!response.ok) {
        if (auth && response.status === 401 && requestToken && retryAfterRefresh && url !== '/api/refresh' && url !== '/api/signout') {
            try {
                if (getAccessToken() === requestToken) await refreshAccessToken(requestToken);
            } catch (error) {
                // A temporary outage should not erase the user's session.
                if ([400, 401, 403].includes(error.status) && getAccessToken() === requestToken) clearSession();
                throw error;
            }
            if (getAccessToken()) return apiRequest(url, options, false);
        }
        if (auth && response.status === 401 && getAccessToken() === requestToken) clearSession();
        throw requestError(response, payload);
    }
    return payload;
}

export async function refreshToken() {
    const token = getAccessToken();
    if (!token) throw new Error('No authenticated session is available to refresh.');
    return refreshAccessToken(token);
}
