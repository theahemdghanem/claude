<?php
/**
 * Blogger categories: add and remove the categories bloggers pick on the
 * onboarding form. Managed from a card on the Onboarding settings page (the
 * separate "Manage Categories" screen was folded in there).
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Tags {

	public static function init() {
		add_action( 'admin_post_cp_tag_add', array( __CLASS__, 'handle_add' ) );
		add_action( 'admin_post_cp_tag_delete', array( __CLASS__, 'handle_delete' ) );
		// Old "Manage Categories" links/bookmarks land on the new card.
		add_action( 'admin_page_access_denied', array( __CLASS__, 'redirect_old_page' ) );
	}

	/**
	 * Where categories are managed now.
	 *
	 * @return string
	 */
	public static function page_url() {
		return CP_Onboarding::settings_url( 'categories' );
	}

	/**
	 * Redirect the retired cp-tags screen.
	 */
	public static function redirect_old_page() {
		if ( isset( $_GET['page'] ) && 'cp-tags' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( self::page_url() );
			exit;
		}
	}

	public static function handle_add() {
		if ( ! isset( $_POST['cp_tag_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cp_tag_nonce'] ) ), 'cp_tag_add' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'hypeit' ) );
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'hypeit' ) );
		}
		$names = isset( $_POST['cp_tag_names'] ) ? (string) wp_unslash( $_POST['cp_tag_names'] ) : '';
		$added = 0;
		foreach ( array_filter( array_map( 'trim', explode( ',', $names ) ) ) as $name ) {
			$name = sanitize_text_field( $name );
			if ( '' === $name || term_exists( $name, CP_Library::TAX_TAG ) ) {
				continue;
			}
			$r = wp_insert_term( $name, CP_Library::TAX_TAG );
			if ( ! is_wp_error( $r ) ) {
				$added++;
			}
		}
		wp_safe_redirect( add_query_arg( 'cp_added', $added, admin_url( 'edit.php?post_type=' . CP_POST_TYPE . '&page=cp-onboarding' ) ) . '#categories' );
		exit;
	}

	public static function handle_delete() {
		$term_id = isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0;
		check_admin_referer( 'cp_tag_delete_' . $term_id );
		if ( $term_id && current_user_can( 'manage_categories' ) ) {
			wp_delete_term( $term_id, CP_Library::TAX_TAG );
		}
		wp_safe_redirect( add_query_arg( 'cp_deleted', 1, admin_url( 'edit.php?post_type=' . CP_POST_TYPE . '&page=cp-onboarding' ) ) . '#categories' );
		exit;
	}

	/**
	 * Categories card (Onboarding settings page).
	 */
	public static function render_card() {
		$tags = get_terms( array( 'taxonomy' => CP_Library::TAX_TAG, 'hide_empty' => false, 'orderby' => 'name' ) );
		$tags = is_wp_error( $tags ) ? array() : $tags;
		$can  = current_user_can( 'manage_categories' );
		$list = admin_url( 'edit.php?post_type=' . CP_Library::CPT );
		?>
		<section class="cpw-card cps-cats" id="categories">
			<h3><?php esc_html_e( 'Categories', 'hypeit' ); ?> <span class="cpw-pill"><?php echo (int) count( $tags ); ?></span></h3>
			<p class="cpw-muted"><?php esc_html_e( 'Bloggers choose from these on the form. You can also filter, build smart lists and read insights by category.', 'hypeit' ); ?></p>
			<?php if ( isset( $_GET['cp_added'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<p class="cps-flash"><?php echo esc_html( sprintf( /* translators: %d count. */ _n( '%d category added.', '%d categories added.', absint( $_GET['cp_added'] ), 'hypeit' ), absint( $_GET['cp_added'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?></p>
			<?php elseif ( isset( $_GET['cp_deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<p class="cps-flash"><?php esc_html_e( 'Category deleted.', 'hypeit' ); ?></p>
			<?php endif; ?>

			<?php if ( $tags ) : ?>
				<ul class="cps-catlist">
					<?php foreach ( $tags as $t ) : ?>
						<li>
							<a class="cps-catname" href="<?php echo esc_url( add_query_arg( CP_Library::TAX_TAG, $t->slug, $list ) ); ?>" title="<?php esc_attr_e( 'See these bloggers', 'hypeit' ); ?>"><?php echo esc_html( $t->name ); ?></a>
							<span class="cps-catn"><?php echo (int) $t->count; ?></span>
							<?php if ( $can ) : ?>
								<a class="cps-catdel" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cp_tag_delete&term=' . $t->term_id ), 'cp_tag_delete_' . $t->term_id ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: category. */ __( 'Delete %s', 'hypeit' ), $t->name ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this category? It will be removed from all bloggers.', 'hypeit' ) ); ?>');">✕</a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="cpw-empty"><?php esc_html_e( 'No categories yet.', 'hypeit' ); ?></p>
			<?php endif; ?>

			<?php if ( $can ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cps-catadd">
					<input type="hidden" name="action" value="cp_tag_add" />
					<?php wp_nonce_field( 'cp_tag_add', 'cp_tag_nonce' ); ?>
					<input type="text" name="cp_tag_names" placeholder="<?php esc_attr_e( 'e.g. Skincare, Travel, Gaming', 'hypeit' ); ?>" aria-label="<?php esc_attr_e( 'New categories', 'hypeit' ); ?>" />
					<button type="submit" class="button"><?php esc_html_e( 'Add', 'hypeit' ); ?></button>
				</form>
				<p class="cpw-muted"><?php esc_html_e( 'Separate several with commas.', 'hypeit' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
	}
}
