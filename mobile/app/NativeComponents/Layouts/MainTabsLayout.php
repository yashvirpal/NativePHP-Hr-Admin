<?php

namespace App\NativeComponents\Layouts;

use App\NativeComponents\Screen;
use Native\Mobile\Edge\Layouts\Builders\NavBar;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

/** Top bar with the screen title plus the five bottom tabs. */
class MainTabsLayout extends NativeLayout
{
    private const TABS = [
        ['Home', '/home', 'home'],
        ['Attendance', '/attendance', 'schedule'],
        ['Leave', '/leave', 'calendar'],
        ['Overtime', '/overtime', 'bolt'],
        ['More', '/more', 'menu'],
    ];

    /** Draw the bars as native chrome (SwiftUI / Compose), not as part of the screen content. */
    public function usesNativeChrome(): bool
    {
        return true;
    }

    public function navBar(NativeComponent $screen): ?NavBar
    {
        return NavBar::make()->title($screen->navTitle());
    }

    public function tabBar(NativeComponent $screen): ?TabBar
    {
        $current = $screen instanceof Screen ? $screen->activeTab() : null;
        $bar = TabBar::make()->labelVisibility('labeled');

        foreach (self::TABS as [$label, $url, $icon]) {
            $bar->add(Tab::link($label, $url, icon: $icon)->active($url === $current));
        }

        return $bar;
    }
}
