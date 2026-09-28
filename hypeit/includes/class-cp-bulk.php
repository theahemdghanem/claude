<?php
/**
 * Add Bloggers in bulk (paste many at once).
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Bulk {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_cp_bulk_add', array( __CLASS__, 'handle_add' ) );
	}

	/**
	 * Submenu.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . CP_POST_TYPE,
			__( 'Add Bloggers', 'hypeit' ),
			__( 'Add Bloggers', 'hypeit' ),
			'edit_posts',
			'cp-bulk',
			array( __CLASS__, 'render' )
		);
	}

	/** @return string */
	private static function page_url() {
		return admin_url( 'edit.php?post_type=' . CP_POST_TYPE . '&page=cp-bulk' );
	}

	/**
	 * Get-or-create a term, returning its ID. Public so the edit pages reuse it.
	 *
	 * @param string $name Term name.
	 * @param string $tax  Taxonomy.
	 * @return int
	 */
	public static function term_id( $name, $tax ) {
		$name = trim( $name );
		if ( '' === $name ) {
			return 0;
		}
		$term = get_term_by( 'name', $name, $tax );
		if ( $term ) {
			return (int) $term->term_id;
		}
		$new = wp_insert_term( $name, $tax );
		return is_wp_error( $new ) ? 0 : (int) $new['term_id'];
	}

	/**
	 * Clean an Instagram handle.
	 *
	 * @param string $raw Raw value.
	 * @return string
	 */
	private static function clean_handle( $raw ) {
		$raw = ltrim( trim( sanitize_text_field( $raw ) ), '@' );
		return preg_replace( '/[^A-Za-z0-9._]/', '', $raw );
	}

	/**
	 * Map of lowercase handle => post ID for existing library bloggers.
	 *
	 * @return array
	 */
	private static function existing_map() {
		$map = array();
		$ids = get_posts(
			array(
				'post_type'      => CP_Library::CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( (array) $ids as $id ) {
			$h = strtolower( CP_Library::handle( $id ) );
			if ( '' !== $h && ! isset( $map[ $h ] ) ) {
				$map[ $h ] = $id;
			}
		}
		return $map;
	}

	/**
	 * Process bulk add.
	 */
	public static function handle_add() {
		if ( ! isset( $_POST['cp_bulk_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cp_bulk_nonce'] ) ), 'cp_bulk_add' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'hypeit' ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'hypeit' ) );
		}

		$raw          = isset( $_POST['cp_bulk_text'] ) ? (string) wp_unslash( $_POST['cp_bulk_text'] ) : '';
		$update_exist = ! empty( $_POST['cp_update_existing'] );
		$def_gender   = isset( $_POST['cp_default_gender'] ) ? sanitize_key( wp_unslash( $_POST['cp_default_gender'] ) ) : '';

		$list_ids = isset( $_POST['cp_lists'] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_POST['cp_lists'] ) ) ) : array();
		$new_list = isset( $_POST['cp_new_list'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_new_list'] ) ) : '';
		if ( '' !== $new_list ) {
			$nid = self::term_id( $new_list, CP_Library::TAX_LIST );
			if ( $nid ) {
				$list_ids[] = $nid;
			}
		}

		$tag_names = array();
		if ( ! empty( $_POST['cp_tags'] ) ) {
			$tag_names = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['cp_tags'] ) ) ) ) );
		}

		$genders = CP_Library::genders();
		$created = 0;
		$updated = 0;
		$skipped = 0;

		$existing = self::existing_map();
		$lines    = preg_split( '/\r\n|\r|\n/', $raw );

		foreach ( (array) $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$parts  = array_map( 'trim', explode( ',', $line ) );
			$handle = self::clean_handle( isset( $parts[0] ) ? $parts[0] : '' );
			if ( '' === $handle ) {
				continue;
			}

			$followers = ( isset( $parts[1] ) && '' !== $parts[1] ) ? absint( preg_replace( '/[^0-9]/', '', $parts[1] ) ) : null;
			$gender    = ( isset( $parts[2] ) && '' !== $parts[2] ) ? strtolower( sanitize_key( $parts[2] ) ) : $def_gender;
			if ( ! array_key_exists( $gender, $genders ) ) {
				$gender = '';
			}
			$url = ( isset( $parts[3] ) && '' !== $parts[3] ) ? esc_url_raw( $parts[3] ) : '';

			$key = strtolower( $handle );
			$id  = isset( $existing[ $key ] ) ? $existing[ $key ] : 0;

			if ( $id && ! $update_exist ) {
				$skipped++;
				continue;
			}

			if ( ! $id ) {
				$id = wp_insert_post(
					array(
						'post_type'   => CP_Library::CPT,
						'post_status' => 'publish',
						'post_title'  => $handle,
					)
				);
				if ( is_wp_error( $id ) || ! $id ) {
					continue;
				}
				$existing[ $key ] = $id;
				update_post_meta( $id, '_cp_ig', $handle );
				$created++;
			} else {
				$updated++;
			}

			if ( null !== $followers ) {
				update_post_meta( $id, '_cp_followers', $followers );
			}
			if ( '' !== $gender ) {
				update_post_meta( $id, '_cp_gender', $gender );
			}
			if ( '' !== $url ) {
				update_post_meta( $id, '_cp_ig_url', $url );
			}

			if ( ! empty( $list_ids ) ) {
				wp_set_post_terms( $id, $list_ids, CP_Library::TAX_LIST, true );
			}
			if ( ! empty( $tag_names ) ) {
				wp_set_post_terms( $id, $tag_names, CP_Library::TAX_TAG, true );
			}

			if ( class_exists( 'CP_Lists' ) ) {
				CP_Lists::sync_blogger( $id );
			}
		}

		if ( class_exists( 'CP_Insights' ) ) {
			CP_Insights::bust();
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'cp_added'   => $created,
					'cp_updated' => $updated,
					'cp_skipped' => $skipped,
				),
				self::page_url()
			)
		);
		exit;
	}

	/**
	 * Render list checkboxes. Public so the edit pages reuse it.
	 *
	 * @param array $lists List terms.
	 */
	public static function lists_checkboxes( $lists ) {
		if ( empty( $lists ) ) {
			echo '<span class="description">' . esc_html__( 'No lists yet — create one below.', 'hypeit' ) . '</span>';
			return;
		}
		foreach ( $lists as $t ) {
			echo '<label style="display:inline-block;margin:0 14px 6px 0;"><input type="checkbox" name="cp_lists[]" value="' . (int) $t->term_id . '" /> ' . esc_html( $t->name ) . '</label>';
		}
	}

	/**
	 * Render the Add Bloggers page.
	 */
	public static function render() {
		$lists   = get_terms( array( 'taxonomy' => CP_Library::TAX_LIST, 'hide_empty' => false ) );
		$lists   = is_wp_error( $lists ) ? array() : $lists;
		$genders = CP_Library::genders();

		if ( isset( $_GET['cp_added'] ) || isset( $_GET['cp_updated'] ) || isset( $_GET['cp_skipped'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$a = isset( $_GET['cp_added'] ) ? absint( $_GET['cp_added'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$u = isset( $_GET['cp_updated'] ) ? absint( $_GET['cp_updated'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$s = isset( $_GET['cp_skipped'] ) ? absint( $_GET['cp_skipped'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
				sprintf(
					/* translators: 1: created, 2: updated, 3: skipped. */
					__( 'Bulk add complete: %1$d added, %2$d updated, %3$d skipped.', 'hypeit' ),
					$a,
					$u,
					$s
				)
			) . '</p></div>';
		}
		?>
		<div class="wrap cp-admin">
			<h1><?php esc_html_e( 'Add Bloggers', 'hypeit' ); ?></h1>
			<p class="description"><?php esc_html_e( 'One blogger per line. Optional extra fields, comma-separated: handle, followers, gender, profile URL. Example: nada, 12000, female', 'hypeit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cp_bulk_add" />
				<?php wp_nonce_field( 'cp_bulk_add', 'cp_bulk_nonce' ); ?>
				<textarea name="cp_bulk_text" rows="8" class="large-text code" placeholder="account_one&#10;account_two, 25000, female&#10;account_three, 8000, male, https://instagram.com/account_three/"></textarea>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Add to lists', 'hypeit' ); ?></th>
						<td>
							<?php self::lists_checkboxes( $lists ); ?>
							<p><input type="text" name="cp_new_list" class="regular-text" placeholder="<?php esc_attr_e( 'or type a new list name', 'hypeit' ); ?>" /></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cp_tags_add"><?php esc_html_e( 'Categories', 'hypeit' ); ?></label></th>
						<td><input type="text" id="cp_tags_add" name="cp_tags" class="regular-text" placeholder="Skincare, Travel, Gaming" />
						<p class="description"><?php esc_html_e( 'Comma-separated. New categories are created automatically.', 'hypeit' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="cp_default_gender"><?php esc_html_e( 'Default gender', 'hypeit' ); ?></label></th>
						<td>
							<select id="cp_default_gender" name="cp_default_gender">
								<option value=""><?php esc_html_e( '— Unspecified —', 'hypeit' ); ?></option>
								<?php foreach ( $genders as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="description"><?php esc_html_e( 'Used when a line does not specify a gender.', 'hypeit' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Existing handles', 'hypeit' ); ?></th>
						<td><label><input type="checkbox" name="cp_update_existing" value="1" checked /> <?php esc_html_e( 'Update bloggers that already exist (otherwise skip them)', 'hypeit' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button( __( 'Add bloggers', 'hypeit' ) ); ?>
			</form>
		</div>
		<?php
	}
}
