<?php
/*!
 *  Elberos
 *
 *  (c) Copyright 2025 "Ildar Bikmamatov" <support@elberos.org>
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

class Settings
{
	private $option_name = "amocrm_settings";
	private $fields = [
		"amocrm_domain"   => "Домен AmoCRM",
		"amocrm_login"    => "Логин AmoCRM",
		"amocrm_key"      => "API-ключ AmoCRM",
		"amocrm_web_hook" => "Webhook URL",
	];
	
	
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
		
		submit_button('Сохранить');
		echo "</form>";
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
		foreach ($this->fields as $key => $label) {
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