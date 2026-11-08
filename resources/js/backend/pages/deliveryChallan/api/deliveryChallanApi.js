import { apiRequest, clearSession, getAccessToken } from '@/shared/apiClient';

function buildDeliveryChallansUrl(params = {}) {
    const query = new URLSearchParams();

    if (params.page) query.set('page', String(params.page));
    if (params.perPage) query.set('per_page', String(params.perPage));
    if (params.search) query.set('search', params.search);
    if (params.customerId) query.set('customer_id', String(params.customerId));
    if (params.orderId) query.set('order_id', String(params.orderId));
    if (params.status) query.set('status', params.status);

    const queryString = query.toString();
    return queryString ? `/api/delivery-challans?${queryString}` : '/api/delivery-challans';
}

export function fetchDeliveryChallans(params = {}) {
    return apiRequest(buildDeliveryChallansUrl(params), { method: 'GET', auth: true });
}

export async function exportOrderChallans(params = {}) {
    const query = new URLSearchParams();
    if (params.customerId) query.set('customer_id', String(params.customerId));
    if (params.challanDateFrom) query.set('challan_date_from', params.challanDateFrom);
    if (params.challanDateTo) query.set('challan_date_to', params.challanDateTo);

    const queryString = query.toString();
    const token = getAccessToken();
    const response = await fetch(`/api/delivery-challans/export/order-challans${queryString ? `?${queryString}` : ''}`, {
        headers: {
            Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
    });

    if (!response.ok) {
        if (response.status === 401) clearSession();
        const payload = await response.json().catch(() => null);
        const messages = payload?.errors ? Object.values(payload.errors).flat().filter(Boolean) : [];
        throw new Error(messages.join(' ') || payload?.message || 'Failed to export delivery challans.');
    }

    const disposition = response.headers.get('content-disposition') || '';
    const fileName = disposition.match(/filename="?([^";]+)"?/i)?.[1] || 'order-challans.xlsx';
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

export function fetchDeliveryChallan(deliveryChallanId) {
    return apiRequest(`/api/delivery-challans/${deliveryChallanId}`, { method: 'GET', auth: true });
}

export function createDeliveryChallan(payload) {
    return apiRequest('/api/delivery-challans', {
        method: 'POST',
        auth: true,
        body: payload,
    });
}

export function updateDeliveryChallan(deliveryChallanId, payload) {
    return apiRequest(`/api/delivery-challans/${deliveryChallanId}`, {
        method: 'PUT',
        auth: true,
        body: payload,
    });
}

export function deleteDeliveryChallan(deliveryChallanId) {
    return apiRequest(`/api/delivery-challans/${deliveryChallanId}`, {
        method: 'DELETE',
        auth: true,
    });
}
