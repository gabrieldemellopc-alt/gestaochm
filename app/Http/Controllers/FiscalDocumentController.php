<?php

namespace App\Http\Controllers;

use App\Services\FiscalDocuments\FiscalDocumentIndexService;
use Illuminate\Http\Request;

class FiscalDocumentController extends Controller
{
    public function index(Request $request, FiscalDocumentIndexService $service)
    {
        $data = $service->build($request->query());

        return view('fiscal-documents.index', $data);
    }
}
