JavaScript App Caching Design
=============================

This React/Inertia frontend uses two separate cache layers:

1. Inertia page caching for internal page visits like `/dashboard`, `/dashboard/tapes`, `/stats`, and `/site-info`.
2. TanStack Query caching for API requests like `/api/tapes`, `/api/users`, `/api/collections`, and `/api/tape-reports`.

These layers solve different problems. Inertia caches page responses. TanStack Query caches API data used inside mounted React pages.

Cache Locations
---------------

### Global TanStack Query client

Defined in:

```txt
resources/js/app.jsx
```

Current default query settings:

```js
const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 10 * 1000,
            retry: false,
            refetchOnWindowFocus: false,
        },
    },
});
```

Meaning:

- API data stays fresh for 10 seconds.
- Repeating the same query key inside 10 seconds should reuse cached data.
- Failed queries do not retry automatically.
- Switching browser tabs back to the app does not auto-refetch.

### Shared cached Inertia link

Defined in:

```txt
resources/js/shared/CachedLink.jsx
```

Current defaults:

```jsx
prefetch="click"
cacheFor="10s"
preserveState
preserveScroll
```

Meaning:

- Internal page links can use Inertia's page cache for 10 seconds.
- Page prefetch does not happen on hover.
- The request starts when the user clicks the link.
- State and scroll are preserved for smoother internal navigation.

Page Cache vs API Cache
-----------------------

### Inertia page cache

Example request:

```txt
GET /dashboard/tapes
```

This is a page visit. It returns the Inertia page payload and mounts a React page component.

Controlled by:

```txt
resources/js/shared/CachedLink.jsx
```

Used by internal links in frontend and backend layouts/pages.

### TanStack API cache

Example request:

```txt
GET /api/tapes?page=1&per_page=25
```

This is an API request made from a mounted React component with `useQuery`.

Controlled by:

```txt
resources/js/app.jsx
```

Used by pages/components that call `useQuery`, `useMutation`, or `queryClient`.

Important: TanStack Query does not call every API globally. A page's query runs when that page component mounts, or when code explicitly asks TanStack Query to fetch/prefetch a query.

Expected Behavior
-----------------

First visit to a page:

```txt
/dashboard/tapes
```

The browser may show a page request.

First API load inside that page:

```txt
/api/tapes?page=1&per_page=25
```

The browser may show an API request.

Navigating back to the same page or repeating the same API query within 10 seconds should prefer cached data when the query key and page URL match.

After 10 seconds, cached data becomes stale and the app can fetch fresh data again.

Internal Link Rule
------------------

Use `CachedLink` for normal internal GET navigation:

```jsx
import Link from '@/shared/CachedLink';

<Link href="/dashboard/tapes">Tapes</Link>
```

Use it for:

- Backend sidebar links.
- Backend page action links.
- Frontend navbar/footer links.
- Normal internal page links.

Do not use it for:

- External links.
- `mailto:` links.
- POST/PUT/PATCH/DELETE actions.
- Buttons that mutate data.
- Forced redirects after mutations, such as `window.location.href`.

Mutation Rule
-------------

Create, update, delete, import, and status-change flows should not rely on old cached data.

After a mutation, invalidate affected TanStack queries:

```js
queryClient.invalidateQueries({ queryKey: ['backend-tapes'] });
```

Direct redirects after mutations are acceptable when the page should reload fresh state:

```js
window.location.href = '/dashboard/tapes?created=1';
```

Query Key Rule
--------------

TanStack cache only works correctly when the query key includes every value that changes the API result.

Good example:

```js
useQuery({
    queryKey: ['backend-tapes', page, perPage, filters.search, filters.user_id],
    queryFn: () => fetchTapes({ page, per_page: perPage, ...filters }),
});
```

If `user_id`, page number, search text, sort mode, or filters change the API response, they must be included in the query key.

Zustand + TanStack Pattern
-------------------------

Some backend pages still use Zustand as the shared UI state holder.

In that pattern:

1. TanStack Query owns API fetch timing and cache.
2. The query function returns real API data.
3. A small sync effect copies cached query data into Zustand.
4. UI components continue reading from Zustand.

This is useful when many page sections already depend on the same store.

Valid `useEffect` Usage
-----------------------

After TanStack Query, avoid `useEffect` for API fetching.

Still valid:

- Syncing route params into local/Zustand state.
- Syncing successful query data into Zustand.
- Debouncing form input into active filters.
- Redirecting unauthorized users.
- UI-only effects.

Not valid:

```js
useEffect(() => {
    loadTapes();
}, []);
```

Use `useQuery` for API loading instead.

Network Tab Guide
-----------------

When debugging, separate these request types:

```txt
/dashboard/... = Inertia page request
/api/...       = API request controlled by TanStack Query or direct API utilities
```

If a `/dashboard/...` request appears, check `CachedLink` and Inertia navigation.

If a `/api/...` request appears, check the mounted page's `useQuery`, query key, enabled guard, and invalidations.

Current Cache Duration
----------------------

The current app-wide cache duration is:

```txt
10 seconds
```

Change both places together if the app should use a different global default:

```txt
resources/js/app.jsx
resources/js/shared/CachedLink.jsx
```
