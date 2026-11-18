import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { usePermissions } from '../../layouts/PermissionsContext';
import { request, money } from './catalogUi';

export default function ProductShowPage({ productId }) {
  const { can, loading } = usePermissions();
  const allowed = can('products.view');
  const [detail, setDetail] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    setDetail(null);
    setError('');
    if (allowed) request(`products/${productId}`).then(data => {
      if (!cancelled) setDetail(data);
    }).catch(e => { if (!cancelled) setError(e.message); });
    return () => { cancelled = true; };
  }, [allowed, productId]);

  if (loading) return <p role="status">Loading permissions…</p>;
  if (!allowed) return <p className="alert alert-error">You do not have access to this page.</p>;
  const { product, variants, images } = detail || {};

  return <section className="space-y-5">
    <div className="flex flex-wrap items-center justify-between gap-3">
      <h1 className="text-3xl font-bold">{product?.name || 'Product details'}</h1>
      <div className="flex flex-wrap gap-2">
        <Link className="btn" href={`/dashboard/products/${productId}/reviews`}>Reviews</Link>
        {detail && can('products.update') && <Link className="btn btn-primary" href={`/dashboard/products/${productId}/edit`}>Edit product</Link>}
        <Link className="btn" href="/dashboard/products">Back to products</Link>
      </div>
    </div>
    {error && <p role="alert" className="alert alert-error">{error}</p>}
    {!detail && !error && <p role="status">Loading product…</p>}
    {detail && <div className="rounded-box bg-base-100 p-5 space-y-5">
      <dl className="grid gap-3 sm:grid-cols-2">
        {[
          ['Product code', product.product_code], ['Status', product.status],
          ['Categories', product.categories?.map(category => category.name).join(', ')],
          ['Brand', product.brand], ['Model', product.model], ['Color', product.color], ['Unit', product.unit],
        ].map(([label, value]) => <div key={label}><dt className="text-sm opacity-60">{label}</dt><dd>{value || '—'}</dd></div>)}
      </dl>
      {product.description && <div><h3 className="font-semibold">Description</h3><p className="whitespace-pre-wrap">{product.description}</p></div>}
      {images.length > 0 && <div className="flex flex-wrap gap-3">{images.map(image => <img key={image.id} src={image.url} alt={product.name} className="h-28 w-28 rounded-box object-contain" />)}</div>}
      <div className="overflow-x-auto">
        <table className="table"><caption className="text-left text-lg font-semibold mb-2">SKU &amp; variants</caption>
          <thead><tr><th>SKU / Variant</th><th>Price before discount</th><th>Retail / discounted price</th><th>Purchase</th><th>Dealer</th><th>Corporate</th><th>Stock</th><th>Status</th></tr></thead>
          <tbody>{variants.map(variant => <tr key={variant.id}>
            <td><div>{variant.sku}</div>{variant.name && <div>{variant.name}</div>}
              <div className="text-sm opacity-70">{Object.entries(variant.attributes || {}).map(([key, value]) => `${key}: ${value}`).join(' · ')}</div>
              {variant.images?.length > 0 && <div className="flex flex-wrap gap-2 mt-2">{variant.images.map(image => <img key={image.id} src={image.url} alt={variant.name || variant.sku} className="h-16 w-16 rounded object-contain" />)}</div>}
            </td>
            {['compare_at_price_cents', 'price_cents', 'purchase_price_cents', 'dealer_price_cents', 'corporate_price_cents'].map(field => <td key={field}>{variant[field] == null ? '—' : money(variant[field])}</td>)}
            <td>{variant.stock}</td><td>{variant.active ? 'Active' : 'Archived'}</td>
          </tr>)}</tbody>
        </table>
      </div>
    </div>}
  </section>;
}
