<?php
/** Check in → Check out timeline. Expects $attendance (array|null). */
$tlIn = $attendance['check_in'] ?? null;
$tlOut = $attendance['check_out'] ?? null;
?>
<div class="timeline flex-1 min-w-[240px] max-w-[420px]" aria-label="Today's check-in and check-out">
  <div class="timeline-step<?= $tlIn ? ' is-done' : '' ?>">
    <span class="timeline-dot"></span>
    <span class="timeline-time"><?= e(fmt_time($tlIn)) ?></span>
    <span class="timeline-label">Check in<?= !empty($attendance['location']) ? ' · ' . e($attendance['location']) : '' ?></span>
  </div>
  <div class="timeline-line<?= $tlOut ? ' is-done' : '' ?>"></div>
  <div class="timeline-step<?= $tlOut ? ' is-done' : '' ?>">
    <span class="timeline-dot"></span>
    <span class="timeline-time"><?= e(fmt_time($tlOut)) ?></span>
    <span class="timeline-label">Check out</span>
  </div>
</div>
