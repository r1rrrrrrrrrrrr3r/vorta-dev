<?php
require_once __DIR__ . '/icons.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** $style: short "3 Oct" | medium "3 Oct 2026" | day "Fri, 3 Oct" | long "Fri, 3 Oct 2026" | full "Friday, 3 October 2026" */
function fmt_date(?string $date, string $style = 'medium'): string
{
    $ts = $date ? strtotime($date) : false;
    if (!$ts) return '–';
    return match ($style) {
        'short'  => date('j M', $ts),
        'day'    => date('D, j M', $ts),
        'long'   => date('D, j M Y', $ts),
        'full'   => date('l, j F Y', $ts),
        default  => date('j M Y', $ts),
    };
}

/** "2026-10" → "Oct 2026" */
function fmt_month(string $ym): string
{
    $ts = strtotime($ym . '-01');
    return $ts ? date('M Y', $ts) : $ym;
}

/** "07:52:10" → "07:52"; null → "–" */
function fmt_time(?string $time): string
{
    return $time ? substr($time, 0, 5) : '–';
}

/** Validasi "YYYY-MM"; fallback bulan ini. */
function valid_month(?string $ym): string
{
    return ($ym && preg_match('/^\d{4}-\d{2}$/', $ym) && strtotime($ym . '-01')) ? $ym : date('Y-m');
}

function valid_date(?string $d): string
{
    return ($d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d)) ? $d : date('Y-m-d');
}

const STATUS_TONE = [
    'Completed' => 'ok', 'Present' => 'ok', 'On track' => 'ok', 'Complete' => 'ok', 'Active' => 'ok',
    'Progress' => 'warn', 'Late' => 'warn', 'Behind' => 'warn',
    'Absent' => 'bad', 'Forgot' => 'bad', 'No reports' => 'bad', 'At risk' => 'bad', 'Not checked in' => 'bad',
    'Leave' => 'info', 'Sick' => 'info', 'Others' => 'info',
    'Inactive' => 'neutral', 'Unknown' => 'neutral',
];

const STATUS_LABEL = [
    'Progress' => 'In progress',
    'Forgot'   => 'Forgot to check in',
];

/** Satu-satunya cara menampilkan status. */
function status_pill(?string $status, ?string $tone = null, ?string $label = null): string
{
    $status = $status ?: 'Unknown';
    $tone   = $tone ?? (STATUS_TONE[$status] ?? 'neutral');
    $label  = $label ?? (STATUS_LABEL[$status] ?? $status);
    return '<span class="pill pill-' . e($tone) . '">' . e($label) . '</span>';
}

function role_pill(string $role): string
{
    return $role === 'admin'
        ? '<span class="pill pill-role pill-role-admin">Admin</span>'
        : '<span class="pill pill-role">Staff</span>';
}

/** Return [label, tone]. */
function monthly_target_status(int $total, int $min): array
{
    if ($total >= $min) return ['On track', 'ok'];
    if ($total >= $min * 0.6) return ['Behind', 'warn'];
    return ['At risk', 'bad'];
}

/** URL halaman sekarang dengan $_GET di-merge; nilai null = hapus param. */
function query_url(array $overrides = [], ?string $path = null): string
{
    $q = array_merge($_GET, $overrides);
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    $path = $path ?? basename($_SERVER['PHP_SELF']);
    return $path . ($q ? '?' . http_build_query($q) : '');
}

function page_header(string $title, ?string $subtitle = null, string $actionsHtml = '', ?array $back = null): string
{
    $html = '<div class="page-header"><div>';
    if ($back) {
        $html .= '<a class="back-link" href="' . e($back['href']) . '">' . icon('chevron-left') . e($back['label']) . '</a>';
    }
    $html .= '<h1 class="page-title">' . e($title) . '</h1>';
    if ($subtitle !== null && $subtitle !== '') {
        $html .= '<p class="page-subtitle">' . e($subtitle) . '</p>';
    }
    $html .= '</div>';
    if ($actionsHtml !== '') {
        $html .= '<div class="flex flex-wrap items-center gap-2">' . $actionsHtml . '</div>';
    }
    return $html . '</div>';
}

/**
 * Stepper periode. $type 'month' (YYYY-MM) atau 'date' (YYYY-MM-DD).
 */
