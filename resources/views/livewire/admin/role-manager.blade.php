<div class="space-y-6">
    <x-page-header
        title="Roles & Permissions"
        eyebrow="Administration · Roles"
        sub="Define company-scoped roles and customize granular permissions for each role."
    >
        <x-slot name="actions">
            @canany(['users.view', 'users.manage'])
                <x-button :href="route('admin.users.index')" variant="secondary" icon="users">Manage Users</x-button>
            @endcanany
            @can('roles.manage')
                <x-button type="button" wire:click="openCreateModal" variant="primary" icon="plus">New Role</x-button>
            @endcan
        </x-slot>
    </x-page-header>

    @error('role')
        <x-alert type="danger">{{ $message }}</x-alert>
    @enderror

    <x-card>
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">Configured Company Roles</h2>
            <span class="text-xs text-slate-500">Roles are scoped exclusively to this company.</span>
        </div>

        <x-table caption="Company roles and assigned permissions">
            <x-slot name="head">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Role Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Assigned Members</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Permissions</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </tr>
            </x-slot>
            <x-slot name="body">
                @forelse ($roles as $r)
                    <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                        <td class="px-4 py-3">
                            <span class="font-semibold text-slate-900">{{ $r['name'] }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            <span class="font-mono font-medium">{{ $r['user_count'] }}</span> user(s)
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                    {{ count($r['permissions']) }} granted
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    wire:click="openEditModal({{ $r['id'] }})"
                                    class="rounded px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 hover:text-slate-900"
                                >
                                    Edit permissions
                                </button>
                                @if ($r['user_count'] === 0)
                                    <button
                                        type="button"
                                        wire:click="deleteRole({{ $r['id'] }})"
                                        wire:confirm="Delete role '{{ $r['name'] }}'?"
                                        class="rounded px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-50"
                                    >
                                        Delete
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">
                            No roles defined for this company yet. Click "New Role" to add one.
                        </td>
                    </tr>
                @endforelse
            </x-slot>
        </x-table>
    </x-card>

    {{-- Role Create / Edit Modal --}}
    @if ($showRoleModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
            <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl border border-slate-200 bg-white p-6 shadow-pop">
                <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-base font-semibold text-slate-900">
                        {{ $editingRoleId ? 'Edit Role: ' . $roleName : 'Create New Company Role' }}
                    </h2>
                    <button type="button" wire:click="closeRoleModal" class="rounded p-1 text-slate-400 hover:text-slate-600">
                        <x-icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit.prevent="saveRole" class="space-y-5">
                    <x-field label="Role name" name="roleName" :error="$errors->first('roleName')">
                        <x-input name="roleName" type="text" wire:model="roleName" placeholder="E.g. Senior Accountant, Sales Clerk" />
                    </x-field>

                    {{-- Templates Bar --}}
                    <div class="rounded-lg border border-brand-100 bg-brand-50/50 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-brand-900">Quick Permission Templates:</span>
                            <div class="flex flex-wrap gap-1.5">
                                <button type="button" wire:click="applyTemplate('admin')" class="rounded bg-white px-2 py-1 text-xs font-medium text-brand-700 border border-brand-200 hover:bg-brand-100">
                                    Full Admin
                                </button>
                                <button type="button" wire:click="applyTemplate('accountant')" class="rounded bg-white px-2 py-1 text-xs font-medium text-brand-700 border border-brand-200 hover:bg-brand-100">
                                    Accountant
                                </button>
                                <button type="button" wire:click="applyTemplate('sales_clerk')" class="rounded bg-white px-2 py-1 text-xs font-medium text-brand-700 border border-brand-200 hover:bg-brand-100">
                                    Sales Clerk
                                </button>
                                <button type="button" wire:click="applyTemplate('purchase_clerk')" class="rounded bg-white px-2 py-1 text-xs font-medium text-brand-700 border border-brand-200 hover:bg-brand-100">
                                    Purchase Clerk
                                </button>
                                <button type="button" wire:click="applyTemplate('viewer')" class="rounded bg-white px-2 py-1 text-xs font-medium text-brand-700 border border-brand-200 hover:bg-brand-100">
                                    Read-Only
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Permissions Matrix --}}
                    <div>
                        <label class="mb-3 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                            Permissions ({{ count($selectedPermissions) }} selected)
                        </label>
                        <div class="space-y-4">
                            @foreach ($catalog as $category => $perms)
                                <div class="rounded-lg border border-slate-200 p-4">
                                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-800">{{ $category }}</h3>
                                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                        @foreach ($perms as $permKey => $permLabel)
                                            <label class="flex items-start gap-2.5 rounded-md border border-slate-100 bg-slate-50/50 p-2 text-xs hover:bg-slate-100/70 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    value="{{ $permKey }}"
                                                    wire:model="selectedPermissions"
                                                    class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                                >
                                                <div>
                                                    <span class="block font-medium text-slate-900">{{ $permLabel }}</span>
                                                    <span class="block font-mono text-[11px] text-slate-400">{{ $permKey }}</span>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <x-button type="button" wire:click="closeRoleModal" variant="secondary">Cancel</x-button>
                        <x-button type="submit" variant="primary">{{ $editingRoleId ? 'Update Role' : 'Create Role' }}</x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
