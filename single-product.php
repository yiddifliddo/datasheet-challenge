<?php get_header(); ?>

<div class="product-detail-container">
	<?php if ( function_exists( 'abs_breadcrumbs_output' ) ) abs_breadcrumbs_output(); ?>
    <section class="section top-section">
        <div class="section-content">
            <div class="image-document-column">
                <?php
                $listing_image = get_field('listing_image');

                if( $listing_image ):
                    ?>
                        <div class="single-slide">
                            <img class="single-slide-image" src="<?php echo esc_url($listing_image['url']); ?>" alt="<?php echo esc_attr($listing_image['alt']); ?>" title="<?php echo esc_attr($listing_image['title']); ?>">
                            
                            <?php if( have_rows('related_images') ): ?>
                                <?php while( have_rows('related_images') ): the_row(); 
                                    $image = get_sub_field('image');
                                    if( $image ): ?>
                                        <img class="single-slide-image" src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" title="<?php echo esc_attr($image['title']); ?>">
                                    <?php endif; ?>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>

                        <div class="slider-nav">
                            <div class="image-thumbnail">
                                <img src="<?php echo esc_url($listing_image['sizes']['thumbnail']); ?>" alt="<?php echo esc_attr($listing_image['alt']); ?>" title="<?php echo esc_attr($listing_image['title']); ?>">
                            </div>

                            <?php if( have_rows('related_images') ): ?>
                                <?php while( have_rows('related_images') ): the_row(); 
                                    $image = get_sub_field('image');
                                    if( $image ): ?>
                                        <div class="image-thumbnail">
                                            <img src="<?php echo esc_url($image['sizes']['thumbnail']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" title="<?php echo esc_attr($image['title']); ?>">
                                        </div>
                                    <?php endif; ?>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                    <?php
                endif;
                ?>

                <?php
                $deg_gate_enabled = function_exists('deg_is_enabled') && deg_is_enabled();
                if( have_rows('related_documents_or_other_files') ): ?>
                    <div class="document-dropdown-div">
                        <button class="button js-document-dropdown">
                            <div class="text-div">View / Print Product Information</div>
                            <div class="icon-div">
                                <svg class="icon icon-document-download"><use xlink:href="#icon-document-download"></use></svg>
                            </div>
                            <div class="arrow-div"></div>
                        </button>

                        <div class="dropdown-body" style="display: none;">
                            <?php while( have_rows('related_documents_or_other_files') ): the_row();
                                $label = get_sub_field('label');
                                $file = get_sub_field('file');
                                if( $file ): ?>
                                    <?php if ( $deg_gate_enabled ): ?>
                                        <a class="document-link dropdown-item js-document-gate" href="#" data-file-url="<?php echo esc_url($file['url']); ?>" data-file-label="<?php echo esc_attr($label); ?>">
                                            <?php echo esc_html($label); ?>
                                        </a>
                                    <?php else: ?>
                                        <a class="document-link dropdown-item" href="<?php echo esc_url($file['url']); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo esc_html($label); ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( $deg_gate_enabled ):
                    $deg_form_id       = deg_get_form_id();
                    $deg_hidden_field  = deg_get_hidden_field_name();
                    $deg_modal_heading = esc_html( get_option( 'deg_modal_heading', 'Enter your email to receive this document' ) );
                ?>
                <!-- Email Gate Modal -->
                <div id="document-email-modal" class="document-email-modal" style="display:none;">
                    <div class="document-email-modal-overlay"></div>
                    <div class="document-email-modal-content">
                        <button class="document-email-modal-close" aria-label="Close">&times;</button>
                        <h3><?php echo $deg_modal_heading; ?></h3>
                        <p class="document-email-modal-label"></p>
                        <?php echo do_shortcode('[gravityform id="' . $deg_form_id . '" title="false" description="false" ajax="true"]'); ?>
                    </div>
                </div>

                <style>
                    .document-email-modal {
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        z-index: 99999;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .document-email-modal-overlay {
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0,0,0,0.45);
                    }
                    .document-email-modal-content {
                        position: relative;
                        background: #ffffff;
                        padding: 40px 35px 35px;
                        border-radius: 4px;
                        max-width: 480px;
                        width: 90%;
                        z-index: 1;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                        border-top: 3px solid #0078BF;
                    }
                    .document-email-modal-content h3 {
                        color: #333333;
                        font-size: 20px;
                        font-weight: 700;
                        margin: 0 0 8px;
                        line-height: 1.3;
                    }
                    .document-email-modal-close {
                        position: absolute;
                        top: 12px;
                        right: 15px;
                        background: none;
                        border: none;
                        font-size: 22px;
                        cursor: pointer;
                        color: #999;
                        transition: color 0.2s;
                    }
                    .document-email-modal-close:hover {
                        color: #333;
                    }
                    .document-email-modal-label {
                        font-weight: 600;
                        color: #0078BF;
                        font-size: 14px;
                        margin-bottom: 20px;
                    }
                    /* Gravity Form overrides inside the modal */
                    .document-email-modal-content .gform_wrapper input[type="email"],
                    .document-email-modal-content .gform_wrapper input[type="text"] {
                        border: 1px solid #ddd;
                        border-radius: 3px;
                        padding: 10px 12px;
                        font-size: 14px;
                        width: 100%;
                        color: #333;
                        background: #f9f9f9;
                    }
                    .document-email-modal-content .gform_wrapper input[type="email"]:focus,
                    .document-email-modal-content .gform_wrapper input[type="text"]:focus {
                        border-color: #0078BF;
                        outline: none;
                        background: #fff;
                    }
                    .document-email-modal-content .gform_wrapper .gform_button,
                    .document-email-modal-content .gform_wrapper input[type="submit"] {
                        background: #0078BF;
                        color: #ffffff;
                        border: none;
                        border-radius: 3px;
                        padding: 12px 28px;
                        font-size: 14px;
                        font-weight: 600;
                        cursor: pointer;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        transition: background 0.2s;
                        width: 100%;
                    }
                    .document-email-modal-content .gform_wrapper .gform_button:hover,
                    .document-email-modal-content .gform_wrapper input[type="submit"]:hover {
                        background: #005f99;
                    }
                    .document-email-modal-content .gform_wrapper .gfield_label {
                        color: #333;
                        font-size: 13px;
                        font-weight: 600;
                    }
                    .document-email-modal-content .gform_wrapper .validation_message {
                        color: #cc0000;
                        font-size: 12px;
                    }
                    .document-email-modal-content .gform_confirmation_message {
                        color: #333;
                        font-size: 15px;
                        text-align: center;
                        padding: 20px 0;
                    }
                </style>

                <script>
                (function() {
                    var modal = document.getElementById('document-email-modal');
                    if (!modal) return;
                    var modalLabel = modal.querySelector('.document-email-modal-label');
                    var closeBtn = modal.querySelector('.document-email-modal-close');
                    var overlay = modal.querySelector('.document-email-modal-overlay');
                    var hiddenFieldName = '<?php echo esc_js( $deg_hidden_field ); ?>';
                    var gateFormId = <?php echo (int) $deg_form_id; ?>;

                    document.querySelectorAll('.js-document-gate').forEach(function(link) {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            var fileUrl = this.getAttribute('data-file-url');
                            var fileLabel = this.getAttribute('data-file-label');

                            modalLabel.textContent = fileLabel;

                            var hiddenField = modal.querySelector('input[name="' + hiddenFieldName + '"]');
                            if (hiddenField) {
                                hiddenField.value = fileUrl;
                            }

                            modal.style.display = 'flex';
                        });
                    });

                    closeBtn.addEventListener('click', function() {
                        modal.style.display = 'none';
                    });
                    overlay.addEventListener('click', function() {
                        modal.style.display = 'none';
                    });

                    if (typeof jQuery !== 'undefined') {
                        jQuery(document).on('gform_confirmation_loaded', function(event, formId) {
                            if (formId == gateFormId) {
                                setTimeout(function() {
                                    modal.style.display = 'none';
                                }, 3000);
                            }
                        });
                    }
                })();
                </script>
                <?php endif; ?>
            </div>
            <div class="title-description-column">
                <h1 class="product-title"><?php the_field('product_title'); ?></h1>
                <div class="product-number"> Product Number: <span><?php the_field('product_id'); ?></span>
                    <?php if (get_field('is_new_product')) : ?>
                        <span class="new-product-badge"><span class="star">★</span> NEW PRODUCT</span>
                    <?php endif; ?>
					<?php if ( function_exists( 'abs_available_sizes_output' ) ) abs_available_sizes_output(); ?>
                </div>
				
                <div class="product-summary"><?php the_field('summary'); ?></div>
				
				<?php echo do_shortcode('[quote_button]'); ?>
				
                <div class="product-quote">
                    <?php /*<a href="/request-a-quote" class="product-quote-button button">Request a Quote</a>*/ ?>
					<a href="#product_inquiry_div" class="i_scroll_to product_inquiry_button button is-outline primary">Product Inquiry</a>
                </div>
            </div>
        </div>
    </section>
    <section class="section">
        <div class="section-content relative">
            <div class="row">
                <div class="col small-12 large-12">
                    <div class="col-inner">
                        <div class="tab-panel-section" id="tab-parent">
                            <div class="tab-container">
                                <?php $maxTabs = 3; ?>
                                <?php for ($i = 1; $i <= $maxTabs; $i++): ?>
                                    <?php if (get_field("tab_${i}_title")): ?>
                                        <a class="tab <?php echo $i === 1 ? 'active' : ''; ?>" href="#tab-pane<?php echo $i; ?>" data-toggle="tab" data-parent="tab-parent" data-target="tab-pane<?php echo $i; ?>" id="data-toggle-<?php echo $i - 1; ?>" role="button" aria-haspopup="true" aria-expanded="<?php echo $i === 1 ? 'true' : 'false'; ?>">
                                            <span><?php the_field("tab_${i}_title"); ?></span>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            <div class="panel-container">
                                <?php for ($i = 1; $i <= $maxTabs; $i++): ?>
                                    <?php if (get_field("tab_${i}_content")): ?>
                                        <div class="panel <?php echo $i === 1 ? 'active' : ''; ?>" id="tab-pane<?php echo $i; ?>" aria-labelledby="data-toggle-<?php echo $i - 1; ?>">
                                            <?php the_field("tab_${i}_content"); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
				
					<?php
		$related_products = get_field('related_products');

		if ( $related_products ) : ?>
			<section class="section related-products-section">
				<div class="section-content">
					<h2 class="related-products-title">Related Products</h2>

					<div class="related-products-carousel">
						<?php foreach ( $related_products as $post ) : setup_postdata( $post ); ?>
							<?php
								$listing_image = get_field('listing_image');
								$product_id    = get_field('product_id');
							?>
							<div class="related-product-item">
								<a href="<?php the_permalink(); ?>" class="related-product-link">
									<div class="related-product-image-wrap">
										<?php if ( $listing_image ) : ?>
											<img src="<?php echo esc_url( $listing_image['sizes']['icetro-251'] ?? $listing_image['url'] ); ?>"
												 alt="<?php echo esc_attr( $listing_image['alt'] ); ?>">
										<?php endif; ?>
									</div>
									<div class="related-product-content">
										<div class="related-product-title"><?php the_title(); ?></div>
										<?php if ( $product_id ) : ?>
											<div class="related-product-number">Product Number: <span><?php echo esc_html( $product_id ); ?></span></div>
										<?php endif; ?>
									</div>
								</a>
							</div>
						<?php endforeach; ?>
						<?php wp_reset_postdata(); ?>
					</div>
				</div>
			</section>
		<?php endif; ?>
				
				
                <div class="col small-12 large-12">
                    <div class="col-inner">
					<?php
					//if( $_SERVER['REMOTE_ADDR'] == '5.77.201.39' ){}
					?>
						<div id="product_inquiry_div" class="product_inquiry_div">
						<h2>Inquire About This Product</h2>
						<?php 
						echo do_shortcode('[gravityform id="5" title="false" description="false" ajax="false"]');
						?>
						</div>
					</div>
				</div>
            </div>
        </div>
    </section>
</div>

<?php get_footer(); ?>