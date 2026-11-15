<?php
$stage=manufacturing_stage();
$steps=['planned'=>['Local manufacturing project','ပြည်တွင်း ထုတ်လုပ်ရေးစီမံကိန်း'],'installation'=>['Factory installation','စက်ရုံ တပ်ဆင်မှု'],'commissioning'=>['Commissioning','စမ်းသပ်လည်ပတ်မှု'],'production'=>['Production','ထုတ်လုပ်မှု']];
?>
<div class="milestones"><div><span class="status completed"><?= t('Since 2015','၂၀၁၅ ခုနှစ်မှစ၍') ?></span><h3><?= t('UAE import & distribution','ယူအေအီးမှ တင်သွင်းဖြန့်ချိမှု') ?></h3></div><div><span class="status current"><?= t('Latest confirmed stage','နောက်ဆုံး အတည်ပြုအဆင့်') ?></span><h3><?= t(...$steps[$stage]) ?></h3></div><?php if($stage!=='production'): ?><div><span class="status"><?= t('Planned','စီစဉ်ထားသည်') ?></span><h3><?= t('First production','ပထမဆုံးထုတ်လုပ်မှု') ?></h3></div><?php endif; ?></div>
