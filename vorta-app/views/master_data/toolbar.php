<?php
/** Master data toolbar. Expects $mdTab, $search, $mdPlaceholder, $mdAddLabel, $mdPanel, $mdTotal, $mdNoun; optional $mdExtraActions (HTML). */
?>
<div class="toolbar">
  <form method="GET" id="md-filters" class="flex flex-wrap gap-2" role="search" data-turbo-frame="md-results" data-turbo-action="replace">
    <input type="hidden" name="tab" value="<?= e($mdTab) ?>">
    <div class="input-icon">
      <?= icon('magnifying-glass') ?>
      <label for="md-search" class="sr-only"><?= e($mdPlaceholder) ?></label>
      <input type="search" id="md-search" name="search" class="input" value="<?= e($search) ?>" placeholder="<?= e($mdPlaceholder) ?>">
    </div>
    <a href="?tab=<?= e($mdTab) ?>" class="btn btn-ghost" data-filter-clear="md-filters"<?= $search ? '' : ' hidden' ?>>Clear</a>
  </form>
  <div class="ml-auto flex flex-wrap gap-2">
    <?= $mdExtraActions ?? '' ?>
    <button type="button" class="btn btn-primary" data-drawer-panel="<?= e($mdPanel) ?>" data-mode="create"><?= icon('plus') ?><?= e($mdAddLabel) ?></button>
  </div>
</div>
<?php /* Frame hasil: ditutup oleh file tab setelah kartu tabel. Drawer form berada di luar frame. */ ?>
<turbo-frame id="md-results" class="results-frame" data-turbo-action="advance" autoscroll data-autoscroll-block="start">
<div class="toolbar"><span class="toolbar-count"><?= (int) $mdTotal ?> <?= e($mdNoun) ?></span></div>
