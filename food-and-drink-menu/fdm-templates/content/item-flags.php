<ul class="fdm-menu-item-flags">
	<?php foreach ( $this->flags as $flag ) : ?>
	<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fdm_format_classes() sanitizes every class token. ?>
	<li<?php echo fdm_format_classes( $flag->classes ); ?> title="<?php echo esc_attr( $flag->name ); ?>"><?php if ( isset( $flag->custom_icon_url ) ) : ?><img src="<?php echo esc_url( $flag->custom_icon_url ); ?>" alt=""><span class="screen-reader-text"><?php echo esc_html( $flag->name ); ?></span><?php elseif ( isset( $flag->text_only ) ) : echo esc_html( $flag->name ); else : ?><span class="screen-reader-text"><?php echo esc_html( $flag->name ); ?></span><?php endif; ?></li>
	<?php endforeach; ?>
</ul>
