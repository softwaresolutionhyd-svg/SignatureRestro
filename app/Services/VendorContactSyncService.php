<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\PurchaseVendor;
use App\Support\EnsuresVendorCreditSchema;

class VendorContactSyncService
{
    use EnsuresVendorCreditSchema;

    /** Ensure a Contact exists for the vendor and keep it in sync. */
    public function ensureContactForVendor(PurchaseVendor $vendor): ?Contact
    {
        $this->ensureVendorCreditSchema();

        $category = Contact::ensureSupplierCategory();

        $vendor->loadMissing('contact');

        if ($vendor->contact_id && $vendor->contact) {
            $this->updateContactFromVendor($vendor->contact, $vendor, $category);

            return $vendor->contact;
        }

        $contact = null;
        if ($vendor->phone) {
            $contact = Contact::query()
                ->where('name', $vendor->name)
                ->where('phone', $vendor->phone)
                ->first();
        }
        if ($contact === null) {
            $contact = Contact::query()
                ->where('name', $vendor->name)
                ->where('category', $category)
                ->first();
        }

        if ($contact === null) {
            $contact = Contact::create([
                'name' => $vendor->name,
                'phone' => $vendor->phone,
                'email' => $vendor->email,
                'address' => $vendor->address,
                'category' => $category,
                'active' => (bool) $vendor->active,
                'notes' => 'Supplier / Vendor',
            ]);
        } else {
            $this->updateContactFromVendor($contact, $vendor, $category);
        }

        if ((int) $vendor->contact_id !== (int) $contact->id) {
            $vendor->forceFill(['contact_id' => $contact->id])->save();
        }

        return $contact;
    }

    public function syncAllVendors(): int
    {
        $this->ensureVendorCreditSchema();

        $count = 0;
        PurchaseVendor::query()->orderBy('id')->chunk(100, function ($vendors) use (&$count) {
            foreach ($vendors as $vendor) {
                $this->ensureContactForVendor($vendor);
                $count++;
            }
        });

        return $count;
    }

    /** Only create contacts for vendors that don't have one yet (cheap, for lazy backfill). */
    public function backfillMissing(): int
    {
        $this->ensureVendorCreditSchema();

        $count = 0;
        PurchaseVendor::query()
            ->whereNull('contact_id')
            ->orderBy('id')
            ->chunk(100, function ($vendors) use (&$count) {
                foreach ($vendors as $vendor) {
                    $this->ensureContactForVendor($vendor);
                    $count++;
                }
            });

        return $count;
    }

    private function updateContactFromVendor(Contact $contact, PurchaseVendor $vendor, string $category): void
    {
        $contact->update([
            'name' => $vendor->name,
            'phone' => $vendor->phone ?: $contact->phone,
            'email' => $vendor->email ?: $contact->email,
            'address' => $vendor->address ?: $contact->address,
            'category' => $contact->category ?: $category,
            'active' => (bool) $vendor->active,
        ]);
    }
}
