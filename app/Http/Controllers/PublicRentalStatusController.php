<?php

namespace App\Http\Controllers;

use App\Services\Rental\RentalMasterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PublicRentalStatusController extends Controller
{
    public function show(string $token): Response
    {
        $contract = $this->contract($token);

        return Inertia::render('Public/RentalStatus', [
            'contract' => [
                'service_type' => $contract->service_type,
                'customer_name' => $contract->customer_name,
                'currency' => $contract->currency,
                'plan_name' => $contract->plan_name,
                'agreed_price' => $contract->agreed_price,
                'billing_days' => $contract->billing_days,
                'starts_at' => $contract->starts_at,
                'expires_at' => $contract->expires_at,
                'grace_until' => $contract->grace_until,
                'status' => $contract->status,
                'deployment_status' => $contract->deployment_status,
                'trial_ends_at' => $contract->trial_ends_at,
            ],
            'invoices' => DB::table('rental_invoices')
                ->where('rental_contract_id', $contract->id)
                ->orderByDesc('id')->limit(12)
                ->get([
                    'id','invoice_no','period_start','period_end','amount','discount',
                    'paid_amount','due_amount','status','issue_date','due_date',
                ]),
            'payments' => DB::table('rental_payments')
                ->where('rental_contract_id', $contract->id)
                ->orderByDesc('id')->limit(12)
                ->get(['amount','payment_date','payment_method','created_at']),
            'notifications' => DB::table('rental_notifications')
                ->where('rental_contract_id', $contract->id)
                ->orderByDesc('id')->limit(10)
                ->get(['level','title','message','created_at']),
            'tickets' => DB::table('rental_tickets')
                ->where('rental_contract_id', $contract->id)
                ->orderByDesc('id')->limit(20)
                ->get(['id','category','priority','subject','status','resolution','created_at','closed_at']),
            'token' => $token,
        ]);
    }

    public function ticket(Request $request, string $token, RentalMasterService $service): RedirectResponse
    {
        $contract = $this->contract($token);
        $data = $request->validate([
            'category' => ['required','string','in:general,payment,network,router,hotel,compliance,account'],
            'priority' => ['required','string','in:low,normal,high,urgent'],
            'subject' => ['required','string','max:255'],
            'message' => ['required','string','max:10000'],
        ]);

        try {
            $service->createTicket((int) $contract->id, $data, null);
        } catch (Throwable $e) {
            return back()->withErrors(['ticket' => $e->getMessage()]);
        }

        return back()->with('success', 'Support ticket submitted.');
    }

    private function contract(string $token): object
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token) === 1, 404);
        $contract = DB::table('rental_contracts')->where('portal_token', $token)->first();
        abort_unless($contract, 404);
        return $contract;
    }
}
