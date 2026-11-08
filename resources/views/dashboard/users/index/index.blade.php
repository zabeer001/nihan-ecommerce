@extends('dashboard.layouts.master')

@section('title', 'Users')
@section('page_title_in_header', 'Overview')

@section('content')

    <div x-data="usersTable()" x-init="fetchUsers(1)" class="max-w-7xl mx-auto p-6">
        <h1 class="text-2xl font-semibold mb-6 dark:text-dark-text-100">Users</h1>

        <div class="mb-4 flex justify-between items-center">
            <input type="text" x-model.debounce.500ms="search" @input="fetchUsers(1, $event.target.value)"
                placeholder="Search users..."
                class="border border-gray-300 p-2 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-64 dark:bg-dark-bg-800 dark:border-gray-700 dark:text-dark-text-100">

            <div x-show="!loading" class="text-sm text-gray-600 dark:text-dark-text-400">
                Showing <span x-text="from"></span> to <span x-text="to"></span> of <span x-text="total"></span> results
            </div>
        </div>

        <div x-show="loading" class="text-gray-500 py-10 text-center dark:text-dark-text-400">
            Loading users...
        </div>

        <div x-show="!loading && users.length > 0" class="overflow-x-auto bg-white rounded-xl shadow dark:bg-dark-bg-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-dark-bg-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 dark:text-dark-text-400">ID</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 dark:text-dark-text-400">Name
                        </th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 dark:text-dark-text-400">Email
                        </th>
                        {{-- ⭐ NEW: Actions Header ⭐ --}}
                        <th class="px-6 py-3 text-center text-sm font-semibold text-gray-700 dark:text-dark-text-400">
                            Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <template x-for="user in users" :key="user.id">
                        <tr class="hover:bg-gray-50 dark:hover:bg-dark-bg-900">
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-dark-text-400" x-text="user.id"></td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-dark-text-100"
                                x-text="user.name"></td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-dark-text-400" x-text="user.email"></td>

                            {{-- ⭐ NEW: Actions Data Cell ⭐ --}}
                            <td class="px-6 py-4 text-sm font-medium text-center whitespace-nowrap">
                                <a :href="'/dashboard/users/' + user.id + '/edit'"
                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3">
                                    Edit
                                </a>
                                <button @click="deleteUser(user.id, user.name)"
                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="!loading && users.length === 0"
            class="text-center py-10 bg-white rounded-xl shadow dark:bg-dark-bg-800">
            <p class="text-gray-500 dark:text-dark-text-400">No users found matching your criteria.</p>
        </div>

        <div x-show="!loading && total > 0" class="mt-4 flex justify-center">
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <template x-for="link in paginationLinks" :key="link.label">
                    <button @click.prevent="link.url && fetchUsers(new URL(link.url).searchParams.get('page'))"
                        :disabled="!link.url"
                        :class="{
                            'bg-indigo-50 border-indigo-500 text-indigo-600 dark:bg-indigo-900 dark:border-indigo-500 dark:text-indigo-200': link
                                .active,
                            'bg-white border-gray-300 text-gray-500 hover:bg-gray-50 dark:bg-dark-bg-800 dark:border-gray-700 dark:text-dark-text-400 dark:hover:bg-dark-bg-900':
                                !link.active && link.url,
                            'text-gray-300 cursor-default dark:text-gray-600': !link.url && !link
                                .active // Disabled link
                        }"
                        class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                        x-html="link.label.replace('&laquo; Previous', 'Prev').replace('Next &raquo;', 'Next')"></button>
                </template>
            </nav>
        </div>
    </div>

    <script>
        function usersTable() {
            return {
                users: [],
                loading: true,
                currentPage: 1,
                lastPage: 1,
                total: 0,
                from: 0,
                to: 0,
                search: '',
                paginationLinks: [],

                async fetchUsers(page = this.currentPage, searchTerm = this.search) {
                    this.loading = true;
                    this.currentPage = page;
                    this.search = searchTerm;

                    let url = `/api/users?page=${this.currentPage}&search=${this.search}`;

                    try {
                        const response = await fetch(url);
                        const result = await response.json();

                        const data = result.data;

                        this.users = data.data;
                        this.currentPage = data.current_page;
                        this.lastPage = data.last_page;
                        this.total = data.total;
                        this.from = data.from;
                        this.to = data.to;
                        this.paginationLinks = data.links;

                    } catch (error) {
                        console.error('Error fetching users:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                async deleteUser(id, name) {
                    if (!confirm('Are you sure you want to delete ' + name + '?')) return;

                    try {
                        const response = await fetch(`/api/users/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            }
                        });

                        if (response.ok) {
                            this.fetchUsers(this.currentPage);
                        } else {
                            alert('Unable to delete user.');
                        }
                    } catch (error) {
                        console.error('Error deleting user:', error);
                    }
                }
            }
        }
    </script>

@endsection
