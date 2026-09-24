<?php

namespace App\Http\Controllers\Portal;

use App\Enums\LedgerAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreLedgerAccountRequest;
use App\Http\Requests\Portal\UpdateLedgerAccountRequest;
use App\Models\Company;
use App\Models\LedgerAccount;
use App\Support\CompanyContext;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChartOfAccountsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', LedgerAccount::class);

        $company = $this->company();
        $accounts = LedgerAccount::query()->orderBy('code')->get();

        $groups = collect(LedgerAccountType::cases())->map(function (LedgerAccountType $type) use ($accounts, $company) {
            $ofType = $accounts->where('type', $type->value);
            $roots = $ofType->whereNull('parent_id')->sortBy('code')->values()->map(function (LedgerAccount $root) use ($ofType, $company) {
                $children = $ofType->where('parent_id', $root->id)->sortBy('code')->values()
                    ->map(fn (LedgerAccount $child) => $this->present($child, $company))
                    ->all();

                $row = $this->present($root, $company);
                $row['movement'] += array_sum(array_map(fn (array $child) => $child['movement'], $children));
                $row['movement_display'] = Money::of($row['movement'], $company->currency)->format();
                $row['children'] = $children;

                return $row;
            })->all();

            return ['type' => $type->value, 'label' => $type->label(), 'accounts' => $roots];
        })->values();

        return Inertia::render('Accounts/Chart', [
            'groups' => $groups,
            'can' => ['create' => Gate::allows('create', LedgerAccount::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', LedgerAccount::class);

        return Inertia::render('Accounts/ChartForm', $this->formProps(null));
    }

    public function store(StoreLedgerAccountRequest $request): RedirectResponse
    {
        LedgerAccount::create($request->validated());

        return redirect()->route('portal.chart-of-accounts.index')->with('success', 'Account created.');
    }

    public function edit(LedgerAccount $account): Response
    {
        Gate::authorize('update', $account);

        return Inertia::render('Accounts/ChartForm', $this->formProps($account));
    }

    public function update(UpdateLedgerAccountRequest $request, LedgerAccount $account): RedirectResponse
    {
        $account->update($request->validated());

        return redirect()->route('portal.chart-of-accounts.index')->with('success', 'Account updated.');
    }

    public function destroy(LedgerAccount $account): RedirectResponse
    {
        Gate::authorize('delete', $account);

        if ($account->children()->exists() || $account->transactions()->exists()) {
            return back()->with('error', 'Accounts with transactions or sub-accounts cannot be deleted.');
        }

        $account->delete();

        return redirect()->route('portal.chart-of-accounts.index')->with('success', 'Account removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(LedgerAccount $account, Company $company): array
    {
        $movement = $account->movement();

        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
            'is_active' => $account->is_active,
            'movement' => $movement,
            'movement_display' => Money::of($movement, $company->currency)->format(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?LedgerAccount $account): array
    {
        return [
            'account' => $account ? [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'parent_id' => $account->parent_id,
                'is_active' => $account->is_active,
            ] : null,
            'types' => collect(LedgerAccountType::cases())->map(fn (LedgerAccountType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values(),
            'parents' => LedgerAccount::query()
                ->when($account, fn ($query) => $query->whereKeyNot($account->id))
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (LedgerAccount $parent) => [
                    'id' => $parent->id,
                    'label' => trim(($parent->code ? $parent->code.' · ' : '').$parent->name),
                ])
                ->values(),
        ];
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}
