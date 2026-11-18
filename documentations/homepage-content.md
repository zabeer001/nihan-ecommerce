# Homepage content

Homepage media and copy are managed in one `homepage_contents` table. Run `php artisan migrate` to create the table, assign `homepage.manage` to administrator roles, and add the initial hero video and collection illustration to the configured `public` storage disk.

## Admin workflow

Open **Dashboard → Homepage**. Add rows for the **Hero carousel** or **Collection image** placement, upload the media, set its position (lower numbers appear first), and publish it. Hero rows can use an image or a muted looping video and can optionally include a poster, heading, description, and button. Image rows require alt text. Staff roles can be granted `homepage.manage` under Roles.

## API

- `GET /api/storefront/homepage-content` returns published rows ordered by placement and position.
- Authenticated admin endpoints under `/api/admin/homepage-content` provide list, create, update, and delete operations. They require `homepage.manage`; create and update accept multipart form data with a `media` file and optional `poster` file.
- Media is saved to Laravel's configured `public` disk. Responses expose `media_url` and `poster_url` for use by the storefront.

The Next.js homepage requests the storefront endpoint during server rendering, filters rows by placement, and displays hero slides and collection image blocks in their saved order. If the request fails or a placement has no rows, the current bundled media remains the fallback.
