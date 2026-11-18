# NIHAN starter and demo data

Run inside the Laravel app container:

```sh
docker compose exec -T laravel.test php artisan migrate
docker compose exec -T laravel.test php artisan db:seed
docker compose exec -T laravel.test php artisan storage:link
```

`db:seed` adds data to the existing database. No reset or `migrate:fresh` is needed.
The bundled fixtures work offline and are copied into the public storage disk.

## Included data on a fresh database

- System permissions (including customers and homepage), system roles and two demo staff roles. Existing role grants are preserved; newly introduced permissions are granted to administrators.
- The existing starter administrator, created only if its email does not exist. Reruns preserve its password, name, role and frozen status. No extra staff logins are created.
- Two header logos based on the existing NIHAN vector wordmark: white before hover, gold after hover. Filled logo slots are preserved.
- Four homepage blocks: a video hero, two image interludes and an unpublished campaign preview. Existing content occupying those positions is preserved. Legacy fixture blocks receive missing headings, descriptions and buttons.
- Accessories and Fragrance categories, scent cards and three-step usage guides. Only null content is filled; intentional empty arrays are preserved.
- 13 products and 20 variants: diffusers, 50 ml/100 ml refills, starter kits, duo bundles, discovery sets and an unpublished preview. Includes multi-attribute variants, discounts, cost/dealer/corporate prices, barcode identifiers, low stock, out-of-stock and inactive examples. New bundles reuse existing illustrative product photos.
- 78 clearly labelled demo reviews across published, pending and rejected moderation states. Demo reviews are sample content, not customer endorsements. Published samples are visible on the storefront; remove them before launch.
- 16 demo customers and 28 orders across Pending, Confirmed, Processing, Shipped, Delivered and Cancelled, with staff and storefront sources. Extended orders span approximately eight weeks. Repeat customers have multiple orders.
- Real order lines, totals, status events and inventory movements, created through the application services. Cancellation restores stock. Stock movement timestamps reflect actual seeding; order and status-event dates simulate history.

## Repeatability

Catalog records use stable `DEMO-NIHAN-*` product codes and SKUs. Orders use deterministic UUID submission keys. Reruns do not restock existing variants, recreate existing orders or reset status histories. Existing products and their variants are left unchanged; therefore new pricing defaults apply to newly created demo products, not previously customized ones. Existing review text, ratings and moderation statuses are preserved using the demo author/source identity. Renaming a demo author or deleting a demo row allows the sample to be recreated on the next run.

Missing logo slots and null category/default homepage content are filled again on a later run. Existing nonempty values are preserved. If a demo SKU was deactivated, deleted, or has insufficient stock, its new sample orders are skipped with a warning rather than reactivating or restocking it.

Run individual seeders when needed:

```sh
docker compose exec -T laravel.test php artisan db:seed --class=HeaderLogoSeeder
docker compose exec -T laravel.test php artisan db:seed --class=CategoryContentSeeder
docker compose exec -T laravel.test php artisan db:seed --class=ProductReviewSeeder
docker compose exec -T laravel.test php artisan db:seed --class=StorefrontDemoOrderSeeder
```

For a clean database, run the complete `DatabaseSeeder` so dependencies are created in order. The legacy `ExpandedDemoSeeder`, `OrderDemoSeeder` and `ItemSeeder` belong to the previous ERP schema and are deliberately excluded.

## Missing product images

`ProductImageFallbackSeeder` runs after the demo catalog and can also run independently:

```sh
docker compose exec -T laravel.test php artisan db:seed --class=ProductImageFallbackSeeder
```

It applies to all products, including drafts, but skips any product with an existing product or variant image record. Known fragrances and product types use matching local images; other recognised categories use a stable choice based on the product ID. Unknown categories receive a neutral placeholder. Assets are bundled in `public/product-fallbacks` and copied into public storage under `seed-fallbacks/`.

Reruns preserve existing selections, uploads, product details, inventory and orders. A later real product or variant gallery upload removes that product's reserved fallback records only after its save succeeds; shared fallback files remain available to other products. Merely staging an upload does not remove the fallback. Products created later receive defaults the next time the seeder runs. Existing image records pointing to missing files are preserved for admin review rather than replaced.
