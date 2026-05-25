<?php

namespace App\Repositories\POS;

use App\Models\POS\Customer;
use Illuminate\Database\Eloquent\Collection;

class CustomerRepository
{
    public function findByUuidOrCard(string $value): ?Customer
    {
        return Customer::where('uuid', $value)
            ->orWhere('card_number', $value)
            ->where('status', 'active')
            ->first();
    }

    public function findByCardNumber(string $cardNumber): ?Customer
    {
        return Customer::where('card_number', $cardNumber)
            ->where('status', 'active')
            ->first();
    }

    public function findByPhone(string $phone): ?Customer
    {
        $normalized = preg_replace('/[^0-9+]/', '', $phone);

        return Customer::where('phone', $normalized)
            ->orWhere('phone', 'LIKE', "%{$normalized}")
            ->where('status', 'active')
            ->first();
    }

    /**
     * @return Collection<int, Customer>
     */
    public function searchByName(string $query, int $limit = 10): Collection
    {
        return Customer::where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'LIKE', "%{$query}%")
                  ->orWhere('last_name', 'LIKE', "%{$query}%")
                  ->orWhereRaw("(first_name || ' ' || last_name) LIKE ?", ["%{$query}%"]);
            })
            ->limit($limit)
            ->get();
    }
}
