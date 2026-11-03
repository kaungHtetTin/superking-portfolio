<div class="panel">
    <div class="panel-heading"><p>Create a promotion, print the codes, then give prizes at the counter.</p><div class="record-heading-actions"><a class="btn" href="<?= url('admin/distribution') ?>">Distribution map</a><a class="btn" href="<?= url('admin/lucky-draw/redeem') ?>">Give a prize</a><a class="btn btn-primary" href="<?= url('admin/lucky-draw/create') ?>">Create promotion</a></div></div>
    <?php if (!$campaigns): ?><p>No promotions yet. Create your first promotion to get started.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Promotion</th><th>Dates (Myanmar)</th><th>Status</th><th>Played</th><th>Collected</th><th></th></tr></thead><tbody>
    <?php foreach ($campaigns as $campaign): ?><tr><td><strong><?= e($campaign['name']) ?></strong><?= number_format($campaign['total_codes']) ?> codes</td><td><?= e($campaign['starts_on']) ?> &ndash; <?= e($campaign['ends_on']) ?></td><td><span class="badge <?= $campaign['status']==='active'?'published':'draft' ?>"><?= e(ucfirst($campaign['status'])) ?></span></td><td><?= number_format($campaign['played']) ?></td><td><?= number_format($campaign['collected']) ?></td><td><a class="btn btn-sm" href="<?= url('admin/lucky-draw/'.$campaign['id']) ?>">Open</a></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</div>
