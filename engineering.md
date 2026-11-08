# Engineering Notes

This project is being developed as a Laravel API backend with an Inertia + React frontend. The main idea is simple: keep business rules in small service classes, keep controllers thin, protect API access with JWT and middleware, and let the frontend separate server state from UI state.

## Backend Architecture

The backend is Laravel 12 with PHP 8.2. API routes live in `routes/api.php`, browser/dashboard routes live in the web route files, and the main domain code is organized under `app/Http/Controllers/Api`, `app/Services`, `app/Models`, and `app/Http/Middleware`.

### API Design

API endpoints are grouped by resource and responsibility:

- Auth endpoints: `signin`, `register`, `forgot-password`, `reset-password`, `refresh`, `signout`
- User endpoints: `Route::apiResource('users', UserController::class)`
- Order endpoints: `Route::apiResource('orders', OrderController::class)->only(['index', 'show'])`
- Profile endpoints: protected by `auth:api`

Controllers should stay small. A controller method should receive the request, delegate to a service, and return a JSON response. The actual validation, querying, persistence, and business decisions should live in service classes.

Example pattern:

```php
public function signin(Request $request, AuthSigninService $service): JsonResponse
{
    return $service->handle($request);
}
```

### JWT Authentication

Authentication uses `php-open-source-saver/jwt-auth` through Laravel's `api` guard.

The JWT flow is:

1. User posts credentials to `/api/signin`.
2. `AuthSigninService` validates the request.
3. `SignInService` calls `Auth::guard('api')->attempt($credentials)`.
4. A successful login returns `access_token`, `token_type`, `expires_in`, and `user`.
5. Protected requests send `Authorization: Bearer <token>`.
6. `/api/refresh` refreshes a valid token.
7. `/api/signout` invalidates the current token.

Frozen accounts are blocked at sign-in and also checked during protected auth flows.

### Middleware

Middleware is used for cross-cutting rules that should not be duplicated inside controllers.

- `auth:api` protects JWT-only routes.
- `active_account` blocks frozen accounts.
- `role` is available for role-based access control.
- Auth routes are throttled to reduce abuse.

Middleware aliases are registered in `bootstrap/app.php`.

### Response Shape

API responses should use a consistent shape through `ApiResponse`.

Success:

```json
{
  "success": true,
  "status": 200,
  "message": "Orders fetched successfully.",
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "status": 422,
  "message": "Validation failed.",
  "errors": {}
}
```

This makes the frontend predictable because every API call can read `message`, `data`, and `errors` in the same way.

### Service Layer

Services are the main place for business logic. This keeps controllers thin and makes logic easier to test.

Current examples:

- `app/Http/Controllers/Api/Auth/Services/AuthSigninService.php`
- `app/Services/Auth/SignInService.php`
- `app/Http/Controllers/Api/User/Services/UserIndexService.php`
- `app/Http/Controllers/Api/Order/Services/OrderIndexService/OrderIndexService.php`
- `app/Http/Controllers/Api/Order/Services/OrderShowService/OrderShowService.php`

Use service classes when the action has validation, authorization decisions, persistence, external API calls, complex queries, or formatting rules.

### SOLID Principles

The backend should follow SOLID in a practical Laravel way.

Single Responsibility:
Each class should have one job. Controllers route requests, services handle use cases, models represent data, middleware handles request gates, and resources/formatters shape output.

Open/Closed:
Prefer adding a new service, method, filter, or resource instead of modifying unrelated code. For example, adding new order filters should extend `OrderIndexService` without changing frontend auth or unrelated order display logic.

Liskov Substitution:
When introducing interfaces or interchangeable classes, each implementation should behave consistently for the caller. Do not make one implementation return a different payload shape or throw unrelated exceptions.

Interface Segregation:
Keep contracts small. A service that only signs in a user should not also handle registration, refresh, password reset, and profile updates.

Dependency Inversion:
Depend on injected services instead of creating everything manually inside controllers. Laravel's container should resolve service dependencies.

### Data Access

Use Eloquent models and relationships for domain data. Query services should eagerly load required relations to avoid N+1 issues.

Example from order listing:

```php
Order::query()
    ->with([
        'customer:id,name,email,mobile',
        'creator:id,name',
    ])
    ->withCount('items');
```

Pagination should be done on the backend with validated `per_page` limits. Filters should be validated before being applied to queries.

### Validation

Validate every API request before using input. Keep validation close to the use case unless a form request is reused across multiple actions.

Good defaults:

- Use type rules such as `string`, `integer`, `date`, `email`.
- Add limits such as `max:100`, `min:1`, `max:100`.
- Validate foreign keys with `exists`.
- Validate date ranges with `after_or_equal`.

### Authorization

Authorization should happen before mutation or sensitive reads.

Use a combination of:

- JWT auth for identity
- `active_account` for account status
- `role` middleware for route-level role checks
- Policy or service-level checks for domain-specific ownership rules

### Testing

