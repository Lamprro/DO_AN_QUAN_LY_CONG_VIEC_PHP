<?php

namespace App\View\Composers;

use App\Services\Notification\NotificationService;
use App\Services\Setting\SettingService;
use App\View\AppShell;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Data every page of the main layout needs: navigation, preferences, unread count. */
class LayoutComposer
{
    public function __construct(
        private Request $request,
        private AppShell $shell,
        private SettingService $settings,
        private NotificationService $notifications,
    ) {}

    public function compose(View $view): void
    {
        $user = $this->request->user();
        $view->with('shell', $this->shell->build($this->request));
        $view->with('preferences', $user ? $this->settings->values($user) : config('work_management.settings_defaults'));
        $view->with('unreadNotifications', $user ? $this->notifications->visible($user)->whereNull('read_at')->count() : 0);
    }
}
