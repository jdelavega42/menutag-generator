{{--
    Product constants injected for the JS layer (contract 04 parity rule):
    the viewer and the Alpine configurator read THESE values — the same
    config/product.php and config/printers.php tables used by PHP and by the
    Python engine — never constants duplicated in JavaScript.
--}}
<script>
    window.menuTagProduct = @js([
        'size_min_mm' => config('product.size_min_mm'),
        'size_max_mm' => config('product.size_max_mm'),
        'thickness_min_mm' => config('product.thickness_min_mm'),
        'thickness_max_mm' => config('product.thickness_max_mm'),
        'qr' => config('product.qr'),
        'nfc' => config('product.nfc'),
        'graphics' => config('product.graphics'),
        'inlay' => config('product.inlay'),
        'materials' => config('product.materials'),
        'guests' => config('product.guests'),
        'plate' => config('product.plate'),
        'xy_comp_range_mm' => config('product.xy_comp_range_mm'),
        'printers' => config('printers.profiles'),
    ]);
</script>
