const AUTH_REDIRECT_KEY = 'auth_redirect_intent';
const PREVIEW_TAPE_ID_PARAM = 'previewTapeId';

function canUseSessionStorage() {
    return typeof window !== 'undefined' && typeof window.sessionStorage !== 'undefined';
}

function buildPreviewUrl(previewTapeId, options = {}) {
    const url = new URL(window.location.origin);
    url.pathname = '/';
    url.searchParams.set(PREVIEW_TAPE_ID_PARAM, String(previewTapeId));

    if (options.comment) {
        url.searchParams.set('comment', '1');
    }

    if (options.report) {
        url.searchParams.set('report', '1');
    }

    return `${url.pathname}${url.search}`;
}

export function saveAuthRedirectIntent(previewTapeId, options = {}) {
    if (!canUseSessionStorage() || !previewTapeId) {
        return;
    }

    window.sessionStorage.setItem(
        AUTH_REDIRECT_KEY,
        JSON.stringify({
            previewTapeId,
            url: buildPreviewUrl(previewTapeId, options),
        })
    );
}

export function consumeAuthRedirectIntent() {
    if (!canUseSessionStorage()) {
        return null;
    }

    const rawValue = window.sessionStorage.getItem(AUTH_REDIRECT_KEY);

    if (!rawValue) {
        return null;
    }

    window.sessionStorage.removeItem(AUTH_REDIRECT_KEY);

    try {
        return JSON.parse(rawValue);
    } catch (_error) {
        return null;
    }
}

export function clearAuthRedirectIntent() {
    if (!canUseSessionStorage()) {
        return;
    }

    window.sessionStorage.removeItem(AUTH_REDIRECT_KEY);
}

export function getPreviewTapeIdFromUrl() {
    if (typeof window === 'undefined') {
        return null;
    }

    const params = new URLSearchParams(window.location.search);
    const previewTapeId = Number(params.get(PREVIEW_TAPE_ID_PARAM));

    if (!Number.isInteger(previewTapeId) || previewTapeId < 1) {
        return null;
    }

    return previewTapeId;
}

export function shouldFocusPreviewCommentFromUrl() {
    if (typeof window === 'undefined') {
        return false;
    }

    const params = new URLSearchParams(window.location.search);

    return params.get('comment') === '1';
}

export function shouldOpenPreviewReportFromUrl() {
    if (typeof window === 'undefined') {
        return false;
    }

    const params = new URLSearchParams(window.location.search);

    return params.get('report') === '1';
}

export function clearPreviewCommentFocusInUrl() {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.delete('comment');
    url.searchParams.delete('report');

    window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
}

export function syncPreviewTapeIdInUrl(previewTapeId) {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);

    if (previewTapeId) {
        url.searchParams.set(PREVIEW_TAPE_ID_PARAM, String(previewTapeId));
    } else {
        url.searchParams.delete(PREVIEW_TAPE_ID_PARAM);
        url.searchParams.delete('comment');
        url.searchParams.delete('report');
    }

    window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
}
