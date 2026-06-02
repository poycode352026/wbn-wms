<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use App\Models\User;
use App\Models\WmsNotification;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user'          => $request->user(),
                'permissions'   => $this->loadPermissions($request->user()),
                'notifications' => $this->loadNotifications($request->user()),
                'unread_count'  => $this->countUnread($request->user()),
            ],
            'flash' => [
                'success'   => $request->session()->get('success'),
                'error'     => $request->session()->get('error'),
                'newItemId' => $request->session()->get('newItemId'),
            ],
            'impersonating' => $request->session()->has('impersonating_from'),
        ];
    }

    /**
     * Load DB permissions for the current user's role.
     *
     * super_admin → ['__all' => true]  (frontend checks this as "bypass all")
     * others      → ['moduleName' => ['view'=>bool, 'create'=>bool, 'edit'=>bool, 'delete'=>bool, 'approve'=>int|null]]
     */
    private function loadNotifications(?User $user): array
    {
        if (!$user) return [];

        return WmsNotification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get(['id', 'type', 'title', 'message', 'data', 'is_read', 'created_at'])
            ->map(fn ($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'message'    => $n->message,
                'data'       => $n->data,
                'is_read'    => $n->is_read,
                'created_at' => $n->created_at,
            ])
            ->all();
    }

    private function countUnread(?User $user): int
    {
        if (!$user) return 0;
        return WmsNotification::where('user_id', $user->id)->where('is_read', false)->count();
    }

    private function loadPermissions(?User $user): array
    {
        if (!$user) return [];

        if ($user->role === 'super_admin') {
            return ['__all' => true];
        }

        return RolePermission::where('role', $user->role)
            ->get(['module', 'can_view', 'can_create', 'can_edit', 'can_delete', 'can_approve'])
            ->mapWithKeys(fn($p) => [$p->module => [
                'view'    => (bool) $p->can_view,
                'create'  => (bool) $p->can_create,
                'edit'    => (bool) $p->can_edit,
                'delete'  => (bool) $p->can_delete,
                'approve' => $p->can_approve,
            ]])
            ->all();
    }
}
