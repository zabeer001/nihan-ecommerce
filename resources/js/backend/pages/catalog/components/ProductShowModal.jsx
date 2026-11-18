import { useEffect, useRef } from 'react';
import { money } from '../catalogUi';

export default function ProductShowModal({ detail, close }) {
  const dialogRef = useRef(null);
  const { product, variants, images } = detail;

  useEffect(() => {
    const dialog = dialogRef.current;
    dialog.showModal();
    return () => dialog.close();
  }, []);

  return <dialog ref={dialogRef} className="modal" aria-labelledby="product-show-title"
    onCancel={event => { event.preventDefault(); close(); }}
    onClick={event => { if (event.target === event.currentTarget) close(); }}>
    <div className="modal-box w-11/12 max-w-5xl bg-base-100 text-base-content space-y-5">
      <div className="flex items-center justify-between gap-3">
        <h2 id="product-show-title" className="text-2xl font-bold">{product.name}</h2>
        <button type="button" className="btn btn-sm btn-circle" aria-label="Close product details" onClick={close}>✕</button>
      </div>
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
      <div className="modal-action"><button type="button" className="btn" onClick={close}>Close</button></div>
    </div>
  </dialog>;
}
