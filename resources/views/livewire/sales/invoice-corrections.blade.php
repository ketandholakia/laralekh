<section class="mb-5 space-y-3 rounded-lg border border-indigo-200 bg-white p-4">
    <div class="flex flex-wrap justify-between gap-3"><h2 class="font-semibold">Corrections and reversals</h2>
    @if (!$reversal && $source->status === 'posted')
        <div class="flex flex-wrap gap-4 text-sm">
            @can('corrections.create')
            <a class="text-indigo-700 underline" href="{{ route('corrections.create', ['invoice' => $source->id, 'kind' => 'credit']) }}">Create credit note</a>
            <a class="text-indigo-700 underline" href="{{ route('corrections.create', ['invoice' => $source->id, 'kind' => 'debit']) }}">Create debit note</a>
            @endcan
            @can('reversals.post')<a class="text-red-700 underline" href="{{ route('reversals.create', $source->voucher_id) }}">Review full reversal</a>@endcan
        </div>
    @endif
    </div>
    @if ($reversal)
        <p class="rounded bg-amber-50 p-3 text-sm">The financial voucher has been reversed. The original invoice remains in the audit trail. <a class="underline" href="{{ route('reversals.show', $reversal->id) }}">View internal reversal</a></p>
    @else
        <details class="text-sm"><summary class="cursor-pointer text-slate-600">Remaining original amounts available for credit</summary>
            <ul class="mt-2 space-y-1">@foreach ($capacity as $line)<li>{{ $line['description'] }}: INR {{ $line['amount'] }} · Quantity {{ $line['quantity'] }}</li>@endforeach</ul>
        </details>
    @endif
    @forelse ($notes as $note)
        <p class="text-sm"><a class="underline text-indigo-700" href="{{ route('corrections.show', $note->id) }}">{{ ucfirst($note->label()) }} {{ $note->number ? $note->series.'/'.$note->number : '#'.$note->id.' draft' }}</a> · {{ $note->date->format('d-m-Y') }} · INR {{ $note->total }} · {{ $note->status }}</p>
    @empty <p class="text-sm text-slate-500">No correction notes.</p>@endforelse
</section>

