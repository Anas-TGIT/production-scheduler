@extends('layouts.app')

@section('title', $order ? "Edit order #{$order->id}" : 'New order')

@section('content')
    @php
        $oldItems = old('items', $order
            ? $order->items->map(fn ($item) => ['product_id' => $item->product_id, 'quantity' => $item->quantity])->all()
            : [['product_id' => '', 'quantity' => '']]);
        $needBy = old('need_by_at', $order?->need_by_at->setTimezone($timezone)->format('Y-m-d\TH:i'));
        // Picker hint only (the server enforces the rule); omitted when an existing order keeps a deadline that has passed.
        $minNeedBy = $order?->need_by_at->isPast() ? null : now($timezone)->format('Y-m-d\TH:i');
    @endphp

    <h1 class="mb-1 text-2xl font-semibold">{{ $order ? "Edit order #{$order->id}" : 'New order' }}</h1>
    <p class="mb-6 text-sm text-slate-600">All products in one order must belong to the same product type.</p>

    @if ($errors->any())
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <p class="font-medium">The order was not saved. Please fix the following:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $order ? route('orders.update', $order) : route('orders.store') }}" class="space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" novalidate>
        @csrf
        @if ($order)
            @method('PUT')
        @endif

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label for="customer_name" class="block text-sm font-medium">Customer name</label>
                <input id="customer_name" name="customer_name" type="text" value="{{ old('customer_name', $order?->customer_name) }}" maxlength="255" required
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 @error('customer_name') border-red-500 @enderror">
                @error('customer_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="need_by_at" class="block text-sm font-medium">Need-by date &amp; time <span class="font-normal text-slate-500">({{ $timezone }})</span></label>
                <input id="need_by_at" name="need_by_at" type="datetime-local" value="{{ $needBy }}" @if ($minNeedBy) min="{{ $minNeedBy }}" @endif required
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 @error('need_by_at') border-red-500 @enderror">
                @error('need_by_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <fieldset>
            <legend class="text-sm font-medium">Products</legend>
            @error('items') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

            <div id="item-rows" class="mt-2 space-y-3">
                @foreach ($oldItems as $index => $item)
                    @include('orders.partials.item-row', ['index' => $index, 'item' => $item])
                @endforeach
            </div>

            <button type="button" id="add-item" class="mt-3 rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">+ Add product</button>
        </fieldset>

        <div class="flex justify-end gap-3">
            <a href="{{ route('schedule.index') }}" class="rounded-md px-4 py-2 text-sm text-slate-600 hover:text-slate-900">Cancel</a>
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">{{ $order ? 'Save changes' : 'Create order' }}</button>
        </div>
    </form>

    @if ($order)
        <form method="POST" action="{{ route('orders.destroy', $order) }}" class="mt-4 flex justify-end"
              onsubmit="return confirm('Delete order #{{ $order->id }}? This cannot be undone.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-md px-4 py-2 text-sm text-red-600 hover:bg-red-50">Delete this order</button>
        </form>
    @endif

    <template id="item-row-template">
        @include('orders.partials.item-row', ['index' => '__INDEX__', 'item' => ['product_id' => '', 'quantity' => '']])
    </template>

    <script>
        (() => {
            const rows = document.getElementById('item-rows');
            const template = document.getElementById('item-row-template').innerHTML;
            let nextIndex = {{ count($oldItems) ? max(array_keys($oldItems)) + 1 : 0 }};

            document.getElementById('add-item').addEventListener('click', () => {
                rows.insertAdjacentHTML('beforeend', template.replaceAll('__INDEX__', nextIndex++));
            });

            rows.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-item]');
                if (button && rows.children.length > 1) {
                    button.closest('[data-item-row]').remove();
                }
            });
        })();
    </script>
@endsection
