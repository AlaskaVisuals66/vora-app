<?php

namespace App\Http\Middleware;

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
        return array_merge(parent::share($request), [
            'auth' => fn () => [
                'user' => $request->user()
                    ? $request->user()->only(['id','name','email','avatar_path','status','tenant_id'])
                      + ['roles' => $request->user()->getRoleNames()]
                    : null,
            ],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
            'config' => fn () => [
                'pusher' => [
                    'key'     => config('broadcasting.connections.pusher.key'),
                    'host'    => env('VITE_PUSHER_HOST', 'localhost'),
                    'port'    => (int) env('VITE_PUSHER_PORT', 6001),
                    'scheme'  => env('VITE_PUSHER_SCHEME', 'http'),
                    'cluster' => env('VITE_PUSHER_APP_CLUSTER', 'mt1'),
                ],
            ],
        ]);
    }
}
