<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $users = User::query()
            ->whereNotNull('name')
            ->when($query !== '', fn ($builder) => $builder->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json($users);
    }
}
