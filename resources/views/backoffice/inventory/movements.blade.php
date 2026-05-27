@extends('layouts.backoffice')

@section('page-title', 'Stock Movements')

@section('content')
<h1 class="text-2xl font-bold mb-6">Stock Movements</h1>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <x-branch-selector :branch-id="$branchId" :branches="$branches" :auto-submit="false" />
        <div>
            <label class="block text-xs text-gray-500 mb-1">Type</label>
            <select name="type" class="p-2 border rounded">
                <option value="">All</option>
                @foreach(['stock_in','sale','adjustment'] as $t)
                <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Product</label>
            <select name="product_id" class="p-2 border rounded">
                <option value="">All</option>
                @foreach($products as $p)
                <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded">Filter</button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3">When</th>
                <th class="text-left p-3">Product</th>
                <th class="text-left p-3">Type</th>
                <th class="text-right p-3">Qty</th>
                <th class="text-left p-3">Reference</th>
                <th class="text-left p-3">Reason</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movements as $m)
            <tr class="border-t">
                <td class="p-3 text-gray-500">{{ $m->created_at->format('Y-m-d H:i') }}</td>
                <td class="p-3">{{ $m->product->name ?? '—' }}</td>
                <td class="p-3"><span class="px-2 py-0.5 rounded bg-gray-100 text-xs">{{ $m->type }}</span></td>
                <td class="p-3 text-right font-mono {{ $m->qty < 0 ? 'text-red-600' : 'text-green-700' }}">{{ $m->qty }}</td>
                <td class="p-3 font-mono text-xs">{{ $m->reference_number ?? '—' }}</td>
                <td class="p-3 text-gray-600">{{ $m->reason ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="p-6 text-center text-gray-400">No movements.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $movements->links() }}</div>
@endsection
