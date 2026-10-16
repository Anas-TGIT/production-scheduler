<div data-item-row class="grid grid-cols-[1fr_9rem_auto] items-start gap-3">
    <div>
        <label class="sr-only" for="items-{{ $index }}-product">Product</label>
        <select id="items-{{ $index }}-product" name="items[{{ $index }}][product_id]" required
                class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 @error("items.$index.product_id") border-red-500 @enderror">
            <option value="">Choose a product…</option>
            @foreach ($productTypes as $type)
                <optgroup label="{{ $type->name }} — {{ number_format($type->units_per_hour) }} units/h">
                    @foreach ($type->products as $product)
                        <option value="{{ $product->id }}" @selected((string) ($item['product_id'] ?? '') === (string) $product->id)>
                            {{ $product->name }} ({{ $type->name }})
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @error("items.$index.product_id") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="sr-only" for="items-{{ $index }}-quantity">Quantity</label>
        <input id="items-{{ $index }}-quantity" name="items[{{ $index }}][quantity]" type="number" min="1" step="1" placeholder="Quantity"
               value="{{ $item['quantity'] ?? '' }}" required
               class="w-full rounded-md border border-slate-300 px-3 py-2 @error("items.$index.quantity") border-red-500 @enderror">
        @error("items.$index.quantity") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <button type="button" data-remove-item class="rounded-md px-3 py-2 text-sm text-slate-500 hover:text-red-600" aria-label="Remove product line">Remove</button>
</div>
