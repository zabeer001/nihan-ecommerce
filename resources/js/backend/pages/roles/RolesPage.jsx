import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { usePermissions } from '../../layouts/PermissionsContext';
import { Field, input, request } from '../catalog/catalogUi';

const newRole = { name: '', permission_ids: [] };

export default function RolesPage({ mode, roleId }) {
    const isFormPage = Boolean(mode);
    const { can, loading } = usePermissions();
    const canManage = can('roles.manage');
    const [roles, setRoles] = useState([]);
    const [permissions, setPermissions] = useState([]);
    const [form, setForm] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    useEffect(() => {
        if (!canManage) return;
        let active = true;
        setForm(null);
        setError('');
        setNotice('');
        Promise.all([
            mode === 'create' ? [] : request('roles'),
            isFormPage ? request('roles/options') : [],
        ])
            .then(([roles, permissions]) => {
                if (!active) return;
                setRoles(roles);
                setPermissions(permissions);
                if (mode === 'create') setForm(newRole);
                if (mode === 'edit') {
                    const role = roles.find(role => role.id === roleId);
                    if (!role || role.protected) {
                        setError('This role cannot be edited.');
                        return;
                    }
                    setForm({ id: role.id, name: role.name, permission_ids: role.permissions.map(permission => permission.id) });
                }
            })
            .catch(error => { if (active) setError(error.message); });

        return () => { active = false; };
    }, [canManage, isFormPage, mode, roleId]);

    async function run(action, message) {
        setBusy(true);
        setError('');
        setNotice('');
        try {
            await action();
            setNotice(message);
        } catch (error) {
            setError(error.message);
        } finally {
            setBusy(false);
        }
    }

    function save(event) {
        event.preventDefault();
        run(async () => {
            const { id, ...body } = form;
            await request(id ? `roles/${id}` : 'roles', { method: id ? 'PUT' : 'POST', body });
            router.visit('/dashboard/roles');
        }, 'Role saved.');
    }

    function remove(role) {
        if (!window.confirm(`Delete the role “${role.name}”?`)) return;
        run(async () => {
            await request(`roles/${role.id}`, { method: 'DELETE' });
            setRoles(await request('roles'));
        }, 'Role deleted.');
    }

    if (loading) return <p role="status">Loading permissions…</p>;
    if (!canManage) return <p className="alert alert-error">You do not have access to this page.</p>;

    return <section className="space-y-5">
        <h1 className="text-3xl font-bold">{mode === 'edit' ? 'Edit role' : mode === 'create' ? 'Add role' : 'Roles'}</h1>
        {error && <p role="alert" className="alert alert-error">{error}</p>}
        {notice && <p role="status" className="alert alert-success">{notice}</p>}
        {isFormPage && !form && (error
            ? <Link href="/dashboard/roles" className="btn">Back to roles</Link>
            : <p role="status">Loading role form…</p>)}
        <fieldset disabled={busy} className="space-y-5">
            {!isFormPage && <div className="rounded-box bg-base-100 p-5 space-y-4">
                <Link href="/dashboard/roles/create" className="btn btn-primary">Add role</Link>
                {[[true, 'System roles'], [false, 'Custom roles']].map(([isProtected, title]) => <section key={title} className="space-y-2">
                    <h2 className="text-xl font-semibold">{title}</h2>
                    <div className="overflow-x-auto">
                    <table className="table">
                        <thead><tr><th>Role</th><th>Permissions</th><th>Actions</th></tr></thead>
                        <tbody>{roles.filter(role => role.protected === isProtected).map(role => <tr key={role.id}>
                            <th scope="row">{role.name}</th>
                            <td>{role.permissions.map(permission => permission.name).join(', ') || 'None'}</td>
                            <td>{role.protected ? 'System role' : <div className="flex gap-2">
                                <Link href={`/dashboard/roles/${role.id}/edit`} className="btn btn-sm">Edit</Link>
                                <button type="button" className="btn btn-sm" onClick={() => remove(role)}>Delete</button>
                            </div>}</td>
                        </tr>)}</tbody>
                    </table>
                    </div>
                </section>)}
            </div>}
            {isFormPage && form && <form onSubmit={save} className="rounded-box bg-base-100 p-5 space-y-4">
                <Field label="Role name">
                    <input className={input} required maxLength={100} pattern={String.raw`[a-z][a-z0-9_\-]*`}
                        title="Use lowercase letters, numbers, underscores, or hyphens. Start with a letter."
                        value={form.name} onChange={event => setForm({ ...form, name: event.target.value })} />
                </Field>
                <fieldset className="space-y-4">
                    <legend className="mb-2 font-medium">Permissions</legend>
                    {Object.entries(permissions.reduce((groups, permission) => {
                        const [module] = permission.name.split('.');
                        (groups[module] ??= []).push(permission);
                        return groups;
                    }, {})).map(([module, options]) =>
                        <fieldset key={module} className="grid gap-2 rounded-box border border-base-300 p-3 sm:grid-cols-2 lg:grid-cols-3">
                            <legend className="px-1 font-medium capitalize">{module}</legend>
                            {options.map(permission => <label className="flex items-center gap-2" key={permission.id}>
                        <input type="checkbox" className="checkbox checkbox-sm"
                            checked={form.permission_ids.includes(permission.id)}
                            onChange={event => setForm({
                                ...form,
                                permission_ids: event.target.checked
                                    ? [...form.permission_ids, permission.id]
                                    : form.permission_ids.filter(id => id !== permission.id),
                            })} />
                        {permission.name}
                            </label>)}
                        </fieldset>
                    )}
                </fieldset>
                <div className="flex gap-2">
                    <button className="btn btn-primary">Save role</button>
                    <Link href="/dashboard/roles" className="btn">Cancel</Link>
                </div>
            </form>}
        </fieldset>
    </section>;
}
