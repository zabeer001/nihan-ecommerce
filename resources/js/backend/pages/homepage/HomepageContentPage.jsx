import { useEffect, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { usePermissions } from '../../layouts/PermissionsContext';
import { input, request } from '../catalog/catalogUi';

const blank = () => ({
    placement: 'hero', media_type: 'video', position: 0, is_published: true,
    alt_text: '', heading: '', description: '', button_text: '', button_url: '', media: [],
});

function Preview({ src, type, alt, poster }) {
    return type === 'video'
        ? <video className="w-full max-w-lg max-h-64 rounded-box bg-black" src={src} poster={poster || undefined} controls playsInline preload="metadata" />
        : <img className="w-full max-w-lg max-h-64 object-contain rounded-box" src={src} alt={alt || 'Homepage image'} />;
}

function SelectedMedia({ file, type }) {
    const [url, setUrl] = useState('');
    useEffect(() => {
        const preview = URL.createObjectURL(file);
        setUrl(preview);
        return () => URL.revokeObjectURL(preview);
    }, [file]);
    return <div className="space-y-2">
        <p className="text-sm">{file.name} · {(file.size / 1024 / 1024).toFixed(1)} MB · Ready to upload</p>
        {url && <Preview src={url} type={type} />}
    </div>;
}

export default function HomepageContentPage({ mode = 'index', contentId = null }) {
    const { can, loading } = usePermissions();
    const allowed = can('homepage.manage');
    const [rows, setRows] = useState([]);
    const [form, setForm] = useState(null);
    const [files, setFiles] = useState([]);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [upload, setUpload] = useState(null);

    async function load() { setRows(await request('homepage-content')); }
    useEffect(() => {
        let cancelled = false;
        setError(''); setNotice(''); setForm(null); setFiles([]);
        if (allowed) request('homepage-content').then(items => {
            if (cancelled) return;
            setRows(items);
            if (mode === 'edit') {
                const row = items.find(item => item.id === Number(contentId));
                if (row) setForm({ ...blank(), ...row });
                else setError('Homepage content not found.');
            } else if (mode === 'create') {
                const hasHero = items.some(item => item.placement === 'hero');
                setForm({ ...blank(), ...(hasHero ? { placement: 'collection_interlude', media_type: 'image' } : {}) });
            }
        }).catch(e => { if (!cancelled) setError(e.message); });
        return () => { cancelled = true; };
    }, [allowed, mode, contentId]);

    function update(key, value) {
        if (key === 'placement' || key === 'media_type') setFiles([]);
        setForm(current => ({ ...current, [key]: value }));
    }

    function selectFiles(event) {
        const selected = Array.from(event.target.files || []);
        event.target.value = '';
        const valid = form.media_type === 'video' ? /\.(mp4|webm)$/i : /\.(jpe?g|png|webp)$/i;
        if (selected.some(file => !valid.test(file.name) || file.size > 60 * 1024 * 1024)) {
            setError(form.media_type === 'video' ? 'Choose MP4 or WebM videos up to 60 MB each.' : 'Choose JPG, PNG or WebP images up to 60 MB each.');
            return;
        }
        setError(''); setNotice('');
        setFiles(current => [...current, ...selected.map(file => ({ key: crypto.randomUUID(), file }))]);
    }

    function move(index, direction) {
        setForm(current => {
            const media = [...current.media];
            [media[index], media[index + direction]] = [media[index + direction], media[index]];
            return { ...current, media };
        });
    }

    async function save(event) {
        event.preventDefault();
        setBusy(true); setError(''); setNotice('');
        try {
            if (!form.media.length && !files.length) throw new Error('Select at least one file.');
            const payload = {};
            ['placement', 'media_type', 'position', 'is_published', 'alt_text', 'heading', 'description', 'button_text', 'button_url'].forEach(key => { payload[key] = form[key]; });
            if (form.id) payload.items = form.media.map(item => ({ id: item.id, is_published: item.is_published }));
            const saved = await request(`homepage-content${form.id ? `/${form.id}` : ''}`, { method: form.id ? 'PUT' : 'POST', body: payload });
            setForm({ ...blank(), ...saved });
            for (const [index, selected] of files.entries()) {
                const body = new FormData(); body.append('media', selected.file);
                setUpload({ name: selected.file.name, progress: 0, index: index + 1, total: files.length });
                const media = await request(`homepage-content/${saved.id}/media`, {
                    method: 'POST', body, onUploadProgress: progress => setUpload(current => ({ ...current, progress })),
                });
                setForm(current => ({ ...current, media: [...current.media, media] }));
                setFiles(current => current.filter(item => item.key !== selected.key));
            }
            setNotice('All files uploaded. Homepage content saved.');
            await load();
            if (!form.id) router.visit(`/dashboard/homepage-content/${saved.id}/edit`, { replace: true });
        } catch (e) { setError(e.message); }
        finally { setBusy(false); setUpload(null); }
    }

    async function removeMedia(item) {
        if (!window.confirm('Remove this file from the content and delete its upload?')) return;
        setBusy(true); setError(''); setNotice('');
        try {
            await request(`homepage-content/${form.id}/media/${item.id}`, { method: 'DELETE' });
            setForm(current => ({ ...current, media: current.media.filter(file => file.id !== item.id) }));
            await load();
        } catch (e) { setError(e.message); }
        finally { setBusy(false); }
    }

    async function removeContent(row) {
        if (!window.confirm('Delete this content and all of its uploaded files?')) return;
        setBusy(true); setError('');
        try { await request(`homepage-content/${row.id}`, { method: 'DELETE' }); await load(); }
        catch (e) { setError(e.message); }
        finally { setBusy(false); }
    }

    if (loading) return <p role="status">Loading permissions…</p>;
    if (!allowed) return <p className="alert alert-error">You do not have access to this page.</p>;
    const hasHero = rows.some(row => row.placement === 'hero');
    return <section className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div><h1 className="text-3xl font-bold">{mode === 'index' ? 'Homepage content' : mode === 'create' ? 'Add homepage content' : form?.placement === 'hero' ? 'Edit hero carousel' : 'Edit collection content'}</h1><p className="opacity-70">Manage each content section and its files together.</p></div>
            {mode === 'index' ? <Link className="btn btn-primary" href="/dashboard/homepage-content/create">Add content</Link> : <Link className="btn" href="/dashboard/homepage-content">Back to homepage content</Link>}
        </div>
        {error && <p role="alert" className="alert alert-error">{error}</p>}
        {notice && <p role="status" className="alert alert-success">{notice}</p>}
        {mode !== 'index' && !form && !error && <p role="status">Loading content…</p>}
        {form && <form onSubmit={save} className="rounded-box bg-base-100 p-5 space-y-5">
            <fieldset disabled={busy} className="space-y-5">
                <div className="grid gap-4 md:grid-cols-2">
                    <label className="grid gap-1">Placement<select className="select select-bordered" value={form.placement} disabled={Boolean(form.id)} onChange={e => update('placement', e.target.value)}><option value="hero" disabled={hasHero && !form.id}>Hero carousel{hasHero && !form.id ? ' (already created)' : ''}</option><option value="collection_interlude">Collection content</option></select></label>
                    <label className="grid gap-1">File type<select className="select select-bordered" value={form.media_type} onChange={e => update('media_type', e.target.value)}><option value="video">Videos</option><option value="image">Images</option></select></label>
                    <label className="grid gap-1">Section position<input className={input} type="number" min="0" max="1000" required value={form.position} onChange={e => update('position', Number(e.target.value))} /></label>
                    {form.media_type === 'image' && <label className="grid gap-1">Image description<input className={input} required maxLength={255} value={form.alt_text || ''} onChange={e => update('alt_text', e.target.value)} /></label>}
                </div>
                <div className="space-y-4">
                    <h2 className="text-xl font-semibold">{form.placement === 'hero' ? 'Carousel files' : 'Content files'} ({form.media.length})</h2>
                    <p className="text-sm opacity-70">Files appear in the order below. Add multiple {form.media_type === 'video' ? 'videos' : 'images'} to this content, then save.</p>
                    {form.media.map((item, index) => <div className="rounded-box border border-base-300 p-4 space-y-3" key={item.id}>
                        <div className="flex flex-wrap justify-between items-center gap-2"><span className="font-semibold">File {index + 1}</span><label className="flex items-center gap-2"><input type="checkbox" className="checkbox checkbox-sm" checked={item.is_published} onChange={e => update('media', form.media.map(file => file.id === item.id ? { ...file, is_published: e.target.checked } : file))} />Published</label></div>
                        <Preview src={item.media_url} type={item.media_type} alt={item.alt_text || form.alt_text} poster={item.poster_url} />
                        <div className="flex flex-wrap gap-2"><button type="button" className="btn btn-sm" disabled={index === 0} onClick={() => move(index, -1)}>Move up</button><button type="button" className="btn btn-sm" disabled={index === form.media.length - 1} onClick={() => move(index, 1)}>Move down</button><button type="button" className="btn btn-sm btn-error btn-outline" onClick={() => removeMedia(item)}>Remove file</button></div>
                    </div>)}
                    <label className="grid gap-1">{form.media_type === 'video' ? 'Add videos' : 'Add images'}<input key={form.media_type} type="file" multiple className="file-input file-input-bordered w-full" accept={form.media_type === 'video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp'} onChange={selectFiles} /></label>
                    <p className="text-sm opacity-70">Up to 60 MB per file. Selected files upload when you save.</p>
                    {files.map(selected => <div className="rounded-box border border-base-300 p-4 space-y-3" key={selected.key}><SelectedMedia file={selected.file} type={form.media_type} /><button type="button" className="btn btn-sm" onClick={() => setFiles(current => current.filter(item => item.key !== selected.key))}>Remove selection</button></div>)}
                </div>
                <details className="rounded-box border border-base-300 p-4"><summary className="cursor-pointer font-medium">Optional text and button</summary><div className="grid gap-4 pt-4 md:grid-cols-2">
                    <label className="grid gap-1">Heading<input className={input} value={form.heading || ''} onChange={e => update('heading', e.target.value)} /></label>
                    <label className="grid gap-1">Button text<input className={input} value={form.button_text || ''} onChange={e => update('button_text', e.target.value)} /></label>
                    <label className="grid gap-1">Button URL<input className={input} value={form.button_url || ''} onChange={e => update('button_url', e.target.value)} /></label>
                    <label className="grid gap-1">Description<textarea className="textarea textarea-bordered" rows="3" value={form.description || ''} onChange={e => update('description', e.target.value)} /></label>
                </div></details>
                <label className="flex items-center gap-2"><input className="checkbox" type="checkbox" checked={form.is_published} onChange={e => update('is_published', e.target.checked)} />Publish this content</label>
            </fieldset>
            {upload && <div role="status" className="space-y-2"><p>File {upload.index} of {upload.total}: {upload.name} — {upload.progress < 100 ? `Uploading ${upload.progress}%` : 'Upload received. Saving…'}</p><progress className="progress progress-primary w-full" value={upload.progress} max="100" /></div>}
            <div className="flex gap-2"><button className="btn btn-primary" disabled={busy}>{busy ? 'Saving…' : 'Save content'}</button><Link className="btn" href="/dashboard/homepage-content">Cancel</Link></div>
        </form>}
        {mode === 'index' && <div className="overflow-x-auto rounded-box bg-base-100">
            <table className="table"><thead><tr><th>Placement</th><th>Position</th><th>Files</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                {rows.map(row => <tr key={row.id}><td>{row.placement === 'hero' ? 'Hero carousel' : 'Collection content'}</td><td>{row.position}</td><td>{row.media.filter(file => file.media_type === 'video').length} video(s), {row.media.filter(file => file.media_type === 'image').length} image(s)</td><td>{row.is_published ? 'Published' : 'Draft'}</td><td><div className="flex gap-2"><Link className="btn btn-sm" href={`/dashboard/homepage-content/${row.id}/edit`}>Edit</Link><button className="btn btn-sm btn-error btn-outline" disabled={busy} onClick={() => removeContent(row)}>Delete</button></div></td></tr>)}
            </tbody></table>
            {!rows.length && <p className="p-5">No homepage content yet.</p>}
        </div>}
    </section>;
}
