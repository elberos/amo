<?php
/**
 * Plugin Name: Elberos AmoCRM
 * Description: AmoCRM Integration
 * Author:      Ildar Bikmamatov <support@bayrell.org>
 * License:     Apache License 2.0
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      https://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

/* Check if Wordpress */
if (!defined("ABSPATH")) exit;

function load_elberos_amocrm()
{
	include_once __DIR__ . "/src/Client.php";
	include_once __DIR__ . "/src/Import.php";
	include_once __DIR__ . "/src/Settings.php";
}

/* Отключить обновления плагина */
add_filter("site_transient_update_plugins", function($value){
	$name = plugin_basename(__FILE__);
	if (isset($value->response[$name]))
	{
		unset($value->response[$name]);
	}
	return $value;
});

/* Добавить элементы в админку WordPress */
add_action('admin_menu', function(){
	add_menu_page(
		'AmoCRM', 'AmoCRM', 
		'manage_options', 'amocrm',
		function ()
		{
			load_elberos_amocrm();
			$settings = new \Elberos\AmoCRM\Settings();
			$settings->show();
		},
		null, 30
	);
});

/* Api обновить данные аккаунта из AmoCRM */
add_action('wp_ajax_amocrm_settings', function(){
	
	if (!isset($_POST['_nonce']) || !wp_verify_nonce($_POST['_nonce'], 'amocrm_settings_nonce'))
	{
		wp_send_json_error("Ошибка безопасности");
	}
	
	load_elberos_amocrm();
	$settings = new \Elberos\AmoCRM\Settings();
	$json = $settings->apiUpdate();
	wp_send_json($json);
});

/* Api авторизация в AmoCRM */
add_action('wp_ajax_amocrm_auth', function(){
	
	if (!isset($_POST['_nonce']) || !wp_verify_nonce($_POST['_nonce'], 'amocrm_auth_nonce'))
	{
		wp_send_json_error("Ошибка безопасности");
	}
	
	load_elberos_amocrm();
	$settings = new \Elberos\AmoCRM\Settings();
	$auth_code = isset($_POST["auth_code"]) ? $_POST["auth_code"] : "";
	$json = $settings->apiAuth($auth_code);
	wp_send_json($json);
});

/* Регистрация задач cron */
add_filter('cron_schedules', function(){
	$schedules['amocrm_import'] = array(
		'interval' => 120, // Каждые 2 минуты
		'display'  => 'Once Two Minute',
	);
	return $schedules;
});
if (!wp_next_scheduled('amocrm_import'))
{
	wp_schedule_event(time() + 60, 'amocrm_import', 'amocrm_import');
}
add_action('amocrm_import', function(){
	load_elberos_amocrm();
	$import = new \Elberos\AmoCRM\Import();
	$import->import();
	//$import->test();
});