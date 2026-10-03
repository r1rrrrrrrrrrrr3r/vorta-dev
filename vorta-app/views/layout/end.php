<?php
$layout      = $layout ?? 'app';
$pageScripts = $pageScripts ?? [];
$activeNav   = $activeNav ?? '';
$hasTabbar   = $hasTabbar ?? false;
?>
<?php if ($layout === 'bare'): ?>
</main>
<?php else: ?>
  </main>
</div>
<?php if ($hasTabbar):
  $tabs = [
      ['today', 'dashboard.php', 'Today', 'home'],
      ['my_reports', 'my_reports.php', 'Reports', 'document-text'],
      ['attendance', 'attendance.php', 'Attendance', 'clock'],
      ['account', 'account.php', 'Account', 'user'],
  ];
?>
<nav class="tabbar" aria-label="Quick navigation">
  <?php foreach ($tabs as [$key, $href, $label, $ic]): ?>
    <a href="<?= $href ?>" class="tabbar-item<?= $activeNav === $key ? ' is-active' : '' ?>"<?= $activeNav === $key ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= $label ?></span></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
<div class="toast-region" id="toasts" aria-live="polite"></div>
<div class="drawer-scrim" data-drawer-scrim hidden></div>
<aside class="drawer" id="drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-title" hidden>
  <div class="drawer-header">
    <div class="min-w-0"><div class="drawer-eyebrow" data-drawer-eyebrow></div><h2 class="drawer-title" id="drawer-title" data-drawer-title></h2></div>
    <button type="button" class="btn btn-ghost btn-icon" data-drawer-close aria-label="Close"><?= icon('x-mark') ?></button>
  </div>
  <div class="drawer-body" data-drawer-body></div>
  <div class="drawer-footer" data-drawer-footer></div>
</aside>
<?php /* Data (bukan script yang dieksekusi) supaya tetap terbaca setelah morph refresh Turbo; dibaca ui.js. */ ?>
<script type="application/json" data-vorta-flash><?= json_encode(flash_take(), JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php if (!empty($pageScriptHtml)) echo $pageScriptHtml; ?>
</body>
</html>
