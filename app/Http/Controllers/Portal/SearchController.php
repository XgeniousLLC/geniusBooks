<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Transaction;
use App\Support\CompanyContext;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->string('q')->toString());
        $company = $this->company();
        $groups = [];

        if (mb_strlen($query) >= 2) {
            $term = '%'.$query.'%';

            if (Gate::allows(Permission::ManageSales)) {
                $groups[] = $this->group('Customers', Customer::query()
                    ->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('company_name', 'like', $term))
                    ->limit(6)->get()
                    ->map(fn (Customer $c) => ['label' => $c->name, 'sublabel' => $c->email, 'url' => route('portal.customers.show', $c)]));

                $groups[] = $this->group('Invoices', Invoice::query()
                    ->where('number', 'like', $term)->limit(6)->get()
                    ->map(fn (Invoice $i) => ['label' => $i->number, 'sublabel' => Money::of((int) $i->total, $i->currency)->format(), 'url' => route('portal.invoices.show', $i)]));

                $groups[] = $this->group('Payments', Payment::query()
                    ->with('customer:id,name')
                    ->where(fn ($q) => $q->where('reference', 'like', $term)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)))
                    ->limit(6)->get()
                    ->map(fn (Payment $p) => ['label' => $p->reference ?: 'Payment #'.$p->id, 'sublabel' => $p->customer?->name, 'url' => route('portal.payments.show', $p)]));
            }

            if (Gate::allows(Permission::ManageExpenses)) {
                $groups[] = $this->group('Expenses', Expense::query()
                    ->where(fn ($q) => $q->where('description', 'like', $term)->orWhere('reference', 'like', $term))
                    ->limit(6)->get()
                    ->map(fn (Expense $e) => ['label' => $e->description, 'sublabel' => Money::of((int) $e->amount, $e->currency)->format(), 'url' => route('portal.expenses.show', $e)]));
            }

            if (Gate::allows(Permission::ManageFinances)) {
                $groups[] = $this->group('Transactions', Transaction::query()
                    ->where('description', 'like', $term)->limit(6)->get()
                    ->map(fn (Transaction $t) => ['label' => $t->description, 'sublabel' => $t->occurred_on->toDateString(), 'url' => route('portal.transactions.index', ['search' => $t->description])]));
            }

            $groups = array_values(array_filter($groups, fn (array $group) => $group['items'] !== []));
        }

        return Inertia::render('Search/Index', [
            'query' => $query,
            'groups' => $groups,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $items
     * @return array{title: string, items: list<array<string, mixed>>}
     */
    private function group(string $title, $items): array
    {
        return ['title' => $title, 'items' => $items->values()->all()];
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}
