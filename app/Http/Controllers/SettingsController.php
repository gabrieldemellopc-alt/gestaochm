<?php

namespace App\Http\Controllers;

use App\Services\TenantFiscalSettingService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request, TenantFiscalSettingService $fiscalSettings)
    {
        abort_unless($request->user() && (userHasProfile('admin') || userHasProfile('manager')), 403);
        $allowedTabs = ['general', 'fiscal-documents', 'permissions', 'system'];
        $tab = $request->query('tab', 'general');
        abort_unless(in_array($tab, $allowedTabs, true), 404);
        $fiscalRequirements = $fiscalSettings->requirements();
        return view('settings.index', compact('tab', 'fiscalRequirements'));
    }

    public function updateFiscalDocuments(Request $request, TenantFiscalSettingService $fiscalSettings)
    {
        abort_unless($request->user() && (userHasProfile('admin') || userHasProfile('manager')), 403);
        $validated = $request->validate([
            'stock_entry' => ['nullable', 'boolean'],
            'external_fuel_filling' => ['nullable', 'boolean'],
            'fuel_receipt' => ['nullable', 'boolean'],
            'maintenance_external_service' => ['nullable', 'boolean'],
        ]);
        $fiscalSettings->updateRequirements($validated);
        return redirect()->route('settings.index', ['tab' => 'fiscal-documents'])->with('success', 'Configurações fiscais salvas.');
    }
}
