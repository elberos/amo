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

class Import
{
	var $client;
	
	
	/**
	 * Constructor
	 */
	function __construct()
	{
		$this->client = new Client();
		$this->client->init();
	}
	
	
	/**
	 * Преобразовать данные формы
	 */
	function transform($data)
	{
		$client = [];
		foreach ($data as $row)
		{
			if ($row["id"] == "input-name")
			{
				$client["name"] = isset($row["value"]) ? $row["value"] : "";
			}
			else if ($row["id"] == "input-phone")
			{
				$client["phone"] = isset($row["value"]) ? $row["value"] : "";
			}
			else if ($row["id"] == "input-message")
			{
				$client["message"] = isset($row["value"]) ? $row["value"] : "";
			}
		}
		return $client;
	}
	
	
	/**
	 * Run import
	 */
	function import()
	{
		global $wpdb;
		if (!$this->client->isAuth()) return;
		
		/* Проверяем воронку и статус */
		$pipeline_id = $this->client->getCurrentPipeline();
		$status_id = $this->client->getCurrentStatus();
		if ($pipeline_id == 0 || $status_id == 0) return;
		
		/* Список новых клиентов */
		$last_id = (int)get_option("amocrm_gutenverse_id");
		$query = $wpdb->prepare(
			"select postmeta.* from {$wpdb->prefix}postmeta as postmeta
			inner join {$wpdb->prefix}posts as post on (postmeta.post_id = post.id)
			where post.post_type = %s and postmeta.meta_key = %s and
				post.id > %s and post.post_status='publish'
			", ["gutenverse-entries", "entry-data", $last_id]
		);
		$items = $wpdb->get_results($query);
		
		/* Each item */
		foreach ($items as $item)
		{
			$form_data = @unserialize($item->meta_value);
			if (!$form_data) continue;
			
			/* Преобразовать данные формы в dict */
			$client = $this->transform($form_data);
			if (!isset($client["name"]) || !isset($client["phone"])) continue;
			
			/* Отправить в AmoCRM */
			$result = $this->sendAmoCRM($client);
			
			/* Обновляем ID */
			update_option("amocrm_gutenverse_id", $item->post_id);
		}
	}
	
	
	/**
	 * Отправить данные в AmoCRM
	 */
	function sendAmoCRM($client_data)
	{
		$client = $this->client->findClient($client_data);
		if (!$client)
		{
			$client_id = $this->client->createClient($client_data);
		}
		else
		{
			$client_id = $client["id"];
		}
		
		$deal_id = $this->client->createDeal([
			"contact_id" => $client_id,
			"pipeline_id" => $this->client->getCurrentPipeline(),
			"status_id" => $this->client->getCurrentStatus(),
		]);
		if ($deal_id && isset($client_data["message"]))
		{
			$this->client->createTextNote($deal_id, $client_data["message"]);
		}
		
		return [
			"client_id" => $client_id,
			"deal_id" => $deal_id,
		];
	}
	
	
	/**
	 * Test
	 */
	function test()
	{
		if (!$this->client->isAuth()) return;
	}
}