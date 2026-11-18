# Homepage content

Each `homepage_contents` record is a content section. Its `media()` relationship contains ordered files in `homepage_media`. The hero carousel is one parent with many files, rather than one content row per slide. The migration groups existing hero rows under the first carousel and preserves file paths, ordering, publication settings, posters, and slide copy. It does not move or delete uploaded files.

## Admin workflow

Open **Dashboard → Homepage** and edit **Hero carousel**. Use the single **Add videos** picker to select multiple files. Each selection shows a local preview; saving uploads the files individually with progress and completion feedback. Uploaded files can be reordered, published/unpublished, or removed within that same editor. Every content editor offers an image/video choice. It controls which files the picker and upload endpoint accept. Changing that choice preserves existing files with their original types; collection content supports both images and videos on the storefront. Optional copy and the publish setting belong to the parent content.

The list displays one row per content section with its file count. Creating a second hero carousel is rejected; add files to the existing carousel instead. Removing one file preserves the carousel and its remaining files. Deleting a content section deletes its files as well.

## API

All admin endpoints require `homepage.manage`:

- `GET /api/admin/homepage-content` returns content parents with nested `media` arrays.
- `POST /api/admin/homepage-content` creates a parent; `PUT /api/admin/homepage-content/{id}` updates its settings. The optional `items` array contains every saved child ID in display order, with each child's `is_published` setting.
- `POST /api/admin/homepage-content/{id}/media` uploads one multipart `media` file (up to 60 MB) into that parent's collection.
- `DELETE /api/admin/homepage-content/{id}/media/{mediaId}` removes a file scoped to its parent.
- `DELETE /api/admin/homepage-content/{id}` removes the parent and its media.

`GET /api/storefront/homepage-content` keeps the existing flat slide response consumed by Next.js. It expands each published parent's published files in their saved order. Each slide has a child `id`, a parent `content_id`, and its media URL. Existing per-slide copy is retained during migration; changing a shared copy field applies the new value across the parent's files.
