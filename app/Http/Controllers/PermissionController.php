<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(): Response
    {
        $roleCounts = User::where('is_active', true)
            ->selectRaw('role, count(*) as cnt')
            ->groupBy('role')
            ->pluck('cnt', 'role')
            ->all();

        return Inertia::render('Master/Permissions/Index', [
            'roleCounts' => $roleCounts,
        ]);
    }
}