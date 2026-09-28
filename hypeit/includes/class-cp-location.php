<?php
/**
 * Location data (Country → City → Area) for onboarding and filtering.
 *
 * @package HypeIt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Location {

	/**
	 * Countries (extensible).
	 *
	 * @return array
	 */
	public static function countries() {
		return array(
			'Egypt' => __( 'Egypt', 'hypeit' ),
		);
	}

	/**
	 * Egypt cities / governorates.
	 *
	 * @return array
	 */
	public static function cities() {
		return array(
			'Cairo', 'Giza', 'Alexandria', 'Qalyubia', 'Port Said', 'Suez',
			'Dakahlia', 'Sharqia', 'Gharbia', 'Monufia', 'Beheira', 'Kafr El Sheikh',
			'Damietta', 'Ismailia', 'Faiyum', 'Beni Suef', 'Minya', 'Asyut', 'Sohag',
			'Qena', 'Luxor', 'Aswan', 'Red Sea', 'New Valley', 'Matrouh',
			'North Sinai', 'South Sinai',
		);
	}

	/**
	 * Predefined areas per city (where available). Others allow manual entry.
	 *
	 * @return array
	 */
	public static function areas_map() {
		return array(
			'Cairo'      => array( 'Nasr City', 'Heliopolis', 'Maadi', 'Zamalek', 'Downtown', 'New Cairo', 'Fifth Settlement', 'Rehab', 'Madinaty', 'Shubra', 'Ain Shams', 'El Marg', 'Mokattam', 'Helwan', 'Garden City', 'Manial', 'Abbassia', 'Sheraton', 'Obour' ),
			'Giza'       => array( 'Dokki', 'Mohandessin', 'Agouza', 'Haram', 'Faisal', 'Imbaba', '6th of October', 'Sheikh Zayed', 'Hadayek El Ahram', 'Warraq', 'Boulaq El Dakrour' ),
			'Alexandria' => array( 'Smouha', 'Sidi Gaber', 'Roushdy', 'Stanley', 'Gleem', 'San Stefano', 'Miami', 'Montaza', 'Sporting', 'Kafr Abdo', 'Agami', 'Borg El Arab', 'Mandara' ),
		);
	}

	/**
	 * Areas for a given city.
	 *
	 * @param string $city City.
	 * @return array
	 */
	public static function areas( $city ) {
		$map = self::areas_map();
		return isset( $map[ $city ] ) ? $map[ $city ] : array();
	}

	/**
	 * Cities that actually have bloggers assigned (for admin filtering).
	 *
	 * @return array
	 */
	public static function used_cities() {
		global $wpdb;
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' ORDER BY meta_value ASC",
				'_cp_city'
			)
		);
		return array_map( 'sanitize_text_field', (array) $rows );
	}
}
