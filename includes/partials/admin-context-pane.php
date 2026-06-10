<?php
/**
 * Admin template editor — properties context pane markup.
 *
 * @package WC_Generic_Product_Designer
 * @var array $ps Plugin settings defaults.
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="wc-gpd-context-layer-name" id="wc-gpd-context-layer-name"></p>

<div class="wc-gpd-context-accordion is-open" id="wc-gpd-context-block-layer" data-context-for="text,image,shape,slot">
	<button type="button" class="wc-gpd-context-accordion__toggle" aria-expanded="true"><?php esc_html_e( 'Layer', 'wc-generic-product-designer' ); ?></button>
	<div class="wc-gpd-context-accordion__body">
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check">
			<label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_customer_editable" checked="checked" /> <?php esc_html_e( 'Customer can edit this layer', 'wc-generic-product-designer' ); ?></label>
		</div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check">
			<label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_show_in_customer_layers" checked="checked" /> <?php esc_html_e( 'Show in customer layers list', 'wc-generic-product-designer' ); ?></label>
		</div>
	</div>
</div>

<div class="wc-gpd-context-accordion is-open" id="wc-gpd-context-block-dims" data-context-for="all">
	<button type="button" class="wc-gpd-context-accordion__toggle" aria-expanded="true"><?php esc_html_e( 'Size & position', 'wc-generic-product-designer' ); ?></button>
	<div class="wc-gpd-context-accordion__body">
		<div class="wc-gpd-prop-row">
			<label class="wc-gpd-prop-label" for="wc_gpd_tpl_units"><?php esc_html_e( 'Units', 'wc-generic-product-designer' ); ?></label>
			<select id="wc_gpd_tpl_units" class="wc-gpd-prop-control">
				<option value="px">px</option>
				<option value="in">in</option>
				<option value="mm">mm</option>
				<option value="cm">cm</option>
			</select>
		</div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--pair">
			<div class="wc-gpd-prop-field">
				<label class="wc-gpd-prop-label" for="wc_gpd_sel_width"><?php esc_html_e( 'Width', 'wc-generic-product-designer' ); ?></label>
				<span class="wc-gpd-prop-input-wrap"><input type="number" id="wc_gpd_sel_width" class="wc-gpd-prop-control" min="0.01" step="0.01" /><span class="wc-gpd-unit-suffix" id="wc_gpd_unit_suffix_w">px</span></span>
			</div>
			<div class="wc-gpd-prop-field">
				<label class="wc-gpd-prop-label" for="wc_gpd_sel_height"><?php esc_html_e( 'Height', 'wc-generic-product-designer' ); ?></label>
				<span class="wc-gpd-prop-input-wrap"><input type="number" id="wc_gpd_sel_height" class="wc-gpd-prop-control" min="0.01" step="0.01" /><span class="wc-gpd-unit-suffix" id="wc_gpd_unit_suffix_h">px</span></span>
			</div>
		</div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--pair">
			<div class="wc-gpd-prop-field">
				<label class="wc-gpd-prop-label" for="wc_gpd_sel_left">X</label>
				<span class="wc-gpd-prop-input-wrap"><input type="number" id="wc_gpd_sel_left" class="wc-gpd-prop-control" step="0.01" /><span class="wc-gpd-unit-suffix" id="wc_gpd_unit_suffix_x">px</span></span>
			</div>
			<div class="wc-gpd-prop-field">
				<label class="wc-gpd-prop-label" for="wc_gpd_sel_top">Y</label>
				<span class="wc-gpd-prop-input-wrap"><input type="number" id="wc_gpd_sel_top" class="wc-gpd-prop-control" step="0.01" /><span class="wc-gpd-unit-suffix" id="wc_gpd_unit_suffix_y">px</span></span>
			</div>
		</div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check">
			<label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_lock_aspect" /> <?php esc_html_e( 'Lock aspect ratio (design time)', 'wc-generic-product-designer' ); ?></label>
		</div>
		<hr class="wc-gpd-prop-divider" />
		<p class="wc-gpd-prop-subheading"><?php esc_html_e( 'Customer access', 'wc-generic-product-designer' ); ?></p>
		<div class="wc-gpd-prop-customer-group" id="wc-gpd-customer-dims-text" data-customer-for="text" hidden>
			<div class="wc-gpd-prop-row wc-gpd-prop-row--check"><label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_allow_move" checked="checked" /> <?php esc_html_e( 'Customer can change position', 'wc-generic-product-designer' ); ?></label></div>
			<div class="wc-gpd-prop-row wc-gpd-prop-row--check"><label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_allow_resize" checked="checked" /> <?php esc_html_e( 'Customer can resize text box', 'wc-generic-product-designer' ); ?></label></div>
		</div>
		<div class="wc-gpd-prop-customer-group" id="wc-gpd-customer-dims-image" data-customer-for="image" hidden>
			<div class="wc-gpd-prop-row"><button type="button" class="button button-small" id="wc-gpd-set-mockup-background"><?php esc_html_e( 'Set as mockup background', 'wc-generic-product-designer' ); ?></button></div>
			<div class="wc-gpd-prop-row wc-gpd-prop-row--check" id="wc-gpd-image-mockup-visible-row" hidden><label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_template_mockup_visible" checked="checked" /> <?php esc_html_e( 'Show in customer mockup', 'wc-generic-product-designer' ); ?></label></div>
			<div class="wc-gpd-prop-row wc-gpd-prop-row--check"><label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_graphic_allow_move" checked="checked" /> <?php esc_html_e( 'Customer can move image', 'wc-generic-product-designer' ); ?></label></div>
			<div class="wc-gpd-prop-row wc-gpd-prop-row--check"><label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_graphic_allow_resize" checked="checked" /> <?php esc_html_e( 'Customer can resize image', 'wc-generic-product-designer' ); ?></label></div>
			<div class="wc-gpd-prop-row wc-gpd-prop-row--check"><label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_graphic_lock_aspect" /> <?php esc_html_e( 'Customer must keep aspect ratio', 'wc-generic-product-designer' ); ?></label></div>
		</div>
		<div class="wc-gpd-prop-customer-group" id="wc-gpd-customer-dims-shape" data-customer-for="shape" hidden>
			<div class="wc-gpd-prop-row wc-gpd-prop-row--check"><label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_shape_allow_move" checked="checked" /> <?php esc_html_e( 'Customer can move shape', 'wc-generic-product-designer' ); ?></label></div>
			<div class="wc-gpd-prop-row wc-gpd-prop-row--check"><label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_shape_allow_resize" checked="checked" /> <?php esc_html_e( 'Customer can resize shape', 'wc-generic-product-designer' ); ?></label></div>
		</div>
	</div>
</div>

<div class="wc-gpd-context-accordion is-open" id="wc-gpd-context-block-colors" data-context-for="text,shape" hidden>
	<button type="button" class="wc-gpd-context-accordion__toggle" id="wc-gpd-context-colors-toggle" aria-expanded="true"><?php esc_html_e( 'Color', 'wc-generic-product-designer' ); ?></button>
	<div class="wc-gpd-context-accordion__body">
		<div class="wc-gpd-prop-shape-appearance" id="wc-gpd-shape-appearance-panel" data-prop-for="shape" hidden>
			<div class="wc-gpd-prop-row wc-gpd-prop-row--check">
				<label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_shape_use_fill" /> <?php esc_html_e( 'Fill', 'wc-generic-product-designer' ); ?></label>
			</div>
			<div class="wc-gpd-prop-row wc-gpd-prop-row--check">
				<label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_shape_use_stroke" checked="checked" /> <?php esc_html_e( 'Outline', 'wc-generic-product-designer' ); ?></label>
			</div>
			<div class="wc-gpd-prop-row" id="wc-gpd-shape-stroke-width-row">
				<label class="wc-gpd-prop-label" for="wc_gpd_template_stroke_width"><?php esc_html_e( 'Line thickness', 'wc-generic-product-designer' ); ?></label>
				<input type="number" id="wc_gpd_template_stroke_width" class="wc-gpd-prop-control" min="0.1" max="20" step="0.1" value="<?php echo esc_attr( (string) $ps['outline_stroke_width'] ); ?>" />
			</div>
			<div class="wc-gpd-prop-row wc-gpd-prop-row--check">
				<label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_template_is_outline" checked="checked" /> <?php esc_html_e( 'Production outline (cut line)', 'wc-generic-product-designer' ); ?></label>
			</div>
			<div class="wc-gpd-prop-row wc-gpd-prop-row--check">
				<label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_template_product_outline" /> <?php esc_html_e( 'Product outline (export)', 'wc-generic-product-designer' ); ?></label>
			</div>
			<div class="wc-gpd-prop-row wc-gpd-prop-row--check">
				<label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_template_imprint_area" /> <?php esc_html_e( 'Max imprint / designable area', 'wc-generic-product-designer' ); ?></label>
			</div>
			<hr class="wc-gpd-prop-divider" />
		</div>
		<div class="wc-gpd-prop-row" id="wc-gpd-fill-colors-panel">
			<label class="wc-gpd-prop-label" for="wc_gpd_layer_palette_id" id="wc-gpd-fill-palette-label"><?php esc_html_e( 'Color palette for this layer', 'wc-generic-product-designer' ); ?></label>
			<select id="wc_gpd_layer_palette_id" class="wc-gpd-prop-control"></select>
		</div>
		<p id="wc-gpd-template-colors-active-notice" class="description wc-gpd-template-colors-notice" data-template-colors-lock hidden><?php esc_html_e( 'Template color settings are active. Per-layer palette options are disabled while “Use same colors on entire template” is enabled in Settings.', 'wc-generic-product-designer' ); ?></p>
		<div class="wc-gpd-prop-row" id="wc-gpd-layer-colors-list-row">
			<span class="wc-gpd-prop-label" id="wc-gpd-layer-colors-list-label"><?php esc_html_e( 'Colors', 'wc-generic-product-designer' ); ?></span>
			<div class="wc-gpd-layer-color-list" id="wc-gpd-layer-color-swatches"></div>
			<button type="button" class="button button-small" id="wc-gpd-layer-add-color" hidden><?php esc_html_e( 'Add color', 'wc-generic-product-designer' ); ?></button>
		</div>
		<div class="wc-gpd-prop-row" id="wc-gpd-stroke-colors-panel" hidden>
			<label class="wc-gpd-prop-label" for="wc_gpd_stroke_layer_palette_id"><?php esc_html_e( 'Outline color palette', 'wc-generic-product-designer' ); ?></label>
			<select id="wc_gpd_stroke_layer_palette_id" class="wc-gpd-prop-control"></select>
		</div>
		<div class="wc-gpd-prop-row" id="wc-gpd-stroke-colors-list-row" hidden>
			<span class="wc-gpd-prop-label" id="wc-gpd-stroke-colors-list-label"><?php esc_html_e( 'Outline colors', 'wc-generic-product-designer' ); ?></span>
			<div class="wc-gpd-layer-color-list" id="wc-gpd-stroke-layer-color-swatches"></div>
			<button type="button" class="button button-small" id="wc-gpd-stroke-layer-add-color" hidden><?php esc_html_e( 'Add color', 'wc-generic-product-designer' ); ?></button>
		</div>
		<hr class="wc-gpd-prop-divider" />
		<p class="wc-gpd-prop-subheading"><?php esc_html_e( 'Customer access', 'wc-generic-product-designer' ); ?></p>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check" id="wc-gpd-customer-color-text" data-customer-for="text" hidden>
			<label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_allow_color" checked="checked" /> <?php esc_html_e( 'Customer can change text color', 'wc-generic-product-designer' ); ?></label>
		</div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check" id="wc-gpd-customer-color-shape" data-customer-for="shape" hidden>
			<label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_shape_allow_color" checked="checked" /> <?php esc_html_e( 'Customer can change shape color', 'wc-generic-product-designer' ); ?></label>
		</div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check" id="wc-gpd-customer-palette-only-row" hidden>
			<label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_customer_color_palette_only" checked="checked" /> <?php esc_html_e( 'Customer limited to palette colors only', 'wc-generic-product-designer' ); ?></label>
			<p class="description"><?php esc_html_e( 'When off, customers get a full color picker.', 'wc-generic-product-designer' ); ?></p>
		</div>
	</div>
</div>

<div class="wc-gpd-context-accordion is-open" id="wc-gpd-context-block-text" data-context-for="text" hidden>
	<button type="button" class="wc-gpd-context-accordion__toggle" aria-expanded="true"><?php esc_html_e( 'Text', 'wc-generic-product-designer' ); ?></button>
	<div class="wc-gpd-context-accordion__body">
		<div class="wc-gpd-prop-row">
			<label class="wc-gpd-prop-label" for="wc_gpd_tpl_text_content"><?php esc_html_e( 'Text content', 'wc-generic-product-designer' ); ?></label>
			<textarea id="wc_gpd_tpl_text_content" class="wc-gpd-prop-control wc-gpd-tpl-text-content-input" rows="4" placeholder="<?php esc_attr_e( 'Type your text…', 'wc-generic-product-designer' ); ?>"></textarea>
			<p class="description"><?php esc_html_e( 'Edit here or double-click the text on the canvas.', 'wc-generic-product-designer' ); ?></p>
		</div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check">
			<label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_allow_text_edit" checked="checked" /> <?php esc_html_e( 'Customer can edit text on canvas', 'wc-generic-product-designer' ); ?></label>
		</div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check">
			<label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_text_customer_fills" /> <?php esc_html_e( 'Customer enters text in Details panel instead', 'wc-generic-product-designer' ); ?></label>
		</div>
		<div class="wc-gpd-prop-row">
			<label class="wc-gpd-prop-label" for="wc_gpd_text_layer_label"><?php esc_html_e( 'Layer label', 'wc-generic-product-designer' ); ?></label>
			<input type="text" id="wc_gpd_text_layer_label" class="wc-gpd-prop-control widefat" />
		</div>
		<div class="wc-gpd-prop-row">
			<label class="wc-gpd-prop-label" for="wc_gpd_placeholder_width"><?php esc_html_e( 'Text box width (px)', 'wc-generic-product-designer' ); ?></label>
			<input type="number" id="wc_gpd_placeholder_width" class="wc-gpd-prop-control" min="40" max="2000" value="240" />
		</div>
		<div class="wc-gpd-prop-row">
			<label class="wc-gpd-prop-label" for="wc_gpd_tpl_fit_mode"><?php esc_html_e( 'Fit to box', 'wc-generic-product-designer' ); ?></label>
			<select id="wc_gpd_tpl_fit_mode" class="wc-gpd-prop-control">
				<option value="none"><?php esc_html_e( 'None', 'wc-generic-product-designer' ); ?></option>
				<option value="horizontal"><?php esc_html_e( 'Horizontal', 'wc-generic-product-designer' ); ?></option>
				<option value="vertical"><?php esc_html_e( 'Vertical', 'wc-generic-product-designer' ); ?></option>
				<option value="both"><?php esc_html_e( 'Horizontal & vertical', 'wc-generic-product-designer' ); ?></option>
			</select>
		</div>
		<div class="wc-gpd-prop-row">
			<label class="wc-gpd-prop-label" for="wc_gpd_tpl_font_family"><?php esc_html_e( 'Font', 'wc-generic-product-designer' ); ?></label>
			<select id="wc_gpd_tpl_font_family" class="wc-gpd-prop-control wc-gpd-rich-font-select"></select>
		</div>
		<div class="wc-gpd-prop-row" id="wc-gpd-layer-fonts-panel" data-template-fonts-lock>
			<label class="wc-gpd-prop-label" for="wc_gpd_layer_font_palette_id"><?php esc_html_e( 'Font palette for this layer', 'wc-generic-product-designer' ); ?></label>
			<select id="wc_gpd_layer_font_palette_id" class="wc-gpd-prop-control"></select>
		</div>
		<p id="wc-gpd-template-fonts-active-notice" class="description wc-gpd-template-colors-notice" data-template-fonts-lock hidden><?php esc_html_e( 'Template font settings are active. Per-layer font palette options are disabled while “Use same fonts on entire template” is enabled in Settings.', 'wc-generic-product-designer' ); ?></p>
		<div class="wc-gpd-prop-row" id="wc-gpd-layer-fonts-list-row" data-template-fonts-lock>
			<span class="wc-gpd-prop-label"><?php esc_html_e( 'Allowed fonts', 'wc-generic-product-designer' ); ?></span>
			<div class="wc-gpd-font-palette-picks" id="wc-gpd-layer-font-picks"></div>
		</div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check">
			<label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_allow_font" checked="checked" /> <?php esc_html_e( 'Customer can change font', 'wc-generic-product-designer' ); ?></label>
		</div>
		<div class="wc-gpd-prop-row">
			<label class="wc-gpd-prop-label" for="wc_gpd_tpl_font_size"><?php esc_html_e( 'Font size', 'wc-generic-product-designer' ); ?></label>
			<input type="number" id="wc_gpd_tpl_font_size" class="wc-gpd-prop-control" min="8" max="400" value="32" />
		</div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check">
			<label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_allow_size" checked="checked" /> <?php esc_html_e( 'Customer can change font size', 'wc-generic-product-designer' ); ?></label>
		</div>
		<div class="wc-gpd-prop-row">
			<span class="wc-gpd-prop-label"><?php esc_html_e( 'Style', 'wc-generic-product-designer' ); ?></span>
			<div class="wc-gpd-prop-btn-group" aria-label="<?php esc_attr_e( 'Text style', 'wc-generic-product-designer' ); ?>">
				<button type="button" class="wc-gpd-rich-btn" id="wc_gpd_tpl_bold" title="<?php esc_attr_e( 'Bold', 'wc-generic-product-designer' ); ?>"><span class="dashicons dashicons-editor-bold"></span></button>
				<button type="button" class="wc-gpd-rich-btn" id="wc_gpd_tpl_italic" title="<?php esc_attr_e( 'Italic', 'wc-generic-product-designer' ); ?>"><span class="dashicons dashicons-editor-italic"></span></button>
				<button type="button" class="wc-gpd-rich-btn" id="wc_gpd_tpl_underline" title="<?php esc_attr_e( 'Underline', 'wc-generic-product-designer' ); ?>"><span class="dashicons dashicons-editor-underline"></span></button>
			</div>
		</div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check"><label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_allow_bold" checked="checked" /> <?php esc_html_e( 'Customer can use bold', 'wc-generic-product-designer' ); ?></label></div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check"><label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_allow_italic" checked="checked" /> <?php esc_html_e( 'Customer can use italic', 'wc-generic-product-designer' ); ?></label></div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check"><label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_allow_underline" checked="checked" /> <?php esc_html_e( 'Customer can use underline', 'wc-generic-product-designer' ); ?></label></div>
		<div class="wc-gpd-prop-row">
			<span class="wc-gpd-prop-label"><?php esc_html_e( 'Alignment', 'wc-generic-product-designer' ); ?></span>
			<span class="wc-gpd-tpl-align-group wc-gpd-prop-btn-group" role="group">
				<button type="button" class="wc-gpd-rich-btn wc-gpd-tpl-align" data-align="left" title="<?php esc_attr_e( 'Align left', 'wc-generic-product-designer' ); ?>"><span class="dashicons dashicons-editor-alignleft"></span></button>
				<button type="button" class="wc-gpd-rich-btn wc-gpd-tpl-align" data-align="center" title="<?php esc_attr_e( 'Align center', 'wc-generic-product-designer' ); ?>"><span class="dashicons dashicons-editor-aligncenter"></span></button>
				<button type="button" class="wc-gpd-rich-btn wc-gpd-tpl-align" data-align="right" title="<?php esc_attr_e( 'Align right', 'wc-generic-product-designer' ); ?>"><span class="dashicons dashicons-editor-alignright"></span></button>
			</span>
		</div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check"><label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_allow_align" checked="checked" /> <?php esc_html_e( 'Customer can change alignment', 'wc-generic-product-designer' ); ?></label></div>
		<div class="wc-gpd-prop-row">
			<label class="wc-gpd-prop-label"><?php esc_html_e( 'Line height', 'wc-generic-product-designer' ); ?></label>
			<div class="wc-gpd-stepper">
				<button type="button" class="wc-gpd-stepper-btn" data-stepper="line_height" data-dir="-1" aria-label="<?php esc_attr_e( 'Decrease line height', 'wc-generic-product-designer' ); ?>">−</button>
				<span class="wc-gpd-stepper-val" id="wc_gpd_tpl_line_height_display">1.16</span>
				<button type="button" class="wc-gpd-stepper-btn" data-stepper="line_height" data-dir="1" aria-label="<?php esc_attr_e( 'Increase line height', 'wc-generic-product-designer' ); ?>">+</button>
				<input type="hidden" id="wc_gpd_tpl_line_height" value="1.16" />
			</div>
		</div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check"><label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_allow_line_height" checked="checked" /> <?php esc_html_e( 'Customer can change line height', 'wc-generic-product-designer' ); ?></label></div>
		<div class="wc-gpd-prop-row">
			<label class="wc-gpd-prop-label"><?php esc_html_e( 'Letter spacing', 'wc-generic-product-designer' ); ?></label>
			<div class="wc-gpd-stepper">
				<button type="button" class="wc-gpd-stepper-btn" data-stepper="letter_spacing" data-dir="-1" aria-label="<?php esc_attr_e( 'Decrease letter spacing', 'wc-generic-product-designer' ); ?>">−</button>
				<span class="wc-gpd-stepper-val" id="wc_gpd_tpl_letter_spacing_display">0</span>
				<button type="button" class="wc-gpd-stepper-btn" data-stepper="letter_spacing" data-dir="1" aria-label="<?php esc_attr_e( 'Increase letter spacing', 'wc-generic-product-designer' ); ?>">+</button>
				<input type="hidden" id="wc_gpd_tpl_letter_spacing" value="0" />
			</div>
		</div>
		<div class="wc-gpd-prop-row wc-gpd-prop-row--check"><label class="wc-gpd-prop-check"><input type="checkbox" id="wc_gpd_allow_letter_spacing" checked="checked" /> <?php esc_html_e( 'Customer can change letter spacing', 'wc-generic-product-designer' ); ?></label></div>
	</div>
</div>

<div class="wc-gpd-context-accordion is-open" id="wc-gpd-context-block-image" data-context-for="image" hidden>
	<button type="button" class="wc-gpd-context-accordion__toggle" aria-expanded="true"><?php esc_html_e( 'Image', 'wc-generic-product-designer' ); ?></button>
	<div class="wc-gpd-context-accordion__body">
		<div class="wc-gpd-tpl-selection" id="wc-gpd-image-props">
			<div class="wc-gpd-prop-row"><button type="button" class="button button-link-delete" id="wc-gpd-template-delete-image"><?php esc_html_e( 'Remove image', 'wc-generic-product-designer' ); ?></button></div>
		</div>
	</div>
</div>

<div class="wc-gpd-context-accordion is-open" id="wc-gpd-context-block-export-part" data-context-for="all">
	<button type="button" class="wc-gpd-context-accordion__toggle" aria-expanded="true"><?php esc_html_e( 'Document part', 'wc-generic-product-designer' ); ?></button>
	<div class="wc-gpd-context-accordion__body">
		<p class="description"><?php esc_html_e( 'Tag each layer so production and proof downloads can include or omit backgrounds, engraving art, and outlines.', 'wc-generic-product-designer' ); ?></p>
		<div class="wc-gpd-prop-row">
			<label class="wc-gpd-prop-label" for="wc_gpd_export_part"><?php esc_html_e( 'Export as', 'wc-generic-product-designer' ); ?></label>
			<select id="wc_gpd_export_part" class="wc-gpd-prop-control">
				<option value="engraving"><?php esc_html_e( 'Engraving design', 'wc-generic-product-designer' ); ?></option>
				<option value="backdrop"><?php esc_html_e( 'Backdrop / mockup photo', 'wc-generic-product-designer' ); ?></option>
				<option value="product_outline"><?php esc_html_e( 'Product outline', 'wc-generic-product-designer' ); ?></option>
				<option value="cut_outline"><?php esc_html_e( 'Cut / imprint outline', 'wc-generic-product-designer' ); ?></option>
				<option value="exclude"><?php esc_html_e( 'Exclude from downloads', 'wc-generic-product-designer' ); ?></option>
			</select>
		</div>
	</div>
</div>

<div class="wc-gpd-context-accordion is-open" id="wc-gpd-context-block-slot" data-context-for="slot" hidden>
	<button type="button" class="wc-gpd-context-accordion__toggle" aria-expanded="true"><?php esc_html_e( 'Replaceable slot', 'wc-generic-product-designer' ); ?></button>
	<div class="wc-gpd-context-accordion__body">
		<div class="wc-gpd-tpl-selection" id="wc-gpd-graphic-slot-props">
			<div class="wc-gpd-prop-row">
				<label class="wc-gpd-prop-label" for="wc_gpd_slot_library_id"><?php esc_html_e( 'Graphic library', 'wc-generic-product-designer' ); ?></label>
				<select id="wc_gpd_slot_library_id" class="wc-gpd-prop-control"></select>
			</div>
			<p class="description"><?php esc_html_e( 'Position and size are locked. Customers click this slot on the canvas to replace its content with a graphic from the assigned library.', 'wc-generic-product-designer' ); ?></p>
			<div class="wc-gpd-prop-row"><button type="button" class="button button-link-delete" id="wc-gpd-template-delete-slot"><?php esc_html_e( 'Remove replaceable slot', 'wc-generic-product-designer' ); ?></button></div>
		</div>
	</div>
</div>
