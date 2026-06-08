<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Services\MaintenanceService;

class MaintenanceController extends Controller
{
    public function store(Request $request)
    {
        $result =
            MaintenanceService::create(
                $request->all()
            );
        
        return response()->json([
        
            'success' => true,
        
            'maintenance' =>
                $result['maintenance'],
        
            'updated_stock_item' =>
                $result['updated_stock_item']
        ]);
    }
}