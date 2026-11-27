import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { request, money } from '../catalogUi';

export default function OrderProductPicker({ items, onAdd, reservedQuantities = {} }) {
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const products = useQuery({
    queryKey: ['pos-products', search, page],
    queryFn: () => request(`orders/variants?${new URLSearchParams({ search, page })}`),
    staleTime: 0,
  });

  useEffect(() => {
    const timer = setTimeout(() => { setSearch(searchInput.trim()); setPage(1); }, 250);
    return () => clearTimeout(timer);
  }, [searchInput]);

  return (
    <section className="min-w-0 rounded-box border border-base-300 bg-base-100 p-4 sm:p-5 space-y-4" aria-label="Product catalog">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div><h2 className="text-xl font-bold">Products</h2><p className="text-sm opacity-70">Click a product to add it to the cart.</p></div>
        <button type="button" className="btn btn-ghost btn-sm" disabled={products.isFetching} onClick={() => products.refetch()}>Refresh stock</button>
      </div>
      <form role="search" onSubmit={event => { event.preventDefault(); setSearch(searchInput.trim()); setPage(1); }}>
        <label className="input input-bordered flex items-center gap-2 w-full">
          <svg className="size-5 shrink-0 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 5 5" /></svg>
          <input type="search" className="grow min-w-0" aria-label="Search product name, SKU or barcode" placeholder="Search product name, SKU or barcode…"
            maxLength={255} value={searchInput} onChange={event => setSearchInput(event.target.value)} />
        </label>
      </form>
      {products.error && <div role="alert" className="alert alert-error"><span>{products.error.message}</span>
        <button type="button" className="btn btn-sm" onClick={() => products.refetch()}>Retry</button></div>}
      {products.isPending && <p role="status" className="py-12 text-center opacity-70">Loading products…</p>}
      {products.data && <>
        <p className="text-sm opacity-60">{products.data.total} product variant{products.data.total === 1 ? '' : 's'}{products.isFetching ? ' · Updating…' : ''}</p>
        {!products.data.data.length && <p role="status" className="py-12 text-center opacity-70">No products found. Try a different name or SKU.</p>}
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-2 2xl:grid-cols-3">
          {products.data.data.map(variant => {
            const selected = items.find(item => item.variant_id === variant.id);
            const inCart = Number(selected?.quantity) || 0;
            const maximum = Math.min(Number(variant.stock) + Number(reservedQuantities[variant.id] ?? 0), 1000000);
            const unavailable = maximum <= inCart || maximum < 1 || (!selected && items.length >= 100);
            return <button key={variant.id} type="button" disabled={unavailable}
              aria-label={`Add ${variant.product_name} / ${variant.sku} to cart`}
              className={`flex flex-col overflow-hidden rounded-box border text-left transition-colors disabled:cursor-not-allowed ${selected ? 'border-primary bg-primary/5' : 'border-base-300 hover:border-primary'} disabled:opacity-60`}
              onClick={() => onAdd(variant)}>
              <div className="relative flex h-28 w-full items-center justify-center bg-base-200 sm:h-36">
                {variant.image_url ? <img src={variant.image_url} alt="" loading="lazy" className="h-full w-full object-contain p-3" />
                  : <span className="text-3xl font-bold opacity-20" aria-hidden="true">{variant.product_name.slice(0, 2).toUpperCase()}</span>}
                {inCart > 0 && <span className="badge badge-primary absolute right-2 top-2">In cart: {inCart}</span>}
              </div>
              <div className="flex w-full flex-1 flex-col gap-1 p-3">
                <span className="font-semibold leading-snug">{variant.product_name}</span>
                {variant.name && <span className="text-xs opacity-70">{variant.name}</span>}
                <span className="break-all text-xs opacity-50">{variant.sku}</span>
                <span className="mt-1 font-bold text-primary">{money(variant.price_cents)}</span>
                <span className="mt-auto flex flex-wrap items-center justify-between gap-1 pt-2 text-xs">
                  <span className={variant.stock > 0 ? 'text-success' : 'text-error'}>{variant.stock > 0 ? `Stock: ${variant.stock}` : 'Out of stock'}</span>
                  <span className="font-semibold">{unavailable ? (selected ? 'Limit reached' : 'Unavailable') : '+ Add'}</span>
                </span>
              </div>
            </button>;
          })}
        </div>
        {products.data.last_page > 1 && <div className="flex items-center justify-between gap-2 border-t border-base-300 pt-4">
          <button type="button" className="btn btn-sm" disabled={page <= 1 || products.isFetching} onClick={() => setPage(current => current - 1)}>Previous</button>
          <span className="text-sm">Page {page} of {products.data.last_page}</span>
          <button type="button" className="btn btn-sm" disabled={page >= products.data.last_page || products.isFetching} onClick={() => setPage(current => current + 1)}>Next</button>
        </div>}
      </>}
    </section>

  );
}
