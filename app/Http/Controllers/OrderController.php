<?php

namespace App\Http\Controllers;

use App\Data\OrderFormData;
use App\Models\Order;
use App\Models\ProductType;
use App\Support\DisplayTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function create(): View
    {
        return $this->form(null);
    }

    public function store(OrderFormData $data): RedirectResponse
    {
        $order = DB::transaction(function () use ($data): Order {
            $order = Order::create([
                'customer_name' => $data->customer_name,
                'need_by_at' => $data->needByUtc(),
            ]);
            $order->items()->createMany($data->itemAttributes());

            return $order;
        });

        return redirect()
            ->route('schedule.index')
            ->with('status', "Order #{$order->id} for {$order->customer_name} was created.");
    }

    public function edit(Order $order): View
    {
        return $this->form($order->load('items'));
    }

    public function update(OrderFormData $data, Order $order): RedirectResponse
    {
        DB::transaction(function () use ($data, $order): void {
            $order->update([
                'customer_name' => $data->customer_name,
                'need_by_at' => $data->needByUtc(),
            ]);
            // Lines are replaced as a whole: simpler than diffing and safe inside the transaction.
            $order->items()->delete();
            $order->items()->createMany($data->itemAttributes());
        });

        return redirect()
            ->route('schedule.index')
            ->with('status', "Order #{$order->id} for {$order->customer_name} was updated.");
    }

    public function destroy(Order $order): RedirectResponse
    {
        // Items are removed by the cascading foreign key.
        $order->delete();

        return redirect()
            ->route('schedule.index')
            ->with('status', "Order #{$order->id} for {$order->customer_name} was deleted.");
    }

    private function form(?Order $order): View
    {
        return view('orders.form', [
            'order' => $order,
            'productTypes' => ProductType::query()->with(['products' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get(),
            'timezone' => DisplayTime::timezone(),
        ]);
    }
}
