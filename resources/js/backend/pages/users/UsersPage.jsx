import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { usePermissions } from '../../layouts/PermissionsContext';
import { request } from '../catalog/catalogUi';

export default function UsersPage() {
    const { can, loading } = usePermissions();
    const canManage = can('roles.manage');
    const queryClient = useQueryClient();
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [editing, setEditing] = useState(null);
    const [creating, setCreating] = useState(false);
    const [form, setForm] = useState({ name: '', email: '', role_id: '', password: '', password_confirmation: '' });
    const params = new URLSearchParams({ page, search });

    const users = useQuery({
        queryKey: ['role-assignment-users', page, search],
        queryFn: () => request(`users?${params}`),
        enabled: canManage,
        staleTime: 0,
    });
    const roles = useQuery({
        queryKey: ['role-assignment-roles'],
        queryFn: () => request('roles'),
        enabled: canManage,
        staleTime: 0,
    });
    const assignment = useMutation({
        mutationFn: body => request('roles/assignment', { method: 'PUT', body }),
        onSuccess: async () => {
            setEditing(null);
            await queryClient.invalidateQueries({ queryKey: ['role-assignment-users'] });
        },
    });

    function changePage(nextPage) {
        setEditing(null);
        assignment.reset();
        setPage(nextPage);
    }

    const creation = useMutation({
        mutationFn: () => request('users', { method: 'POST', body: { ...form, role_id: Number(form.role_id) } }),
        onSuccess: async () => {
            setCreating(false);
            setForm({ name: '', email: '', role_id: '', password: '', password_confirmation: '' });
            await queryClient.invalidateQueries({ queryKey: ['role-assignment-users'] });
        },
    });

    if (loading) return <p role="status">Loading permissions…</p>;
    if (!canManage) return <p className="alert alert-error">You do not have access to this page.</p>;

    return <section className="space-y-5">
        <div className="flex items-center justify-between gap-3">
            <h1 className="text-3xl font-bold">Users</h1>
            <button type="button" className="btn btn-primary" onClick={() => { creation.reset(); setCreating(true); }}>Add user</button>
        </div>
        {[users.error, roles.error, assignment.error, creation.error].filter(Boolean).map((error, index) =>
            <p key={index} role="alert" className="alert alert-error">{error.message}</p>
        )}
        {assignment.isSuccess && <p role="status" className="alert alert-success">Role assigned.</p>}
        {creation.isSuccess && <p role="status" className="alert alert-success">User created.</p>}
        {creating && <form className="rounded-box bg-base-100 p-5" onSubmit={event => { event.preventDefault(); creation.mutate(); }}>
            <h2 className="text-xl font-semibold mb-4">Add user</h2>
            <fieldset disabled={creation.isPending} className="grid gap-4 md:grid-cols-2">
                {['name', 'email', 'password', 'password_confirmation'].map(name => <label key={name} className="grid gap-1">
                    {{ name: 'Full name', email: 'Email', password: 'Password', password_confirmation: 'Confirm password' }[name]}
                    <input className="input input-bordered w-full" required name={name} value={form[name]}
                        type={name.startsWith('password') ? 'password' : name === 'email' ? 'email' : 'text'}
                        minLength={name.startsWith('password') ? 8 : undefined} maxLength={name === 'name' || name === 'email' ? 255 : undefined}
                        autoComplete={name.startsWith('password') ? 'new-password' : name}
                        onChange={event => setForm(current => ({ ...current, [name]: event.target.value }))} />
                </label>)}
                <label className="grid gap-1">Role
                    <select className="select select-bordered w-full" required value={form.role_id}
                        onChange={event => setForm(current => ({ ...current, role_id: event.target.value }))}>
                        <option value="">Choose a role</option>
                        {roles.data?.filter(role => role.assignable).map(role => <option key={role.id} value={role.id}>{role.name}</option>)}
                    </select>
                </label>
                <div className="flex items-end gap-2 md:col-span-2">
                    <button className="btn btn-primary" disabled={!roles.data}>{creation.isPending ? 'Creating…' : 'Create user'}</button>
                    <button type="button" className="btn" onClick={() => { setCreating(false); creation.reset(); setForm({ name: '', email: '', role_id: '', password: '', password_confirmation: '' }); }}>Cancel</button>
                </div>
            </fieldset>
        </form>}

        <fieldset disabled={assignment.isPending} className="rounded-box bg-base-100 p-5 space-y-4">
            <form className="flex flex-wrap gap-2" onSubmit={event => {
                event.preventDefault();
                setSearch(searchInput.trim());
                changePage(1);
            }}>
                <input type="search" aria-label="Search users by name or email" placeholder="Search by name or email"
                    className="input input-bordered w-full sm:w-72" value={searchInput}
                    onChange={event => setSearchInput(event.target.value)} />
                <button className="btn">Search</button>
            </form>

            {users.isPending && <p role="status">Loading users…</p>}
            {users.data && <>
                <div className="overflow-x-auto">
                    <table className="table">
                        <thead><tr><th>Name</th><th>Email</th><th>Current role</th><th>Assign role</th></tr></thead>
                        <tbody>
                            {users.data.data.length === 0 && <tr><td colSpan={4}>No users found.</td></tr>}
                            {users.data.data.map(user => <tr key={user.id}>
                                <th scope="row">{user.name}</th>
                                <td>{user.email}</td>
                                <td>{user.role}</td>
                                <td>
                                    {editing?.userId === user.id ? (
                                        <form className="flex flex-wrap gap-2" onSubmit={event => {
                                            event.preventDefault();
                                            assignment.mutate({ email: user.email, role_id: Number(editing.roleId) });
                                        }}>
                                            <select required aria-label={`New role for ${user.name}`} className="select select-bordered select-sm"
                                                value={editing.roleId} onChange={event => setEditing({ ...editing, roleId: event.target.value })}>
                                                <option value="">Choose a role</option>
                                                {roles.data.map(role => <option key={role.id} value={role.id}
                                                    disabled={!role.assignable || role.name === user.role}>{role.name}</option>)}
                                            </select>
                                            <button className="btn btn-primary btn-sm" disabled={!editing.roleId}>Save</button>
                                            <button type="button" className="btn btn-sm" onClick={() => {
                                                setEditing(null);
                                                assignment.reset();
                                            }}>Cancel</button>
                                        </form>
                                    ) : user.can_change_role ? (
                                        <button type="button" className="btn btn-sm" disabled={!roles.data}
                                            aria-label={`Change role for ${user.name}`} onClick={() => {
                                                assignment.reset();
                                                setEditing({ userId: user.id, roleId: '' });
                                            }}>Change role</button>
                                    ) : 'Protected'}
                                </td>
                            </tr>)}
                        </tbody>
                    </table>
                </div>
                {users.data.last_page > 1 && <div className="flex items-center gap-3">
                    <button type="button" className="btn btn-sm" disabled={page === 1} onClick={() => changePage(page - 1)}>Previous</button>
                    <span>Page {page} of {users.data.last_page}</span>
                    <button type="button" className="btn btn-sm" disabled={page >= users.data.last_page} onClick={() => changePage(page + 1)}>Next</button>
                </div>}
            </>}
        </fieldset>
    </section>;
}
