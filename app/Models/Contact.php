<?php



namespace App\Models;



use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Support\Str;



class Contact extends Model

{

    protected $connection = 'tenant';



    use BelongsToCompany;

    use HasFactory;



    /** @return list<array{slug: string, label: string}> */

    public static function defaultCategoryRows(): array

    {

        return [

            ['slug' => 'mess_bill', 'label' => 'Mess Bill'],

            ['slug' => 'offices', 'label' => 'Offices'],

            ['slug' => 'cat_a', 'label' => 'Cat A'],

            ['slug' => 'school_guest', 'label' => 'School Guest'],

        ];

    }



    public static function ensureCategoryCatalog(): void

    {

        $raw = Setting::get('contact_categories');

        if ($raw === null || trim((string) $raw) === '' || trim((string) $raw) === '[]') {

            self::persistCategoryRows(self::defaultCategoryRows());

        }

    }



    /** @return list<array{slug: string, label: string}> */

    public static function categoryRows(): array

    {

        self::ensureCategoryCatalog();



        $decoded = json_decode((string) Setting::get('contact_categories', '[]'), true);

        if (! is_array($decoded)) {

            return self::defaultCategoryRows();

        }



        $rows = [];

        foreach ($decoded as $row) {

            if (! is_array($row)) {

                continue;

            }

            $slug = trim((string) ($row['slug'] ?? ''));

            $label = trim((string) ($row['label'] ?? ''));

            if ($slug !== '' && $label !== '') {

                $rows[] = ['slug' => $slug, 'label' => $label];

            }

        }



        return $rows !== [] ? $rows : self::defaultCategoryRows();

    }



    /** @return array<string, string> */

    public static function categoryOptions(): array

    {

        $out = [];

        foreach (self::categoryRows() as $row) {

            $out[$row['slug']] = $row['label'];

        }



        return $out;

    }



    public static function slugFromLabel(string $label): string

    {

        $slug = Str::slug($label, '_');

        if ($slug === '') {

            $slug = 'cat_'.substr(md5($label), 0, 8);

        }



        return substr($slug, 0, 40);

    }



    public static function addCategoryRow(string $label): string

    {

        $label = trim($label);

        $rows = self::categoryRows();



        foreach ($rows as $row) {

            if (strcasecmp($row['label'], $label) === 0) {

                return $row['slug'];

            }

        }



        $slug = self::slugFromLabel($label);

        $base = $slug;

        $i = 2;

        while (collect($rows)->contains(fn (array $r) => $r['slug'] === $slug)) {

            $slug = substr($base, 0, 36).'_'.$i;

            $i++;

        }



        $rows[] = ['slug' => $slug, 'label' => $label];

        self::persistCategoryRows($rows);



        return $slug;

    }



    public static function removeCategoryRow(string $slug): void

    {

        $rows = array_values(array_filter(

            self::categoryRows(),

            fn (array $row) => $row['slug'] !== $slug

        ));



        self::persistCategoryRows($rows);

    }



    /** @param  list<array{slug: string, label: string}>  $rows */

    public static function persistCategoryRows(array $rows): void

    {

        Setting::set('contact_categories', json_encode(array_values($rows)));

    }



    protected $fillable = [

        'company_id',

        'name',

        'category',

        'phone',

        'email',

        'address',

        'city',

        'notes',

        'active',

    ];



    protected $casts = ['active' => 'bool'];



    public function posOrders(): HasMany

    {

        return $this->hasMany(PosOrder::class, 'contact_id');

    }



    public function creditLedger(): HasMany

    {

        return $this->hasMany(CreditLedger::class, 'contact_id');

    }



    /** Total outstanding balance (credits minus payments) */

    public function getBalanceAttribute(): float

    {

        $credits  = $this->creditLedger()->where('type', 'credit') ->sum('amount');

        $payments = $this->creditLedger()->where('type', 'payment')->sum('amount');



        return round((float) $credits - (float) $payments, 2);

    }



    public function categoryLabel(): string

    {

        $key = (string) ($this->category ?? '');

        if ($key === '') {

            return '—';

        }



        return self::categoryOptions()[$key] ?? ucfirst(str_replace('_', ' ', $key));

    }

}


