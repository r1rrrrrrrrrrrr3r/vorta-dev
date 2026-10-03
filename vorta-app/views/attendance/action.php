<?php
/**
 * Primary attendance action for today. Expects $attendance, $state, $can_input_absence, $returnTo.
 */
require_once __DIR__ . '/../../lib/csrf.php';
?>
<?php if ($state === 'none'): ?>
  <div class="grid gap-2 justify-items-start">
    <button type="button" class="btn btn-primary" data-dialog-open="dlg-checkin"><?= icon('clock') ?>Check in</button>
    <div class="text-[13px] text-muted">
      <button type="button" class="link" data-dialog-open="dlg-leave">Request leave</button>
      <?php if ($can_input_absence): ?>
        · <button type="button" class="link" data-dialog-open="dlg-absence">Report an absence</button>
      <?php endif; ?>
    </div>
  </div>
<?php elseif ($state === 'checked_in'): ?>
  <form method="post" action="attendance.php" data-confirm="Check out now?"
    data-confirm-message="Your check-out time will be <?= e(date('H:i')) ?>." data-confirm-text="Check out" data-checkout-form>
    <?= csrf_field() ?>
    <input type="hidden" name="check_out" value="1">
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <button type="submit" class="btn btn-primary"><?= icon('arrow-right-on-rectangle') ?>Check out</button>
  </form>
<?php elseif ($state === 'checked_out'): ?>
  <span class="text-muted">Done for today</span>
<?php else: ?>
  <?php if (!empty($attendance['explanation'])): ?>
    <p class="m-0 text-muted max-w-[320px] break-words"><?= e($attendance['explanation']) ?></p>
  <?php endif; ?>
<?php endif; ?>
