<?php

namespace App\Http\Controllers;

use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use App\Services\StockService;

class StockController extends Controller
{
    public function index()
    {
        $categories =
            StockCategory::with('items')
            ->orderBy('name')
            ->get();
    
        /*
        |--------------------------------------------------------------------------
        | STATUS OPERACIONAL
        |--------------------------------------------------------------------------
        */
    
        foreach($categories as $category)
        {
            foreach($category->items as $item)
            {
                $item->stock_status =
                    \App\Services\StockService::getStatus($item);
            }
        }
    
        return view(
    
            'stock.index',
    
            compact('categories')
    
        );
    }
    public function showItem(
        StockItem $item
    ) {
    
        $item->load([
    
            'category',
    
            'movements' => function ($query) {
    
                $query
                    ->latest()
                    ->limit(3);
    
            }
    
        ]);
    
        return response()->json($item);
    }
    public function storeCategory(Request $request)
    {
        StockCategory::create([
    
            'tenant_id' =>
                auth()->user()->tenant_id,
    
            'name' => $request->name
    
        ]);
    
        return redirect()->back();
    }
    
    public function storeItem(Request $request)
    {
        $item = StockItem::create([
    
            'tenant_id' =>
                auth()->user()->tenant_id,
    
            'stock_category_id' =>
                $request->stock_category_id,
    
            'name' => $request->name,
    
            'unit' => $request->unit,
    
            'quantity' =>
                $request->quantity ?? 0,
    
            'minimum_quantity' =>
                $request->minimum_quantity ?? 0,
    
            'unit_cost' =>
                $request->unit_cost ?? 0,
    
            'observation' =>
                $request->observation,
    
            'active' => true,
    
        ]);
        if ($item->quantity > 0) {

        StockMovement::create([
    
            'tenant_id' =>
                auth()->user()->tenant_id,
    
            'stock_item_id' =>
                $item->id,
    
            'movement_type' => 'in',
    
            'quantity' =>
                $item->quantity,
    
            'unit_cost' =>
                $item->unit_cost,
    
            'description' =>
                'Estoque inicial',
    
        ]);
    }
    
        return redirect()->back();
    }
    
    public function updateItem(
        Request $request,
        StockItem $item
    ){
        $item->update([
    
            'name' =>
                $request->name,
    
            'brand' =>
                $request->brand,
    
            'unit' =>
                $request->unit,
    
            'minimum_quantity' =>
                $request->minimum_quantity,
    
            'unit_cost' =>
                $request->unit_cost,
    
            'observation' =>
                $request->observation,
    
        ]);
    
        return redirect()->back();
    }

    public function storeMovement(
        Request $request
    ){
        $item = StockItem::findOrFail(
            $request->stock_item_id
        );
        if(
        
            $request->movement_type === 'out' &&
        
            $request->quantity > $item->quantity
        
        ){
            return back()->with(
        
                'error',
        
                'Quantidade indisponível em estoque.'
        
            );
        }
        StockMovement::create([
    
            'tenant_id' =>
                auth()->user()->tenant_id,
    
            'stock_item_id' =>
                $item->id,
    
            'movement_type' =>
                $request->movement_type,
    
            'quantity' =>
                $request->quantity,
    
            'unit_cost' =>
                $request->unit_cost ?? 0,
    
            'description' =>
                $request->description,
    
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | Atualiza estoque
        |--------------------------------------------------------------------------
        */
    
        if($request->movement_type === 'in')
        {
            $item->quantity +=
                $request->quantity;
        }
        else
        {
            $item->quantity -=
                $request->quantity;
        }
    
        $item->save();
    
        return redirect()->back();
    }
    
}