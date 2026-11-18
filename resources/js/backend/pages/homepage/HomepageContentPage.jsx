import { useEffect, useState } from 'react';
import { usePermissions } from '../../layouts/PermissionsContext';
import { input, request } from '../catalog/catalogUi';

const blank = () => ({
    placement: 'hero', content_type: 'hero_slide', media_type: 'video', position: 0,
    is_published: true, alt_text: '', heading: '', description: '', button_text: '', button_url: '',
    media: null, poster: null,
});

export default function HomepageContentPage() {
    const { can, loading } = usePermissions();
    const allowed = can('homepage.manage');
    const [rows, setRows] = useState([]);
    const [form, setForm] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    async function load() { setRows(await request('homepage-content')); }
    useEffect(() => {
        if (allowed) load().catch(e => setError(e.message));
    }, [allowed]);

    function update(key, value) {
        setForm(current => {
            const next = { ...current, [key]: value };
            if (key === 'placement') {
                next.content_type = value === 'hero' ? 'hero_slide' : 'image_block';
                if (value !== 'hero') next.media_type = 'image';
            }
            return next;
        });
    }

    async function save(event) {
        event.preventDefault();
        setBusy(true); setError(''); setNotice('');
        try {
            const body = new FormData();
            ['placement', 'content_type', 'media_type', 'position', 'alt_text', 'heading', 'description', 'button_text', 'button_url'].forEach(key => {
                body.append(key, String(form[key] ?? ''));
            });
            body.append('is_published', form.is_published ? '1' : '0');
            if (form.media) body.append('media', form.media);
            if (form.poster) body.append('poster', form.poster);
            if (form.id) body.append('_method', 'PUT');
            await request(`homepage-content${form.id ? `/${form.id}` : ''}`, { method: 'POST', body });
            setForm(null); await load(); setNotice('Homepage content saved.');
        } catch (e) { setError(e.message); }
        finally { setBusy(false); }
    }

    async function remove(row) {
        if (!window.confirm('Delete this homepage content?')) return;
        setBusy(true); setError('');
        try { await request(`homepage-content/${row.id}`, { method: 'DELETE' }); await load(); }
        catch (e) { setError(e.message); }
        finally { setBusy(false); }
    }

    if (loading) return <p role="status">Loading permissions…</p>;
    if (!allowed) return <p className="alert alert-error">You do not have access to this page.</p>;

    return <section className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div><h1 className="text-3xl font-bold">Homepage content</h1><p className="opacity-70">Manage hero slides and the collection image block.</p></div>
            {!form && <button className="btn btn-primary" onClick={() => { setError(''); setForm(blank()); }}>Add content</button>}
        </div>
        {error && <p role="alert" className="alert alert-error">{error}</p>}
        {notice && <p role="status" className="alert alert-success">{notice}</p>}
        {form && <form onSubmit={save} className="rounded-box bg-base-100 p-5 space-y-4">
            <h2 className="text-xl font-semibold">{form.id ? 'Edit content' : 'New content'}</h2>
            <div className="grid gap-4 md:grid-cols-2">
                <label className="grid gap-1">Placement<select className="select select-bordered" value={form.placement} onChange={e => update('placement', e.target.value)}><option value="hero">Hero carousel</option><option value="collection_interlude">Collection image</option></select></label>
                {form.placement === 'hero' && <label className="grid gap-1">Media type<select className="select select-bordered" value={form.media_type} onChange={e => update('media_type', e.target.value)}><option value="video">Video</option><option value="image">Image</option></select></label>}
                <label className="grid gap-1">Position<input className={input} type="number" min="0" max="1000" value={form.position} onChange={e => update('position', e.target.value)} required /></label>
                <label className="grid gap-1">{form.media_type === 'video' ? 'Video file' : 'Image file'}<input className="file-input file-input-bordered w-full" type="file" accept={form.media_type === 'video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp'} required={!form.id} onChange={e => update('media', e.target.files?.[0] || null)} />{form.media_url && <a className="link" href={form.media_url} target="_blank" rel="noreferrer">Current media</a>}</label>
                {form.media_type === 'video' && <label className="grid gap-1">Poster image<input className="file-input file-input-bordered w-full" type="file" accept="image/jpeg,image/png,image/webp" onChange={e => update('poster', e.target.files?.[0] || null)} />{form.poster_url && <a className="link" href={form.poster_url} target="_blank" rel="noreferrer">Current poster</a>}</label>}
                {form.media_type === 'image' && <label className="grid gap-1">Alt text<input className={input} value={form.alt_text} onChange={e => update('alt_text', e.target.value)} required /></label>}
                <label className="grid gap-1">Heading (optional)<input className={input} value={form.heading} onChange={e => update('heading', e.target.value)} /></label>
                <label className="grid gap-1">Button text (optional)<input className={input} value={form.button_text} onChange={e => update('button_text', e.target.value)} /></label>
                <label className="grid gap-1">Button URL (optional)<input className={input} value={form.button_url} onChange={e => update('button_url', e.target.value)} placeholder="/products" /></label>
            </div>
            <label className="grid gap-1">Description (optional)<textarea className="textarea textarea-bordered" rows="3" value={form.description} onChange={e => update('description', e.target.value)} /></label>
            <label className="flex items-center gap-2"><input className="checkbox" type="checkbox" checked={Boolean(form.is_published)} onChange={e => update('is_published', e.target.checked)} />Published</label>
            <div className="flex gap-2"><button className="btn btn-primary" disabled={busy}>{busy ? 'Saving…' : 'Save content'}</button><button type="button" className="btn" onClick={() => setForm(null)}>Cancel</button></div>
        </form>}
        <div className="overflow-x-auto rounded-box bg-base-100">
            <table className="table"><thead><tr><th>Placement</th><th>Position</th><th>Content</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>{rows.map(row => <tr key={row.id}><td>{row.placement === 'hero' ? 'Hero carousel' : 'Collection image'}</td><td>{row.position}</td><td>{row.heading || row.media_type}{row.alt_text ? ` · ${row.alt_text}` : ''}</td><td>{row.is_published ? 'Published' : 'Draft'}</td><td><div className="flex gap-2"><button className="btn btn-sm" onClick={() => { setError(''); setForm({ ...row, media: null, poster: null }); }}>Edit</button><button className="btn btn-sm btn-error btn-outline" disabled={busy} onClick={() => remove(row)}>Delete</button></div></td></tr>)}</tbody>
            </table>
            {!rows.length && <p className="p-5">No homepage content yet.</p>}
        </div>
    </section>;
}
