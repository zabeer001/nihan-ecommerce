import React, { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import Link from '@/shared/CachedLink';
import { Edit3, Eye, FolderOpen, MoreHorizontal, PackageSearch, RotateCcw, Search, Snowflake, Trash2 } from 'lucide-react';
import useAuth from '../../../shared/hooks/useAuth';
import { deleteUser, fetchAllUsers, updateUser } from './api/userApi';

const ROLE_OPTIONS = ['all', 'user', 'editor', 'admin', 'superadmin'];
const STATUS_OPTIONS = ['all', 'active', 'frozen'];

function getStatusBadgeClass(isFrozen) {
    return isFrozen
        ? 'badge badge-error badge-outline whitespace-nowrap'
        : 'badge badge-success badge-outline whitespace-nowrap';
}

function UsersPageHeader({ userRole }) {
    return (
        <section className="card bg-base-100 shadow-xl">
            <div className="card-body">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-3xl font-extrabold">Users</h1>
                        <p className="mt-2 text-sm text-base-content/70">
                            Manage your platform users here. Signed in as:{' '}
                            <span className="font-semibold uppercase">{userRole}</span>
                        </p>
                    </div>
                    <Link href="/dashboard/users/create" className="btn btn-sm btn-neutral text-neutral-content">
                        Add User
                    </Link>
                </div>
            </div>
        </section>
    );
}

function UsersPageLoading() {
    return <p className="text-sm text-base-content/70">Loading users...</p>;
}

function UsersPageError({ message }) {
    if (!message) {
        return null;
    }

    return <div className="alert alert-error py-2 text-sm">{message}</div>;
}

function UsersFilterSection({ searchValue, roleValue, statusValue, onSearchChange, onRoleChange, onStatusChange, onReset }) {
    return (
        <section className="mb-4 rounded-xl border border-base-300 bg-base-100 p-2 shadow-sm">
            <div className="flex flex-col gap-2 lg:flex-row lg:items-center">
                <label className="input input-bordered input-sm flex h-9 min-h-9 w-full items-center gap-1.5 rounded-lg border-base-300 bg-base-100 px-2 shadow-[0_1px_2px_rgba(0,0,0,0.03)] lg:flex-1">
                    <Search className="size-3.5 text-base-content/40" />
                    <input
                        type="search"
                        className="grow text-xs placeholder:text-base-content/40"
                        placeholder="Search by user name or email"
                        value={searchValue}
                        onChange={onSearchChange}
                    />
                </label>

                <div className="flex flex-col gap-2 sm:flex-row lg:w-auto">
                    <select
                        className="select select-bordered select-sm h-9 min-h-9 w-full rounded-lg border-base-300 bg-base-100 px-2 text-xs shadow-[0_1px_2px_rgba(0,0,0,0.03)] sm:w-40"
                        value={roleValue}
                        onChange={onRoleChange}
                    >
                        {ROLE_OPTIONS.map((role) => (
                            <option key={role} value={role}>
                                {role === 'all' ? 'All roles' : role.toUpperCase()}
                            </option>
                        ))}
                    </select>

                    <select
                        className="select select-bordered select-sm h-9 min-h-9 w-full rounded-lg border-base-300 bg-base-100 px-2 text-xs shadow-[0_1px_2px_rgba(0,0,0,0.03)] sm:w-40"
                        value={statusValue}
                        onChange={onStatusChange}
                    >
                        {STATUS_OPTIONS.map((status) => (
                            <option key={status} value={status}>
                                {status === 'all' ? 'All statuses' : status.charAt(0).toUpperCase() + status.slice(1)}
                            </option>
                        ))}
                    </select>

                    <button
                        type="button"
                        className="btn btn-sm h-9 min-h-9 rounded-lg border border-base-300 bg-base-100 px-3 text-xs font-medium text-base-content shadow-[0_1px_2px_rgba(0,0,0,0.03)] transition hover:border-base-content/20 hover:bg-base-200"
                        onClick={onReset}
                    >
                        <RotateCcw className="size-3.5" />
                        Reset
                    </button>
                </div>
            </div>
        </section>
    );
}

function UserActionsDropdown({ user, onDeleteUser, onToggleFreeze }) {
    return (
        <div className="dropdown dropdown-end">
            <button
                type="button"
                tabIndex={0}
                className="btn btn-xs btn-outline gap-1 px-2"
                aria-label={`Actions for ${user.name}`}
            >
                <MoreHorizontal className="size-3.5" />
               
            </button>
            <ul
                tabIndex={0}
                className="menu menu-sm dropdown-content z-[9999] mt-2 w-48 rounded-box border border-base-300 bg-base-100 p-2 text-left shadow-2xl"
            >
                <li>
                    <Link href={`/dashboard/users/${user.id}`}>
                        <Eye className="size-4" />
                        View
                    </Link>
                </li>
                <li>
                    <Link href={`/dashboard/users/${user.id}/collections`}>
                        <FolderOpen className="size-4" />
                        Collection
                    </Link>
                </li>
                <li>
                    <Link href={`/dashboard/tapes?user_id=${user.id}`}>
                        <PackageSearch className="size-4" />
                        Catalog
                    </Link>
                </li>
                <li>
                    <Link href={`/dashboard/users/${user.id}/edit`}>
                        <Edit3 className="size-4" />
                        Edit
                    </Link>
                </li>
                <li>
                    <button type="button" onClick={() => onToggleFreeze(user)}>
                        <Snowflake className="size-4" />
                        {user.is_frozen ? 'Unfreeze' : 'Freeze'}
                    </button>
                </li>
                <li>
                    <button type="button" className="text-error" onClick={() => onDeleteUser(user)}>
                        <Trash2 className="size-4" />
                        Delete
                    </button>
                </li>
            </ul>
        </div>
    );
}

function UsersListSection({ users, onDeleteUser, onToggleFreeze }) {
    return (
        <div className="overflow-x-auto">
            <table className="table w-full table-fixed">
                <colgroup>
                    <col className="w-[5%]" />
                    <col className="w-[15%]" />
                    <col className="w-[21%]" />
                    <col className="w-[8%]" />
                    <col className="w-[8%]" />
                    <col className="w-[8%]" />
                    <col className="w-[11%]" />
                    <col className="w-[24%]" />
                </colgroup>
                <thead>
                    <tr>
                        <th className="whitespace-nowrap">ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th className="whitespace-nowrap">Role</th>
                        <th className="whitespace-nowrap">Status</th>
                        <th className="whitespace-nowrap">Collection</th>
                        <th className="whitespace-nowrap">Catalog Entries</th>
                        <th className="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {users.length === 0 ? (
                        <tr>
                            <td colSpan={8} className="py-8 text-center text-base-content/70">
                                No users found.
                            </td>
                        </tr>
                    ) : (
                        users.map((user) => (
                            <tr key={user.id}>
                                <td className="whitespace-nowrap">{user.id}</td>
                                <td className="font-medium">{user.name}</td>
                                <td className="truncate">{user.email}</td>
                                <td className="whitespace-nowrap uppercase">{user.role || 'user'}</td>
                                <td className="whitespace-nowrap">
                                    <span className={getStatusBadgeClass(user.is_frozen)}>
                                        {user.is_frozen ? 'Frozen' : 'Active'}
                                    </span>
                                </td>
                                <td className="whitespace-nowrap">{user.collection_items_count || 0}</td>
                                <td className="whitespace-nowrap">{user.catalog_entries_count || 0}</td>
                                <td className="whitespace-nowrap text-right align-top">
                                    <div className="flex justify-end">
                                        <UserActionsDropdown
                                            user={user}
                                            onDeleteUser={onDeleteUser}
                                            onToggleFreeze={onToggleFreeze}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}

function UsersPage() {
    const { role: userRole } = useAuth();
    const canAccessUsers = ['admin', 'superadmin'].includes(userRole.toLowerCase());
    const queryClient = useQueryClient();
    const initialFilters = React.useMemo(() => {
        const params = new URLSearchParams(window.location.search);

        return {
            updated: params.get('updated') === '1',
            search: params.get('search') || '',
            role: params.get('role') || 'all',
            status: params.get('status') || 'all',
        };
    }, []);

    const [error, setError] = useState('');
    const [message, setMessage] = useState(initialFilters.updated ? 'User updated successfully.' : '');
    const [searchValue, setSearchValue] = useState(initialFilters.search);
    const [activeSearch, setActiveSearch] = useState(initialFilters.search);
    const [roleValue, setRoleValue] = useState(initialFilters.role);
    const [activeRole, setActiveRole] = useState(initialFilters.role);
    const [statusValue, setStatusValue] = useState(initialFilters.status);
    const [activeStatus, setActiveStatus] = useState(initialFilters.status);
    const didMountFilters = useRef(false);

    const usersQuery = useQuery({
        queryKey: ['backend-users', activeSearch, activeRole, activeStatus],
        queryFn: () => fetchAllUsers({
            search: activeSearch,
            role: activeRole,
            status: activeStatus,
        }),
        enabled: canAccessUsers,
        retry: false,
        refetchOnWindowFocus: false,
    });

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);

        if (params.get('created') === '1') {
            setMessage('User created successfully.');
        }
    }, []);

    const syncPageQuery = (search, role, status) => {
        const params = new URLSearchParams(window.location.search);

        if (search) {
            params.set('search', search);
        } else {
            params.delete('search');
        }

        if (role && role !== 'all') {
            params.set('role', role);
        } else {
            params.delete('role');
        }

        if (status && status !== 'all') {
            params.set('status', status);
        } else {
            params.delete('status');
        }

        params.delete('updated');

        const query = params.toString();
        const nextUrl = query ? `/dashboard/users?${query}` : '/dashboard/users';
        window.history.replaceState({}, '', nextUrl);
    };

    useEffect(() => {
        if (!didMountFilters.current) {
            didMountFilters.current = true;
            return;
        }

        const nextSearch = searchValue.trim();
        const timeoutId = window.setTimeout(() => {
            setMessage('');
            setError('');
            setActiveSearch(nextSearch);
            setActiveRole(roleValue);
            setActiveStatus(statusValue);
            syncPageQuery(nextSearch, roleValue, statusValue);
        }, 300);

        return () => window.clearTimeout(timeoutId);
    }, [searchValue, roleValue, statusValue]);

    const deleteUserMutation = useMutation({
        mutationFn: (userId) => deleteUser(userId),
        onSuccess: (payload) => {
            setMessage(payload?.message || 'User deleted successfully.');
            queryClient.invalidateQueries({ queryKey: ['backend-users'] });
        },
        onError: (err) => {
            setError(err.message || 'Failed to delete user.');
        },
    });

    const freezeUserMutation = useMutation({
        mutationFn: ({ user, nextFrozenState }) => updateUser(user.id, { is_frozen: nextFrozenState }),
        onSuccess: (payload, variables) => {
            setMessage(payload?.message || `User ${variables.nextFrozenState ? 'frozen' : 'unfrozen'} successfully.`);
            queryClient.invalidateQueries({ queryKey: ['backend-users'] });
        },
        onError: (err, variables) => {
            setError(err.message || `Failed to ${variables.nextFrozenState ? 'freeze' : 'unfreeze'} user.`);
        },
    });

    const onDeleteUser = (user) => {
        const ok = window.confirm(`Delete ${user.name}?`);

        if (!ok) {
            return;
        }

        setError('');
        setMessage('');
        deleteUserMutation.mutate(user.id);
    };

    const onToggleFreeze = (user) => {
        const nextFrozenState = !user.is_frozen;
        const ok = window.confirm(
            `${nextFrozenState ? 'Freeze' : 'Unfreeze'} ${user.name}?`,
        );

        if (!ok) {
            return;
        }

        setError('');
        setMessage('');
        freezeUserMutation.mutate({ user, nextFrozenState });
    };

    const onResetFilters = () => {
        setSearchValue('');
        setRoleValue('all');
        setStatusValue('all');
    };

    const users = Array.isArray(usersQuery.data) ? usersQuery.data : [];
    const isLoading = usersQuery.isLoading || usersQuery.isFetching;
    const displayError = error || usersQuery.error?.message || '';

    if (!canAccessUsers) {
        window.location.replace('/dashboard/unauthorized');
        return null;
    }

    return (
        <div className="mx-auto max-w-7xl space-y-6">
            <UsersPageHeader userRole={userRole} />

            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    {message ? <div className="alert alert-success py-2 text-sm">{message}</div> : null}
                    {isLoading ? <UsersPageLoading /> : null}
                    <UsersPageError message={displayError} />

                    <UsersFilterSection
                        searchValue={searchValue}
                        roleValue={roleValue}
                        statusValue={statusValue}
                        onSearchChange={(event) => setSearchValue(event.target.value)}
                        onRoleChange={(event) => setRoleValue(event.target.value)}
                        onStatusChange={(event) => setStatusValue(event.target.value)}
                        onReset={onResetFilters}
                    />

                    {!isLoading && !displayError ? (
                        <UsersListSection users={users} onDeleteUser={onDeleteUser} onToggleFreeze={onToggleFreeze} />
                    ) : null}
                </div>
            </section>
        </div>
    );
}

export default UsersPage;
