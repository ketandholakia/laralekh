@props([
    'name',
    'input' => null,
    'options' => [],
    'model' => null,
    'placeholder' => '',
    'optionId' => 'id',
    'optionLabel' => 'name',
])

@php
    // ARIA combobox/listbox for party/item lookup. State lives on an
    // x-data Alpine scope: the input keeps focus, ArrowUp/Down move the
    // active option (aria-activedescendant), Enter selects it, Escape
    // dismisses the list. Options are rendered by the caller's slot —
    // this component owns the interaction, not the data source.
    $listboxId = $name.'-listbox';
    $inputId = $name.'-input';
    $activeId = $name.'-active';
@endphp

<div
    x-data="{
        open: false,
        query: '',
        active: -1,
        get items() {
            return this.$refs.listbox ? Array.from(this.$refs.listbox.querySelectorAll('[role=option]')) : [];
        },
        setActive(index) {
            const items = this.items;
            if (! items.length) { this.active = -1; this.$refs.input.removeAttribute('aria-activedescendant'); return; }
            this.active = (index + items.length) % items.length;
            items.forEach((item, i) => item.setAttribute('aria-selected', i === this.active ? 'true' : 'false'));
            const el = items[this.active];
            el.id = '{{ $activeId }}';
            this.$refs.input.setAttribute('aria-activedescendant', el.id);
            el.scrollIntoView({ block: 'nearest' });
        },
        selectActive() {
            const items = this.items;
            if (this.active >= 0 && items[this.active]) {
                const label = items[this.active];
                this.$refs.input.value = label.textContent.trim();
                this.open = false;
                this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
                label.click();
            }
        },
    }"
    class="relative"
>
    <input
        type="text" id="{{ $inputId }}" name="{{ $name }}" placeholder="{{ $placeholder }}"
        role="combobox" aria-expanded="false" :aria-expanded="open.toString()"
        aria-controls="{{ $listboxId }}" aria-autocomplete="list" autocomplete="off"
        x-ref="input"
        @input="query = $event.target.value; open = true; setActive(-1)"
        @keydown.arrow-down.prevent="setActive(active + 1)"
        @keydown.arrow-up.prevent="setActive(active - 1)"
        @keydown.enter.prevent="selectActive()"
        @keydown.escape.prevent="open = false; active = -1"
        @blur.window="open = false"
        class="w-full rounded-md border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm
            focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none"
        {{ $attributes }}
    >
    <div x-ref="listbox" id="{{ $listboxId }}" role="listbox" x-show="open && items.length" x-cloak
         class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-slate-200 bg-white shadow-pop">
        {{ $slot }}
    </div>
</div>
