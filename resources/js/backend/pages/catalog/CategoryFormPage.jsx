import { useEffect, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { usePermissions } from '../../layouts/PermissionsContext';
import { request } from './catalogUi';
import CatalogForm from './components/CatalogForm';

export default function CategoryFormPage({ categoryId }) {
  const { can, loading } = usePermissions();
  const allowed = can('categories.update');
  const [form, setForm] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;
    setForm(null);
    setError('');
    if (allowed) request(`categories/${categoryId}`).then(category => {
      if (!cancelled) setForm({ ...category, content_cards: category.content_cards || [] });
    }).catch(e => { if (!cancelled) setError(e.message); });
    return () => { cancelled = true; };
  }, [allowed, categoryId]);

  async function save(event) {
    event.preventDefault();
    setBusy(true);
    setError('');
    try {
      await request(`categories/${categoryId}`, {
        method: 'PUT', body: { name: form.name, active: form.active, content_cards: form.content_cards || [], usage_guide: form.usage_guide || null },
      });
      router.visit('/dashboard/categories');
    } catch (e) { setError(e.message); }
    finally { setBusy(false); }
  }

  if (loading) return <p role="status">Loading permissions…</p>;
  if (!allowed) return <p className="alert alert-error">You do not have access to this page.</p>;

  return <section className="space-y-5">
    <div className="flex flex-wrap items-center justify-between gap-3">
      <h1 className="text-3xl font-bold">Edit category</h1>
      <Link className="btn" href="/dashboard/categories">Back to categories</Link>
    </div>
    {error && <p role="alert" className="alert alert-error">{error}</p>}
    {!form && !error && <p role="status">Loading category…</p>}
    {form && <fieldset disabled={busy}>
      <CatalogForm form={form} section="categories" save={save}
        change={(key, value) => setForm(current => ({ ...current, [key]: value }))}
        setForm={() => router.visit('/dashboard/categories')} />
    </fieldset>}
    {busy && <p role="status">Saving…</p>}
  </section>;
}
