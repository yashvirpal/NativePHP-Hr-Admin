<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') - {{ request()->is('admin', 'admin/*') ? 'HR Admin' : 'My HR' }}</title>
    <style>
        :root {
            --bg:#f4f6fa; --card:#ffffff; --ink:#18202f; --muted:#667085; --line:#e4e7ec; --hover:#f2f4f7;
            --brand:#4f46e5; --brand-ink:#ffffff; --brand-soft:#eef2ff;
            --ok:#067647; --ok-soft:#ecfdf3; --warn:#b54708; --warn-soft:#fffaeb;
            --bad:#b42318; --bad-soft:#fef3f2; --neutral:#475467; --neutral-soft:#f2f4f7;
            --side:#111827; --side-ink:#d0d5dd; --side-active:#1f2937;
            --ok-ink:#ffffff; --bad-ink:#ffffff; /* text on solid green / red buttons */
            --radius:10px; --shadow:0 1px 2px rgba(16,24,40,.06);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg:#0c111b; --card:#141a26; --ink:#e7eaf0; --muted:#98a2b3; --line:#232b3a; --hover:#1a2130;
                --brand:#818cf8; --brand-ink:#0c111b; --brand-soft:#1e2140;
                --ok:#6ce9a6; --ok-soft:#10261b; --warn:#fec84b; --warn-soft:#2a2110;
                --bad:#fda29b; --bad-soft:#2b1513; --neutral:#cbd5e1; --neutral-soft:#1c2331;
                --side:#0a0e16; --side-ink:#98a2b3; --side-active:#161c29; --shadow:none;
                --ok-ink:#0c111b; --bad-ink:#0c111b; /* the dark-theme greens/reds are pastel: dark text, not white */
            }
        }
        * { box-sizing:border-box; }
        html { color-scheme: light dark; }
        body { margin:0; font:14.5px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; background:var(--bg); color:var(--ink); }
        a { color:var(--brand); text-decoration:none; } a:hover { text-decoration:underline; }

        .shell { display:grid; grid-template-columns:240px 1fr; min-height:100vh; }
        aside { background:var(--side); color:var(--side-ink); padding:20px 12px; position:sticky; top:0; height:100vh; display:flex; flex-direction:column; }
        .logo { color:#fff; font-weight:700; font-size:16px; padding:4px 10px 18px; display:flex; gap:10px; align-items:center; }
        .logo i { width:30px; height:30px; border-radius:8px; background:var(--brand); color:var(--brand-ink); display:grid; place-items:center; font-style:normal; font-size:14px; }
        .nav-label { font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#667085; padding:14px 10px 6px; }
        aside nav a { display:flex; align-items:center; gap:10px; padding:9px 10px; border-radius:8px; color:var(--side-ink); }
        aside nav a:hover { background:var(--side-active); color:#fff; text-decoration:none; }
        aside nav a.active { background:var(--side-active); color:#fff; box-shadow:inset 3px 0 0 var(--brand); }
        aside nav a svg { width:18px; height:18px; flex:none; stroke:currentColor; fill:none; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
        aside nav a .count { margin-left:auto; background:var(--brand); color:var(--brand-ink); border-radius:999px; font-size:11px; font-weight:700; padding:1px 7px; }
        .me { margin-top:auto; padding:12px 10px 0; border-top:1px solid #1f2937; display:flex; gap:10px; align-items:center; }
        .avatar { width:34px; height:34px; border-radius:50%; background:var(--brand); color:var(--brand-ink); display:grid; place-items:center; font-weight:700; flex:none; }
        .me b { display:block; color:#fff; font-size:13px; line-height:1.2; } .me small { color:#667085; text-transform:capitalize; }
        .me form { margin-left:auto; } .me button { background:transparent; border:0; color:var(--side-ink); cursor:pointer; padding:6px; border-radius:6px; }
        .me button:hover { background:var(--side-active); color:#fff; }

        main { padding:28px 32px 48px; min-width:0; max-width:1240px; width:100%; }
        .page-head { display:flex; justify-content:space-between; align-items:flex-end; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
        h1 { font-size:24px; line-height:1.25; margin:0; letter-spacing:-.01em; }
        .sub { color:var(--muted); margin:2px 0 0; }

        .card { background:var(--card); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow); margin-bottom:18px; }
        .card > .pad, form.card { padding:18px; }
        .card h2 { font-size:15px; margin:0; padding:14px 18px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:11px 16px; border-bottom:1px solid var(--line); white-space:nowrap; }
        th { color:var(--muted); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.04em; background:var(--hover); }
        tbody tr:hover { background:var(--hover); } tbody tr:last-child td { border-bottom:0; }
        td.empty { text-align:center; color:var(--muted); padding:36px 16px; }
        td.actions { text-align:right; }

        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:16px; margin-bottom:18px; }
        .stat { padding:16px 18px; display:block; color:inherit; margin:0; }
        a.stat:hover { text-decoration:none; border-color:var(--brand); }
        .stat span { color:var(--muted); font-size:13px; } .stat b { display:block; font-size:30px; line-height:1.15; margin-top:4px; letter-spacing:-.02em; }
        .stat.accent { border-left:4px solid var(--brand); } .stat.ok { border-left:4px solid var(--ok); }
        .stat.warn { border-left:4px solid var(--warn); } .stat.bad { border-left:4px solid var(--bad); }

        input, select, textarea, button, .btn { font:inherit; }
        input, select, textarea { padding:8px 11px; border:1px solid var(--line); border-radius:8px; background:var(--card); color:var(--ink); min-height:38px; }
        input:focus, select:focus { outline:2px solid var(--brand); outline-offset:-1px; border-color:transparent; }
        button, .btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; min-height:38px; border-radius:8px; border:1px solid var(--brand);
            background:var(--brand); color:var(--brand-ink); cursor:pointer; font-weight:600; text-decoration:none; }
        button:hover, .btn:hover { filter:brightness(1.07); text-decoration:none; }
        .btn.ghost, button.ghost { background:transparent; color:var(--ink); border-color:var(--line); }
        .btn.ghost:hover, button.ghost:hover { background:var(--hover); filter:none; }
        button.ok, .btn.ok { background:var(--ok); border-color:var(--ok); color:var(--ok-ink); }
        button.bad, .btn.bad { background:var(--bad); border-color:var(--bad); color:var(--bad-ink); }
        /* Destructive but secondary actions (Remove, Delete, Cancel): outlined red, solid on hover. */
        .btn.danger, button.danger { background:transparent; color:var(--bad); border-color:color-mix(in srgb, var(--bad) 55%, transparent); }
        .btn.danger:hover, button.danger:hover { background:var(--bad); border-color:var(--bad); color:var(--bad-ink); filter:none; }
        button:disabled, .btn[aria-disabled=true] { opacity:.45; cursor:not-allowed; filter:none; }
        button:focus-visible, .btn:focus-visible, .tabs a:focus-visible { outline:2px solid var(--brand); outline-offset:2px; }
        button.sm, .btn.sm { padding:4px 10px; min-height:30px; font-size:13px; }
        .row { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
        .tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
        .tabs a { padding:6px 14px; border-radius:999px; border:1px solid var(--line); color:var(--ink); background:var(--card); }
        .tabs a.active { background:var(--brand); border-color:var(--brand); color:var(--brand-ink); } .tabs a:hover { text-decoration:none; }

        .field { margin-bottom:14px; } .field label { display:block; color:var(--muted); font-size:13px; margin-bottom:5px; font-weight:600; }
        .field input, .field select { width:100%; }
        .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:0 18px; }
        /* Checkboxes: must beat `.field label` (display:block) and `.field input` (width:100%) above. */
        .check, .field .check { display:flex; gap:10px; align-items:center; margin:0; font-size:14.5px; font-weight:500; color:var(--ink); cursor:pointer; }
        input[type=checkbox], input[type=radio], .field input[type=checkbox], .field input[type=radio] {
            width:18px; height:18px; min-height:0; padding:0; flex:none; margin:0; accent-color:var(--brand); cursor:pointer;
        }
        .field[hidden] { display:none; }

        .badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:12px; font-weight:600; text-transform:capitalize;
            background:var(--neutral-soft); color:var(--neutral); }
        .badge.approved, .badge.present, .badge.active, .badge.in { background:var(--ok-soft); color:var(--ok); }
        .badge.pending, .badge.late, .badge.half_day { background:var(--warn-soft); color:var(--warn); }
        .badge.rejected, .badge.absent, .badge.inactive { background:var(--bad-soft); color:var(--bad); }
        .badge.admin { background:var(--brand-soft); color:var(--brand); } .badge.hr { background:var(--warn-soft); color:var(--warn); }
        form.inline { display:inline; }
        .person { display:flex; align-items:center; gap:10px; } .person .avatar { width:28px; height:28px; font-size:12px; }
        .muted { color:var(--muted); }

        .flash { padding:11px 14px; border-radius:8px; margin-bottom:18px; border:1px solid transparent; }
        .flash.ok { background:var(--ok-soft); color:var(--ok); border-color:color-mix(in srgb,var(--ok) 25%,transparent); }
        .flash.bad { background:var(--bad-soft); color:var(--bad); border-color:color-mix(in srgb,var(--bad) 25%,transparent); }

        .pagination { list-style:none; display:flex; gap:4px; padding:0; margin:0 0 12px; flex-wrap:wrap; }
        .pagination .page-link, .pagination span.page-link { display:block; padding:6px 12px; border:1px solid var(--line); border-radius:8px; background:var(--card); color:var(--ink); }
        .pagination .active .page-link { background:var(--brand); border-color:var(--brand); color:var(--brand-ink); }
        .pagination .disabled .page-link { color:var(--muted); opacity:.6; }
        nav[aria-label] > div:first-child:not(:last-child) { display:none; }

        .auth { min-height:100vh; display:grid; place-items:center; padding:16px; }
        .auth .card { width:100%; max-width:400px; padding:28px; }

        @media (max-width: 820px) {
            .shell { grid-template-columns:1fr; }
            aside { position:static; height:auto; padding:10px; flex-direction:row; flex-wrap:wrap; align-items:center; gap:4px; }
            aside .logo { padding:4px 8px; } aside .nav-label, .me small, .me b { display:none; }
            aside nav { display:flex; flex-wrap:wrap; gap:2px; flex:1; } aside nav a { padding:7px 9px; }
            aside nav a span.t { display:none; } .me { margin:0; border:0; padding:0; }
            main { padding:18px 16px 40px; }
        }
    </style>
</head>
<body>
@php
    // One layout, two panels. Each panel authenticates with its own guard.
    $isAdminPanel = request()->is('admin', 'admin/*');
    $user = auth($isAdminPanel ? 'admin' : 'web')->user();
@endphp
@if ($user)
    @php
        $icons = [
            'home' => '<path d="M3 11l9-8 9 8M5 10v10h14V10"/>',
            'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.2-5.5 6.5-5.5s5.9 1.9 6.5 5.5M16 4.5a3.5 3.5 0 010 7M18 14.8c1.9.7 3.1 2.5 3.5 5.2"/>',
            'building' => '<path d="M4 21V5l8-2v18M12 9h8v12M4 21h17M8 8h.01M8 12h.01M8 16h.01M16 13h.01M16 17h.01"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
            'bolt' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
            'flag' => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
            'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
            'key' => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3M14 9l2 2"/>',
            'logout' => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>',
        ];

        if ($isAdminPanel) {
            $brand = 'HR Admin';
            $logout = route('admin.logout');
            $roleLabel = $user->role?->name ?? 'No role';
            // [route, label, icon, active pattern, badge count, permission needed (null = any admin)]
            $all = [
                'Overview' => [['admin.dashboard', 'Dashboard', 'home', 'admin.dashboard', null, null]],
                'People' => [
                    ['admin.employees.index', 'Employees', 'users', 'admin.employees.*', null, 'employees.view'],
                    ['admin.departments.index', 'Departments', 'building', 'admin.departments.*', null, 'departments.manage'],
                ],
                'Time' => [
                    ['admin.attendance.index', 'Attendance', 'clock', 'admin.attendance.*', null, 'attendance.view'],
                    ['admin.leave-requests.index', 'Leave', 'calendar', 'admin.leave-requests.*', $pendingLeaveCount ?? 0, 'leave.manage'],
                    ['admin.overtime-requests.index', 'Overtime', 'bolt', 'admin.overtime-requests.*', $pendingOvertimeCount ?? 0, 'overtime.manage'],
                    ['admin.holidays.index', 'Holidays', 'flag', 'admin.holidays.*', null, 'holidays.manage'],
                ],
                'Administration' => [
                    ['admin.admins.index', 'Admins', 'shield', 'admin.admins.*', null, 'admins.manage'],
                    ['admin.roles.index', 'Roles', 'key', 'admin.roles.*', null, 'roles.manage'],
                ],
            ];
            // Show only what this admin's role allows; hide groups that end up empty.
            $nav = collect($all)
                ->map(fn ($items) => array_values(array_filter($items, fn ($i) => ! $i[5] || $user->can($i[5]))))
                ->filter()
                ->all();
        } else {
            $brand = 'My HR';
            $logout = route('employee.logout');
            $roleLabel = $user->employee?->designation ?? 'Employee';
            $nav = [
                'Overview' => [['employee.dashboard', 'Dashboard', 'home', 'employee.dashboard', null]],
                'My time' => [
                    ['employee.attendance.index', 'Attendance', 'clock', 'employee.attendance.*', null],
                    ['employee.leave.index', 'Leave', 'calendar', 'employee.leave.*', null],
                    ['employee.overtime.index', 'Overtime', 'bolt', 'employee.overtime.*', null],
                    ['employee.holidays.index', 'Holidays', 'flag', 'employee.holidays.*', null],
                ],
                'Profile' => [['employee.companies.index', 'My companies', 'building', 'employee.companies.*', null]],
            ];
        }
    @endphp
    <div class="shell">
        <aside>
            <div class="logo"><i>H</i> {{ $brand }}</div>
            <nav>
                @foreach ($nav as $group => $items)
                    <div class="nav-label">{{ $group }}</div>
                    @foreach ($items as [$route, $label, $icon, $pattern, $count])
                        <a href="{{ route($route) }}" @class(['active' => request()->routeIs($pattern)])>
                            <svg viewBox="0 0 24 24">{!! $icons[$icon] !!}</svg>
                            <span class="t">{{ $label }}</span>
                            @if ($count) <span class="count">{{ $count }}</span> @endif
                        </a>
                    @endforeach
                @endforeach
            </nav>
            <div class="me">
                <div class="avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                <div><b>{{ $user->name }}</b><small>{{ $roleLabel }}</small></div>
                <form method="post" action="{{ $logout }}">
                    @csrf
                    <button title="Log out" aria-label="Log out"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['logout'] !!}</svg></button>
                </form>
            </div>
        </aside>
        <main>
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
@else
    <div class="auth">
        <div>
            @include('partials.flash')
            @yield('content')
        </div>
    </div>
@endif
</body>
</html>
