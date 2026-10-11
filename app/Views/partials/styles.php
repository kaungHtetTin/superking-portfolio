<?php
// Direct, versioned links avoid stale @import lists and cached component styles.
foreach (['site.css', 'contrast.css', 'search.css', 'fields.css'] as $stylesheet):
    $version = substr(hash_file('sha256', BASE_PATH . '/public/assets/css/' . $stylesheet), 0, 12);
?>
<link rel="stylesheet" href="<?= e(asset('css/' . $stylesheet) . '?v=' . $version) ?>">
<?php endforeach; ?>
