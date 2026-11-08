import { apiRequest } from '../../../shared/apiClient';

export async function fetchReportStats() {
    return apiRequest('/api/reports/stats', { method: 'GET', auth: true });
}

export async function fetchSiteAnalytics(params = {}) {
    return apiRequest(buildReportUrl('/api/reports/site-analytics', params), { method: 'GET', auth: true });
}

function buildReportUrl(path, params = {}) {
    const query = new URLSearchParams();

    if (params.page) {
        query.set('page', String(params.page));
    }

    if (params.per_page) {
        query.set('per_page', String(params.per_page));
    }

    const queryString = query.toString();
    return queryString ? `${path}?${queryString}` : path;
}

export async function fetchTitleCounts(params = {}) {
    return apiRequest(buildReportUrl('/api/reports/title-counts', params), { method: 'GET', auth: true });
}

export async function fetchDistributorCounts(params = {}) {
    return apiRequest(buildReportUrl('/api/reports/distributor-counts', params), { method: 'GET', auth: true });
}

export async function fetchYearCounts(params = {}) {
    return apiRequest(buildReportUrl('/api/reports/year-counts', params), { method: 'GET', auth: true });
}
