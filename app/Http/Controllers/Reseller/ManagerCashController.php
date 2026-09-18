<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ManagerCashHandover;
use App\Models\ManagerCashLedgerEntry;
use App\Models\User;
use App\Services\ManagerCashLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ManagerCashController extends Controller
{
    public function index(
        Request $request,
        ManagerCashLedgerService $ledger
    ): Response {
        $actor =
            $this->actor(
                $request
            );

        $managerQuery =
            User::query()
                ->where(
                    'reseller_id',
                    $actor->reseller_id
                )
                ->where(
                    'role',
                    'operator'
                )
                ->where(
                    'staff_role',
                    'manager'
                )
                ->orderBy('name')
                ->orderBy('id');

        if ($actor->isManager()) {
            $managerQuery->whereKey(
                $actor->id
            );
        }

        $managers =
            $managerQuery->get([
                'id',
                'name',
                'email',
                'is_active',
            ]);

        $managerIds =
            $managers
                ->pluck('id')
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->all();

        $today =
            now(
                'Asia/Qatar'
            )->toDateString();

        $monthStart =
            now(
                'Asia/Qatar'
            )
                ->startOfMonth()
                ->toDateString();

        $monthEnd =
            now(
                'Asia/Qatar'
            )
                ->endOfMonth()
                ->toDateString();

        $managerRows =
            $managers->map(
                function (
                    User $manager
                ) use (
                    $ledger,
                    $today,
                    $monthStart,
                    $monthEnd
                ): array {
                    $base =
                        ManagerCashLedgerEntry::query()
                            ->where(
                                'reseller_id',
                                $manager->reseller_id
                            )
                            ->where(
                                'manager_id',
                                $manager->id
                            );

                    $todayCollection =
                        (float)
                        (clone $base)
                            ->where(
                                'entry_date',
                                $today
                            )
                            ->where(
                                'entry_type',
                                'collection'
                            )
                            ->where(
                                'direction',
                                'credit'
                            )
                            ->sum('amount');

                    $todayExpense =
                        (float)
                        (clone $base)
                            ->where(
                                'entry_date',
                                $today
                            )
                            ->where(
                                'entry_type',
                                'expense'
                            )
                            ->where(
                                'direction',
                                'debit'
                            )
                            ->sum('amount');

                    $todayHandover =
                        (float)
                        (clone $base)
                            ->where(
                                'entry_date',
                                $today
                            )
                            ->where(
                                'entry_type',
                                'handover'
                            )
                            ->where(
                                'direction',
                                'debit'
                            )
                            ->sum('amount');

                    $openingCredit =
                        (float)
                        (clone $base)
                            ->where(
                                'entry_date',
                                '<',
                                $monthStart
                            )
                            ->where(
                                'direction',
                                'credit'
                            )
                            ->sum('amount');

                    $openingDebit =
                        (float)
                        (clone $base)
                            ->where(
                                'entry_date',
                                '<',
                                $monthStart
                            )
                            ->where(
                                'direction',
                                'debit'
                            )
                            ->sum('amount');

                    $monthCredit =
                        (float)
                        (clone $base)
                            ->whereBetween(
                                'entry_date',
                                [
                                    $monthStart,
                                    $monthEnd,
                                ]
                            )
                            ->where(
                                'direction',
                                'credit'
                            )
                            ->sum('amount');

                    $monthDebit =
                        (float)
                        (clone $base)
                            ->whereBetween(
                                'entry_date',
                                [
                                    $monthStart,
                                    $monthEnd,
                                ]
                            )
                            ->where(
                                'direction',
                                'debit'
                            )
                            ->sum('amount');

                    $monthCollection =
                        (float)
                        (clone $base)
                            ->whereBetween(
                                'entry_date',
                                [
                                    $monthStart,
                                    $monthEnd,
                                ]
                            )
                            ->where(
                                'entry_type',
                                'collection'
                            )
                            ->where(
                                'direction',
                                'credit'
                            )
                            ->sum('amount');

                    $monthExpense =
                        (float)
                        (clone $base)
                            ->whereBetween(
                                'entry_date',
                                [
                                    $monthStart,
                                    $monthEnd,
                                ]
                            )
                            ->where(
                                'entry_type',
                                'expense'
                            )
                            ->where(
                                'direction',
                                'debit'
                            )
                            ->sum('amount');

                    $monthHandover =
                        (float)
                        (clone $base)
                            ->whereBetween(
                                'entry_date',
                                [
                                    $monthStart,
                                    $monthEnd,
                                ]
                            )
                            ->where(
                                'entry_type',
                                'handover'
                            )
                            ->where(
                                'direction',
                                'debit'
                            )
                            ->sum('amount');

                    $pendingHandover =
                        (float)
                        ManagerCashHandover::query()
                            ->where(
                                'reseller_id',
                                $manager->reseller_id
                            )
                            ->where(
                                'manager_id',
                                $manager->id
                            )
                            ->where(
                                'status',
                                'pending'
                            )
                            ->sum('amount');

                    $opening =
                        round(
                            $openingCredit
                            - $openingDebit,
                            2
                        );

                    $closing =
                        round(
                            $opening
                            + $monthCredit
                            - $monthDebit,
                            2
                        );

                    $balance =
                        $ledger->balance(
                            $manager
                        );

                    return [
                        'id' =>
                            $manager->id,

                        'name' =>
                            $manager->name,

                        'email' =>
                            $manager->email,

                        'is_active' =>
                            (bool)
                            $manager->is_active,

                        'cash_balance' =>
                            $balance,

                        'pending_handover' =>
                            round(
                                $pendingHandover,
                                2
                            ),

                        'available_to_handover' =>
                            round(
                                max(
                                    0,
                                    $balance
                                    - $pendingHandover
                                ),
                                2
                            ),

                        'today_collection' =>
                            round(
                                $todayCollection,
                                2
                            ),

                        'today_expense' =>
                            round(
                                $todayExpense,
                                2
                            ),

                        'today_handover' =>
                            round(
                                $todayHandover,
                                2
                            ),

                        'month_opening' =>
                            $opening,

                        'month_collection' =>
                            round(
                                $monthCollection,
                                2
                            ),

                        'month_expense' =>
                            round(
                                $monthExpense,
                                2
                            ),

                        'month_handover' =>
                            round(
                                $monthHandover,
                                2
                            ),

                        'month_credit' =>
                            round(
                                $monthCredit,
                                2
                            ),

                        'month_debit' =>
                            round(
                                $monthDebit,
                                2
                            ),

                        'month_closing' =>
                            $closing,

                        'reconciliation_ok' =>
                            abs(
                                $closing
                                - $balance
                            ) < 0.01,
                    ];
                }
            )
                ->values();

        $ledgerEntries =
            ManagerCashLedgerEntry::query()
                ->where(
                    'reseller_id',
                    $actor->reseller_id
                )
                ->when(
                    $actor->isManager(),
                    fn ($query) =>
                        $query->where(
                            'manager_id',
                            $actor->id
                        )
                )
                ->with([
                    'manager:id,name',
                    'zone:id,name,service_type',
                    'creator:id,name',
                ])
                ->latest(
                    'entry_date'
                )
                ->latest('id')
                ->limit(150)
                ->get()
                ->map(
                    fn (
                        ManagerCashLedgerEntry $entry
                    ): array => [
                        'id' =>
                            $entry->id,

                        'entry_date' =>
                            $entry
                                ->entry_date
                                ?->format(
                                    'Y-m-d'
                                ),

                        'manager' =>
                            $entry->manager
                                ? [
                                    'id' =>
                                        $entry
                                            ->manager
                                            ->id,

                                    'name' =>
                                        $entry
                                            ->manager
                                            ->name,
                                ]
                                : null,

                        'zone' =>
                            $entry->zone
                                ? [
                                    'id' =>
                                        $entry
                                            ->zone
                                            ->id,

                                    'name' =>
                                        $entry
                                            ->zone
                                            ->name,

                                    'service_type' =>
                                        $entry
                                            ->zone
                                            ->service_type,
                                ]
                                : null,

                        'direction' =>
                            $entry->direction,

                        'entry_type' =>
                            $entry->entry_type,

                        'amount' =>
                            (float)
                            $entry->amount,

                        'reference' =>
                            $entry->reference,

                        'notes' =>
                            $entry->notes,
                    ]
                );

        $handovers =
            ManagerCashHandover::query()
                ->where(
                    'reseller_id',
                    $actor->reseller_id
                )
                ->when(
                    $actor->isManager(),
                    fn ($query) =>
                        $query->where(
                            'manager_id',
                            $actor->id
                        )
                )
                ->with([
                    'manager:id,name',
                    'submitter:id,name',
                    'reviewer:id,name',
                ])
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(
                    fn (
                        ManagerCashHandover $handover
                    ): array => [
                        'id' =>
                            $handover->id,

                        'manager' =>
                            $handover->manager
                                ? [
                                    'id' =>
                                        $handover
                                            ->manager
                                            ->id,

                                    'name' =>
                                        $handover
                                            ->manager
                                            ->name,
                                ]
                                : null,

                        'amount' =>
                            (float)
                            $handover->amount,

                        'handover_date' =>
                            $handover
                                ->handover_date
                                ?->format(
                                    'Y-m-d'
                                ),

                        'status' =>
                            $handover->status,

                        'notes' =>
                            $handover->notes,

                        'review_notes' =>
                            $handover
                                ->review_notes,

                        'reviewed_at' =>
                            $handover
                                ->reviewed_at
                                ?->format(
                                    'Y-m-d H:i:s'
                                ),

                        'reviewer' =>
                            $handover->reviewer
                                ? [
                                    'id' =>
                                        $handover
                                            ->reviewer
                                            ->id,

                                    'name' =>
                                        $handover
                                            ->reviewer
                                            ->name,
                                ]
                                : null,
                    ]
                );

        $managerExpenses =
            Expense::withoutGlobalScopes()
                ->where(
                    'reseller_id',
                    $actor->reseller_id
                )
                ->whereIn(
                    'created_by',
                    $managerIds ?: [0]
                )
                ->with([
                    'user:id,name',
                ])
                ->latest(
                    'expense_date'
                )
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(
                    fn (
                        Expense $expense
                    ): array => [
                        'id' =>
                            $expense->id,

                        'expense_date' =>
                            $expense
                                ->expense_date
                                ?->format(
                                    'Y-m-d'
                                ),

                        'manager' =>
                            $expense->user
                                ? [
                                    'id' =>
                                        $expense
                                            ->user
                                            ->id,

                                    'name' =>
                                        $expense
                                            ->user
                                            ->name,
                                ]
                                : null,

                        'zone_id' =>
                            $expense->zone_id,

                        'category' =>
                            $expense->category,

                        'title' =>
                            $expense->title,

                        'amount' =>
                            (float)
                            $expense->amount,

                        'payment_method' =>
                            $expense
                                ->payment_method,

                        'approval_status' =>
                            $expense
                                ->approval_status,

                        'rejection_reason' =>
                            $expense
                                ->rejection_reason,

                        'notes' =>
                            $expense->notes,
                    ]
                );

        return Inertia::render(
            'Reseller/ManagerCash/Index',
            [
                'mode' =>
                    $actor->isManager()
                        ? 'manager'
                        : 'owner',

                'today' =>
                    $today,

                'month' =>
                    now(
                        'Asia/Qatar'
                    )->format(
                        'Y-m'
                    ),

                'managers' =>
                    $managerRows,

                'ledgerEntries' =>
                    $ledgerEntries,

                'handovers' =>
                    $handovers,

                'managerExpenses' =>
                    $managerExpenses,
            ]
        );
    }

    public function storeHandover(
        Request $request,
        ManagerCashLedgerService $ledger
    ): RedirectResponse {
        $manager =
            $this->managerActor(
                $request
            );

        $data =
            $request->validate([
                'amount' => [
                    'required',
                    'numeric',
                    'min:0.01',
                    'max:999999999.99',
                ],

                'handover_date' => [
                    'required',
                    'date',
                    'before_or_equal:today',
                ],

                'notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        $balance =
            $ledger->balance(
                $manager
            );

        $pending =
            (float)
            ManagerCashHandover::query()
                ->where(
                    'reseller_id',
                    $manager->reseller_id
                )
                ->where(
                    'manager_id',
                    $manager->id
                )
                ->where(
                    'status',
                    'pending'
                )
                ->sum('amount');

        $available =
            round(
                $balance - $pending,
                2
            );

        if (
            (float)
            $data['amount']
            > $available + 0.0001
        ) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Handover amount exceeds available Manager cash after pending handovers.',
            ]);
        }

        ManagerCashHandover::query()
            ->create([
                'reseller_id' =>
                    $manager->reseller_id,

                'manager_id' =>
                    $manager->id,

                'amount' =>
                    round(
                        (float)
                        $data['amount'],
                        2
                    ),

                'handover_date' =>
                    $data[
                        'handover_date'
                    ],

                'status' =>
                    'pending',

                'notes' =>
                    $data['notes']
                    ?? null,

                'submitted_by' =>
                    $manager->id,
            ]);

        return back()->with(
            'success',
            'Cash handover submitted for Reseller Admin approval.'
        );
    }

    public function approveHandover(
        Request $request,
        ManagerCashHandover $handover,
        ManagerCashLedgerService $ledger
    ): RedirectResponse {
        $owner =
            $this->ownerActor(
                $request
            );

        DB::transaction(
            function () use (
                $handover,
                $owner,
                $ledger
            ): void {
                $locked =
                    ManagerCashHandover::query()
                        ->whereKey(
                            $handover->id
                        )
                        ->where(
                            'reseller_id',
                            $owner->reseller_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $locked->status
                    !== 'pending'
                ) {
                    throw ValidationException::withMessages([
                        'handover' =>
                            'This cash handover has already been reviewed.',
                    ]);
                }

                /*
                 * Common Manager row lock serializes
                 * simultaneous approvals for the same
                 * physical cash balance.
                 */
                $manager =
                    User::query()
                        ->whereKey(
                            $locked
                                ->manager_id
                        )
                        ->where(
                            'reseller_id',
                            $owner->reseller_id
                        )
                        ->where(
                            'role',
                            'operator'
                        )
                        ->where(
                            'staff_role',
                            'manager'
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $balance =
                    $ledger->balance(
                        $manager
                    );

                if (
                    (float)
                    $locked->amount
                    > $balance + 0.0001
                ) {
                    throw ValidationException::withMessages([
                        'handover' =>
                            'Manager cash balance is lower than this handover amount. Approval stopped.',
                    ]);
                }

                $locked->forceFill([
                    'status' =>
                        'approved',

                    'reviewed_by' =>
                        $owner->id,

                    'reviewed_at' =>
                        now(
                            'Asia/Qatar'
                        ),
                ])->save();

                $ledger
                    ->recordApprovedHandover(
                        $locked,
                        $owner
                    );
            }
        );

        return back()->with(
            'success',
            'Cash handover approved and Manager cash balance updated.'
        );
    }

    public function rejectHandover(
        Request $request,
        ManagerCashHandover $handover
    ): RedirectResponse {
        $owner =
            $this->ownerActor(
                $request
            );

        $data =
            $request->validate([
                'reason' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        DB::transaction(
            function () use (
                $handover,
                $owner,
                $data
            ): void {
                $locked =
                    ManagerCashHandover::query()
                        ->whereKey(
                            $handover->id
                        )
                        ->where(
                            'reseller_id',
                            $owner->reseller_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $locked->status
                    !== 'pending'
                ) {
                    throw ValidationException::withMessages([
                        'handover' =>
                            'This cash handover has already been reviewed.',
                    ]);
                }

                $locked->forceFill([
                    'status' =>
                        'rejected',

                    'reviewed_by' =>
                        $owner->id,

                    'reviewed_at' =>
                        now(
                            'Asia/Qatar'
                        ),

                    'review_notes' =>
                        $data['reason']
                        ?? null,
                ])->save();
            }
        );

        return back()->with(
            'success',
            'Cash handover rejected. Manager cash balance was not reduced.'
        );
    }

    public function rejectExpense(
        Request $request,
        Expense $expense,
        ManagerCashLedgerService $ledger
    ): RedirectResponse {
        $owner =
            $this->ownerActor(
                $request
            );

        $data =
            $request->validate([
                'reason' => [
                    'required',
                    'string',
                    'max:2000',
                ],
            ]);

        DB::transaction(
            function () use (
                $expense,
                $owner,
                $data,
                $ledger
            ): void {
                $locked =
                    Expense::withoutGlobalScopes()
                        ->whereKey(
                            $expense->id
                        )
                        ->where(
                            'reseller_id',
                            $owner->reseller_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $locked->approval_status
                    === 'rejected'
                ) {
                    throw ValidationException::withMessages([
                        'expense' =>
                            'This expense has already been rejected.',
                    ]);
                }

                $manager =
                    User::query()
                        ->whereKey(
                            $locked->created_by
                        )
                        ->where(
                            'reseller_id',
                            $owner->reseller_id
                        )
                        ->where(
                            'role',
                            'operator'
                        )
                        ->where(
                            'staff_role',
                            'manager'
                        )
                        ->first();

                if (!$manager) {
                    throw ValidationException::withMessages([
                        'expense' =>
                            'Only Manager-submitted expenses can be rejected from Manager Cash.',
                    ]);
                }

                $locked->forceFill([
                    'approval_status' =>
                        'rejected',

                    'reviewed_by' =>
                        $owner->id,

                    'reviewed_at' =>
                        now(
                            'Asia/Qatar'
                        ),

                    'rejection_reason' =>
                        $data['reason'],
                ])->save();

                /*
                 * Immutable credit reversal restores
                 * cash. Original expense and original
                 * debit history remain intact.
                 */
                $ledger->reverseExpense(
                    $locked,
                    'Expense rejected by Reseller Admin: '
                    . $data['reason']
                );
            }
        );

        return back()->with(
            'success',
            'Expense rejected and the amount was restored to Manager cash.'
        );
    }

    private function actor(
        Request $request
    ): User {
        $user =
            $request->user();

        abort_unless(
            $user
                && $user->reseller_id
                && (
                    $user
                        ->isResellerOwner()
                    || $user
                        ->isManager()
                ),
            403
        );

        return $user;
    }

    private function managerActor(
        Request $request
    ): User {
        $user =
            $this->actor(
                $request
            );

        abort_unless(
            $user->isManager(),
            403,
            'Only a Manager can submit a cash handover.'
        );

        return $user;
    }

    private function ownerActor(
        Request $request
    ): User {
        $user =
            $this->actor(
                $request
            );

        abort_unless(
            $user->isResellerOwner(),
            403,
            'Only the Reseller Admin can review Manager cash transactions.'
        );

        return $user;
    }
}
