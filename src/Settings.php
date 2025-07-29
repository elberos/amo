<?php
/**
 *  Elberos AmoCRM API Client
 *
 *  (c) Copyright 2025 "Ildar Bikmamatov" <support@bayrell.org>
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

namespace Elberos\AmoCRM;

/* Check if Wordpress */
if (!defined("ABSPATH")) exit;

class Settings
{
	private $option_name = "amocrm_settings";
	private $fields = [
		"auth_domain" => "Домен AmoCRM",
		"auth_id" => "ID интеграции",
		"auth_key" => "Секретный ключ",
		"auth_redirect_uri" => "Редирект",
	];
	
	
	/**
	 * Api auth
	 */
	function apiAuth()
	{
		if (!current_user_can("manage_options"))
		{
			wp_send_json_error("Недостаточно прав");
			return;
		}
		if (!wp_verify_nonce($_POST['_nonce'], 'amocrm_auth_nonce'))
		{
			wp_send_json_error("Ошибка безопасности");
			return;
		}
		
		$client = new Client();
		$client->auth_code = isset($_POST["auth_code"]) ? $_POST["auth_code"] : "";
		$client->readSettings();
		$client->init();
		
		if ($client->isAuth())
		{
			wp_send_json_success();
		}
		else
		{
			wp_send_json_error("Ошибка. " . $client->getAuthError());
		}
	}
	
	
	/**
	 * Показать форму
	 */
	function show()
	{
		$result = $this->update_post();
		if ($result)
		{
			echo "<div class='updated'><p>Настройки сохранены.</p></div>";
		}
		else
		{
			if ($_SERVER["REQUEST_METHOD"] == "POST")
			{
				echo "<div class='error'><p>Ошибка</p></div>";
			}
		}
		
		echo "<style>";
		echo ".amocrm_settings label{";
		echo "display: block;";
		echo "margin-bottom: 5px;";
		echo "}";
		echo ".amocrm_settings p{";
		echo "padding: 0; margin: 15px 0;";
		echo "}";
		echo ".amocrm_settings_buttons{";
		echo "display: flex;";
		echo "gap: 10px;";
		echo "}";
		echo "</style>";
		echo "<div class='amocrm_settings wrap'>";
		echo "<h1>Настройки интеграции с AmoCRM</h1>";
		echo "<form method='post'>";
		
		wp_nonce_field('amocrm_settings_save');
		
		$options = get_option($this->option_name);
		foreach ($this->fields as $key => $label)
		{
			$value = isset($options[$key]) ? $options[$key] : '';
			$this->add_field($key, $label, $value);
		}
		
		echo "<div class='amocrm_settings_buttons'>";
		echo "<button type='submit' name='submit' id='submit'
			class='button button-primary'>Сохранить</button>";
		echo "<button type='button' class='button amocrm_oauth'>
			Авторизация</button>";
		echo "</div>";
		echo "</form>";
		
		echo "<form method='post' onsubmit='return false;'>";
		$this->add_field("auth_code", "Код авторизации", "");
		echo "<button type='button' class='button button-primary amocrm_auth_button'>
			Авторизация</button>";
		echo "<div class='amocrm_auth_result' style='margin-top:10px;'></div>";
		echo "</form>";
		
		echo "<script>
		jQuery(document).ready(function($){
			window.amocrm_popup = null;
			window.amocrm_client_id = " . json_encode($options["auth_id"]) . ";
			$('.amocrm_oauth').on('click', function(){
				var url = 'https://www.amocrm.ru/oauth?client_id=' + window.amocrm_client_id;
				url += '&state=code&mode=post_message';
				window.amocrm_popup = window.open(
					url, 'Предоставить доступ',
					'scrollbars, status, resizable, width=750, height=580'
				);
			});
			window.addEventListener('message', function(event){
				console.log(event);
				if (event.data.error !== undefined)
				{
					console.log('Ошибка - ' + event.data.error)
				}
				else
				{
					console.log('Авторизация прошла');
				}
				window.amocrm_popup.close();
			});
			$('.amocrm_auth_button').on('click', function(){
				$('.amocrm_auth_result').text('Авторизация...');
				$.post(ajaxurl, {
					action: 'amocrm_auth',
					auth_code: $('input#auth_code').val(),
					_nonce: '".wp_create_nonce("amocrm_auth_nonce")."',
				}, function(response)
				{
					if(response.success)
					{
						$('.amocrm_auth_result')
							.html('<div style=\'color:green;\'>Успешно</div>');
					}
					else {
						var error_message = $('<div>').text(response.data).html();
						$('.amocrm_auth_result')
							.html('<div style=\'color:red;\'>Ошибка: ' + error_message + '</div>');
					}
				});
			});
		});
		</script>";
		echo "</div>";
	}
	
	
	/**
	 * Обработка POST запроса
	 */
	function update_post()
	{
		if ($_SERVER["REQUEST_METHOD"] != "POST") return false;
		if (!current_user_can("manage_options")) return false;
		if (!wp_verify_nonce(
			isset($_POST["_wpnonce"]) ? $_POST["_wpnonce"] : "", "amocrm_settings_save"
		)) return false;
		
		$data = [];
		foreach ($this->fields as $key => $label)
		{
			$data[$key] = isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : "";
		}
		update_option($this->option_name, $data);
		
		return true;
	}
	
	
	/**
	 * Input
	 */
	function add_field($id, $label, $value)
	{
		echo "<p><label for='{$id}'>{$label}:</label>";
		echo "<input type='text' id='{$id}' name='{$id}' value='" . esc_attr($value) . "' class='regular-text' />";
		echo "</p>";
	}
}