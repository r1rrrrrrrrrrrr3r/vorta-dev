<?php
/** Report detail fragment for the drawer. Expects $row, $detailSelf. */
$description = trim((string) ($row['description'] ?? ''));
$rawLink = trim((string) ($row['proof_link'] ?? ''));
$isHttp = $rawLink !== '' && filter_var($rawLink, FILTER_VALIDATE_URL)
    && in_array(strtolower((string) parse_url($rawLink, PHP_URL_SCHEME)), ['http', 'https'], true);
$hasImage = trim((string) ($row['proof_image'] ?? '')) !== '';
$imageExists = $hasImage && upload_absolute_path((string) $row['proof_image']) !== null;
$imageUrl = $detailSelf . '?proof_image=' . (int) $row['report_id'];
?>
<div><?= status_pill($row['status'] ?: 'Progress') ?></div>

<dl class="dl">
  <dt>Date</dt><dd><?= e(fmt_date($row['report_date'], 'long')) ?></dd>
  <dt>Submitted by</dt><dd><?= e($row['user_name']) ?></dd>
  <dt>Job type</dt><dd><?= e($row['job_type'] ?: '–') ?></dd>
  <dt>Work force</dt><dd><?= e($row['workforce_name'] ?? '–') ?></dd>
</dl>

<div>
  <div class="section-label">Description</div>
  <?php if ($description !== ''): ?>
    <p class="m-0 break-words"><?= nl2br(e($description)) ?></p>
  <?php else: ?>
    <p class="m-0 text-muted italic">No description.</p>
  <?php endif; ?>
</div>

<div class="grid gap-2">
  <div class="section-label">Proof</div>
  <?php if ($rawLink === '' && !$hasImage): ?>
    <p class="m-0 text-muted italic">No proof attached.</p>
  <?php endif; ?>
  <?php if ($rawLink !== ''): ?>
    <div class="card flex items-center gap-3 p-3">
      <span class="text-muted"><?= icon('link') ?></span>
      <span class="flex-1 min-w-0 break-all text-[13px]"><?= e($rawLink) ?></span>
      <?php if ($isHttp): ?>
        <a class="btn btn-secondary btn-sm" href="<?= e($rawLink) ?>" target="_blank" rel="noopener noreferrer">Open<?= icon('arrow-up-right') ?></a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($hasImage): ?>
    <?php if ($imageExists): ?>
      <a href="<?= e($imageUrl) ?>" target="_blank" rel="noopener noreferrer" class="block">
        <img src="<?= e($imageUrl) ?>" alt="Proof photo" loading="lazy" class="block max-w-full max-h-80 rounded-lg border border-line object-contain"
          onerror="this.parentNode.hidden=true;this.parentNode.nextElementSibling.hidden=false;">
      </a>
      <p class="m-0 text-muted italic" hidden>The photo couldn't be loaded.</p>
    <?php else: ?>
      <p class="m-0 text-muted italic">The photo couldn't be loaded.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>
