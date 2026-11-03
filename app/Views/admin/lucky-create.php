<?php $saved = $_SESSION['lucky_form'] ?? []; unset($_SESSION['lucky_form']); $value = fn($key, $default='') => is_scalar($saved[$key] ?? null) ? $saved[$key] : $default; $names = is_array($saved['prize_name'] ?? null) ? array_slice($saved['prize_name'], 0, 30) : ['']; ?>
<form class="panel crud-form" method="post" action="<?= url('admin/lucky-draw') ?>">
<?= csrf_field() ?>
<p>1. Set up your promotion. 2. Generate and print codes. 3. Activate and distribute.</p>
<div class="form-grid">
<div class="field full"><label for="name">Promotion name</label><input id="name" name="name" required maxlength="190" value="<?= e($value('name')) ?>" placeholder="SUPERKING lucky rewards"></div>
<div class="field"><label for="starts_on">Start date (Myanmar time)</label><input type="date" id="starts_on" name="starts_on" required value="<?= e($value('starts_on',(new DateTimeImmutable('now',new DateTimeZone('Asia/Yangon')))->format('Y-m-d'))) ?>"></div>
<div class="field"><label for="ends_on">Last day to play (Myanmar time)</label><input type="date" id="ends_on" name="ends_on" required value="<?= e($value('ends_on')) ?>"></div>
<div class="field"><label for="total_codes">Total promotional codes</label><input type="number" id="total_codes" name="total_codes" min="1" max="10000" required value="<?= e($value('total_codes',1000)) ?>"><small>Up to 10,000 codes per promotion. Each code plays once.</small></div>
<div class="field full"><label for="instructions">Where and how to collect a prize</label><textarea id="instructions" name="instructions" required maxlength="2000" rows="3"><?= e($value('instructions','Show your original winning label to the participating dealer. Keep your code private until collection.')) ?></textarea></div>
</div>
<h2>Prizes</h2><p>Enter any reward, such as “5,000 MMK cashback”, “A book” or “Smartwatch”. Remaining codes will be non-winning.</p>
<div id="lucky-prizes"><?php foreach ($names as $i=>$name): ?><div class="lucky-prize-row"><label>Prize name<input name="prize_name[]" required maxlength="190" value="<?= e(is_string($name)?$name:'') ?>"></label><label>Quantity<input name="prize_quantity[]" type="number" min="1" max="10000" required value="<?= e(is_scalar($saved['prize_quantity'][$i]??null)?$saved['prize_quantity'][$i]:1) ?>"></label><button type="button" class="btn lucky-remove" aria-label="Remove prize">Remove</button></div><?php endforeach; ?></div>
<button type="button" class="btn" id="lucky-add">Add another prize</button>
<p class="form-note">Prize names, quantities and dates are fixed when you create the promotion. Codes are assigned randomly when generated. You can pause the promotion at any time.</p>
<div class="form-actions"><a class="btn" href="<?= url('admin/lucky-draw') ?>">Cancel</a><button class="btn btn-primary">Create promotion</button></div>
</form>
<template id="lucky-prize-template"><div class="lucky-prize-row"><label>Prize name<input name="prize_name[]" required maxlength="190"></label><label>Quantity<input name="prize_quantity[]" type="number" min="1" max="10000" required value="1"></label><button type="button" class="btn lucky-remove" aria-label="Remove prize">Remove</button></div></template>
<script src="<?= asset('js/lucky-draw.js') ?>"></script>
