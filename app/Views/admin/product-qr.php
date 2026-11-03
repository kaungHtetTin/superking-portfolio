<?php $single=count($products)===1; $drafts=count(array_filter($products,fn($product)=>!$product['published'])); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($single?$products[0]['name']:'Selected products') ?> - Print QR labels</title>
    <link rel="stylesheet" href="<?= e(asset('css/product-qr.css').'?v='.substr(hash_file('sha256',BASE_PATH.'/public/assets/css/product-qr.css'),0,12)) ?>">
    <style id="qr-page-style">@page{size:A4 portrait;margin:0}</style>
</head>
<body>
    <header class="print-controls">
        <div class="toolbar">
            <a class="button" href="<?= e(url($returnTo)) ?>">Back to products</a>
            <?php if($single): ?><a class="button" href="<?= e(url('admin/products/'.$products[0]['id'].'/edit?'.http_build_query(['return_to'=>$returnTo]))) ?>">Edit product</a><?php endif; ?>
            <a class="button" href="<?= e(url('admin/products/qr-settings?'.http_build_query(['ids'=>implode(',',array_column($products,'id')),'mode'=>!empty($batch)?'batch':'single','return_to'=>$returnTo]))) ?>">QR print settings</a>
            <button class="button primary" id="print-qr" type="button" disabled><?= $single?'Print QR code':'Print QR labels' ?></button>
        </div>
        <h1><?= $single?'Product QR label':'Batch QR labels' ?></h1>
        <p><?= count($products) ?> product<?= $single?'':'s' ?> selected. Labels print in selection order, with copies grouped by product. Print or choose Save as PDF in the print dialog.</p>
        <?php if($drafts): ?><p class="notice" role="note"><?= $single?'This product is a draft. Publish it before distributing labels; visitors cannot open its detail page yet.':$drafts.' selected product(s) are drafts. Publish them before distributing labels; their detail pages are not publicly available yet.' ?></p><?php endif; ?>
        <p class="notice" id="local-url-notice" hidden>This link uses a local address. For customer labels, configure APP_URL with the public website address and reopen this page before printing.</p>

        <p id="qr-status" role="status">Preparing QR codes...</p>
        <noscript><p>Enable JavaScript to generate and print QR codes.</p></noscript>
    </header>
    <div id="qr-products" hidden data-batch="<?= !empty($batch)?'true':'false' ?>" data-brand="<?= e(settings()['company_name']) ?>">
        <?php foreach($products as $product): ?><div data-id="<?= $product['id'] ?>" data-name="<?= e($product['name']) ?>" data-code="<?= e($product['product_code']) ?>" data-url="<?= e(url('products/'.$product['id'])) ?>"></div><?php endforeach; ?>
    </div>
    <main id="qr-preview" aria-label="QR print preview"></main>
    <script src="<?= e(asset('js/qr-print-config.js').'?v='.substr(hash_file('sha256',BASE_PATH.'/public/assets/js/qr-print-config.js'),0,12)) ?>"></script>
    <script src="<?= e(asset('js/vendor/qrcodegen.js')) ?>"></script>
    <script src="<?= e(asset('js/product-qr.js').'?v='.substr(hash_file('sha256',BASE_PATH.'/public/assets/js/product-qr.js'),0,12)) ?>"></script>
</body>
</html>
