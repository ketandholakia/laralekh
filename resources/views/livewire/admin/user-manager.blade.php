<div class="space-y-6">
    <x-page-header
        title="Users & Access"
        eyebrow="Administration · Users"
        sub="Manage company members, assign company roles, reset credentials, and control access."
    >
        <x-slot name="actions">
            @can('roles.manage')
                <x-button :href="route('admin.roles.index')" variant="secondary" icon="swap">Manage Roles</x-button>
            @endcan
            @can('users.manage')
                <x-button type="button" wire:click="openCreateModal" variant="primary" icon="plus">Add User</x-button>
            @endcan
        </x-slot>
    </x-page-header>

    @error('status')
        <x-alert type="danger">{{ $message }}</x-alert>
    @enderror

    <x-card>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="w-full sm:w-72">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search users by name or email..."
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm placeholder:text-slate-400 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
                >
            </div>
            <div class="text-xs text-slate-500">
                Total members: <span class="font-semibold text-slate-800">{{ count($users) }}</span>
            </div>
        </div>

        <x-table caption="Company users and access permissions">
            <x-slot name="head">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">User</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Company Roles</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Joined</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </tr>
            </x-slot>
            <x-slot name="body">
                @forelse ($users as $u)
                    <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-slate-900">{{ $u['name'] }}</span>
                                @if ($u['is_self'])
                                    <span class="rounded bg-brand-50 px-1.5 py-0.5 text-[10px] font-semibold text-brand-700">You</span>
                                @endif
                            </div>
                            <span class="block text-xs text-slate-500">{{ $u['email'] }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1.5">
                                @forelse ($u['roles'] as $roleName)
                                    <x-badge variant="brand" size="sm">{{ $roleName }}</x-badge>
                                @empty
                                    <span class="text-xs italic text-slate-400">No roles assigned</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <x-badge :variant="$u['is_active'] ? 'success' : 'danger'" size="sm">
                                {{ $u['is_active'] ? 'Active' : 'Deactivated' }}
                            </x-badge>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            {{ $u['joined_at'] }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <div class="flex items-center justify-end gap-2">
                                @can('users.manage')
                                    <button
                                        type="button"
                                        wire:click="openEditRolesModal({{ $u['id'] }})"
                                        class="rounded px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 hover:text-slate-900"
                                    >
                                        Edit roles
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="openResetPasswordModal({{ $u['id'] }})"
                                        class="rounded px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 hover:text-slate-900"
                                    >
                                        Reset password
                                    </button>
                                    @if (! $u['is_self'])
                                        <button
                                            type="button"
                                            wire:click="toggleUserStatus({{ $u['id'] }})"
                                            class="rounded px-2.5 py-1 text-xs font-medium {{ $u['is_active'] ? 'text-red-700 hover:bg-red-50' : 'text-emerald-700 hover:bg-emerald-50' }}"
                                        >
                                            {{ $u['is_active'] ? 'Deactivate' : 'Reactivate' }}
                                        </button>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">
                            No users match your search criteria.
                        </td>
                    </tr>
                @endforelse
            </x-slot>
        </x-table>
    </x-card>

    {{-- Add User Modal --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-pop">
                <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-base font-semibold text-slate-900">Add User to Company</h2>
                    <button type="button" wire:click="closeCreateModal" class="rounded p-1 text-slate-400 hover:text-slate-600">
                        <x-icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit.prevent="saveUser" class="space-y-4">
                    <x-field label="Full name" name="name" :error="$errors->first('name')">
                        <x-input name="name" type="text" wire:model="name" placeholder="E.g. Rajesh Kumar" />
                    </x-field>

                    <x-field label="Email address" name="email" :error="$errors->first('email')">
                        <x-input name="email" type="email" wire:model="email" placeholder="rajesh@example.com" />
                    </x-field>

                    <x-field label="Initial password" name="password" :error="$errors->first('password')" hint="Minimum 8 characters">
                        <x-input name="password" type="password" wire:model="password" placeholder="••••••••" />
                    </x-field>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-600">Assign Company Roles</label>
                        @if (count($availableRoles) > 0)
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($availableRoles as $roleOption)
                                    <label class="flex items-center gap-2 rounded-md border border-slate-200 p-2.5 text-sm hover:bg-slate-50 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            value="{{ $roleOption }}"
                                            wire:model="selectedRoles"
                                            class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                        >
                                        <span class="font-medium text-slate-800">{{ $roleOption }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-amber-700">No roles created yet for this company. You can create roles in Role Management.</p>
                        @endif
                    </div>

                    <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <x-button type="button" wire:click="closeCreateModal" variant="secondary">Cancel</x-button>
                        <x-button type="submit" variant="primary">Add User</x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Edit Roles Modal --}}
    @if ($showEditRolesModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-pop">
                <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Edit Company Roles</h2>
                        <p class="text-xs text-slate-500">{{ $editingUserName }}</p>
                    </div>
                    <button type="button" wire:click="closeEditRolesModal" class="rounded p-1 text-slate-400 hover:text-slate-600">
                        <x-icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit.prevent="saveUserRoles" class="space-y-4">
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-600">Assigned Roles</label>
                        @if (count($availableRoles) > 0)
                            <div class="space-y-2">
                                @foreach ($availableRoles as $roleOption)
                                    <label class="flex items-center gap-2 rounded-md border border-slate-200 p-2.5 text-sm hover:bg-slate-50 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            value="{{ $roleOption }}"
                                            wire:model="editingRoles"
                                            class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                        >
                                        <span class="font-medium text-slate-800">{{ $roleOption }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-amber-700">No roles available in this company.</p>
                        @endif
                    </div>

                    <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <x-button type="button" wire:click="closeEditRolesModal" variant="secondary">Cancel</x-button>
                        <x-button type="submit" variant="primary">Save Roles</x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Reset Password Modal --}}
    @if ($showResetPasswordModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-pop">
                <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Reset User Password</h2>
                        <p class="text-xs text-slate-500">{{ $passwordResetUserName }}</p>
                    </div>
                    <button type="button" wire:click="closeResetPasswordModal" class="rounded p-1 text-slate-400 hover:text-slate-600">
                        <x-icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit.prevent="saveNewPassword" class="space-y-4">
                    <x-field label="New password" name="newPassword" :error="$errors->first('newPassword')" hint="Minimum 8 characters">
                        <x-input name="newPassword" type="password" wire:model="newPassword" placeholder="••••••••" />
                    </x-field>

                    <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <x-button type="button" wire:click="closeResetPasswordModal" variant="secondary">Cancel</x-button>
                        <x-button type="submit" variant="primary">Update Password</x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
