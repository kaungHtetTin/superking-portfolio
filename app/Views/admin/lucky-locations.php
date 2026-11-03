<?php
$query = array_intersect_key($filters, array_flip(['campaign','from','to']));
$when = fn($date) => (new DateTimeImmutable($date,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Yangon'))->format('d M Y, H:i:s');
?>
<div class="lucky-detail distribution-report">
<section class="panel"><div class="panel-heading"><div><h2>First-reveal locations</h2><p>See where promotional codes were first opened. Each code contributes one location, including non-winning codes.</p></div><a class="btn" href="<?= e(url('admin/distribution/export?'.http_build_query($query))) ?>">Export CSV</a></div>
<form method="get" action="<?= url('admin/distribution') ?>" class="distribution-filters" aria-label="Distribution filters"><div class="distribution-filter-scroll"><div class="distribution-filter-fields">
<div class="distribution-campaign"><label for="location-campaign">Promotion</label><select id="location-campaign" name="campaign"><option value="">All promotions</option><?php foreach($campaigns as $campaign): ?><option value="<?= $campaign['id'] ?>" <?= (string)$campaign['id']===$filters['campaign']?'selected':'' ?>><?= e($campaign['name']) ?></option><?php endforeach; ?></select></div>
<div><label for="location-from">From (Myanmar)</label><input id="location-from" name="from" type="date" value="<?= e($filters['from']) ?>"></div>
<div><label for="location-to">To (Myanmar)</label><input id="location-to" name="to" type="date" value="<?= e($filters['to']) ?>"></div>
</div></div><div class="distribution-filter-actions"><button class="btn btn-primary">Apply</button><a class="btn" href="<?= url('admin/distribution') ?>">Reset</a></div></form>
<?php if($filters['errors']): ?><div class="flash error" role="alert"><?= e(implode(' ',$filters['errors'])) ?></div><?php endif; ?>
<p><strong><?= number_format($total) ?></strong> recorded locations match these filters. All filtered locations appear on the map; overlapping locations share a marker.</p>
<p class="form-note">These are device-reported locations at first reveal, not verified purchase locations or a complete delivery route. Accuracy varies by device. Older reveals made before location capture was added have no location.</p></section>
<section class="panel"><div class="panel-heading"><h2>Distribution map</h2><button type="button" class="btn" id="distribution-fit">Show all locations</button></div>
<div id="distribution-map" role="region" aria-label="Product distribution map" tabindex="0"></div><p id="distribution-map-status" role="status"><?= $total?'Loading map…':'No locations yet. New prize reveals will appear here.' ?></p><p class="form-note">Map background: OpenStreetMap. The records below remain available if map tiles cannot load.</p>
<script type="application/json" id="distribution-points"><?= json_encode($points,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
</section>
<section class="panel"><h2>Location records</h2><?php if(!$rows): ?><p>No locations match these filters.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Promotion / code ending</th><th>Recorded (Myanmar)</th><th>Latitude / longitude</th><th>Accuracy</th><th>Map</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><strong><?= e($row['campaign_name']) ?></strong><small>…<?= e($row['code_ending']) ?></small></td><td><?= e($when($row['recorded_at'])) ?></td><td><?= e($row['latitude']) ?><br><?= e($row['longitude']) ?></td><td>±<?= number_format($row['accuracy_m']) ?> m</td><td><button type="button" class="btn btn-sm distribution-focus" data-lat="<?= e($row['latitude']) ?>" data-lng="<?= e($row['longitude']) ?>" data-accuracy="<?= e($row['accuracy_m']) ?>">Show on map</button></td></tr><?php endforeach; ?>
</tbody></table></div><div class="list-footer"><span>Page <?= $page ?> of <?= $pages ?> · <?= number_format($total) ?> records</span><div class="record-heading-actions"><?php if($page>1): ?><a class="btn" href="<?= e(url('admin/distribution?'.http_build_query($query+['page'=>$page-1]))) ?>">Previous</a><?php endif; ?><?php if($page<$pages): ?><a class="btn" href="<?= e(url('admin/distribution?'.http_build_query($query+['page'=>$page+1]))) ?>">Next</a><?php endif; ?></div></div><?php endif; ?></section>
</div>
<link rel="stylesheet" href="<?= asset('js/vendor/leaflet/leaflet.css') ?>">
<script src="<?= asset('js/vendor/leaflet/leaflet.js') ?>"></script>
<script src="<?= asset('js/distribution-map.js').'?v='.substr(hash_file('sha256',BASE_PATH.'/public/assets/js/distribution-map.js'),0,12) ?>"></script>
