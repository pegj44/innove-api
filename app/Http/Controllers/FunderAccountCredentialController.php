<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FunderAccountCredentialController extends Controller
{
    public function store(Request $request)
    {
        return response()->json([
            'test' => $request->all()
        ]);
    }
}
