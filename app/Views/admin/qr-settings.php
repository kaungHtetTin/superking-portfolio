        <a class="btn" href="<?= e(url($previewPath)) ?>"><?= $hasPreview?'Back to preview':'Back to products' ?></a>
        <h2>QR print settings</h2>
        <p>Choose the paper layout and label content for single and batch QR printing.</p>
        <form id="qr-settings" class="panel crud-form qr-settings-panel" data-return-url="<?= e(url($previewPath)) ?>" data-preview="<?= $hasPreview?'true':'false' ?>" data-single="<?= $single?'true':'false' ?>">
            <div class="settings-heading"><h2>Layout and label options</h2><button class="btn" id="reset-settings" type="button">Reset settings</button></div>
            <p>Settings are remembered in this browser. Match the paper size in your printer and print at 100% scale with headers and footers off.</p>
            <div class="settings-grid">
                <label>QR size (mm)<input id="qr-size" name="qrSize" type="number" min="15" max="100" step="1" value="35" required></label>
                <label>QR codes per page<input id="per-page" name="perPage" type="number" min="1" max="100" step="1" value="<?= $single?1:6 ?>" required></label>
                <label>Copies per product<input id="copies" name="copies" type="number" min="1" max="100" step="1" value="1" required></label>
                <label>Page size<select id="paper-size" name="paper" required><option value="a4">A4 (210 x 297 mm)</option><option value="a5">A5 (148 x 210 mm)</option><option value="letter">Letter (216 x 279 mm)</option><option value="custom">Custom size</option></select></label>
                <label>Orientation<select id="orientation" name="orientation" required><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select></label>
                <label>Page margin (mm)<input id="page-margin" name="margin" type="number" min="5" max="30" step="1" value="10" required></label>
                <label class="custom-paper" hidden>Page width (mm)<input id="page-width" name="pageWidth" type="number" min="50" max="500" step="0.1" value="100" required disabled></label>
                <label class="custom-paper" hidden>Page height (mm)<input id="page-height" name="pageHeight" type="number" min="50" max="500" step="0.1" value="150" required disabled></label>
                <label>Label font size (pt)<input id="label-font" name="fontSize" type="number" min="8" max="16" step="1" value="10" required></label>
                <label class="caption-field">Label caption<input id="label-caption" name="caption" type="text" maxlength="100" value="Scan for product details"></label>
            </div>
            <fieldset class="label-options"><legend>Show on each label</legend>
                <label><input type="checkbox" name="showBrand" checked> Company name</label>
                <label><input type="checkbox" name="showName" checked> Product name</label>
                <label><input type="checkbox" name="showCode" checked> Product code</label>
                <label><input type="checkbox" name="showUrl" checked> Website address</label>
                <label><input type="checkbox" name="showBorder"> Label border</label>
            </fieldset>
            <div class="toolbar settings-actions"><a class="btn" href="<?= e(url($previewPath)) ?>">Cancel</a><button class="btn btn-primary" type="submit"><?= $hasPreview?'Save and return to preview':'Save settings' ?></button></div>
        </form>
        <p id="settings-status" role="status"></p>
        <noscript><p>Enable JavaScript to save QR print settings.</p></noscript>

<script src="<?= e(asset('js/qr-print-config.js').'?v='.substr(hash_file('sha256',BASE_PATH.'/public/assets/js/qr-print-config.js'),0,12)) ?>"></script>
<script src="<?= e(asset('js/qr-settings.js').'?v='.substr(hash_file('sha256',BASE_PATH.'/public/assets/js/qr-settings.js'),0,12)) ?>"></script>
