<?php
/**
 * Report form fields (create & edit).
 * Expects: $report (array|null), $job_types, $work_forces, $lastWorkforceId (int|null),
 *          $jobTypeValueKey ('job_type_id' for create, 'name' for edit — matches what the save endpoint expects),
 *          $acceptTypes (array of MIME types the save endpoint accepts).
 */
$isEdit = $report !== null;
$selectedWorkforce = $isEdit ? (int) $report['workforce_id'] : (int) ($lastWorkforceId ?? 0);
$workforceRemembered = !$isEdit && $lastWorkforceId;
$status = $isEdit ? ($report['status'] ?: 'Progress') : 'Progress';
$existingImage = $isEdit ? basename(trim((string) ($report['proof_image'] ?? ''))) : '';
$acceptLabel = in_array('image/webp', $acceptTypes, true) ? 'JPG, PNG or WebP, up to 1 MB' : 'JPG or PNG, up to 1 MB';
?>
<div class="card-body card-section grid gap-4">
  <h2 class="card-title">What did you work on?</h2>
  <div class="field">
    <label class="label" for="rf-title">Title</label>
    <input type="text" name="title" id="rf-title" class="input" required placeholder="e.g. Login page, Export PDF feature"
      value="<?= e($report['title'] ?? '') ?>">
  </div>
  <div class="grid gap-4 md:grid-cols-2">
    <div class="field">
      <label class="label" for="rf-job-type">Job type</label>
      <select name="job_type" id="rf-job-type" class="select" required>
        <option value="">Select job type</option>
        <?php foreach ($job_types as $jt): ?>
          <option value="<?= e($jt[$jobTypeValueKey]) ?>"<?= $isEdit && $jt['name'] === $report['job_type'] ? ' selected' : '' ?>><?= e($jt['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (empty($job_types)): ?><p class="field-error">No job types yet. Ask an admin to add one.</p><?php endif; ?>
    </div>
    <div class="field">
      <label class="label" for="rf-workforce">Work force</label>
      <select name="workforce_id" id="rf-workforce" class="select" required>
        <option value="">Select work force</option>
        <?php foreach ($work_forces as $wf): ?>
          <option value="<?= (int) $wf['workforce_id'] ?>"<?= (int) $wf['workforce_id'] === $selectedWorkforce ? ' selected' : '' ?>><?= e($wf['workforce_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($workforceRemembered): ?><p class="help">Remembered from your last report</p><?php endif; ?>
    </div>
  </div>
  <div class="field">
    <label class="label" for="rf-description">Description <span class="optional">(optional)</span></label>
    <textarea name="description" id="rf-description" class="textarea" rows="4" placeholder="What changed, what's left…"><?= e($report['description'] ?? '') ?></textarea>
  </div>
</div>

<div class="card-body card-section grid gap-4">
  <h2 class="card-title">Proof</h2>
  <div class="field">
    <label class="label" for="rf-link">Link (repo, Figma, screenshot)</label>
    <input type="url" name="proof_link" id="rf-link" class="input" placeholder="https://" value="<?= e($report['proof_link'] ?? '') ?>">
  </div>
  <div class="field">
    <span class="label">Photo <span class="optional">(optional)</span></span>
    <label class="dropzone" for="proof_image" data-dropzone>
      <?php if ($existingImage !== ''): ?>
        <img src="my_reports.php?proof_image=<?= (int) $report['report_id'] ?>" alt="" data-dz-thumb>
      <?php else: ?>
        <img alt="" data-dz-thumb hidden>
        <span class="dropzone-icon" data-dz-icon><?= icon('photo') ?></span>
      <?php endif; ?>
      <span class="flex-1 min-w-0">
        <span class="block font-semibold" data-dz-text><?= $existingImage !== '' ? 'Current photo. Choose a new one to replace it.' : 'Drop a photo or <u>browse</u>' ?></span>
        <span class="help block"><?= e($acceptLabel) ?></span>
      </span>
      <button type="button" class="btn btn-ghost btn-sm" data-dz-remove hidden>Remove</button>
    </label>
    <input type="file" id="proof_image" name="proof_image" class="sr-only" accept="<?= e(implode(',', $acceptTypes)) ?>" data-accept="<?= e(implode(',', $acceptTypes)) ?>">
    <p class="field-error" data-dz-error hidden></p>
  </div>
</div>

<div class="card-body card-section grid gap-4 md:grid-cols-2">
  <div class="field">
    <label class="label" for="rf-date">Date</label>
    <input type="date" name="report_date" id="rf-date" class="input" required value="<?= e($report['report_date'] ?? date('Y-m-d')) ?>">
  </div>
  <div class="field">
    <span class="label" id="rf-status-label">Status</span>
    <div class="seg" role="radiogroup" aria-labelledby="rf-status-label">
      <label><input type="radio" name="status" value="Progress" class="sr-only"<?= $status === 'Progress' ? ' checked' : '' ?>><span>In progress</span></label>
      <label><input type="radio" name="status" value="Completed" class="sr-only"<?= $status === 'Completed' ? ' checked' : '' ?>><span>Completed</span></label>
    </div>
    <p class="help">Completed reports can't be edited.</p>
  </div>
</div>

<script>
(function () {
  const input = document.getElementById('proof_image');
  const zone = document.querySelector('[data-dropzone]');
  if (!input || !zone) return;
  const thumb = zone.querySelector('[data-dz-thumb]');
  const iconEl = zone.querySelector('[data-dz-icon]');
  const text = zone.querySelector('[data-dz-text]');
  const removeBtn = zone.querySelector('[data-dz-remove]');
  const error = document.querySelector('[data-dz-error]');
  const allowed = (input.dataset.accept || '').split(',');
  const original = { src: thumb.getAttribute('src'), hidden: thumb.hidden, text: text.innerHTML };
  const MAX = 1048576;

  function reset() {
    input.value = '';
    if (original.src) thumb.src = original.src; else thumb.removeAttribute('src');
    thumb.hidden = original.hidden;
    if (iconEl) iconEl.hidden = !original.hidden;
    text.innerHTML = original.text;
    removeBtn.hidden = true;
  }
  function fail(msg) {
    reset();
    error.textContent = msg;
    error.hidden = false;
    input.setAttribute('aria-invalid', 'true');
  }
  function handle(file) {
    error.hidden = true;
    input.removeAttribute('aria-invalid');
    if (!file) { reset(); return; }
    const type = file.type === 'image/jpg' ? 'image/jpeg' : file.type;
    if (!allowed.includes(type)) { fail('This file type isn\'t supported. ' + zone.querySelector('.help').textContent + '.'); return; }
    if (file.size > MAX) { fail('This photo is ' + (file.size / 1048576).toFixed(1) + ' MB. The limit is 1 MB.'); return; }
    const reader = new FileReader();
    reader.onload = (ev) => { thumb.src = ev.target.result; thumb.hidden = false; if (iconEl) iconEl.hidden = true; };
    reader.readAsDataURL(file);
    text.textContent = file.name;
    removeBtn.hidden = false;
  }
  input.addEventListener('change', () => handle(input.files[0]));
  removeBtn.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); reset(); error.hidden = true; });
  ['dragenter', 'dragover'].forEach(t => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.add('is-dragover'); }));
  ['dragleave', 'drop'].forEach(t => zone.addEventListener(t, () => zone.classList.remove('is-dragover')));
  zone.addEventListener('drop', (e) => {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (!file) return;
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    handle(file);
  });

  const form = input.form;
  form.addEventListener('submit', (e) => {
    if (form.dataset.submitting) { e.preventDefault(); return; }
    form.dataset.submitting = '1';
    const btn = form.querySelector('[type=submit]');
    if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
  });
})();
</script>
