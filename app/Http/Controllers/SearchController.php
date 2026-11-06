<?php

namespace App\Http\Controllers;

use App\Services\Search\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:200', 'workspace_id' => 'nullable|uuid', 'type' => 'nullable|in:all,tasks,projects']);

        return view('search.index', $search->search($request->user(), $filters));
    }
}