The project uses Pest/Laravel tests. Auth and API behavior should be covered with feature tests, especially for:

- Sign-in, sign-out, token refresh
- Frozen account behavior
- Password reset
- User permissions
- Order and report API responses

Run tests with:

```bash
composer test
```

Format PHP code with Laravel Pint when needed:

```bash
./vendor/bin/pint
```

## Frontend Architecture

The frontend uses React 18, Inertia, Vite, Tailwind CSS, DaisyUI, Zustand, and TanStack Query.

The entry point is `resources/js/app.jsx`. It creates the Inertia app, resolves frontend/backend/common pages, applies layouts, and wraps the app with `QueryClientProvider`.

### Page Organization

Frontend code is split by area:

- `resources/js/frontend/pages`: public/customer-facing pages
- `resources/js/backend/pages`: dashboard/admin pages
- `resources/js/common`: shared pages or views
- `resources/js/shared`: shared API clients, hooks, utilities, analytics, and links

Backend pages use `BackendLayout`; frontend pages use `FrontendLayout`. Common pages choose layout based on explicit context or the current route.

### API Client

All fetch requests should go through `resources/js/shared/apiClient.js`.

The API client handles:

- JSON headers
- FormData requests
- Bearer token injection
- Session clearing on `401`
- Shared error parsing
- Token refresh

Protected API calls should pass `auth: true`.

```js
apiRequest('/api/orders', { method: 'GET', auth: true });
```

### TanStack Query

TanStack Query is used for server state: data that comes from the API and must be loaded, cached, refreshed, or invalidated.

Use TanStack Query for:

- API lists
- API detail pages
- mutations
- loading states
- error states
- cache invalidation after writes

The global query client is configured in `resources/js/app.jsx`:

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

Query keys should include every filter that changes the response.

```js
queryKey: ['orders', page, perPage, activeSearch, activeStatus, activeDeliveryStatus]
```

### Zustand

Zustand is used for local UI state that needs to be shared across components but does not belong in the server cache.

Good Zustand use cases:

- Search input values
- Active filters
- Pagination state
- Modal state
- UI preferences
- URL-synced page state

Current examples:

- `useOrderIndexStore`
- `useOrderShowStore`
- `useHomePageStore`
- `useReportTablesStore`

The order index uses Zustand for filters and pagination, while TanStack Query fetches the actual order data. This separation is important:

- Zustand owns what the user is currently doing in the UI.
- TanStack Query owns what the server returned.

### Auth State

The frontend stores the JWT access token and role in `localStorage`.

Shared helpers:

- `getAccessToken`
- `setAccessToken`
- `setRole`
- `clearSession`
- `hasStoredSession`
- `refreshToken`

When auth state changes, the API client dispatches an `auth-state-changed` browser event so UI pieces can react.

### Component Design

Pages should compose smaller components instead of becoming one large file.

Example order page structure:

- Page: `OrderIndexPage.jsx`
- API module: `api/orderApi.js`
- Store: `_store/useOrderIndexStore.js`
- Components: `_components/OrderFilters.jsx`, `OrdersTable.jsx`, `OrdersPagination.jsx`
- Formatters: `_utils/orderIndexFormatters.js`

Keep API calls out of presentational components. Components should receive data and callbacks through props unless they are page-level containers.

### Frontend Rules

- Use TanStack Query for API data.
- Use Zustand for shared UI state.
- Keep URL filters in sync when the page must be shareable.
- Keep API request builders in `api/*.js` modules.
- Keep formatting helpers in `_utils`.
- Keep reusable UI pieces in `_components`.
- Handle loading, empty, and error states on every API page.
- Do not duplicate JWT header logic outside `apiClient.js`.

## Development Workflow

Install dependencies:

```bash
composer install
npm install
```

Run the full local development stack:

```bash
composer dev
```

Build frontend assets:

```bash
npm run build
```

Run tests:

```bash
composer test
```

## API Development Checklist

When adding a new API feature:

1. Add or update the route in `routes/api.php`.
2. Create a thin controller method.
3. Create a service class for the use case.
4. Validate all request input.
5. Apply auth, role, and account-status middleware where needed.
6. Use Eloquent relationships and eager loading intentionally.
7. Return responses with `ApiResponse`.
8. Add feature tests for success, validation failure, unauthorized access, and important edge cases.
9. Add a frontend API module if the React app consumes the endpoint.
10. Use TanStack Query or mutations from the page layer.

## Frontend Development Checklist

When adding a new frontend page:

1. Put the page under `frontend`, `backend`, or `common`.
2. Let Inertia apply the correct layout.
3. Put API calls in an `api` module.
4. Use TanStack Query for server state.
5. Use Zustand only for shared UI state.
6. Split tables, filters, headers, and forms into components.
7. Keep formatting logic in `_utils`.
8. Handle loading, empty, and error states.
9. Keep auth requests behind `apiClient.js`.
10. Make the URL reflect filters and pagination when users need shareable dashboard state.

