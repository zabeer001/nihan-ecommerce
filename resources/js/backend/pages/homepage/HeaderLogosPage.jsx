import { useEffect, useState } from 'react';
import { usePermissions } from '../../layouts/PermissionsContext';
import { request } from '../catalog/catalogUi';

const slots = [
    { key: 'before_hover', label: 'Before hover', description: 'Shown over the hero before the header background appears.', background: '#263d32' },
    { key: 'after_hover', label: 'After hover', description: 'Shown when the header background appears on hover, keyboard focus or scrolling, and on other pages.', background: '#f4f1e9' },
];

function LogoPreview({ file, saved, background, label }) {
    const [preview, setPreview] = useState(null);
    useEffect(() => {
        if (!file) { setPreview(null); return; }
        const url = URL.createObjectURL(file);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);
    const src = preview || saved;
    return <div className="flex h-32 items-center justify-center rounded-box p-4" style={{ background }}>
        {src ? <img src={src} alt={label} className="max-h-full max-w-full object-contain" /> : <span className="text-sm text-gray-400">Current default logo</span>}
    </div>;
}

export default function HeaderLogosPage() {
    const { can, loading } = usePermissions();
    const allowed = can('homepage.manage');
    const [logos, setLogos] = useState(null);
    const [files, setFiles] = useState({});
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [revision, setRevision] = useState(0);
    useEffect(() => {
        let active = true;
        if (allowed) request('header-logos').then(data => { if (active) setLogos(data); }).catch(e => { if (active) setError(e.message); });
        return () => { active = false; };
    }, [allowed]);

    async function save(event) {
        event.preventDefault();
        setBusy(true); setError(''); setNotice('');
        try {
            const body = new FormData();
            for (const [slot, file] of Object.entries(files)) if (file) body.append(slot, file);
            setLogos(await request('header-logos', { method: 'POST', body }));
            setFiles({}); setRevision(value => value + 1); setNotice('Header logos saved.');
        } catch (e) { setError(e.message); }
        finally { setBusy(false); }
    }

    if (loading) return <p>Loading…</p>;
    if (!allowed) return <p>You do not have permission to manage header logos.</p>;
    return <div className="space-y-6">
        <h1 className="text-2xl font-semibold">Header logos</h1>
        <p>Upload separate logos for the transparent and filled header. PNG, JPG or WebP, up to 5 MB each. Transparent PNG or WebP works best.</p>
        {error && <div role="alert" className="alert alert-error">{error}</div>}
        {notice && <div role="status" className="alert alert-success">{notice}</div>}
        {!logos ? <p>Loading logos…</p> : <form onSubmit={save} className="space-y-6">
            <div className="grid gap-6 md:grid-cols-2">{slots.map(slot => <div key={slot.key} className="card bg-base-100 p-6 space-y-4">
                <label htmlFor={slot.key} className="font-semibold">{slot.label}</label>
                <p className="text-sm">{slot.description}</p>
                <LogoPreview file={files[slot.key]} saved={logos[`${slot.key}_url`]} background={slot.background} label={slot.label} />
                <input key={revision} id={slot.key} type="file" accept="image/png,image/jpeg,image/webp" disabled={busy} className="file-input file-input-bordered w-full" onChange={event => {
                    const file = event.target.files?.[0];
                    if (file && file.size > 5 * 1024 * 1024) { setError('Choose a logo up to 5 MB.'); event.target.value = ''; setFiles(current => ({ ...current, [slot.key]: undefined })); return; }
                    setError(''); setNotice(''); setFiles(current => ({ ...current, [slot.key]: file }));
                }} />
            </div>)}</div>
            <button className="btn btn-primary" disabled={busy || !Object.values(files).some(Boolean)}>{busy ? 'Saving…' : 'Save logos'}</button>
        </form>}
    </div>;
}
