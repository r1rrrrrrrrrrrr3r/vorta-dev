<?php
/** Master data toolbar. Expects $mdTab, $search, $mdPlaceholder, $mdAddLabel, $mdPanel, $mdTotal, $mdNoun. */
?>
<div class="toolbar">
  <form method="GET" class="flex flex-wrap gap-2" role="search">
    <input type="hidden" name="tab" value="<?= e($mdTab) ?>">
    <div class="input-icon">
      <?= icon('magnifying-glass') ?>
      <label for="md-search" class="sr-only"><?= e($mdPlaceholder) ?></label>
      <input type="search" id="md-search" name="search" class="input" value="<?= e($search) ?>" placeholder="<?= e($mdPlaceholder) ?>">
    </div>
    <button type="submit" class="btn btn-secondary">Search</button>
    <?php if ($search): ?><a href="?tab=<?= e($mdTab) ?>" class="btn btn-ghost">Clear</a><?php endif; ?>
  </form>
  <span class="toolbar-count"><?= (int) $mdTotal ?> <?= e($mdNoun) ?></span>
  <button type="button" class="btn btn-primary" data-drawer-panel="<?= e($mdPanel) ?>" data-mode="create"><?= icon('plus') ?><?= e($mdAddLabel) ?></button>
</div>
