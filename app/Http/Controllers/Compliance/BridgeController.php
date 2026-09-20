<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\Organization;
use App\Models\Compliance\User as ComplianceUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BridgeController extends Controller
{
    public function reseller(Request $request): RedirectResponse
    {
        $panelUser = $request->user();
        if (!$panelUser || !$panelUser->reseller_id) {
            return redirect()->route('login');
        }

        abort_unless(
            method_exists($panelUser, 'isResellerOwner')
            && $panelUser->isResellerOwner(),
            403,
            'Only the Company owner may open Internet Compliance.'
        );

        $grant = DB::table('compliance_access_grants')
            ->where('source_type', 'reseller')
            ->where('source_id', $panelUser->reseller_id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (!$grant) {
            return redirect()->route('reseller.dashboard')
                ->with('error', 'Internet Compliance is not activated for this Company.');
        }

        return $this->enter(
            $request,
            (int) $grant->organization_id,
            'panel_user',
            (int) $panelUser->id,
            $panelUser->name,
            (bool) $grant->logging_enabled,
            (bool) $grant->filtering_enabled,
            'reseller'
        );
    }

    public function hotel(Request $request): RedirectResponse
    {
        $hotelUser = Auth::guard('hotel')->user();
        if (!$hotelUser) {
            return redirect()->route('hotel.login');
        }

        abort_unless(
            method_exists($hotelUser, 'isAdmin') && $hotelUser->isAdmin(),
            403,
            'Only the Hotel Admin may open Internet Compliance.'
        );

        $grant = DB::table('compliance_access_grants')
            ->where('source_type', 'hotel')
            ->where('source_id', $hotelUser->hotel_id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (!$grant) {
            return redirect()->route('hotel.dashboard')
                ->with('error', 'Internet Compliance is not activated for this Hotel.');
        }

        return $this->enter(
            $request,
            (int) $grant->organization_id,
            'hotel_user',
            (int) $hotelUser->id,
            $hotelUser->name,
            (bool) $grant->logging_enabled,
            (bool) $grant->filtering_enabled,
            'hotel'
        );
    }

    private function enter(
        Request $request,
        int $organizationId,
        string $sourceType,
        int $sourceId,
        string $name,
        bool $logging,
        bool $filtering,
        string $bridgeSource
    ): RedirectResponse {
        $organization = Organization::query()
            ->whereKey($organizationId)
            ->where('status', 'active')
            ->firstOrFail();

        $complianceUser = ComplianceUser::query()
            ->where('organization_id', $organization->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if (!$complianceUser) {
            $complianceUser = ComplianceUser::query()->create([
                'organization_id' => $organization->id,
                'name' => $name,
                'email' => $sourceType . '-' . $sourceId . '-' . $organization->id
                    . '@bridge.mikropanel.invalid',
                'password' => Hash::make(Str::random(96)),
                'role' => 'owner',
                'is_active' => true,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'panel_user_id' => $sourceType === 'panel_user' ? $sourceId : null,
            ]);
        } else {
            $complianceUser->forceFill([
                'name' => $name,
                'role' => 'owner',
                'is_active' => true,
                'panel_user_id' => $sourceType === 'panel_user' ? $sourceId : null,
            ])->save();
        }

        $request->session()->regenerate();
        $request->session()->put([
            'compliance_user_id' => $complianceUser->id,
            'compliance_organization_id' => $organization->id,
            'compliance_service_mask' => [
                'logging' => $logging,
                'filtering' => $filtering,
            ],
            'compliance_bridge_source' => $bridgeSource,
        ]);

        DB::table('compliance_audit_logs')->insert([
            'organization_id' => $organization->id,
            'compliance_user_id' => $complianceUser->id,
            'panel_user_id' => $sourceType === 'panel_user' ? $sourceId : null,
            'action' => 'bridge.enter',
            'subject_type' => $sourceType,
            'subject_id' => (string) $sourceId,
            'ip_address' => $request->ip(),
            'metadata' => json_encode([
                'source' => $bridgeSource,
                'logging' => $logging,
                'filtering' => $filtering,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('compliance.dashboard');
    }
}
