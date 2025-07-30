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
	private $fields = [
		"auth_domain" => "Домен AmoCRM",
		"auth_id" => "ID интеграции",
		"auth_key" => "Секретный ключ",
		"auth_redirect_uri" => "Редирект",
	];
	
	
	/**
	 * Api auth
	 */
	function apiAuth($auth_code)
	{
		if (!current_user_can("manage_options"))
		{
			return [
				"success" => false,
				"data" => "Недостаточно прав",
			];
		}
		if ($auth_code == "")
		{
			return [
				"success" => false,
				"data" => "Укажите код авторизации",
			];
		}
		
		/* Авторизация */
		$client = new Client();
		$client->auth_code = $auth_code;
		$client->readSettings();
		$client->auth();
		$client->getAccountInfo();
		
		/* Вернуть результат */
		if ($client->isAuth())
		{
			return [
				"success" => true,
			];
		}
		else
		{
			return [
				"success" => false,
				"data" => "Ошибка. " . $client->getAuthError(),
			];
		}
	}
	
	
	/**
	 * Обновить настройки
	 */
	public function apiUpdate()
	{
		$client = new Client();
		$client->init();
		$client->getAccountInfo();
		if ($client->isAuth())
		{
			return [
				"success" => true,
				"data" => [
					"account_info" => $client->account_info,
				],
			];
		}
		else
		{
			return [
				"success" => false,
				"data" => "Ошибка. " . $client->getAuthError(),
			];
		}
	}
	
	
	/**
	 * Показать окно авторизации
	 */
	function auth()
	{
		$auth_code = isset($_GET["code"]) ? $_GET["code"] : "";
		$result = $this->apiAuth($auth_code);
		
		echo "<style>
            #adminmenumain, #wpadminbar, #adminmenuwrap, #screen-meta-links, #screen-meta {
                display: none !important;
            }
            #wpcontent, #wpbody-content {
                margin-left: 0 !important;
                padding-top: 0 !important;
            }
        </style>";
		if ($result["success"])
		{
			echo "<div class='updated'><p>Авторизация успешна</p></div>";
		}
		else
		{
			echo "<div class='error'><p>" . esc_html($result["data"]) . "</p></div>";
		}
	}
	
	
	/**
	 * Показать форму
	 */
	function show()
	{
		if (isset($_GET["code"]))
		{
			$this->auth();
			return;
		}
		
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
		
		$client = new Client();
		$client->readSettings();
		
		echo "<style>
		.amocrm_settings label{
			display: block;
			margin-bottom: 5px;
		}
		.amocrm_settings p{
			padding: 0; margin: 15px 0;
		}
		.amocrm_settings_buttons{
			display: flex;
			gap: 10px;
		}
		.amocrm_field_group{
			display: flex;
			align-items: center;
			gap: 10px;
		}
		.amocrm_tab { display: none; }
		.amocrm_tab.active { display: block; }
		.nav-tab{ cursor: pointer; }
		</style>";
		
		echo "<div class='amocrm_settings wrap'>";
		echo "<h1>Настройки интеграции с AmoCRM</h1>";
		echo "<div class='nav-tab-wrapper'>
			<a class='nav-tab nav-tab-active' data-tab='amocrm_tab_settings'>Настройки</a>
			<a class='nav-tab' data-tab='amocrm_tab_auth'>Авторизация</a>
		</div>";
		
		/* Настройки */
		echo "<div class='amocrm_tab amocrm_tab_settings active'>";
		echo "<form method='post'>";
		
		wp_nonce_field('amocrm_settings_save');
		foreach ($this->fields as $key => $label)
		{
			$this->add_field($key, $label, $client->$key);
		}
		
		/* Воронка */
		$pipelines = $client->getPipelines();
		$current_pipeline = $client->getCurrentPipeline();
		$current_status = $client->getCurrentStatus();
		echo "<p><label for='amocrm_pipeline'>Воронка:</label>";
		echo "<div class='amocrm_field_group'>";
		echo "<select type='text' id='amocrm_pipeline' name='amocrm_pipeline'>";
		echo "<option>Выбрать значение</option>";
		foreach ($pipelines as $pipeline)
		{
			$id = $pipeline["id"];
			$selected = $id == $current_pipeline ? " selected" : "";
			echo "<option value='" . esc_attr($id) . "'{$selected}>" .
				esc_html($pipeline["name"]) . "</option>";
		}
		echo "</select>";
		echo "<button type='button' class='button amocrm_settings_update'>
			Обновить данные</button>";
		echo "</div>";
		echo "<div class='amocrm_settings_result' style='margin-top: 10px;'></div>";
		echo "</p>";
		
		/* Статус */
		echo "<p><label for='amocrm_pipeline_status'>Статус:</label>";
		echo "<select type='text' id='amocrm_pipeline_status' name='amocrm_pipeline_status'>";
		echo "</select>";
		echo "</p>";
		
		/* Кнопки настроек */
		echo "<div class='amocrm_settings_buttons'>";
		echo "<button type='submit' name='submit' id='submit'
			class='button button-primary'>Сохранить</button>";
		echo "<button type='button' class='button amocrm_oauth'>
			Авторизация</button>";
		echo "</div>";
		echo "</form>";
		echo "</div>";
		
		/* Авторизация */
		echo "<div class='amocrm_tab amocrm_tab_auth'>";
		echo "<form method='post' onsubmit='return false;'>";
		$this->add_field("auth_code", "Код авторизации", "");
		echo "<button type='button' class='button button-primary amocrm_auth_button'>
			Авторизация</button>";
		echo "<div class='amocrm_auth_result' style='margin-top:10px;'></div>";
		echo "</form>";
		echo "</div>";
		
		echo "<script>
		jQuery(document).ready(function($){
			
			$('.nav-tab').on('click', function(e){
				e.preventDefault();
				$('.nav-tab').removeClass('nav-tab-active');
				$('.amocrm_tab').removeClass('active');
				$(this).addClass('nav-tab-active');
				$('.' + $(this).data('tab')).addClass('active');
			});
			
			window.amocrm = {
				findPipeline: function(id)
				{
					for (var i=0; i<this.pipelines.length; i++)
					{
						var pipeline = this.pipelines[i];
						if (pipeline.id == id) return pipeline;
					}
					return null;
				},
				createOption: function(value, text)
				{
					var option = document.createElement('option');
					option.value = value;
					option.textContent = text;
					return option;
				},
				updatePipeline: function(pipeline_status)
				{
					var value = $('select[name=amocrm_pipeline]').val();
					var select = $('select[name=amocrm_pipeline_status]');
					var pipeline = this.findPipeline(value);
					if (pipeline_status == undefined) pipeline_status = select.val();
					select.empty();
					select.append(this.createOption('', 'Выбрать статус'));
					for (var i=0; i<pipeline._embedded.statuses.length; i++)
					{
						var status = pipeline._embedded.statuses[i];
						var option = this.createOption(status.id, status.name);
						if (pipeline_status == status.id)
						{
							option.selected = true;
						}
						select.append(option);
					}
				},
			};
			$('select[name=amocrm_pipeline]').on('change', function(){
				window.amocrm.updatePipeline();
			});
			
			window.amocrm.client_id = " . json_encode($client->auth_id) . ";
			window.amocrm.pipelines = " . json_encode($pipelines) . ";
			window.amocrm.updatePipeline(" . json_encode($current_status) . ");
			
			$('.amocrm_oauth').on('click', function(){
				var url = 'https://www.amocrm.ru/oauth?client_id=' + window.amocrm_client_id;
				url += '&state=code&mode=post_message';
				window.open(
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
			});
			$('.amocrm_settings_update').on('click', function(){
				$('.amocrm_settings_result').text('Обновление');
				$.post(ajaxurl, {
					action: 'amocrm_settings',
					_nonce: '".wp_create_nonce("amocrm_settings_nonce")."',
				}, function(response)
				{
					if(response.success)
					{
						$('.amocrm_settings_result')
							.html('<div style=\'color:green;\'>Успешно</div>');
					}
					else {
						var error_message = $('<div>').text(response.data).html();
						$('.amocrm_settings_result')
							.html('<div style=\'color:red;\'>Ошибка: ' + error_message + '</div>');
					}
				});
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
		update_option("amocrm_settings", $data);
		
		/* Сохранить воронку */
		$client = new Client();
		$client->readSettings();
		$client->account_info["current_pipeline"] = isset($_POST["amocrm_pipeline"]) ?
			$_POST["amocrm_pipeline"] : "";
		$client->account_info["current_status"] = isset($_POST["amocrm_pipeline_status"]) ?
			$_POST["amocrm_pipeline_status"] : "";
		$client->saveAccountInfo();
		
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