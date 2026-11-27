import { useEffect, useState } from 'react';
import { usePermissions } from '../../layouts/PermissionsContext';
import { request, Field, input } from '../catalog/catalogUi';

const emptyPassword = { current_password: '', password: '', password_confirmation: '' };

function errorMessage(error) {
    return Object.values(error.validationErrors || {}).flat().join(' ') || error.message;
}

export default function SettingsPage() {
    const { can, loading } = usePermissions();
    const manageStorefront = can('storefront.manage');
    const [settings, setSettings] = useState(null);
    const [password, setPassword] = useState(emptyPassword);
    const [seoBusy, setSeoBusy] = useState(false);
    const [passwordBusy, setPasswordBusy] = useState(false);
    const [seoError, setSeoError] = useState('');
    const [passwordError, setPasswordError] = useState('');
    const [seoNotice, setSeoNotice] = useState('');
    const [passwordNotice, setPasswordNotice] = useState('');

    useEffect(() => {
        if (!manageStorefront) return;
        let active = true;
        request('settings/storefront').then(data => { if (active) setSettings(data); })
            .catch(error => { if (active) setSeoError(errorMessage(error)); });
        return () => { active = false; };
    }, [manageStorefront]);

    async function saveSeo(event) {
        event.preventDefault();
        setSeoBusy(true); setSeoError(''); setSeoNotice('');
        try {
            setSettings(await request('settings/storefront', { method: 'PUT', body: settings }));
            setSeoNotice('Storefront SEO & tracking settings saved.');
        } catch (error) { setSeoError(errorMessage(error)); }
        finally { setSeoBusy(false); }
    }

    async function changePassword(event) {
        event.preventDefault();
        setPasswordBusy(true); setPasswordError(''); setPasswordNotice('');
        try {
            await request('settings/password', { method: 'PUT', body: password });
            setPassword(emptyPassword);
            setPasswordNotice('Your password has been changed.');
        } catch (error) { setPasswordError(errorMessage(error)); }
        finally { setPasswordBusy(false); }
    }

    if (loading) return <p role="status">Loading settings…</p>;
    return <div className="space-y-6 max-w-4xl">
        <h1 className="text-3xl font-bold">Settings</h1>
        <form onSubmit={changePassword} className="rounded-box border border-base-300 bg-base-100 p-6 space-y-4">
            <h2 className="text-xl font-bold">Change your password</h2>
            {passwordError && <p role="alert" className="alert alert-error">{passwordError}</p>}
            {passwordNotice && <p role="status" className="alert alert-success">{passwordNotice}</p>}
            <fieldset disabled={passwordBusy} className="space-y-4">
                {[['current_password', 'Current password'], ['password', 'New password'], ['password_confirmation', 'Confirm new password']].map(([key, label]) =>
                    <Field key={key} label={label}><input type="password" required className={input} maxLength={255}
                        minLength={key === 'current_password' ? undefined : 8}
                        autoComplete={key === 'current_password' ? 'current-password' : 'new-password'} value={password[key]}
                        onChange={event => setPassword(current => ({ ...current, [key]: event.target.value }))} /></Field>)}
                <p className="text-sm text-base-content/60">Use at least 8 characters for your new password.</p>
                <button className="btn btn-primary">{passwordBusy ? 'Saving…' : 'Change password'}</button>
            </fieldset>
        </form>
        {manageStorefront && <form onSubmit={saveSeo} className="rounded-box border border-base-300 bg-base-100 p-6 space-y-4">
            <h2 className="text-xl font-bold">Storefront SEO &amp; Tracking</h2>
            <p className="text-sm">Manage the site SEO title, description, Google verification, and Meta Pixel here. The Pixel ID applies across the storefront; product page titles are generated automatically.</p>
            {seoError && <p role="alert" className="alert alert-error">{seoError}</p>}
            {seoNotice && <p role="status" className="alert alert-success">{seoNotice}</p>}
            {!settings ? <p role="status">Loading storefront settings…</p> : <fieldset disabled={seoBusy} className="space-y-4">
                <Field label="Site SEO title"><input className={input} maxLength={255} value={settings.meta_title || ''} onChange={event => setSettings(current => ({ ...current, meta_title: event.target.value }))} /></Field>
                <Field label="Site SEO description"><textarea className="textarea textarea-bordered w-full" maxLength={1000} value={settings.meta_description || ''} onChange={event => setSettings(current => ({ ...current, meta_description: event.target.value }))} /></Field>
                <div className="rounded-box bg-base-200 p-4 space-y-2">
                    <h3 className="font-bold">Data Layer <span className="badge badge-success">Enabled</span></h3>
                    <p className="text-sm">Page views, product views, cart changes, checkout, and confirmed orders are available for analytics and advertising. SEO title and description are managed above.</p>
                    <Field label="Google Tag Manager container ID (optional)"><input className={input} maxLength={64} pattern="GTM-[A-Z0-9]+" value={settings.gtm_container_id || ''} placeholder="GTM-XXXXXXX" onChange={event => setSettings(current => ({ ...current, gtm_container_id: event.target.value.trim().toUpperCase() }))} /></Field>
                    <p className="text-sm">Paste your Web container ID, then save. Connect GA4 or advertising tags in GTM and publish the container. Changes apply on the next storefront reload.</p>
                    <details className="text-sm"><summary className="cursor-pointer font-medium">Data Layer setup instructions</summary>
                        <ol className="list-decimal pl-5 mt-2 space-y-1">
                            <li>Add your GA4 Google tag in GTM.</li>
                            <li>Create a Custom Event trigger matching view_item, add_to_cart, remove_from_cart, view_cart, begin_checkout, and purchase.</li>
                            <li>Create a GA4 Event tag using the Event variable as its name and enable ecommerce data from the Data Layer.</li>
                            <li>Use GTM Preview and GA4 DebugView to check events, then publish your container.</li>
                        </ol>
                    </details>
                </div>
                <Field label="Meta Pixel ID (optional)"><input className={input} inputMode="numeric" pattern="[0-9]{5,30}" maxLength={30} value={settings.meta_pixel_id || ''} placeholder="e.g. 123456789012345" onChange={event => setSettings(current => ({ ...current, meta_pixel_id: event.target.value }))} /></Field>
                <p className="text-sm text-base-content/60">Enter the numeric ID from Meta Events Manager to track storefront page views and product views. Leave blank to disable Meta Pixel.</p>
                <Field label="Google site verification token"><input className={input} maxLength={255} pattern="[A-Za-z0-9_-]+" value={settings.google_site_verification || ''} onChange={event => setSettings(current => ({ ...current, google_site_verification: event.target.value }))} /></Field>
                <p className="text-sm text-base-content/60">Paste only the content value from the Google verification meta tag. Leave blank to remove the tag.</p>
                <button className="btn btn-primary">{seoBusy ? 'Saving…' : 'Save storefront settings'}</button>
            </fieldset>}
        </form>}
    </div>;
}
