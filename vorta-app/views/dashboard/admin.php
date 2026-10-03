<?php
/** Admin dashboard. Variables from dashboard.php. */
$max = max(1, (int) $target['max']);
$min = (int) $target['min'];
?>
<?= page_header('Dashboard', 'Production overview', period_picker('month', $month)) ?>

<?= kpi_strip([
    ['label' => 'Reports', 'value' => (int) $totalReportsMonth, 'note' => fmt_month($month)],
    ['label' => 'Active staff', 'value' => (int) $totalStaff],
    ['label' => 'On track', 'value' => (int) $onTrackStaff, 'suffix' => '/ ' . (int) $totalStaff, 'note' => '≥ ' . $min . ' reports this month'],
    ['label' => 'Avg per staff', 'value' => $avgPerStaff, 'note' => 'target ' . $min . '–' . $max],
]) ?>

<div class="grid gap-4 lg:grid-cols-5 items-start">
  <section class="card lg:col-span-3">
    <div class="card-header">
      <h2 class="card-title">Staff performance</h2>
      <span class="card-meta">Target <?= $min ?>–<?= $max ?> / month</span>
    </div>
    <?php if (!$users): ?>
      <?= empty_state('No active staff') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th class="w-8">#</th>
              <th>Staff</th>
              <th class="num">Reports</th>
              <th class="min-w-[140px]">Progress to <?= $max ?></th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $i => $u):
              [$stLabel, $stTone] = monthly_target_status((int) $u['total'], $min);
            ?>
              <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td class="whitespace-nowrap"><span class="cell-strong"><?= e($u['name']) ?></span><?php if ($u['role'] === 'admin'): ?> <?= role_pill('admin') ?><?php endif; ?></td>
                <td class="num"><?= (int) $u['total'] ?></td>
                <td><?= progress_bar(min(100, $u['total'] / $max * 100), [['pct' => $min / $max * 100]]) ?></td>
                <td><?= status_pill(null, $stTone, $stLabel) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer"><span>Showing all <?= count($users) ?> staff · marker = minimum <?= $min ?></span></div>
    <?php endif; ?>
  </section>

  <section class="card lg:col-span-2">
    <div class="card-header">
      <h2 class="card-title">Job types</h2>
      <span class="card-meta"><?= (int) $typeTotal ?> reports</span>
    </div>
    <div class="card-body">
      <?php if (empty($chartRows)): ?>
        <?= empty_state('No reports in ' . fmt_month($month)) ?>
      <?php else: ?>
        <div class="flex flex-wrap items-center justify-center gap-6">
          <div class="relative size-[168px] flex-none">
            <canvas id="pie" aria-label="Reports by job type" role="img"></canvas>
            <div class="absolute inset-0 grid place-content-center text-center pointer-events-none">
              <div class="big-number"><?= (int) $typeTotal ?></div>
              <div class="text-[11px] text-muted">reports</div>
            </div>
          </div>
          <div class="legend flex-1 min-w-[180px]">
            <?php foreach ($chartRows as $t):
              $pctType = $typeTotal > 0 ? round(($t['c'] / $typeTotal) * 100) : 0;
            ?>
              <div class="legend-row" title="<?= e($t['detail'] ?? $t['job_type']) ?>">
                <span class="legend-dot" style="background: var(--vt-<?= e($t['color']) ?>)"></span>
                <span class="legend-name"><?= e($t['job_type']) ?></span>
                <span class="legend-count"><?= (int) $t['c'] ?><span><?= $pctType ?>%</span></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<section class="card">
  <div class="card-body flex flex-wrap items-center gap-x-5 gap-y-3">
    <h2 class="card-title">Today · <?= e(fmt_date($today, 'day')) ?></h2>
    <span class="inline-flex items-center gap-2"><?= status_pill(null, 'ok', 'Complete') ?><strong class="tabular-nums"><?= $todayStats['complete'] ?></strong></span>
    <span class="inline-flex items-center gap-2"><?= status_pill(null, 'warn', 'Partial') ?><strong class="tabular-nums"><?= $todayStats['partial'] ?></strong></span>
    <span class="inline-flex items-center gap-2"><?= status_pill('No reports') ?><strong class="tabular-nums"><?= $todayStats['none'] ?></strong></span>
    <span class="flex-1"></span>
    <a class="link" href="admin_reports.php?tab=today">Open daily completion →</a>
  </div>
</section>

<?php if (!empty($chartRows)): ?>
<script>
  (function () {
    const labels  = <?= json_encode(array_column($chartRows, 'job_type')) ?>;
    const data    = <?= json_encode(array_map('intval', array_column($chartRows, 'c'))) ?>;
    const tokens  = <?= json_encode(array_column($chartRows, 'color')) ?>;
    const details = <?= json_encode(array_map(fn($r) => $r['detail'] ?? '', $chartRows)) ?>;
    const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    const colors = () => tokens.map(t => css('--vt-' + t));

    vortaReady(function () {
      const canvas = document.getElementById('pie');
      if (!canvas) return;
      Vorta.loadScript('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js').then(function () {
        if (!canvas.isConnected) return; // user sudah pindah halaman
        const chart = new Chart(canvas, {
          type: 'doughnut',
          data: { labels, datasets: [{ data, backgroundColor: colors(), borderWidth: 2, borderColor: css('--vt-surface'), hoverOffset: 4 }] },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
              legend: { display: false },
              tooltip: { callbacks: { afterLabel: (ctx) => details[ctx.dataIndex] || '' } }
            }
          }
        });
        const onTheme = function () {
          chart.data.datasets[0].backgroundColor = colors();
          chart.data.datasets[0].borderColor = css('--vt-surface');
          chart.update('none');
        };
        document.addEventListener('vorta:uichange', onTheme);
        Vorta.onLeave(function () { document.removeEventListener('vorta:uichange', onTheme); chart.destroy(); });
      }).catch(function () {});
    });
  })();
</script>
<?php endif; ?>
