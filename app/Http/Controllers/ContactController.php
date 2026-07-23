<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureContactsCategorySchema();

        $query = Contact::withCount('posOrders')
            ->withSum(['creditLedger as total_credit' => fn($q) => $q->where('type','credit')], 'amount')
            ->withSum(['creditLedger as total_paid'   => fn($q) => $q->where('type','payment')], 'amount')
            ->orderBy('name');

        if ($request->filled('search')) {
            $q = '%' . $request->search . '%';
            $query->where(fn($w) => $w->where('name','like',$q)
                                      ->orWhere('phone','like',$q)
                                      ->orWhere('email','like',$q)
                                      ->orWhere('category','like',$q));
        }
        if ($request->filled('active')) {
            $query->where('active', $request->active === '1');
        }

        $contacts = $query
            ->paginate(Setting::pageSize('contacts_per_page', 20))
            ->withQueryString();

        $categoryOptions = Contact::categoryOptions();
        $categoryRows = Contact::categoryRows();

        return view('contacts.index', compact('contacts', 'categoryOptions', 'categoryRows'));
    }

    /** AJAX: quick search for POS contact picker */
    public function search(Request $request)
    {
        $this->ensureContactsCategorySchema();
        $q = '%' . $request->get('q', '') . '%';
        $contacts = Contact::where('active', true)
            ->where(fn($w) => $w->where('name','like',$q)->orWhere('phone','like',$q))
            ->limit(15)
            ->get(['id','name','phone','city','category']);

        return response()->json($contacts);
    }

    public function create()
    {
        $categoryOptions = Contact::categoryOptions();

        return view('contacts.create', compact('categoryOptions'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['active'] = $request->boolean('active', true);
        Contact::create($data);

        if ($request->expectsJson()) {
            $contact = Contact::latest('id')->first();
            return response()->json(['success' => true, 'contact' => $contact]);
        }

        return redirect()->route('contacts.index')->with('success', 'Contact created.');
    }

    public function show(Contact $contact)
    {
        $contact->load(['posOrders.creditLedger']);
        $ledger = $contact->creditLedger()
            ->with(['posOrder', 'purchaseOrder', 'creator'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(Setting::pageSize('contact_ledger_per_page', 20))
            ->withQueryString();

        $balance     = $contact->balance;
        $totalCredit = $contact->creditLedger()->where('type','credit') ->sum('amount');
        $totalPaid   = $contact->creditLedger()->where('type','payment')->sum('amount');

        return view('contacts.show', compact('contact','ledger','balance','totalCredit','totalPaid'));
    }

    public function printLedger(Contact $contact)
    {
        $ledger = $contact->creditLedger()
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $totalCredit = (float) $contact->creditLedger()->where('type', 'credit')->sum('amount');
        $totalPaid   = (float) $contact->creditLedger()->where('type', 'payment')->sum('amount');
        $balance     = round($totalCredit - $totalPaid, 2);
        $companyName = Setting::get('company_name', config('app.name'));
        $currency    = Setting::get('currency_symbol', 'Rs.');

        return view('contacts.ledger-print', compact(
            'contact', 'ledger', 'totalCredit', 'totalPaid', 'balance', 'companyName', 'currency'
        ));
    }

    public function edit(Contact $contact)
    {
        $categoryOptions = Contact::categoryOptions();

        return view('contacts.edit', compact('contact', 'categoryOptions'));
    }

    public function update(Request $request, Contact $contact)
    {
        $data = $this->validated($request, $contact->id);
        $data['active'] = $request->boolean('active', true);
        $contact->update($data);

        return redirect()->route('contacts.show', $contact)->with('success', 'Contact updated.');
    }

    public function destroy(Contact $contact)
    {
        $balance = (float) $contact->balance;
        if ($balance > 0.009) {
            return back()->with('error', 'Contact delete nahi ho sakta — outstanding credit '.number_format($balance, 2).' hai. Pehle settle karein.');
        }

        // Settled / empty ledger rows hatao taake Credit Book totals mein ghost balance na aaye
        $contact->creditLedger()->delete();
        $contact->delete();

        return redirect()->route('contacts.index')->with('success', 'Contact deleted.');
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
        ]);

        Contact::addCategoryRow($data['label']);

        return back()->with('success', 'Category added.');
    }

    public function destroyCategory(string $slug)
    {
        $slug = trim($slug);
        if ($slug === '' || ! array_key_exists($slug, Contact::categoryOptions())) {
            return back()->with('error', 'Category not found.');
        }

        $inUse = Contact::query()->where('category', $slug)->exists();
        if ($inUse) {
            return back()->with('error', 'Category delete nahi ho sakti — contacts is category mein maujood hain.');
        }

        if (count(Contact::categoryRows()) <= 1) {
            return back()->with('error', 'Kam az kam aik category rehni chahiye.');
        }

        Contact::removeCategoryRow($slug);

        return back()->with('success', 'Category removed.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $this->ensureContactsCategorySchema();

        return $request->validate([
            'name'     => 'required|string|max:150',
            'category' => ['nullable', 'string', Rule::in(array_keys(Contact::categoryOptions()))],
            'phone'    => 'nullable|string|max:30',
            'email'    => 'nullable|email|max:150',
            'address'  => 'nullable|string|max:300',
            'city'     => 'nullable|string|max:100',
            'notes'    => 'nullable|string',
            'active'   => 'nullable|boolean',
        ]);
    }

    private function ensureContactsCategorySchema(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('contacts') || $schema->hasColumn('contacts', 'category')) {
            return;
        }

        $schema->table('contacts', function (Blueprint $table) {
            $table->string('category', 40)->nullable()->after('name')->index();
        });
    }
}
