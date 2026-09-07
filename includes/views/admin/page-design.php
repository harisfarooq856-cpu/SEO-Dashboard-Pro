<?php if ( ! defined('ABSPATH') ) exit;
$dv = fn(string $k, string $def='#ffffff') => esc_attr($d[$k] ?? $def);
?>
<div class="seo-page">
    <div class="seo-page-hd">
        <div>
            <h1 class="seo-page-title">🎨 Dashboard Design</h1>
            <p class="seo-page-subtitle">Customise the client dashboard appearance</p>
        </div>
        <div class="seo-page-actions">
            <button class="seo-btn seo-btn-ghost" id="seo-design-reset-btn">↺ Reset</button>
            <a href="<?php echo esc_url(get_permalink(get_option('seo_dash_client_page_id'))); ?>" target="_blank" class="seo-btn seo-btn-ghost">🌐 Preview</a>
            <button class="seo-btn seo-btn-primary" id="seo-design-save-btn">💾 Save Design</button>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;">

        <div class="seo-panel">
            <div class="seo-panel-hd"><h2>🌐 Global</h2></div>
            <div class="seo-panel-body" style="display:flex;flex-direction:column;gap:14px;">
                <?php seo_dash_color_field('page_bg','Page Background',$dv('page_bg','#f1f5f9')); ?>
                <?php seo_dash_color_field('primary_color','Primary / Accent',$dv('primary_color','#6366f1')); ?>
                <div class="seo-field">
                    <label>Font Family</label>
                    <select id="design-font_family" class="seo-in seo-in-sm" data-key="font_family">
                        <?php foreach ([''=>'Default','Inter'=>'Inter','Roboto'=>'Roboto','Open Sans'=>'Open Sans','Lato'=>'Lato','Poppins'=>'Poppins','Nunito'=>'Nunito'] as $v=>$l) : ?>
                        <option value="<?php echo esc_attr($v); ?>" <?php selected($d['font_family']??'',$v); ?>><?php echo esc_html($l); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="seo-panel">
            <div class="seo-panel-hd"><h2>🔝 Header & Nav</h2></div>
            <div class="seo-panel-body" style="display:flex;flex-direction:column;gap:14px;">
                <?php seo_dash_color_field('header_bg','Header Background',$dv('header_bg','#ffffff')); ?>
                <?php seo_dash_color_field('header_text','Header Text',$dv('header_text','#1e293b')); ?>
                <?php seo_dash_color_field('nav_bg','Tab Nav Background',$dv('nav_bg','#ffffff')); ?>
                <?php seo_dash_color_field('nav_text','Tab Text',$dv('nav_text','#64748b')); ?>
                <?php seo_dash_color_field('nav_active','Active Tab',$dv('nav_active','#6366f1')); ?>
            </div>
        </div>

        <div class="seo-panel">
            <div class="seo-panel-hd"><h2>📋 Tables</h2></div>
            <div class="seo-panel-body" style="display:flex;flex-direction:column;gap:14px;">
                <?php seo_dash_color_field('table_header_bg','Header BG',$dv('table_header_bg','#f8fafc')); ?>
                <?php seo_dash_color_field('table_header_text','Header Text',$dv('table_header_text','#64748b')); ?>
                <?php seo_dash_color_field('table_row_hover','Row Hover',$dv('table_row_hover','#f8fafc')); ?>
                <?php seo_dash_color_field('table_border','Border Color',$dv('table_border','#e2e8f0')); ?>
                <div class="seo-field">
                    <label>Font Size: <span id="table-fs-val"><?php echo intval($d['table_font_size']??13); ?>px</span></label>
                    <input type="range" min="10" max="16" value="<?php echo intval($d['table_font_size']??13); ?>"
                           oninput="document.getElementById('table-fs-val').textContent=this.value+'px';"
                           class="seo-design-slider" data-key="table_font_size" style="width:100%;accent-color:var(--c-primary);">
                    <input type="hidden" id="design-table_font_size" data-key="table_font_size" value="<?php echo intval($d['table_font_size']??13); ?>">
                </div>
            </div>
        </div>

        <div class="seo-panel">
            <div class="seo-panel-hd"><h2>🃏 Cards</h2></div>
            <div class="seo-panel-body" style="display:flex;flex-direction:column;gap:14px;">
                <?php seo_dash_color_field('card_bg','Card Background',$dv('card_bg','#ffffff')); ?>
                <?php seo_dash_color_field('card_border','Card Border',$dv('card_border','#e2e8f0')); ?>
                <div class="seo-field">
                    <label>Border Radius: <span id="card-r-val"><?php echo intval($d['card_radius']??12); ?>px</span></label>
                    <input type="range" min="0" max="24" value="<?php echo intval($d['card_radius']??12); ?>"
                           oninput="document.getElementById('card-r-val').textContent=this.value+'px';"
                           class="seo-design-slider" data-key="card_radius" style="width:100%;accent-color:var(--c-primary);">
                    <input type="hidden" id="design-card_radius" data-key="card_radius" value="<?php echo intval($d['card_radius']??12); ?>">
                </div>
            </div>
        </div>

        <div class="seo-panel">
            <div class="seo-panel-hd"><h2>🦶 Footer</h2></div>
            <div class="seo-panel-body" style="display:flex;flex-direction:column;gap:14px;">
                <?php seo_dash_color_field('footer_bg','Footer Background',$dv('footer_bg','#ffffff')); ?>
                <?php seo_dash_color_field('footer_color','Footer Text',$dv('footer_color','#94a3b8')); ?>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
