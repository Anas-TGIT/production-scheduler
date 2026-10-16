@extends('layouts.app')

@section('title', 'Schedule')

@section('content')
    @php
        $at = fn (\Carbon\CarbonInterface $time): string => $time->setTimezone($timezone)->format('D d M, H:i');
        $duration = fn (int $minutes): string => $minutes >= 60
            ? intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m'
            : $minutes.'m';
        $palette = ['bg-indigo-500', 'bg-amber-500', 'bg-emerald-500', 'bg-sky-500', 'bg-rose-500'];
        $typeColor = fn (int $typeId): string => $palette[$typeId % count($palette)];
        $makespan = max(1, $schedule->makespanMinutes());
    @endphp

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Production schedule</h1>
            <p class="text-sm text-slate-600">
                Single line, continuous production, {{ $changeoverMinutes }} min changeover between product types.
                Times shown in {{ $timezone }}.
            </p>
        </div>
        <a href="{{ route('orders.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">New order</a>
    </div>

    <form method="GET" action="{{ route('schedule.index') }}" class="mb-6 flex flex-wrap items-end gap-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label for="start" class="block text-sm font-medium">Start at ({{ $timezone }})</label>
            <input id="start" name="start" type="datetime-local" value="{{ $startAt->setTimezone($timezone)->format('Y-m-d\TH:i') }}"
                   class="mt-1 rounded-md border border-slate-300 px-3 py-2">
        </div>
        <div>
            <label for="strategy" class="block text-sm font-medium">Sequencing</label>
            <select id="strategy" name="strategy" class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2">
                @foreach ($strategies as $key => $option)
                    <option value="{{ $key }}" @selected($key === $strategy->key())>{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Recalculate</button>
        @if ($errors->any())
            <p class="w-full text-sm text-red-600">{{ $errors->first() }}</p>
        @endif
    </form>

    @if ($skippedOrders !== [])
        <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            <p class="font-medium">{{ count($skippedOrders) }} order(s) could not be scheduled and are left out:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($skippedOrders as $orderId => $reason)
                    <li>{{ $reason }} <a href="{{ route('orders.edit', $orderId) }}" class="underline">Fix order #{{ $orderId }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($schedule->isEmpty())
        @if ($skippedOrders === [])
            <div class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">
                No orders yet. <a href="{{ route('orders.create') }}" class="text-indigo-700 underline">Create the first one</a>.
            </div>
        @endif
    @else
        <dl class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ([
                'Finishes' => $at($schedule->endAt),
                'Makespan' => $duration($schedule->makespanMinutes()),
                'Production' => $duration($schedule->totalProductionMinutes),
                'Setup' => $duration($schedule->totalSetupMinutes).' ('.$schedule->changeoverCount.'×)',
                'Late orders' => $schedule->lateOrderCount.' / '.count($schedule->entries),
                'Total tardiness' => $duration($schedule->totalTardinessMinutes),
            ] as $label => $value)
                <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                    <dd class="mt-1 font-semibold">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        <section class="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm" aria-label="Timeline">
            <h2 class="mb-3 text-sm font-semibold">Timeline</h2>
            <div class="flex h-8 w-full overflow-hidden rounded bg-slate-100">
                @foreach ($schedule->entries as $entry)
                    @if ($entry->setupMinutes > 0)
                        <div class="h-full bg-slate-300 bg-[repeating-linear-gradient(45deg,transparent,transparent_4px,rgba(255,255,255,.5)_4px,rgba(255,255,255,.5)_8px)]"
                             style="width: {{ $entry->setupMinutes / $makespan * 100 }}%" title="Changeover · {{ $entry->setupMinutes }} min"></div>
                    @endif
                    <div class="h-full border-l border-white/60 {{ $typeColor($entry->order->productTypeId) }} {{ $entry->isLate() ? 'ring-2 ring-inset ring-red-600' : '' }} flex items-center justify-center text-xs font-medium text-white"
                         style="width: {{ $entry->productionMinutes() / $makespan * 100 }}%"
                         title="#{{ $entry->order->orderId }} {{ $entry->order->customerName }} · {{ $at($entry->productionStartAt) }} → {{ $at($entry->finishAt) }}">
                        #{{ $entry->order->orderId }}
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-xs text-slate-500">
                <span>{{ $at($schedule->startAt) }}</span><span>{{ $at($schedule->endAt) }}</span>
            </div>
        </section>

        <div class="mb-8 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm" id="schedule-table">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">Order</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-right">Setup</th>
                        <th class="px-3 py-2">Start</th>
                        <th class="px-3 py-2 text-right">Duration</th>
                        <th class="px-3 py-2">Finish</th>
                        <th class="px-3 py-2">Need by</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($schedule->entries as $entry)
                        <tr data-order-id="{{ $entry->order->orderId }}">
                            <td class="px-3 py-2 text-slate-500">{{ $entry->position }}</td>
                            <td class="px-3 py-2"><span class="font-medium">#{{ $entry->order->orderId }}</span> {{ $entry->order->customerName }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="mr-1 inline-block h-2.5 w-2.5 rounded-full {{ $typeColor($entry->order->productTypeId) }}"></span>{{ $entry->order->productTypeName }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($entry->order->totalQuantity) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $entry->setupMinutes ? $entry->setupMinutes.'m' : '—' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $at($entry->productionStartAt) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $duration($entry->productionMinutes()) }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $at($entry->finishAt) }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $at($entry->order->dueAt) }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($entry->isLate())
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Late by {{ $duration($entry->tardinessMinutes) }}</span>
                                @else
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">On time</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-right">
                                <a href="{{ route('orders.edit', $entry->order->orderId) }}" class="text-indigo-700 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('orders.destroy', $entry->order->orderId) }}" class="inline"
                                      onsubmit="return confirm('Delete order #{{ $entry->order->orderId }}? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ml-2 text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if (count($comparison) === 1)
            <p class="text-sm text-slate-600">
                Want fewer changeovers?
                <a href="{{ route('schedule.index', ['start' => $startAt->setTimezone($timezone)->format('Y-m-d\TH:i'), 'strategy' => \App\Domain\Scheduling\Strategies\ChangeoverAwareStrategy::KEY]) }}"
                   class="text-indigo-700 underline">Try the changeover-aware heuristic</a> and compare it with this baseline.
            </p>
        @else
        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold">Strategy comparison (same orders, same start)</h2>
            <p class="mb-3 text-xs text-slate-500">Ranked by late orders, then total tardiness, then setup time. The heuristic is a local search, not a proven optimum.</p>
            <table class="min-w-full text-sm" id="comparison-table">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="py-1 pr-3">Strategy</th>
                        <th class="py-1 pr-3">Sequence</th>
                        <th class="py-1 pr-3 text-right">Late</th>
                        <th class="py-1 pr-3 text-right">Tardiness</th>
                        <th class="py-1 pr-3 text-right">Setup</th>
                        <th class="py-1 pr-3 text-right">Makespan</th>
                        <th class="py-1">Finishes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($comparison as $key => $row)
                        <tr class="{{ $key === $strategy->key() ? 'font-semibold' : '' }}">
                            <td class="py-1.5 pr-3">{{ $row['label'] }}</td>
                            <td class="py-1.5 pr-3 font-mono text-xs">{{ implode(' → ', array_map(fn ($id) => '#'.$id, $row['result']->orderIds())) }}</td>
                            <td class="py-1.5 pr-3 text-right tabular-nums">{{ $row['result']->lateOrderCount }}</td>
                            <td class="py-1.5 pr-3 text-right tabular-nums">{{ $duration($row['result']->totalTardinessMinutes) }}</td>
                            <td class="py-1.5 pr-3 text-right tabular-nums">{{ $duration($row['result']->totalSetupMinutes) }}</td>
                            <td class="py-1.5 pr-3 text-right tabular-nums">{{ $duration($row['result']->makespanMinutes()) }}</td>
                            <td class="py-1.5 whitespace-nowrap">{{ $at($row['result']->endAt) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        @endif
    @endif
@endsection
