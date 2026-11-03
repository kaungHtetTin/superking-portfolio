<div class="panel crud-form"><h2>Check the original winning label</h2>
<form method="get" action="<?= url('admin/lucky-draw/redeem') ?>"><label for="code">Promotional code</label><input id="code" name="code" maxlength="40" required autocomplete="off" value="<?= e(input_text('code',100)) ?>" placeholder="Enter the code from the label"><button class="btn btn-primary" style="margin-top:12px">Check prize</button></form>
<?php if($ticket): ?><hr><h2><?= e($ticket['name']) ?></h2>
<?php if(!$ticket['revealed_at']): ?><p>This code has not been played. Ask the customer to scan their QR code and tap Reveal prize first.</p>
<?php elseif(!$ticket['prize_name']): ?><p>This code did not win a prize.</p>
<?php else: ?><h2><?= e($ticket['prize_name']) ?></h2><p><?= nl2br(e($ticket['instructions'])) ?></p>
<?php if($ticket['redeemed_at']): ?><p class="badge published">Already collected</p><p>Recorded at <?= e($ticket['redeemed_at']) ?> (<?= e(config('app.timezone','UTC')) ?>).</p>
<?php else: ?><p>Verify the original label. Hand over the item or pay the displayed cashback, then confirm below.</p><form method="post" action="<?= url('admin/lucky-draw/collect/'.$ticket['code']) ?>"><?= csrf_field() ?><button class="btn btn-primary">Confirm prize given</button></form><?php endif; ?>
<?php endif; ?><?php endif; ?></div>
