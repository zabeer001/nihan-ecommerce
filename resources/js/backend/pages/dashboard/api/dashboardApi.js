import { apiRequest } from '@/shared/apiClient';

export function fetchDashboardSummary() {
    return apiRequest('/api/dashboard/summary', { method: 'GET', auth: true });
}
