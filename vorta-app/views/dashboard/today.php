<?php
/** Staff "Today" page. Variables from dashboard.php. */
$n = count($todayReports);
if ($n >= $dailyMin) {
    $todayPill = status_pill(null, 'ok', 'Complete');
} elseif ($n > 0) {
    $todayPill = status_pill(null, 'warn', ($dailyMin - $n) . ' more needed');
} else {
    $todayPill = status_pill(null, 'bad', 'No reports yet');
}
$max = max(1, (int) $target['max']);
$min = (int) $target['min'];
?>
<?= page_header('Today', fmt_date($today, 'full'), '<a class="btn btn-primary" href="report_form.php">' . icon('plus') . 'New report</a>') ?>

<?= attendance_alert_html() ?>

<div class="grid gap-4 lg:grid-cols-2">
  <section class="card">
    <div class="card-header">
      <h2 class="card-title">Attendance</h2>
      <?= ($attendance && $attendance['status']) ? status_pill($attendance['status']) : status_pill(null, 'neutral', 'Not checked in') ?>
    </div>
    <div class="card-body grid gap-4">
      <div>
        <div class="big-number" data-clock><?= e(date('H:i')) ?></div>
        <?php if ($state === 'checked_in'): ?>
          <div class="text-muted">Checked in at <?= e(fmt_time($attendance['check_in'])) ?><?= !empty($attendance['location']) ? ' · ' . e($attendance['location']) : '' ?></div>
        <?php elseif ($state === 'checked_out'): ?>
          <div class="text-muted"><?= e(fmt_time($attendance['check_in'])) ?> – <?= e(fmt_time($attendance['check_out'])) ?><?= !empty($attendance['location']) ? ' · ' . e($attendance['location']) : '' ?></div>
        <?php else: ?>
          <div class="text-muted"><?= $isIntern ? 'Intern' : 'Full-time employee' ?></div>
        <?php endif; ?>
      </div>
      <?php include __DIR__ . '/../attendance/action.php'; ?>
    </div>
  </section>

  <section class="card">
    <div class="card-header">
      <h2 class="card-title">Today's reports</h2>
      <span class="card-meta">minimum <?= (int) $dailyMin ?> per day</span>
    </div>
    <div class="card-body grid gap-3">
      <div class="flex items-center gap-3 flex-wrap">
        <div class="big-number"><?= $n ?> <small>/ <?= (int) $dailyMin ?></small></div>
        <?= $todayPill ?>
      </div>
      <?= segbar(min($n, $dailyMin), $dailyMin) ?>
      <?php if ($n === 0): ?>
        <p class="m-0 text-muted">Nothing yet today. <a class="link" href="report_form.php">Add a report</a></p>
      <?php else: ?>
        <ul class="m-0 p-0 list-none grid">
          <?php foreach (array_slice($todayReports, 0, 5) as $r): ?>
            <li class="flex items-center gap-3 py-2 border-t border-line first:border-t-0">
              <div class="flex-1 min-w-0">
                <div class="font-semibold truncate"><?= e($r['title']) ?></div>
                <div class="text-[12px] text-muted"><?= e($r['job_type']) ?></div>
              </div>
              <?= status_pill($r['status']) ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <a class="link text-[13px]" href="my_reports.php">View all</a>
      <?php endif; ?>
    </div>
  </section>
</div>

<div class="grid gap-4 lg:grid-cols-3">
  <section class="card lg:col-span-2">
    <div class="card-header">
      <h2 class="card-title">This month</h2>
      <span class="card-meta"><?= e(fmt_month($month)) ?></span>
    </div>
    <div class="card-body grid gap-4">
      <div class="flex flex-wrap items-baseline justify-between gap-2">
        <div class="big-number"><?= (int) $monthCount ?> <small>reports</small></div>
        <span class="text-muted text-[13px]">
          <?php if ($monthCount < $min): ?>
            <?= $min - $monthCount ?> more to reach <?= $min ?> · <?= $weekdaysLeft ?> weekday<?= $weekdaysLeft === 1 ? '' : 's' ?> left
          <?php else: ?>
            Minimum reached · max <?= $max ?>
          <?php endif; ?>
        </span>
      </div>
      <?= progress_bar(min(100, $monthCount / $max * 100), [
          ['pct' => $min / $max * 100, 'label' => 'min ' . $min],
          ['pct' => 100, 'label' => 'max ' . $max],
      ], true) ?>
      <?= daybars($month, $dailyCounts, $dailyMin) ?>
    </div>
  </section>

  <section class="card">
    <div class="card-header">
      <h2 class="card-title">Team ranking</h2>
      <span class="card-meta"><?= e(fmt_month($month)) ?></span>
    </div>
    <table class="table table-compact">
      <tbody>
        <?php
        $rankRows = array_slice($users, 0, 3, true);
        if ($myRank !== null && $myRank > 3) {
            $rankRows[$myRank - 1] = $users[$myRank - 1];
        }
        foreach ($rankRows as $i => $u):
            $isMe = (int) $u['user_id'] === $user_id;
        ?>
          <?php if ($i >= 3 && $i > 3): ?>
            <tr><td colspan="3" class="text-center text-muted">…</td></tr>
          <?php endif; ?>
          <tr<?= $isMe ? ' class="is-highlight"' : '' ?>>
            <td class="text-muted w-8"><?= $i + 1 ?></td>
            <td class="<?= $isMe ? 'cell-strong' : '' ?>"><?= e(short_name($u['name'])) ?><?= $isMe ? ' <span class="text-muted font-normal">(you)</span>' : '' ?></td>
            <td class="num"><?= (int) $u['total'] ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?>
          <tr><td><?= empty_state('No ranking yet') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>
</div>

<a class="fab md:hidden" href="report_form.php" aria-label="New report"><?= icon('plus') ?></a>

<?php include __DIR__ . '/../attendance/dialogs.php'; ?>
