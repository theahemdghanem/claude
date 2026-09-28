<?php
/**
 * Manage Tags: add and remove blogger tags/categories.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Tags {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_cp_tag_add', array( __CLASS__, 'handle_add' ) );
		add_action( 'admin_post_cp_tag_delete', array( __CLASS__, 'handle_delete' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . CP_POST_TYPE,
			__( 'Manage Categories', 'hypeit' ),
			__( 'Manage Categories', 'hypeit' ),
			'manage_categories',
			'cp-tags',
			array( __CLASS__, 'render' )
		);
	}

	private static function page_url() {
		return admin_url( 'edit.php?post_type=' . CP_POST_TYPE . '&page=cp-tags' );
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
		wp_safe_redirect( add_query_arg( 'cp_added', $added, self::page_url() ) );
		exit;
	}

	public static function handle_delete() {
		$term_id = isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0;
		check_admin_referer( 'cp_tag_delete_' . $term_id );
		if ( $term_id && current_user_can( 'manage_categories' ) ) {
			wp_delete_term( $term_id, CP_Library::TAX_TAG );
		}
		wp_safe_redirect( add_query_arg( 'cp_deleted', 1, self::page_url() ) );
		exit;
	}

	public static function render() {
		$tags = get_terms( array( 'taxonomy' => CP_Library::TAX_TAG, 'hide_empty' => false, 'orderby' => 'name' ) );
		$tags = is_wp_error( $tags ) ? array() : $tags;

		if ( isset( $_GET['cp_added'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( /* translators: %d count. */ __( '%d categor(y/ies) added.', 'hypeit' ), absint( $_GET['cp_added'] ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( isset( $_GET['cp_deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Category deleted.', 'hypeit' ) . '</p></div>';
		}
		?>
		<div class="wrap cp-admin">
			<h1><?php esc_html_e( 'Manage Categories', 'hypeit' ); ?></h1>
			<p class="cp-sub"><?php esc_html_e( 'Categories you can assign to bloggers and use in smart lists.', 'hypeit' ); ?></p>

			<div class="cp-card">
				<h2 class="title"><?php esc_html_e( 'Add categories', 'hypeit' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cp_tag_add" />
					<?php wp_nonce_field( 'cp_tag_add', 'cp_tag_nonce' ); ?>
					<input type="text" name="cp_tag_names" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Skincare, Travel, Gaming', 'hypeit' ); ?>" />
					<?php submit_button( __( 'Add', 'hypeit' ), 'primary', 'submit', false ); ?>
					<p class="description"><?php esc_html_e( 'Separate multiple categories with commas.', 'hypeit' ); ?></p>
				</form>
			</div>

			<div class="cp-card">
				<h2 class="title"><?php esc_html_e( 'Your categories', 'hypeit' ); ?></h2>
				<?php if ( empty( $tags ) ) : ?>
					<p class="description"><?php esc_html_e( 'No tags yet.', 'hypeit' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead><tr>
							<th><?php esc_html_e( 'Category', 'hypeit' ); ?></th>
							<th><?php esc_html_e( 'Bloggers', 'hypeit' ); ?></th>
							<th><?php esc_html_e( 'Action', 'hypeit' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $tags as $t ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $t->name ); ?></strong></td>
								<td><?php echo (int) $t->count; ?></td>
								<td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cp_tag_delete&term=' . $t->term_id ), 'cp_tag_delete_' . $t->term_id ) ); ?>" style="color:#b3261e;" onclick="return confirm('<?php echo esc_js( __( 'Delete this category? It will be removed from all bloggers.', 'hypeit' ) ); ?>');"><?php esc_html_e( 'Delete', 'hypeit' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
