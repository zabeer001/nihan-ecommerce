import { apiRequest } from '../../../../shared/apiClient';

function buildUsersUrl(params = {}) {
    const query = new URLSearchParams();

    if (params.page) {
        query.set('page', String(params.page));
    }

    if (params.search) {
        query.set('search', params.search);
    }

    if (params.role && params.role !== 'all') {
        query.set('role', params.role);
    }

    if (params.status && params.status !== 'all') {
        query.set('status', params.status);
    }

    const queryString = query.toString();
    return queryString ? `/api/users?${queryString}` : '/api/users';
}

export async function fetchUsersPage(params = {}) {
    return apiRequest(buildUsersUrl(params), { method: 'GET', auth: true });
}

export async function fetchAllUsers(params = {}) {
    const firstPayload = await fetchUsersPage({ ...params, page: 1 });
    const firstPage = firstPayload?.data;
    const allUsers = Array.isArray(firstPage?.data) ? [...firstPage.data] : [];
    const currentPage = Number(firstPage?.current_page || 1);
    const lastPage = Number(firstPage?.last_page || 1);

    if (lastPage > currentPage) {
        const pageRequests = [];

        for (let page = currentPage + 1; page <= lastPage; page += 1) {
            pageRequests.push(fetchUsersPage({ ...params, page }));
        }

        const pagePayloads = await Promise.all(pageRequests);

        pagePayloads.forEach((pagePayload) => {
            const pageUsers = Array.isArray(pagePayload?.data?.data) ? pagePayload.data.data : [];
            allUsers.push(...pageUsers);
        });
    }

    return allUsers;
}

export async function fetchUser(userId) {
    return apiRequest(`/api/users/${userId}`, { method: 'GET', auth: true });
}

export async function createUser(payload) {
    return apiRequest('/api/users', {
        method: 'POST',
        auth: true,
        body: payload,
    });
}

export async function updateUser(userId, payload) {
    return apiRequest(`/api/users/${userId}`, {
        method: 'PUT',
        auth: true,
        body: payload,
    });
}

export async function deleteUser(userId) {
    return apiRequest(`/api/users/${userId}`, {
        method: 'DELETE',
        auth: true,
    });
}