function period_picker(string $param, string $value, string $type = 'month', array $resetParams = ['page']): string
{
    $isMonth = $type === 'month';
    $value = $isMonth ? valid_month($value) : valid_date($value);
    $base = $isMonth ? $value . '-01' : $value;
    $prev = $isMonth ? date('Y-m', strtotime($base . ' -1 month')) : date('Y-m-d', strtotime($base . ' -1 day'));
    $next = $isMonth ? date('Y-m', strtotime($base . ' +1 month')) : date('Y-m-d', strtotime($base . ' +1 day'));
    $nextDisabled = $isMonth ? $next > date('Y-m') : $next > date('Y-m-d');
    $label = $isMonth ? fmt_month($value) : fmt_date($value, 'long');

    $reset = array_fill_keys($resetParams, null);
    $prevUrl = query_url(array_merge($reset, [$param => $prev]));
    $nextUrl = query_url(array_merge($reset, [$param => $next]));

    $hidden = '';
    foreach ($_GET as $k => $v) {
        if ($k === $param || in_array($k, $resetParams, true) || is_array($v)) continue;
        $hidden .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }

    $unit = $isMonth ? 'month' : 'day';
    // data-query-own: param milik kontrol ini; param lain disinkronkan dari URL terkini di client (filter via Turbo Frame)
    $own = e(implode(',', array_merge([$param], $resetParams)));
    $html = '<form method="get" class="period" action="' . e(basename($_SERVER['PHP_SELF'])) . '" data-query-own="' . $own . '">' . $hidden;
    $html .= '<a class="period-step" href="' . e($prevUrl) . '" data-query-own="' . $own . '" aria-label="Previous ' . $unit . '">' . icon('chevron-left') . '</a>';
    $html .= '<label class="period-label"><span>' . e($label) . '</span>'
        . '<input type="' . ($isMonth ? 'month' : 'date') . '" name="' . e($param) . '" value="' . e($value) . '"'
        . ' max="' . ($isMonth ? date('Y-m') : date('Y-m-d')) . '" aria-label="Choose ' . $unit . '" tabindex="-1"></label>';
    if ($nextDisabled) {
        $html .= '<span class="period-step" aria-disabled="true" aria-label="Next ' . $unit . '">' . icon('chevron-right') . '</span>';
    } else {
        $html .= '<a class="period-step" href="' . e($nextUrl) . '" data-query-own="' . $own . '" aria-label="Next ' . $unit . '">' . icon('chevron-right') . '</a>';
    }
    return $html . '</form>';
}

/** Footer pagination "1–20 of 134" + prev/next. */
function pagination(int $page, int $perPage, int $total, string $param = 'page'): string
{
    if ($total <= 0) return '';
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    $from = ($page - 1) * $perPage + 1;
    $to = min($total, $page * $perPage);

    $btn = function (bool $enabled, int $target, string $iconName, string $label) use ($param) {
        $cls = 'btn btn-secondary btn-sm btn-icon';
        if (!$enabled) {
            return '<span class="' . $cls . '" aria-disabled="true" aria-label="' . $label . '">' . icon($iconName) . '</span>';
        }
        return '<a class="' . $cls . '" href="' . e(query_url([$param => $target])) . '" aria-label="' . $label . '">' . icon($iconName) . '</a>';
    };

    return '<div class="card-footer"><span>' . $from . '–' . $to . ' of ' . $total . '</span><div class="flex gap-1">'
        . $btn($page > 1, $page - 1, 'chevron-left', 'Previous page')
        . $btn($page < $pages, $page + 1, 'chevron-right', 'Next page')
        . '</div></div>';
}

/** $items: [['label'=>'Reports','value'=>412,'suffix'=>'/ 14','note'=>'Oct 2026','tone'=>'bad'?], ...] */
function kpi_strip(array $items): string
{
    $html = '<div class="kpi-strip">';
    foreach ($items as $it) {
        $html .= '<div class="kpi"><div class="kpi-label">' . e($it['label']) . '</div>'
            . '<div class="kpi-value' . (($it['tone'] ?? '') === 'bad' ? ' is-bad' : '') . '">' . e($it['value'])
            . (isset($it['suffix']) ? ' <small>' . e($it['suffix']) . '</small>' : '') . '</div>'
            . (isset($it['note']) ? '<div class="kpi-note">' . e($it['note']) . '</div>' : '')
            . '</div>';
    }
    return $html . '</div>';
}

