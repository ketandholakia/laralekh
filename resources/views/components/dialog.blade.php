@props(['id' => 'dialog', 'title', 'icon' => null, 'size' => 'md', 'description' => null])

@php
    // ARIA dialog on the native <dialog> element: showModal() supplies the
    // modal semantics (role="dialog", aria-modal), the top layer, focus
    // containment, Escape-to-close and focus restoration. The script below
    // adds only two things: click triggers ([data-dialog-open]) and
    // backdrop/close-button handling — all via a single delegated document
    // listener, so multiple dialogs on one page and Livewire morphs are
    // both safe.
    $panel = ['sm' => 'max-w-md', 'md' => 'max-w-lg', 'lg' => 'max-w-2xl'][$size] ?? 'max-w-lg';
    $titleId = $id.'-title';
@endphp

<dialog
    id="{{ $id }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $titleId }}"
    class="x-dialog w-full {{ $panel }} rounded-lg border border-slate-200 bg-white p-0 shadow-pop backdrop:bg-navy/50 backdrop:backdrop-blur-sm"
>
    <div class="x-dialog-panel p-5">
        <div class="flex items-start gap-3">
            @if ($icon)
                <span class="rounded-md bg-brand-50 p-1.5 text-brand-700"><x-icon name="{{ $icon }}" class="h-5 w-5"/></span>
            @endif
            <div class="min-w-0 flex-1">
                <h2 id="{{ $titleId }}" class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                @if ($description)
                    <p class="mt-1 text-sm text-slate-600">{{ $description }}</p>
                @endif
            </div>
            <button type="button" data-dialog-close
                    class="rounded-md p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-brand-600"
                    aria-label="Close dialog" autofocus>
                <x-icon name="close" class="h-5 w-5"/>
            </button>
        </div>
        @isset($default)
            <div class="mt-4 text-sm text-slate-800">{{ $default }}</div>
        @endisset
        @isset($actions)
            <div class="mt-5 flex flex-wrap items-center justify-end gap-2">{{ $actions }}</div>
        @endisset
    </div>
</dialog>
<script>
    if (typeof window.__xDialogBound === 'undefined') {
        window.__xDialogBound = true;
        document.addEventListener('click', function (event) {
            var open = event.target.closest('[data-dialog-open]');
            if (open) {
                var panel = document.getElementById(open.getAttribute('data-dialog-open'));
                if (panel) { panel.showModal(); }
                return;
            }
            if (event.target.closest('[data-dialog-close]')) {
                var closer = event.target.closest('dialog');
                if (closer) { closer.close(); }
                return;
            }
            // Click on the backdrop: the dialog fills the backdrop area with
            // no padding, so the click lands on the dialog element itself.
            if (event.target.tagName === 'DIALOG' && event.target.classList.contains('x-dialog')) {
                event.target.close();
            }
        });
    }
</script>
