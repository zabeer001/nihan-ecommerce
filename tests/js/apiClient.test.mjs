import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../../resources/js/shared/apiClient.js', import.meta.url), 'utf8');
let instance = 0;
async function client() {
    const values = new Map([['access_token', 'expired-token'], ['role', 'admin']]);
    globalThis.localStorage = {
        getItem: key => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
        removeItem: key => values.delete(key),
    };
    return import(`data:text/javascript;base64,${Buffer.from(source + `\n// instance ${instance++}`).toString('base64')}`);
}
const json = (body, status = 200) => new Response(JSON.stringify(body), {
    status, headers: { 'Content-Type': 'application/json' },
});

test('concurrent expired requests share one refresh and retry with the new token', async () => {
    const api = await client();
    let refreshes = 0;
    globalThis.fetch = async (url, options) => {
        if (url === '/api/refresh') {
            refreshes++;
            return json({ data: { access_token: 'new-token' } });
        }
        return options.headers.Authorization === 'Bearer new-token' ? json({ ok: true }) : json({}, 401);
    };
    const responses = await Promise.all([api.apiRequest('/api/profile', { auth: true }), api.apiRequest('/api/admin/products', { auth: true })]);
    assert.equal(refreshes, 1);
    assert.ok(responses.every(response => response.ok));
    assert.equal(api.getAccessToken(), 'new-token');
});

test('temporary refresh failures preserve the session', async () => {
    const api = await client();
    globalThis.fetch = async url => json({}, url === '/api/refresh' ? 503 : 401);
    await assert.rejects(api.apiRequest('/api/profile', { auth: true }), { status: 503 });
    assert.equal(api.getAccessToken(), 'expired-token');
});

test('unrefreshable sessions are cleared without retry loops', async () => {
    const api = await client();
    let calls = 0;
    globalThis.fetch = async () => { calls++; return json({}, 401); };
    await assert.rejects(api.apiRequest('/api/profile', { auth: true }), { status: 401 });
    assert.equal(calls, 2);
    assert.equal(api.getAccessToken(), null);
});

test('a refresh finishing after signout cannot restore the session', async () => {
    const api = await client();
    let finishRefresh;
    globalThis.fetch = () => new Promise(resolve => { finishRefresh = resolve; });
    const pending = api.refreshToken();
    api.clearSession();
    finishRefresh(json({ data: { access_token: 'new-token' } }));
    await pending;
    assert.equal(api.getAccessToken(), null);
});

test('validation messages stay readable', async () => {
    const api = await client();
    globalThis.fetch = async () => json({ errors: { name: ['Name is required.'] } }, 422);
    await assert.rejects(api.apiRequest('/api/admin/products', { auth: true }), { message: 'Name is required.', status: 422 });
});
