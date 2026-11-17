<div>
    <x-page-header title="Financial years" eyebrow="Company setup"
                   :sub="'Create financial years and close them for posting. Closing is permanent.'"/>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-card">
            <h2 class="mb-3 text-sm font-semibold">New financial year</h2>
            <div class="space-y-3">
                <x-field label="Label (e.g. 2027-28)" name="fy-name" :error="$errors->first('name')">
                    <x-input name="fy-name" type="text" wire:model="name" placeholder="2027-28"/>
                </x-field>
                <x-field label="Starts on" name="fy-start" :error="$errors->first('dates')">
                    <x-input name="fy-start" type="date" wire:model="startsOn"/>
                </x-field>
                <x-field label="Ends on" name="fy-end">
                    <x-input name="fy-end" type="date" wire:model="endsOn"/>
                </x-field>
                <x-button wire:click="create" variant="primary" class="w-full">Create financial year</x-button>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-card lg:col-span-2">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Financial years</h2></div>
            @if (count($this->years) === 0)
                <x-empty-state icon="clock" title="No financial years yet"
                               message="Create the first financial year to start posting documents."/>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-semibold">Year</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Period</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                                <th scope="col" class="px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->years as $year)
                                <tr class="border-t border-slate-100 hover:bg-slate-50/60">
                                    <td class="px-5 py-3 font-medium text-slate-900">{{ $year['name'] }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $year['starts_on'] }} to {{ $year['ends_on'] }}</td>
                                    <td class="px-5 py-3">
                                        @if ($year['is_closed'])
                                            <x-badge status="cancelled">Closed</x-badge>
                                        @else
                                            <x-badge status="posted">Open</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        @unless ($year['is_closed'])
                                            @if ($confirmCloseId === $year['id'])
                                                <span class="text-xs text-red-700">Close permanently?</span>
                                                <button wire:click="close({{ $year['id'] }})" class="ml-2 rounded bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700">Yes, close</button>
                                                <button wire:click="$set('confirmCloseId', null)" class="ml-1 text-xs text-slate-500 hover:underline">Cancel</button>
                                            @else
                                                <button wire:click="requestClose({{ $year['id'] }})"
                                                        class="text-sm font-medium text-red-600 hover:text-red-700 hover:underline">Close year</button>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</div>
