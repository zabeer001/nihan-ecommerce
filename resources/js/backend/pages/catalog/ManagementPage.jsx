import { useEffect, useState } from 'react';
import { usePermissions } from '../../layouts/PermissionsContext';
import { request, money, cents, input } from './catalogUi';
import CategoryModal from './components/CategoryModal';
import StockAdjustmentForm from './components/StockAdjustmentForm';
import StockHistory from './components/StockHistory';
import OrderForm from './components/OrderForm';
import OrderInvoice from './components/OrderInvoice';
const emptyCategory = {
  name: '',
  active: true
};
export default function ManagementPage({
  section
}) {
  const { can, loading } = usePermissions();
  const [rows, setRows] = useState([]),
    [page, setPage] = useState(1),
    [last, setLast] = useState(1),
    [search, setSearch] = useState('');
  const [form, setForm] = useState(null),
    [detail, setDetail] = useState(null),
    [variant, setVariant] = useState(null),
    [history, setHistory] = useState(null);
  const [error, setError] = useState(''),
    [busy, setBusy] = useState(false),
    [order, setOrder] = useState(null),
    [options, setOptions] = useState([]),
    [variantSearch, setVariantSearch] = useState('');
  const canView = can(section + '.view');
  const canCreate = can(section + '.create');
  const canUpdate = can(section + '.update');
  const canDelete = can(section + '.delete');
  async function load() {
    const data = await request(`${section === 'inventory' ? 'variants' : section}?page=${page}&search=${encodeURIComponent(search)}`);
    setRows(data.data || data);
    setLast(data.last_page || 1);
  }
  async function run(fn) {
    setBusy(true);
    setError('');
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
    setForm(null);
    setDetail(null);
    setHistory(null);
    setOrder(null);
    setVariant(null);
  }, [section]);
  useEffect(() => {
    if (canView) run(load);
  }, [section, page, search, canView]);
  const change = (key, value) => setForm(f => ({
    ...f,
    [key]: value
  }));
  async function save(e) {
    e.preventDefault();
    await run(async () => {
      await request('categories' + (form.id ? '/' + form.id : ''), {
        method: form.id ? 'PUT' : 'POST',
        body: { name: form.name, active: form.active },
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
        setDetail(null);
      }}>Add {section === 'categories' ? 'category' : 'product'}</button>}
      {canCreate && section === 'orders' && <button className="btn btn-primary" onClick={() => {
        setOrder({
          submission_key: crypto.randomUUID(),
          customer_name: '',
          phone: '',
          email: '',
          address: '',
          delivery: '0',
          items: []
        });
        setDetail(null);
      }}>Create COD order</button>}
    </div>
    {error && !form && <div role="alert" className="alert alert-error print:hidden">{error}</div>}
    <fieldset disabled={busy} className="space-y-5">
 {form && section === 'categories' && (form.id ? canUpdate : canCreate) && <CategoryModal form={form} save={save} change={change} setForm={setForm} busy={busy} error={error} />}
 {canView && !order && <div className="print:hidden">
        <input className={input} placeholder="Search products or SKU" value={search} onChange={e => {
          setSearch(e.target.value);
          setPage(1);
        }} />
        <div className="overflow-x-auto bg-base-100 rounded-box mt-3">
          <table className="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>{section === 'orders' ? 'Customer' : section === 'inventory' ? 'SKU' : 'Name'}</th>
                <th>Details</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>{rows.map(row => <tr key={row.id}>
                <td>{row.id}</td>
                <td>{row.name || row.sku || row.customer_name}</td>
                <td>{section === 'categories' ? row.active ? 'Active' : 'Inactive' : section === 'products' ? row.status : section === 'inventory' ? `${row.product_name} · Stock ${row.stock} · ${money(row.price_cents)}` : `${row.status} · ${money(row.total_cents)}`}</td>
                <td className="flex gap-2">{canUpdate && ['categories', 'products'].includes(section) && <button className="btn btn-sm" onClick={() => {
                    if (section === 'products') {
                      window.location.assign(`/dashboard/products/${row.id}/edit`);
                      return;
                    }
                    setError('');
                    setForm({ ...row });
                    setDetail(null);
                  }}>Edit</button>}
{canDelete && section === 'categories' && <button className="btn btn-sm" onClick={() => run(async () => {
                    await request(`categories/${row.id}`, {
                      method: 'DELETE'
                    });
                    await load();
                  })}>Delete</button>}

{section === 'inventory' && <>
{canUpdate && <button className="btn btn-sm" onClick={() => setVariant({
                      id: row.id,
                      sku: row.sku,
                      quantity: '',
                      reason: ''
                    })}>Adjust</button>}
<button className="btn btn-sm" onClick={() => run(async () => setHistory(await request(`variants/${row.id}/movements`)))}>History</button>
</>}
{section === 'orders' && <button className="btn btn-sm" onClick={() => run(async () => setDetail(await request(`orders/${row.id}`)))}>View / invoice</button>}</td>
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
 
 {canCreate && order && <OrderForm canView={canView} order={order} setOrder={setOrder} run={run} setDetail={setDetail} load={load} variantSearch={variantSearch} setVariantSearch={setVariantSearch} options={options} setOptions={setOptions} />}
 {section === 'orders' && canView && detail && <OrderInvoice canUpdate={canUpdate} detail={detail} run={run} setDetail={setDetail} load={load} />}
 </fieldset>
    {busy && <p role="status">Saving / loading…</p>}
    <style>{'@media print { body * { visibility:hidden; } #order-invoice, #order-invoice * { visibility:visible; } #order-invoice { position:absolute; left:0; top:0; width:100%; } #order-invoice .print\\:hidden { display:none; } }'}</style>
  </section>;
}
