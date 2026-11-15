@props(['href', 'label', 'active' => false, 'icon' => null])

{{-- Sidebar navigation entry. Collapse state is read from the live Alpine
     `rail` value (not a server-rendered prop), so toggling hides labels
     instantly instead of clipping them. The anchor always carries an
     aria-label, and the layout-level fixed tooltip (data-tip) reveals the
     label on hover and keyboard focus while in the icon rail. --}}
<a href="{{ $href }}" aria-label="{{ $label }}" data-tip="{{ $label }}"
   {{ $attributes->merge([
       'class' => 'group relative flex items-center gap-3 whitespace-nowrap rounded-md py-2 text-sm font-medium transition-colors '.
           ($active ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'),
   ]) }}
   :class="rail ? 'lg:justify-center lg:px-0' : 'px-3'"
   @if ($active) aria-current="page" @endif>
    @if ($icon)<x-icon name="{{ $icon }}" class="h-5 w-5 shrink-0"/>@endif
    <span :class="rail ? 'lg:hidden' : ''">{{ $label }}</span>
</a>
