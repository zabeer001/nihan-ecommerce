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

    if (loading) return <p role="status">Loading permissions…</p>;
    if (!canManage) return <p className="alert alert-error">You do not have access to this page.</p>;

    return <section className="space-y-5">
        <h1 className="text-3xl font-bold">Users</h1>
        {[users.error, roles.error, assignment.error].filter(Boolean).map((error, index) =>
            <p key={index} role="alert" className="alert alert-error">{error.message}</p>
        )}
        {assignment.isSuccess && <p role="status" className="alert alert-success">Role assigned.</p>}

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
