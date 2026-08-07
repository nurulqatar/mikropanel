<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    private const CATEGORIES = [
        'Office Rent',
        'Internet & Network',
        'Electricity',
        'Staff Salary',
        'Equipment',
        'Maintenance',
        'Transport',
        'Office Supplies',
        'Marketing',
        'Government Fees',
        'Food & Refreshment',
        'Other',
    ];

    private const PAYMENT_METHODS = [
        'Cash',
        'Bank Transfer',
        'Credit / Debit Card',
        'Ooredoo Money',
        'iPay',
        'Online Payment',
        'Manual Adjustment',
        'Other',
    ];

    public function index(): Response
    {
        $expenses = Expense::query()
            ->with('user:id,name')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (Expense $expense): array {
                return [
                    'id' => $expense->id,
                    'expense_date' =>
                        $expense->expense_date?->format('Y-m-d'),

                    'category' => $expense->category,
                    'title' => $expense->title,
                    'amount' => $expense->amount,
                    'payment_method' =>
                        $expense->payment_method,

                    'notes' => $expense->notes,
                    'created_by' => $expense->created_by,
                    'user' => $expense->user,
                    'created_at' => $expense->created_at,
                ];
            });

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,

            'summary' => [
                'total' => (float) Expense::query()
                    ->sum('amount'),

                'this_month' => (float) Expense::query()
                    ->whereBetween('expense_date', [
                        now()->startOfMonth()->toDateString(),
                        now()->endOfMonth()->toDateString(),
                    ])
                    ->sum('amount'),

                'today' => (float) Expense::query()
                    ->whereDate(
                        'expense_date',
                        today()->toDateString()
                    )
                    ->sum('amount'),

                'count' => Expense::query()->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Expenses/Create', [
            'categories' => self::CATEGORIES,
            'paymentMethods' => self::PAYMENT_METHODS,
        ]);
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $data = $this->validatedData($request);

        $data['created_by'] = auth()->id();

        Expense::create($data);

        return redirect()
            ->route('expenses.index')
            ->with(
                'success',
                'Expense recorded successfully.'
            );
    }

    public function show(
        Expense $expense
    ): RedirectResponse {
        return redirect()
            ->route('expenses.index');
    }

    public function edit(
        Expense $expense
    ): Response {
        return Inertia::render('Expenses/Edit', [
            'expense' => [
                'id' => $expense->id,
                'expense_date' =>
                    $expense->expense_date?->format('Y-m-d'),

                'category' => $expense->category,
                'title' => $expense->title,
                'amount' => $expense->amount,
                'payment_method' =>
                    $expense->payment_method,

                'notes' => $expense->notes,
            ],

            'categories' => self::CATEGORIES,
            'paymentMethods' => self::PAYMENT_METHODS,
        ]);
    }

    public function update(
        Request $request,
        Expense $expense
    ): RedirectResponse {
        $expense->update(
            $this->validatedData($request)
        );

        return redirect()
            ->route('expenses.index')
            ->with(
                'success',
                'Expense updated successfully.'
            );
    }

    public function destroy(
        Expense $expense
    ): RedirectResponse {
        $expense->delete();

        return back()->with(
            'success',
            'Expense deleted successfully.'
        );
    }

    private function validatedData(
        Request $request
    ): array {
        return $request->validate([
            'expense_date' => [
                'required',
                'date',
            ],

            'category' => [
                'required',
                'string',
                'max:100',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:9999999999.99',
            ],

            'payment_method' => [
                'required',
                'string',
                'max:100',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);
    }
}