function empty_state(string $title, string $text = '', string $actionHtml = ''): string
{
    return '<div class="empty"><div class="empty-title">' . e($title) . '</div>'
        . ($text !== '' ? '<div class="empty-text">' . e($text) . '</div>' : '')
        . ($actionHtml !== '' ? '<div class="mt-2">' . $actionHtml . '</div>' : '')
        . '</div>';
}

/** Alert banner. $tone: ok|warn|bad|info */
function alert_box(string $tone, string $message): string
{
    $iconName = ['ok' => 'check-circle', 'warn' => 'exclamation-triangle', 'bad' => 'x-circle', 'info' => 'information-circle'][$tone] ?? 'information-circle';
    return '<div class="alert alert-' . e($tone) . '" role="' . ($tone === 'bad' ? 'alert' : 'status') . '">' . icon($iconName) . '<div>' . e($message) . '</div></div>';
}

/** Flash untuk toast di request berikutnya. $tone: ok|bad|info */
function flash_set(string $tone, string $message): void
{
    $_SESSION['flash'][] = ['tone' => $tone, 'message' => $message];
}

function flash_take(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last) ?: '?';
}

/** "Dimas Pratama" → "Dimas P." */
function short_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    if (count($parts) < 2) return trim($name);
    return $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.';
}

/** Progress bar with optional markers: [['pct'=>57,'label'=>'min 50','end'=>false]] */
function progress_bar(float $pct, array $markers = [], bool $large = false): string
{
    $pct = max(0, min(100, $pct));
    $html = '<div class="progress' . ($large ? ' progress-lg' : '') . '" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . (int) round($pct) . '">'
        . '<div class="progress-fill" style="width:' . round($pct, 2) . '%"></div>';
    foreach ($markers as $m) {
        $p = max(0, min(100, (float) $m['pct']));
        $html .= '<div class="progress-marker' . ($p >= 99.5 ? ' is-end' : '') . '" style="left:' . round($p, 2) . '%">'
            . (!empty($m['label']) ? '<span class="progress-marker-label">' . e($m['label']) . '</span>' : '')
            . '</div>';
    }
    return $html . '</div>';
}

function segbar(int $filled, int $total): string
{
    $total = max(1, $total);
    $html = '<div class="segbar" aria-hidden="true">';
    for ($i = 0; $i < $total; $i++) {
        $html .= '<span' . ($i < $filled ? ' class="is-filled"' : '') . '></span>';
    }
    return $html . '</div>';
}

/** Daily bar chart for a month. $counts: 'Y-m-d' => n. */
function daybars(string $month, array $counts, int $dailyMin): string
{
    $start = $month . '-01';
    $days = (int) date('t', strtotime($start));
    $today = date('Y-m-d');
    $max = max($dailyMin + 1, max($counts ?: [0]));
    $html = '<div><div class="daybars" role="img" aria-label="Reports per day in ' . e(fmt_month($month)) . '">';
    for ($d = 1; $d <= $days; $d++) {
        $date = sprintf('%s-%02d', $month, $d);
        $n = (int) ($counts[$date] ?? 0);
        $cls = [];
        if ($n >= $dailyMin && $n > 0) $cls[] = 'is-met';
        if ($date === $today) $cls[] = 'is-today';
        $h = $n > 0 ? round($n / $max * 100, 1) : 0;
        $html .= '<span' . ($cls ? ' class="' . implode(' ', $cls) . '"' : '') . ' style="height:' . $h . '%" title="'
            . e(fmt_date($date, 'short') . ' · ' . $n . ' report' . ($n === 1 ? '' : 's')) . '"></span>';
    }
    $html .= '<div class="daybars-min" style="bottom:' . round($dailyMin / $max * 100, 1) . '%"><span>min ' . $dailyMin . '</span></div>';
    $html .= '</div><div class="daybars-axis">';
    foreach ([1, 8, 15, 22, $days] as $d) {
        $html .= '<span>' . $d . '</span>';
    }
    return $html . '</div></div>';
}
