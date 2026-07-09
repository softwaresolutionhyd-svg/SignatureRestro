<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyInstalledFeature;
use App\Models\CompanyUpdate;
use App\Models\Setting;
use App\Services\TenantFeatureInstaller;
use App\Support\CompanyFeatures;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class CompanyUpdateController extends Controller
{
    /** In-app updates / release notes for the active company only. */
    public function tenantIndex()
    {
        $cid = current_company_id();
        abort_if($cid === null, 404);

        $updates = CompanyUpdate::query()
            ->where('company_id', $cid)
            ->publishedForTenant()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(Setting::pageSize('company_updates_per_page', 20))
            ->withQueryString();

        $installedFeatureKeys = CompanyInstalledFeature::query()
            ->where('company_id', $cid)
            ->pluck('feature_key')
            ->all();

        return view('updates.index', compact('updates', 'installedFeatureKeys'));
    }

    public function installFeature(CompanyUpdate $companyUpdate)
    {
        $cid = current_company_id();
        abort_if($cid === null || (int) $companyUpdate->company_id !== (int) $cid, 403);

        $user = auth()->user();
        $canInstall = $user && ($user->isPlatformSuperAdmin() || $user->isCompanyAdmin() || ($user->role ?? '') === 'admin');
        abort_unless($canInstall, 403);

        abort_unless($companyUpdate->feature_key && $companyUpdate->published_at, 404);

        if (CompanyFeatures::isInstalled($cid, $companyUpdate->feature_key)) {
            return redirect()->route('updates.index')->with('status', 'Yeh feature pehle se install hai.');
        }

        $company = Company::query()->findOrFail($cid);

        try {
            TenantFeatureInstaller::install(
                $company,
                $companyUpdate->feature_key,
                $user->id,
                $companyUpdate->id
            );
        } catch (Throwable $e) {
            return redirect()
                ->route('updates.index')
                ->withErrors(['install' => $e->getMessage()]);
        }

        return redirect()
            ->route('updates.index')
            ->with('status', 'Feature install ho gaya — ab app mein naya section available hai.');
    }

    /** Super admin: manage release notes for the single active company (session). */
    public function platformUpdatesIndex()
    {
        $company = $this->resolvePlatformAdminCompany();

        $updates = CompanyUpdate::query()
            ->where('company_id', $company->id)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(Setting::pageSize('platform_updates_per_page', 20))
            ->withQueryString();

        return view('companies.updates.index', compact('company', 'updates'));
    }

    public function platformUpdatesCreate()
    {
        $company = $this->resolvePlatformAdminCompany();

        return view('companies.updates.create', compact('company'));
    }

    public function platformUpdatesStore(Request $request)
    {
        $company = $this->resolvePlatformAdminCompany();

        $request->merge([
            'feature_key' => $request->filled('feature_key') ? (string) $request->input('feature_key') : null,
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:65000'],
            'version' => ['nullable', 'string', 'max:50'],
            'feature_key' => ['nullable', 'string', 'max:80', Rule::in(CompanyFeatures::packageKeys())],
            'published_at' => ['nullable', 'date'],
        ]);

        $publishedAt = null;
        if ($request->boolean('publish_now')) {
            $publishedAt = now();
        } elseif (! empty($data['published_at'])) {
            $publishedAt = $data['published_at'];
        }

        CompanyUpdate::create([
            'company_id' => $company->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'version' => $data['version'] ?? null,
            'feature_key' => $data['feature_key'] ?? null,
            'published_at' => $publishedAt,
        ]);

        return redirect()
            ->route('platform.updates.index')
            ->with('status', 'Update saved. It is visible to this company only when a publish date is set.');
    }

    public function platformUpdatesEdit(CompanyUpdate $update)
    {
        $company = $this->resolvePlatformAdminCompany();
        $this->assertUpdateBelongsToCompany($company, $update);

        return view('companies.updates.edit', compact('company', 'update'));
    }

    public function platformUpdatesUpdate(Request $request, CompanyUpdate $update)
    {
        $company = $this->resolvePlatformAdminCompany();
        $this->assertUpdateBelongsToCompany($company, $update);

        $request->merge([
            'feature_key' => $request->filled('feature_key') ? (string) $request->input('feature_key') : null,
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:65000'],
            'version' => ['nullable', 'string', 'max:50'],
            'feature_key' => ['nullable', 'string', 'max:80', Rule::in(CompanyFeatures::packageKeys())],
            'published_at' => ['nullable', 'date'],
            'clear_publish' => ['nullable', 'boolean'],
        ]);

        $publishedAt = $update->published_at;
        if ($request->boolean('clear_publish')) {
            $publishedAt = null;
        } elseif ($request->boolean('publish_now')) {
            $publishedAt = now();
        } elseif (! empty($data['published_at'])) {
            $publishedAt = $data['published_at'];
        }

        $update->update([
            'title' => $data['title'],
            'body' => $data['body'],
            'version' => $data['version'] ?? null,
            'feature_key' => $data['feature_key'] ?? null,
            'published_at' => $publishedAt,
        ]);

        return redirect()
            ->route('platform.updates.index')
            ->with('status', 'Update updated.');
    }

    public function platformUpdatesDestroy(CompanyUpdate $update)
    {
        $company = $this->resolvePlatformAdminCompany();
        $this->assertUpdateBelongsToCompany($company, $update);

        $update->delete();

        return redirect()
            ->route('platform.updates.index')
            ->with('status', 'Update removed.');
    }

    private function resolvePlatformAdminCompany(): Company
    {
        abort_unless(auth()->user()?->isPlatformSuperAdmin(), 403);
        $cid = current_company_id();
        abort_if($cid === null, 404);

        return Company::query()->where('id', $cid)->where('active', true)->firstOrFail();
    }

    private function assertUpdateBelongsToCompany(Company $company, CompanyUpdate $update): void
    {
        abort_if((int) $update->company_id !== (int) $company->id, 404);
    }
}
