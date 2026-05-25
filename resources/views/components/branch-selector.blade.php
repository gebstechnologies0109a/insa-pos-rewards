@props(['branchId' => null, 'branches' => null, 'autoSubmit' => true])

@php
    $user = auth()->user();
    $isScoped = $user->isBranchScoped();
    $allBranches = $branches ?? \App\Models\POS\Branch::orderBy('name')->get();
    $currentBranchId = $branchId ?? $user->branch_id ?? ($allBranches->first()?->id);
    $currentBranch = $allBranches->firstWhere('id', $currentBranchId);
@endphp

@if($isScoped)
    <div class="flex items-center gap-2 text-sm">
        <span class="text-gray-500">Branch:</span>
        <span class="font-medium">{{ $currentBranch?->name ?? 'Unassigned' }}</span>
        <input type="hidden" name="branch_id" value="{{ $currentBranchId }}">
    </div>
@else
    <div class="flex items-center gap-2 text-sm">
        <label class="text-gray-500">Branch:</label>
        <select name="branch_id"
                class="p-2 border rounded text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                @if($autoSubmit) onchange="this.closest('form').submit()" @endif>
            @foreach($allBranches as $branch)
            <option value="{{ $branch->id }}" @selected($currentBranchId == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
    </div>
@endif
