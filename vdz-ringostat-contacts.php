<?php
/*
Plugin Name: VDZ Ringostat Contacts
Plugin URI:  http://online-services.org.ua
Description: Интеграция Ringostat + Contact From 7
Version:     1.4.3
Author:      VadimZ
Author URI:  http://online-services.org.ua#vdz-ringostat-contacts
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VDZ_RC_API', 'vdz_info_ringostat_contacts' );

require_once 'api.php';
require_once 'updated_plugin_admin_notices.php';

// Код активации плагина
register_activation_hook( __FILE__, 'vdz_rc_activate_plugin' );
function vdz_rc_activate_plugin() {
	global $wp_version;
	if ( version_compare( $wp_version, '3.8', '<' ) ) {
		// Деактивируем плагин
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( 'This plugin required WordPress version 3.8 or higher' );
	}
	add_option( 'vdz_ringostat_contacts_secret_key', '' );
	add_option( 'vdz_ringostat_contacts_on', 1 );

	do_action( VDZ_RC_API, 'on', plugin_basename( __FILE__ ) );
}

// Код деактивации плагина
register_deactivation_hook( __FILE__, function () {
	$plugin_name = preg_replace( '|\/(.*)|', '', plugin_basename( __FILE__ ));
	$response = wp_remote_get( "http://api.online-services.org.ua/off/{$plugin_name}" );
	if ( ! is_wp_error( $response ) && isset( $response['body'] ) && ( json_decode( $response['body'] ) !== null ) ) {
		//TODO Вывод сообщения для пользователя
	}
} );
//Сообщение при отключении плагина
add_action( 'admin_init', function (){
	if(is_admin()){
		$plugin_data = get_plugin_data(__FILE__);
		$plugin_name = isset($plugin_data['Name']) ? $plugin_data['Name'] : ' us';
		$plugin_dir_name = preg_replace( '|\/(.*)|', '', plugin_basename( __FILE__ ));
		$handle = 'admin_'.$plugin_dir_name;
		wp_register_script( $handle, '', null, false, true );
		wp_enqueue_script( $handle );
		$msg = '';
		if ( function_exists( 'get_locale' ) && in_array( get_locale(), array( 'uk', 'ru_RU' ), true ) ) {
			$msg .= "Спасибо, что были с нами! ({$plugin_name}) Хорошего дня!";
		}else{
			$msg .= "Thanks for your time with us! ({$plugin_name}) Have a nice day!";
		}
		wp_add_inline_script( $handle, "document.getElementById('deactivate-".esc_attr($plugin_dir_name)."').onclick=function (e){alert('".esc_attr( $msg )."');}" );
	}
} );




/*Добавляем новые поля для в настройках шаблона шаблона для верификации сайта*/
function vdz_rc_theme_customizer( $wp_customize ) {

	if ( ! class_exists( 'WP_Customize_Control' ) ) {
		exit;
	}

	// Добавляем секцию для идетнтификатора YS
	$wp_customize->add_section(
		'vdz_ringostat_contacts_section',
		array(
			'title'    => __( 'VDZ Ringostat Contacts' ),
			'priority' => 10,
		// 'description' => __( 'Ringostat Contacts code on your site' ),
		)
	);
	// Добавляем настройки
	$wp_customize->add_setting(
		'vdz_ringostat_contacts_secret_key',
		array(
			'type'              => 'option',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_setting(
		'vdz_ringostat_contacts_on',
		array(
			'type'              => 'option',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	// Ringostat SECRET KEY
	$wp_customize->add_control(
		new WP_Customize_Control(
			$wp_customize,
			'vdz_ringostat_contacts_secret_key',
			array(
				'label'       => __( 'Ringostat SECRET KEY' ),
				'section'     => 'vdz_ringostat_contacts_section',
				'settings'    => 'vdz_ringostat_contacts_secret_key',
				'type'        => 'text',
				'description' => __( 'Нужно вставить секретный ключ. Чтобы скопировать этот ключ, откройте Каталог интеграций -> CRM -> Настроить интеграцию. Секретный ключ находится в нижней части экрана в строке Ключ для интеграции.' ),
				'input_attrs' => array(
					'placeholder' => 'XXXXXXXX', // для примера
				),
			)
		)
	);
	// Footer OR HEAD
	$wp_customize->add_control(
		new WP_Customize_Control(
			$wp_customize,
			'vdz_ringostat_contacts_on',
			array(
				'label'       => __( 'VDZ Ringostat Contacts' ),
				'section'     => 'vdz_ringostat_contacts_section',
				'settings'    => 'vdz_ringostat_contacts_on',
				'type'        => 'select',
				'description' => __( 'ON/OFF' ),
				'choices'     => array(
					1 => __( 'Show' ),
					0 => __( 'Hide' ),
				),
			)
		)
	);

	// Добавляем ссылку на сайт
	$wp_customize->add_setting(
		'vdz_ringostat_contacts_link',
		array(
			'type' => 'option',
		)
	);
	$wp_customize->add_control(
		new WP_Customize_Control(
			$wp_customize,
			'vdz_ringostat_contacts_link',
			array(
				// 'label'    => __( 'Link' ),
									'section' => 'vdz_ringostat_contacts_section',
				'settings'                    => 'vdz_ringostat_contacts_link',
				'type'                        => 'hidden',
				'description'                 => '<br/><a href="//online-services.org.ua#vdz-ringostat-contacts" target="_blank">VadimZ</a>',
			)
		)
	);
}
add_action( 'customize_register', 'vdz_rc_theme_customizer', 1 );


function vzd_rc_send_to_roistat( $WPCF7_contact_form ) {
	if ( ! (int) get_option( 'vdz_ringostat_contacts_on' ) ) {
		return;
	}
	$form_title = $WPCF7_contact_form->title();
	$submission = WPCF7_Submission::get_instance();
	if ( null !== $submission ) {
		$posted_data = $submission->get_posted_data();
		if ( ! empty( $posted_data ) ) {
			$name    = null;
			$phone   = null;
			$email   = null;
			$comment = null;

			foreach ( $posted_data as $field_name => $value ) {
				if ( in_array(
					$field_name,
					array(
						'your-name',
						'name',
						'fio',
					),
					true
				) ) {
					$name = sanitize_text_field( $value );
				}
				if ( in_array(
					$field_name,
					array(
						'your-phone',
						'phone',
						'tel',
						'telephone',
					),
					true
				)
					|| substr_count( $field_name, 'tel-' )
				) {
					$phone = sanitize_text_field( $value );
				}
				if ( in_array(
					$field_name,
					array(
						'your-email',
						'email',
					),
					true
				)
				|| substr_count( $field_name, 'email-' )
				) {
					$email = sanitize_email( $value );
				}
				if ( in_array(
					$field_name,
					array(
						'your-message',
						'message',
						'comment',
						'msg',
					),
					true
				)
				|| substr_count( $field_name, 'textarea-' )
				) {
					$comment = sanitize_text_field( $value );
				}
			}

			$roistat_data = array(
				'roistat' => isset( $_COOKIE['roistat_visit'] ) ? sanitize_text_field( $_COOKIE['roistat_visit'] ) : null,
				'key'     => sanitize_text_field( get_option( 'vdz_ringostat_contacts_on' ) ), // Нужно вставить секретный ключ. Чтобы скопировать этот ключ, откройте Каталог интеграций -> CRM -> Настроить интеграцию. Секретный ключ находится в нижней части экрана в строке Ключ для интеграции.
				'title'   => "Заявка с сайта, форма: '$form_title'", // Постоянное значение
				'name'    => $name,
				'phone'   => $phone,
				'email'   => $email,
				'comment' => $comment,
			// 'is_skip_sending' => '1', // Не отправлять заявку в CRM.
			// 'fields'  => array(
			// Массив дополнительных полей, если нужны, или просто пустой массив.
			// Примеры использования:
			// "price" => 123, // Поле бюджет в amoCRM
			// "responsible_user_id" => 3, // Ответственный по сделке
			// "1276733" => "Текст", // Заполнение доп. поля с ID 1276733
			// Подробную информацию о наименовании полей и получить список доп. полей вы можете в документации amoCRM: https://developers.amocrm.ru/rest_api/#lead
			// Более подробную информацию по работе с дополнительными полями в amoCRM вы можете получить у нашей службы поддержки
			// "charset" => "Windows-1251", // Сервер преобразует значения полей из указанной кодировки в UTF-8
			// ),
			);
			$response = wp_remote_get(
				'https://cloud.roistat.com/api/proxy/1.0/leads/add?' . http_build_query( $roistat_data, null, '&' ),
				array(
					'timeout' => 1,
				)
			);
			// $response = wp_remote_post( 'https://api.ringostat.net/callback/outward_call', array(
			// 'body'    => array(
			// 'extension' => '',
			// 'destination' => $phone,
			// ),
			// 'headers' => array(
			// 'Content-Type' => 'application/x-www-form-urlencoded',
			// 'Auth-key' => '',API_KEY
			// ),
			// ) );

		}
	}
}

add_action( 'wpcf7_before_send_mail', 'vzd_rc_send_to_roistat' );


// Добавляем допалнительную ссылку настроек на страницу всех плагинов
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'customize.php?autofocus[section]=vdz_ringostat_contacts_section' ) ) . '">' . esc_html__( 'Settings' ) . '</a>';
		array_unshift( $links, $settings_link );
		array_walk( $links, 'wp_kses_post' );
		return $links;
	}
);


