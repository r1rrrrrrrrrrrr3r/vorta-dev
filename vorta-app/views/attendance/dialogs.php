<?php
/** Attendance dialogs. Expects $returnTo ('attendance.php'|'dashboard.php'), $can_input_absence. */
require_once __DIR__ . '/../../lib/csrf.php';
$returnTo = $returnTo ?? 'attendance.php';
?>
<dialog class="dialog" id="dlg-checkin" aria-labelledby="dlg-checkin-title">
  <form method="post" action="attendance.php" id="checkInForm">
    <?= csrf_field() ?>
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <div class="dialog-header"><h2 class="dialog-title" id="dlg-checkin-title">Check in</h2></div>
    <div class="dialog-body">
      <p class="dialog-text">Morning 07:30–11:59 · Afternoon 13:00–17:30</p>
      <div class="field">
        <label class="label" for="shiftSelect">Shift</label>
        <select name="shift" required class="select" id="shiftSelect">
          <option value="">Select shift</option>
          <option value="Morning">Morning</option>
          <option value="Afternoon">Afternoon</option>
          <option value="WFO">Whole day at office (WFO)</option>
          <option value="WAC">Working at client (WAC)</option>
          <option value="WFH">Working from home (WFH)</option>
          <option value="WFA">Working from anywhere (WFA)</option>
        </select>
        <p id="shiftHint" class="help">Pick the shift you're working today.</p>
      </div>
      <div class="field">
        <label class="label" for="checkin-location">Location</label>
        <input type="text" name="location" id="checkin-location" placeholder="Office, WFH, client site" class="input" required>
      </div>
      <div class="field" id="explanationContainer" hidden>
        <label class="label" for="checkin-explanation">Reason for being late</label>
        <textarea name="explanation" id="checkin-explanation" class="textarea" rows="3" placeholder="e.g. Traffic, health issue, family emergency"></textarea>
        <p class="help">At least 10 characters</p>
        <p class="field-error" data-late-error hidden>Please give a reason of at least 10 characters.</p>
      </div>
    </div>
    <div class="dialog-footer">
      <button type="button" class="btn btn-secondary" data-dialog-close>Cancel</button>
      <button type="submit" name="submitCheckIn" class="btn btn-primary">Check in</button>
    </div>
  </form>
</dialog>

<dialog class="dialog" id="dlg-leave" aria-labelledby="dlg-leave-title">
  <form method="post" action="attendance.php">
    <?= csrf_field() ?>
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <div class="dialog-header"><h2 class="dialog-title" id="dlg-leave-title">Request leave</h2></div>
    <div class="dialog-body">
      <div class="field">
        <label class="label" for="leave-type">Type</label>
        <select name="leave_type" id="leave-type" required class="select">
          <option value="">Select type</option>
          <option value="Sick">Sick</option>
          <option value="Leave">Leave</option>
          <option value="Others">Others</option>
        </select>
      </div>
      <div class="field">
        <label class="label" for="leave-explanation">Reason</label>
        <textarea name="explanation" id="leave-explanation" class="textarea" rows="3" placeholder="e.g. High fever, family event" required></textarea>
      </div>
    </div>
    <div class="dialog-footer">
      <button type="button" class="btn btn-secondary" data-dialog-close>Cancel</button>
      <button type="submit" name="submitLeave" class="btn btn-primary">Request leave</button>
    </div>
  </form>
</dialog>

<?php if (!empty($can_input_absence)): ?>
<dialog class="dialog" id="dlg-absence" aria-labelledby="dlg-absence-title">
  <form method="post" action="attendance.php" id="absenceReasonForm">
    <?= csrf_field() ?>
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <input type="hidden" name="absence_date" value="<?= e(date('Y-m-d')) ?>">
    <div class="dialog-header"><h2 class="dialog-title" id="dlg-absence-title">Report an absence</h2></div>
    <div class="dialog-body">
      <p class="dialog-text">For today only, until 23:59.</p>
      <div class="field">
        <label class="label" for="absence-type">Type</label>
        <select name="absence_type" id="absence-type" required class="select">
          <option value="">Select type</option>
          <option value="Absent">Absent</option>
          <option value="Forgot">Forgot to check in</option>
          <option value="Sick">Sick</option>
          <option value="Leave">Leave</option>
          <option value="Others">Others</option>
        </select>
      </div>
      <div class="field">
        <label class="label" for="absence-explanation">Reason</label>
        <textarea name="explanation" id="absence-explanation" class="textarea" rows="3" placeholder="Explain the reason for your absence" required></textarea>
        <p class="help">At least 10 characters</p>
      </div>
    </div>
    <div class="dialog-footer">
      <button type="button" class="btn btn-secondary" data-dialog-close>Cancel</button>
      <button type="submit" name="submitAbsenceReason" class="btn btn-primary">Report absence</button>
    </div>
  </form>
</dialog>
<?php endif; ?>
