<?php

namespace App\Http\Controllers;

use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use Illuminate\Http\Request;
class ChecklistController extends Controller
{
    public function index()
    {
        $templates =
            ChecklistTemplate::with('items')
            ->where(
                'division_id',
                session('active_division_id')
            )
            ->latest()
            ->get();

        return view(
            'checklists.index',
            compact('templates')
        );
    }

    public function toggleItem(Request $request)
    {
        $item =
            ChecklistTemplateItem::findOrFail(
                $request->item_id
            );
    
        $item->update([
    
            'is_active' =>
                (bool) $request->active
    
        ]);
    
        return response()->json([
    
            'success' => true
    
        ]);
    }
}