import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { usePermissions } from '../../layouts/PermissionsContext';
import { input, request } from './catalogUi';

const blank = () => ({ author_name: '', rating: 5, title: '', body: '', status: 'published' });

export default function ProductReviewsPage({ productId }) {
  const { can, loading } = usePermissions();
  const allowed = can('products.view') || can('products.update');
  const editable = can('products.update');
  const [name, setName] = useState('');
  const [rows, setRows] = useState([]);
  const [page, setPage] = useState(1);
  const [last, setLast] = useState(1);
  const [status, setStatus] = useState('');
  const [form, setForm] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  async function load() {
    const data = await request(`products/${productId}/reviews?${new URLSearchParams({ page, status })}`);
    setRows(data.data); setLast(data.last_page);
    if (page > data.last_page) setPage(data.last_page);
  }
  useEffect(() => {
    let cancelled = false;
    if (allowed) {
      setError('');
      Promise.all([
        request(`products/${productId}`),
        request(`products/${productId}/reviews?${new URLSearchParams({ page, status })}`),
      ]).then(([detail, data]) => {
        if (cancelled) return;
        setName(detail.product.name); setRows(data.data); setLast(data.last_page);
      }).catch(e => { if (!cancelled) setError(e.message); });
    }
    return () => { cancelled = true; };
  }, [allowed, productId, page, status]);

  async function save(event) {
    event.preventDefault();
    const addAnother = event.nativeEvent.submitter?.value === 'another';
    setBusy(true); setError(''); setNotice('');
    try {
      await request(`products/${productId}/reviews${form.id ? `/${form.id}` : ''}`, {
        method: form.id ? 'PUT' : 'POST', body: form,
      });
      setForm(addAnother ? blank() : null); setNotice(addAnother ? 'Review saved. Add the next review below.' : 'Review saved.'); await load();
    } catch (e) { setError(e.message); }
    finally { setBusy(false); }
  }
  async function remove(row) {
    if (!window.confirm('Delete this review?')) return;
    setBusy(true); setError(''); setNotice('');
    try {
      await request(`products/${productId}/reviews/${row.id}`, { method: 'DELETE' });
      if (form?.id === row.id) setForm(null);
      setNotice('Review deleted.'); await load();
    } catch (e) { setError(e.message); }
    finally { setBusy(false); }
  }
  const change = (key, value) => setForm(current => ({ ...current, [key]: value }));
  if (loading) return <p role="status">Loading permissions…</p>;
  if (!allowed) return <p className="alert alert-error">You do not have access to this page.</p>;

  return <section className="space-y-5">
    <div className="flex flex-wrap justify-between items-center gap-3">
      <div><h1 className="text-3xl font-bold">Product reviews</h1><p className="opacity-70">{name}</p></div>
      <div className="flex flex-wrap gap-2">{editable && !form && <button className="btn btn-primary" onClick={() => { setForm(blank()); setError(''); }}>Add review manually</button>}<Link className="btn" href="/dashboard/products">Back to products</Link></div>
    </div>
    {error && <p role="alert" className="alert alert-error">{error}</p>}
    {notice && <p role="status" className="alert alert-success">{notice}</p>}
    {editable && form && <form onSubmit={save} className="rounded-box bg-base-100 p-5 space-y-4">
      <h2 className="text-xl font-semibold">{form.id ? 'Edit review' : 'Add review manually'}</h2>
      <fieldset disabled={busy} className="grid gap-4 md:grid-cols-2">
        <label className="grid gap-1">Reviewer name<input className={input} required maxLength={100} value={form.author_name} onChange={e => change('author_name', e.target.value)} /></label>
        <label className="grid gap-1">Rating<select className="select select-bordered" value={form.rating} onChange={e => change('rating', Number(e.target.value))}>{[5, 4, 3, 2, 1].map(rating => <option key={rating} value={rating}>{rating} {rating === 1 ? 'star' : 'stars'}</option>)}</select></label>
        <label className="grid gap-1">Title (optional)<input className={input} maxLength={150} value={form.title || ''} onChange={e => change('title', e.target.value)} /></label>
        <label className="grid gap-1">Status<select className="select select-bordered" value={form.status} onChange={e => change('status', e.target.value)}><option value="published">Published</option><option value="pending">Pending approval</option><option value="rejected">Rejected</option></select></label>
        <label className="grid gap-1 md:col-span-2">Review<textarea className="textarea textarea-bordered" required minLength={5} maxLength={3000} rows={4} value={form.body} onChange={e => change('body', e.target.value)} /></label>
      </fieldset>
      <div className="flex gap-2"><button className="btn btn-primary" disabled={busy}>{busy ? 'Saving…' : 'Save review'}</button>{!form.id && <button className="btn" type="submit" name="action" value="another" disabled={busy}>Save and add another</button>}<button type="button" className="btn" disabled={busy} onClick={() => setForm(null)}>Cancel</button></div>
    </form>}
    <label className="flex flex-wrap items-center gap-2">Filter status<select className="select select-bordered" value={status} onChange={e => { setStatus(e.target.value); setPage(1); }}><option value="">All reviews</option><option value="pending">Pending approval</option><option value="published">Published</option><option value="rejected">Rejected</option></select></label>
    <div className="overflow-x-auto rounded-box bg-base-100"><table className="table"><thead><tr><th>Reviewer</th><th>Rating</th><th>Review</th><th>Status</th><th>Source</th><th>Actions</th></tr></thead><tbody>{rows.map(row => <tr key={row.id}>
      <td>{row.author_name}<div className="text-xs opacity-60">{new Date(row.created_at).toLocaleDateString()}</div></td><td>{row.rating} / 5</td><td className="max-w-md"><strong>{row.title}</strong><p className="whitespace-pre-wrap break-words">{row.body}</p></td><td className="capitalize">{row.status}</td><td>{row.source === 'admin' ? 'Manually added' : 'Customer submission'}</td><td>{editable && <div className="flex gap-2"><button className="btn btn-sm" disabled={busy} onClick={() => { setForm({ ...row }); setError(''); }}>Edit / approve</button><button className="btn btn-sm btn-error btn-outline" disabled={busy} onClick={() => remove(row)}>Delete</button></div>}</td>
    </tr>)}</tbody></table>{!rows.length && <p className="p-5">No reviews found.</p>}</div>
    <div className="flex items-center gap-3"><button className="btn btn-sm" disabled={busy || page <= 1} onClick={() => setPage(current => current - 1)}>Previous</button><span>Page {page} of {last}</span><button className="btn btn-sm" disabled={busy || page >= last} onClick={() => setPage(current => current + 1)}>Next</button></div>
  </section>;
}
