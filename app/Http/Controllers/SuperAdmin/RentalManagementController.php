<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Rental\RentalMasterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RentalManagementController extends Controller
{
    public function index(Request $request, RentalMasterService $service): Response
    {
        $this->superAdmin($request);
        $service->syncAll();

        $query = DB::table('rental_contracts');

        if ($request->filled('service')) {
            $query->where('service_type', $request->string('service')->toString());
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('q')) {
            $term = trim($request->string('q')->toString());
            $query->where(function ($builder) use ($term): void {
                $builder->where('customer_name', 'like', '%' . $term . '%')
                    ->orWhere('plan_name', 'like', '%' . $term . '%');
            });
        }

        $contracts = $query
            ->orderByRaw("FIELD(status, 'expired', 'grace', 'suspended', 'trial', 'active', 'pending', 'replaced', 'cancelled')")
            ->orderBy('expires_at')
            ->orderBy('customer_name')
            ->get()
            ->map(function (object $contract) use ($service): array {
                $due = (float) DB::table('rental_invoices')
                    ->where('rental_contract_id', $contract->id)
                    ->sum('due_amount');

                return [
                    ...(array) $contract,
                    'total_due' => round($due, 2),
                    'native_url' => $service->nativeUrl($contract),
                    'portal_url' => route('rental.status', $contract->portal_token),
                ];
            });

        return Inertia::render('SuperAdmin/Rentals/Index', [
            'stats' => $this->stats(),
            'contracts' => $contracts,
            'filters' => [
                'service' => $request->input('service'),
                'status' => $request->input('status'),
                'q' => $request->input('q'),
            ],
            'recentNotifications' => DB::table('rental_notifications as n')
                ->join('rental_contracts as c', 'c.id', '=', 'n.rental_contract_id')
                ->orderByDesc('n.id')
                ->limit(20)
                ->get([
                    'n.id', 'n.level', 'n.title', 'n.message', 'n.created_at',
                    'c.customer_name', 'c.service_type',
                ]),
        ]);
    }

    public function show(Request $request, int $contract, RentalMasterService $service): Response
    {
        $this->superAdmin($request);
        $service->syncAll();

        $row = DB::table('rental_contracts')->find($contract);
        abort_unless($row, 404);

        return Inertia::render('SuperAdmin/Rentals/Show', [
            'contract' => [
                ...(array) $row,
                'native_url' => $service->nativeUrl($row),
                'portal_url' => route('rental.status', $row->portal_token),
            ],
            'invoices' => DB::table('rental_invoices')
                ->where('rental_contract_id', $contract)->orderByDesc('id')->get(),
            'payments' => DB::table('rental_payments')
                ->where('rental_contract_id', $contract)->orderByDesc('id')->limit(100)->get(),
            'events' => DB::table('rental_events')
                ->where('rental_contract_id', $contract)->orderByDesc('id')->limit(100)->get(),
            'tickets' => DB::table('rental_tickets')
                ->where('rental_contract_id', $contract)->orderByDesc('id')->get(),
            'notifications' => DB::table('rental_notifications')
                ->where('rental_contract_id', $contract)->orderByDesc('id')->limit(100)->get(),
        ]);
    }

    public function update(Request $request, int $contract, RentalMasterService $service): RedirectResponse
    {
        $this->superAdmin($request);
        $data = $request->validate([
            'agreed_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'billing_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:365'],
            'deployment_status' => ['required', 'string', 'in:not_started,pending_hardware,router_connected,testing,live,problem'],
            'trial_ends_at' => ['nullable', 'date'],
            'retention_until' => ['nullable', 'date'],
            'auto_invoice' => ['required', 'boolean'],
            'auto_notify' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $service->updateContract($contract, $data, $request->user()?->id);
        } catch (Throwable $e) {
            return back()->withErrors(['rental' => $e->getMessage()]);
        }

        return back()->with('success', 'Rental commercial settings updated.');
    }

    public function invoice(Request $request, int $contract, RentalMasterService $service): RedirectResponse
    {
        $this->superAdmin($request);
        $data = $request->validate([
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'discount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $service->createInvoice($contract, $data, $request->user()?->id);
        } catch (Throwable $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('success', 'Rental invoice created.');
    }

    public function payment(Request $request, int $invoice, RentalMasterService $service): RedirectResponse
    {
        $this->superAdmin($request);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $service->recordPayment($invoice, $data, $request->user()?->id);
        } catch (Throwable $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'Rental payment recorded.');
    }

    public function ticket(Request $request, int $contract, RentalMasterService $service): RedirectResponse
    {
        $this->superAdmin($request);
        $data = $request->validate([
            'category' => ['required', 'string', 'in:general,payment,network,router,hotel,compliance,account'],
            'priority' => ['required', 'string', 'in:low,normal,high,urgent'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
        ]);

        try {
            $service->createTicket($contract, $data, $request->user()?->id);
        } catch (Throwable $e) {
            return back()->withErrors(['ticket' => $e->getMessage()]);
        }

        return back()->with('success', 'Support ticket created.');
    }

    public function ticketStatus(Request $request, int $ticket, RentalMasterService $service): RedirectResponse
    {
        $this->superAdmin($request);
        $data = $request->validate([
            'status' => ['required', 'string', 'in:open,in_progress,waiting_customer,resolved,closed'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'resolution' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            $service->updateTicket($ticket, $data, $request->user()?->id);
        } catch (Throwable $e) {
            return back()->withErrors(['ticket_status' => $e->getMessage()]);
        }

        return back()->with('success', 'Ticket updated.');
    }

    public function cancel(Request $request, int $contract, RentalMasterService $service): RedirectResponse
    {
        $this->superAdmin($request);
        $data = $request->validate([
            'retention_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $service->cancelContract(
                $contract,
                $data['retention_until'] ?? null,
                $data['notes'] ?? null,
                $request->user()?->id
            );
        } catch (Throwable $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }

        return back()->with('success', 'Rental contract cancelled without deleting source service data.');
    }

    public function sync(Request $request, RentalMasterService $service): RedirectResponse
    {
        $this->superAdmin($request);
        $result = $service->maintain();
        return back()->with('success', 'Rental Master synchronized: ' . json_encode($result, JSON_UNESCAPED_SLASHES));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->superAdmin($request);

        $rows = DB::table('rental_contracts as c')
            ->leftJoin('rental_invoices as i', 'i.rental_contract_id', '=', 'c.id')
            ->groupBy([
                'c.id', 'c.service_type', 'c.customer_name', 'c.plan_name',
                'c.currency', 'c.agreed_price', 'c.billing_days', 'c.status',
                'c.deployment_status', 'c.starts_at', 'c.expires_at',
            ])
            ->orderBy('c.service_type')->orderBy('c.customer_name')
            ->get([
                'c.id', 'c.service_type', 'c.customer_name', 'c.plan_name', 'c.currency',
                'c.agreed_price', 'c.billing_days', 'c.status', 'c.deployment_status',
                'c.starts_at', 'c.expires_at',
                DB::raw('COALESCE(SUM(i.due_amount), 0) as total_due'),
            ]);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID','Service','Customer','Plan','Currency','Agreed Price','Billing Days','Status','Deployment','Start','Expiry','Due']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id, $row->service_type, $row->customer_name, $row->plan_name,
                    $row->currency, $row->agreed_price, $row->billing_days, $row->status,
                    $row->deployment_status, $row->starts_at, $row->expires_at, $row->total_due,
                ]);
            }
            fclose($out);
        }, 'rental-master-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function printInvoice(Request $request, int $invoice)
    {
        $this->superAdmin($request);

        $row = DB::table('rental_invoices as i')
            ->join('rental_contracts as c', 'c.id', '=', 'i.rental_contract_id')
            ->where('i.id', $invoice)
            ->first([
                'i.*', 'c.customer_name', 'c.customer_email',
                'c.service_type', 'c.plan_name', 'c.currency',
            ]);
        abort_unless($row, 404);

        $payments = DB::table('rental_payments')
            ->where('rental_invoice_id', $invoice)->orderBy('id')->get();

        return view('rentals.invoice', ['invoice' => $row, 'payments' => $payments]);
    }

    private function stats(): array
    {
        return [
            'total' => DB::table('rental_contracts')->count(),
            'active' => DB::table('rental_contracts')->whereIn('status', ['active','trial','grace'])->count(),
            'expired' => DB::table('rental_contracts')->where('status', 'expired')->count(),
            'suspended' => DB::table('rental_contracts')->where('status', 'suspended')->count(),
            'due' => round((float) DB::table('rental_invoices')->sum('due_amount'), 2),
            'collected_month' => round((float) DB::table('rental_payments')
                ->where('payment_date', '>=', now()->startOfMonth()->toDateString())->sum('amount'), 2),
            'mrr' => round((float) (
                DB::table('rental_contracts')
                    ->whereIn('status', ['active','trial','grace'])
                    ->selectRaw('COALESCE(SUM(agreed_price * 30 / GREATEST(billing_days, 1)), 0) as mrr')
                    ->value('mrr')
                ?? 0
            ), 2),
            'open_tickets' => DB::table('rental_tickets')->whereNotIn('status', ['resolved','closed'])->count(),
        ];
    }

    private function superAdmin(Request $request): void
    {
        abort_unless($request->user() && $request->user()->isSuperAdmin(), 403);
    }
}
