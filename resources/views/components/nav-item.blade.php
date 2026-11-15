@props(['href', 'label', 'active' => false, 'icon' => null, 'rail' => false])

{{-- Sidebar navigation entry. The anchor always carries an aria-label so the
     collapsed icon rail still exposes an accessible name. --}}
<a href="{{ $href }}" aria-label="{{ $label }}"
   {{ $attributes->merge([
       'class' => 'group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors '.
           ($active ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'),
   ]) }}
   @if ($active) aria-current="page" @endif>
    @if ($icon)<x-icon name="{{ $icon }}" class="h-5 w-5 shrink-0"/>@endif
    <span class="{{ $rail ? 'lg:hidden' : '' }}">{{ $label }}</span>
</a>
