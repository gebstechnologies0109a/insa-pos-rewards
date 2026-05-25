<?php

namespace App\Services\POS;

use App\Models\POS\Customer;
use App\Repositories\POS\CustomerRepository;
use Illuminate\Database\Eloquent\Collection;

class CustomerLookupService
{
    public function __construct(
        protected CustomerRepository $customerRepository,
        protected QRDecoderService $qrDecoder,
    ) {}

    /**
     * Resolve a customer from any lookup type.
     *
     * @return Customer|Collection<int, Customer>|null
     */
    public function resolve(string $type, string $value): Customer|Collection|null
    {
        return match ($type) {
            'qr'      => $this->fromQR($value),
            'barcode' => $this->fromBarcode($value),
            'phone'   => $this->fromPhone($value),
            'search'  => $this->fromName($value),
            default   => null,
        };
    }

    public function fromQR(string $payload): ?Customer
    {
        $decoded = $this->qrDecoder->decode($payload);

        return match ($decoded['type']) {
            'uuid'        => $this->customerRepository->findByUuidOrCard($decoded['value']),
            'card_number' => $this->customerRepository->findByCardNumber($decoded['value']),
            default       => null,
        };
    }

    public function fromBarcode(string $barcode): ?Customer
    {
        return $this->customerRepository->findByCardNumber($barcode);
    }

    public function fromPhone(string $phone): ?Customer
    {
        return $this->customerRepository->findByPhone($phone);
    }

    /**
     * @return Collection<int, Customer>
     */
    public function fromName(string $name): Collection
    {
        return $this->customerRepository->searchByName($name);
    }
}
