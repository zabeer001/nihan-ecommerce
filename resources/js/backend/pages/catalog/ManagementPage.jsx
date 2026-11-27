import { useEffect, useState } from 'react';
import { usePermissions } from '../../layouts/PermissionsContext';
import { request, money, input } from './catalogUi';
import DeleteCatalogModal from './components/DeleteCatalogModal';
import CategoryModal from './components/CategoryModal';
import { Link } from '@inertiajs/react';
import StockAdjustmentForm from './components/StockAdjustmentForm';
import StockHistory from './components/StockHistory';
const emptyCategory = {
  name: '',
  active: true,
  content_cards: []
};
export default function ManagementPage({
  section
}) {
  const { can, loading } = usePermissions();
  const [rows, setRows] = useState([]),
    [page, setPage] = useState(1),
    [last, setLast] = useState(1),
    [search, setSearch] = useState('');
  const [reference, setReference] = useState('');
  const [orderFilters, setOrderFilters] = useState({ reference: '', source: '', status: '' });
  const [form, setForm] = useState(null),
    [variant, setVariant] = useState(null),
    [history, setHistory] = useState(null);
  const [notice, setNotice] = useState('');
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [error, setError] = useState(''),
    [busy, setBusy] = useState(false);
  const canView = can(section + '.view');
  const canCreate = can(section + '.create');
  const canUpdate = can(section + '.update');
  const canDelete = can(section + '.delete');
  async function load() {
    const params = new URLSearchParams({ page, ...(section === 'orders' ? orderFilters : { search }) });
    const data = await request(`${section === 'inventory' ? 'variants' : section}?${params}`);
    setRows(data.data || data);
    setLast(data.last_page || 1);
  }
  async function run(fn) {
    setBusy(true);
    setError('');
    setNotice('');
    try {
      await fn();
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  }
  useEffect(() => {
    setPage(1);
    setDeleteTarget(null);
    setForm(null);
    setHistory(null);
    setVariant(null);
    setSearch('');
    setReference('');
    setOrderFilters({ reference: '', source: '', status: '' });
  }, [section]);
  useEffect(() => {
    if (canView) run(load);
  }, [section, page, search, canView, orderFilters]);
  const change = (key, value) => setForm(f => ({
    ...f,
    [key]: value
  }));
  async function save(e) {
    e.preventDefault();
    await run(async () => {
      await request('categories' + (form.id ? '/' + form.id : ''), {
        method: form.id ? 'PUT' : 'POST',
        body: { name: form.name, active: form.active, content_cards: form.content_cards || [], usage_guide: form.usage_guide || null },
      });
      setForm(null);
      if (canView) await load();
    });
  }
  if (loading) return <p role="status">Loading permissions…</p>;
  if (!canView && !canCreate) return <p className="alert alert-error">You do not have access to this page.</p>;
  return <section className="space-y-5">
    <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
      <h1 className="text-3xl font-bold capitalize">{section}</h1>
      {canCreate && ['categories', 'products'].includes(section) && <button className="btn btn-primary" onClick={() => {
        if (section === 'products') {
          window.location.assign('/dashboard/products/create');
          return;
        }
        setError('');
        setForm({ ...emptyCategory });
      }}>Add {section === 'categories' ? 'category' : 'product'}</button>}
      {canCreate && section === 'orders' && <Link className="btn btn-primary" href="/dashboard/orders/create">Create COD order</Link>}
    </div>
    {error && !form && <div role="alert" className="alert alert-error print:hidden">{error}</div>}
    {notice && <p role="status" className="alert alert-success">{notice}</p>}
    <fieldset disabled={busy} className="space-y-5">
 {form && section === 'categories' && (form.id ? canUpdate : canCreate) && <CategoryModal form={form} save={save} change={change} setForm={setForm} busy={busy} error={error} />}
 {canView && <div className="print:hidden">
        {section === 'orders' ? <div className="flex flex-wrap items-end gap-3">
          <form className="flex items-end gap-2" onSubmit={e => {
            e.preventDefault();
            setPage(1);
            setOrderFilters(filters => ({ ...filters, reference: reference.trim().replace(/^#/, '') }));
          }}>
            <label className="grid gap-1">Order reference
              <input className={input} inputMode="numeric" pattern="#?[0-9]+" value={reference} onChange={e => setReference(e.target.value)} />
            </label>
            <button className="btn" type="submit">Find order</button>
          </form>
          <label className="grid gap-1">Source
            <select className="select select-bordered" value={orderFilters.source} onChange={e => {
              setPage(1);
              setOrderFilters({ ...orderFilters, source: e.target.value });
            }}>
              <option value="">All sources</option>
              <option value="storefront">Storefront</option>
              <option value="staff">Staff</option>
            </select>
          </label>
          <label className="grid gap-1">Status
            <select className="select select-bordered" value={orderFilters.status} onChange={e => {
              setPage(1);
              setOrderFilters({ ...orderFilters, status: e.target.value });
            }}>
              <option value="">All statuses</option>
              {['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'].map(status => <option key={status}>{status}</option>)}
            </select>
          </label>
        </div> : <input className={input} placeholder="Search products or SKU" value={search} onChange={e => {
          setSearch(e.target.value);
          setPage(1);
        }} />}
        <div className="overflow-x-auto bg-base-100 rounded-box mt-3">
          <table className="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>{section === 'orders' ? 'Customer' : section === 'inventory' ? 'SKU' : 'Name'}</th>
                {section === 'inventory' && <th>Variant name</th>}
                {section === 'orders' && <><th>Source</th><th>Status</th></>}
                <th>Details</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>{rows.map(row => <tr key={row.id}>
                <td>{row.id}</td>
                <td>{section === 'inventory' ? row.sku : row.name || row.sku || row.customer_name}</td>
                {section === 'inventory' && <td>{row.name || '—'}</td>}
                {section === 'orders' && <><td>{row.source === 'storefront' ? 'Storefront' : 'Staff'}</td>
                  <td>{canUpdate ? <select className="select select-bordered select-sm" aria-label={`Status for order #${row.id}`}
                    value={row.status} disabled={!row.allowed_statuses?.length} onChange={event => {
                      const status = event.target.value;
                      run(async () => {
                        try { await request(`orders/${row.id}/status`, { method: 'PATCH', body: { status } }); }
                        catch (failure) { await load(); throw failure; }
                        await load();
                        setNotice(`Order #${row.id} status updated.`);
                      });
                    }}>
                    <option value={row.status}>{row.status}</option>
                    {row.allowed_statuses?.map(status => <option key={status}>{status}</option>)}
                  </select> : row.status}</td></>}
                <td>{section === 'categories' ? row.active ? 'Active' : 'Inactive' : section === 'products' ? row.status : section === 'inventory' ? `${row.product_name} · Stock ${row.stock} · ${money(row.price_cents)}` : money(row.total_cents)}</td>
                <td className="flex gap-2">{section === 'products' && canView && <><Link className="btn btn-sm" href={`/dashboard/products/${row.id}`}>Show</Link><Link className="btn btn-sm" href={`/dashboard/products/${row.id}/reviews`}>Reviews</Link></>}{canUpdate && ['categories', 'products'].includes(section) && <button className="btn btn-sm" onClick={() => {
                    if (section === 'products') {
                      window.location.assign(`/dashboard/products/${row.id}/edit`);
                      return;
                    }
                    window.location.assign(`/dashboard/categories/${row.id}/edit`);
                  }}>Edit</button>}
{canDelete && ['categories', 'products'].includes(section) && <button type="button" className="btn btn-sm btn-error btn-outline" onClick={() => {
                    setError('');
                    setDeleteTarget(row);
                  }}>Delete</button>}


{section === 'inventory' && <>
{canUpdate && <button className="btn btn-sm" onClick={() => setVariant({
                      id: row.id,
                      sku: row.sku,
                      quantity: '',
                      reason: ''
                    })}>Adjust</button>}
<button className="btn btn-sm" onClick={() => run(async () => setHistory(await request(`variants/${row.id}/movements`)))}>History</button>
</>}
{section === 'orders' && <>
  <Link className="btn btn-sm" href={`/dashboard/orders/${row.id}/invoice`}>View invoice</Link>
  {canUpdate && <>
    <Link className="btn btn-sm" href={`/dashboard/orders/${row.id}/edit`}>Edit order</Link>
  </>}
</>}</td>
              </tr>)}</tbody>
          </table>
          {!rows.length && <p className="p-5">No records yet.</p>}
        </div>
        <div className="flex gap-3 mt-3">
          <button className="btn btn-sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</button>
          <span>Page {page} of {last}</span>
          <button className="btn btn-sm" disabled={page >= last} onClick={() => setPage(page + 1)}>Next</button>
        </div>
      </div>}
 {section === 'inventory' && canView && canUpdate && variant && <StockAdjustmentForm variant={variant} setVariant={setVariant} run={run} setHistory={setHistory} load={load} />}
 {canView && history && <StockHistory history={history} run={run} setHistory={setHistory} />}
 
 </fieldset>
    {deleteTarget && ['categories', 'products'].includes(section) && canDelete && <DeleteCatalogModal
      item={deleteTarget} kind={section === 'products' ? 'product' : 'category'} busy={busy} error={error}
      onClose={() => { if (!busy) setDeleteTarget(null); }}
      onConfirm={() => run(async () => {
        await request(`${section}/${deleteTarget.id}`, { method: 'DELETE' });
        setDeleteTarget(null);
        setNotice(section === 'products' ? 'Product deleted. Existing orders and stock history are preserved.' : 'Category deleted.');
        if (rows.length === 1 && page > 1) setPage(page - 1);
        else await load();
      })} />}
    {busy && <p role="status">Saving / loading…</p>}
  </section>;
}
