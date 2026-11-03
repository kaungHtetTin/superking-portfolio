<nav class="settings-tabs" aria-label="Settings sections">
    <?php foreach(['brand'=>'Brand & website','account'=>'Your account','qr'=>'QR printing'] as $tab=>$label): ?>
    <a id="settings-tab-<?= $tab ?>" class="<?= $settingsTab===$tab?'is-active':'' ?>" href="<?= e(url('admin/settings?'.http_build_query(['tab'=>$tab]+$settingsQuery))) ?>" <?= $settingsTab===$tab?'aria-current="page"':'' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
<section class="settings-content" aria-labelledby="settings-tab-<?= $settingsTab ?>">
    <?php require BASE_PATH.'/app/Views/admin/'.['brand'=>'settings.php','account'=>'account.php','qr'=>'qr-settings.php'][$settingsTab]; ?>
</section>