(function($){
    // Sync color picker ↔ hex text
    $(document).on('input','.seo-color-swatch',function(){$(this).next('.seo-color-hex').val($(this).val());});
    $(document).on('input','.seo-color-hex',function(){
        var v=$(this).val();
        if(/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(v)) $(this).prev('.seo-color-swatch').val(v);
    });
    // Sync sliders
    $('.seo-design-slider').on('input',function(){
        var key=$(this).data('key');
        $('#design-'+key).val($(this).val());
    });
    // Collect
    function collectDesign(){
        var d={};
        $('.seo-color-hex').each(function(){d[$(this).data('key')]=$(this).val();});
        $('[id^="design-"]').each(function(){d[$(this).data('key')]=$(this).val();});
        $('select[data-key]').each(function(){d[$(this).data('key')]=$(this).val();});
        return d;
    }
    // Save
    $('#seo-design-save-btn').on('click',function(){
        var $btn=$(this).text('Saving…').prop('disabled',true);
        $.post(seoDash.ajax,{action:'seo_dash_save_design',nonce:seoDash.nonce,design:collectDesign()},function(r){
            $btn.text('💾 Save Design').prop('disabled',false);
            seoToast(r.data&&r.data.message?r.data.message:(r.success?'Design saved.':'Error.'),r.success?'ok':'err');
        });
    });
    // Reset
    $('#seo-design-reset-btn').on('click',function(){
        if(!confirm('Reset all design to defaults?')) return;
        $.post(seoDash.ajax,{action:'seo_dash_reset_design',nonce:seoDash.nonce},function(r){
            if(r.success) location.reload();
        });
    });
})(jQuery);
});
</script>

<?php
function seo_dash_color_field(string $key, string $label, string $value): void {
    echo '<div class="seo-field">';
    echo '<label>' . esc_html($label) . '</label>';
    echo '<div class="seo-color-row">';
    echo '<input type="color" class="seo-color-swatch" value="' . esc_attr($value) . '">';
    echo '<input type="text" class="seo-in seo-in-sm seo-color-hex" data-key="' . esc_attr($key) . '" value="' . esc_attr($value) . '" maxlength="25" style="width:120px;font-family:monospace;font-size:12px;">';
    echo '</div></div>';
}
