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

class Client
{
	var $auth = null;
	var $auth_id = "";
	var $auth_key = "";
	var $auth_code = "";
	var $auth_domain = "";
	var $auth_redirect_uri = "";
	var $account_info = [];
	var $refresh_token = null;
	var $cache_timeout = 24*60*60;
	
	
	/**
	 * Returns true if is auth
	 */
	public function isAuth()
	{
		return $this->auth != null && !isset($this->auth["error"]) &&
			isset($this->auth["access_token"]);
	}
	
	
	/**
	 * Returns true if is auth failed
	 */
	public function isAuthFail()
	{
		return $this->auth && isset($this->auth["error"]);
	}
	
	
	/**
	 * Returns User Agent
	 */
	public static function getUserAgent()
	{
		return "AmoCRM-API-client/1.0";
	}
	
	
	/**
	 * Returns amocrm domain
	 */
	public function getDomain()
	{
		return $this->auth_domain . ".amocrm.ru";
	}
	
	
	/**
	 * Returns AmoCRM API Auth Url
	 */
	public function getAuthUrl()
	{
		return "https://" . $this->getDomain() . "/oauth2/access_token";
	}
	
	
	/**
	 * Returns search url
	 */
	public function getSearchUrl($kind)
	{
		return "https://" . $this->getDomain() . "/api/v4/" . $kind;
	}
	
	
	/**
	 * Returns AmoCRM API Account Url
	 */
	public function getAccountUrl($fields = [])
	{
		$str = implode(",", $fields);
		return "https://" . $this->getDomain() . "/api/v4/account?with=".$str;
	}
	
	
	/**
	 * Send curl
	 */
	public function curl($url, $post = null, $headers = null)
	{
		# Сохраняем дескриптор сеанса cURL
		$curl = curl_init();
		
		# Устанавливаем необходимые опции для сеанса cURL
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_USERAGENT, static::getUserAgent());
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		
		if ($post != null)
		{
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($post));
		}
		else
		{
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
		}
		
		if ($headers != null && count($headers) > 0)
		{
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		}
		
		# Инициируем запрос к API и сохраняем ответ в переменную
		$out = curl_exec($curl);
		
		# Получим HTTP-код ответа сервера
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		
		# Завершаем сеанс cURL
		curl_close($curl); 
		
		$response = null;
		$code = (int)$code;
		if ($code == 200 || $code == 204)
		{
			$response = @json_decode($out, true);
		}
		
		return [$out, $code, $response];
	}
	
	
	/**
	 * Возвращает access_token
	 */
	public function getAccessToken()
	{
		if (!$this->isAuth()) return null;
		return $this->auth["access_token"];
	}
	
	
	/**
	 * Отправляет API запрос
	 */
	public function sendApi($url, $post = null, $headers = null)
	{
		if ($headers == null) $headers = [];
		$headers[] = "Authorization: Bearer " . $this->getAccessToken();
		return $this->curl($url, $post, $headers);
	}
	
	
	/**
	 * Авторизация в AmoCRM
	 * https://www.amocrm.ru/developers/content/oauth/step-by-step
	 */
	public function auth()
	{
		if ($this->auth_id == "" ||
			$this->auth_domain == "" ||
			$this->auth_key == "" ||
			$this->auth_redirect_uri == ""
		)
		{
			return;
		}
		
		/* Подготовим запрос */
		$url = $this->getAuthUrl();
		$post = array(
			"client_id" => $this->auth_id,
			"client_secret" => $this->auth_key,
			"redirect_uri" => $this->auth_redirect_uri,
		);
		
		/* Токен авторизации */
		if ($this->refresh_token != null)
		{
			$post["grant_type"] = "refresh_token";
			$post["refresh_token"] = $this->refresh_token;
		}
		else
		{
			if ($this->auth_code == "") return;
			$post["grant_type"] = "authorization_code";
			$post["code"] = $this->auth_code;
		}
		
		/* Отправка запроса авторизации */
		list($out, $code, $response) = $this->curl($url, $post);
		if ($response != null)
		{
			$this->auth = $response;
		}
		else if ($code == 400)
		{
			if (!$this->auth) $this->auth = [];
			$this->auth["out"] = $out;
			$this->auth["error"] = true;
		}
		
		/* Сохранить данные авторизации */
		update_option("amocrm_auth", $this->auth);
	}
	
	
	/**
	 * Обновляет авторизацию
	 */
	public function refreshAuth()
	{
		$this->auth = get_option("amocrm_auth");
		$this->refresh_token = $this->auth["refresh_token"];
		$this->auth();
	}
	
	
	/**
	 * Возвращает ошибку авторизации
	 */
	public function getAuthError()
	{
		if (!$this->auth || !isset($this->auth["out"])) return "Неверные данные";
		
		$json = @json_decode($this->auth["out"], true);
		if (!$json) return "Неверные данные";
		
		$arr = [];
		if (isset($json["title"])) $arr[] = $json["title"];
		if (isset($json["hint"])) $arr[] = $json["hint"];
		return implode(". ", $arr);
	}
	
	
	/**
	 * Возвращает список воронок
	 */
	public function getPipelines()
	{
		if (!$this->account_info) return [];
		return isset($this->account_info["pipelines"]) ? $this->account_info["pipelines"] : [];
	}
	
	
	/**
	 * Возвращает список полей
	 */
	public function getCustomFields()
	{
		if (!$this->account_info) return [];
		return isset($this->account_info["contacts_custom_fields"]) ?
			$this->account_info["contacts_custom_fields"] : [];
	}
	
	
	/**
	 * Возвращает текущую воронку
	 */
	public function getCurrentPipeline()
	{
		if (!$this->account_info) return null;
		$id = isset($this->account_info["current_pipeline"]) ?
			$this->account_info["current_pipeline"] : null;
		return (int)$id;
	}
	
	
	/**
	 * Возвращает текущий статус
	 */
	public function getCurrentStatus()
	{
		if (!$this->account_info) return null;
		$id = isset($this->account_info["current_status"]) ?
			$this->account_info["current_status"] : null;
		return (int)$id;
	}
	
	
	/**
	 * Возвращает текущий email
	 */
	public function getCurrentEmail()
	{
		if (!$this->account_info) return null;
		return isset($this->account_info["current_email"]) ?
			$this->account_info["current_email"] : null;
	}
	
	
	/**
	 * Возвращает текущий телефон
	 */
	public function getCurrentPhone()
	{
		if (!$this->account_info) return null;
		return isset($this->account_info["current_phone"]) ?
			$this->account_info["current_phone"] : null;
	}
	
	
	/**
	 * Возвращает данные AmoCRM
	 */
	public function getAccountInfo()
	{
		if (!$this->isAuth()) return;
		
		/* Отправляем запрос API */
		$url = $this->getAccountUrl(["users_groups", "task_types"]);
		list($out, $code, $response) = $this->sendApi($url);
		if (!$response || !isset($response["_embedded"]))
		{
			return false;
		}
		
		/* Инициализация информации об аккаунте */
		if ($this->account_info == null)
		{
			$this->account_info = [];
		}
		
		/* Анализируем ответ */
		$data = $response["_embedded"];
		$this->account_info["timestamp"] = time() + $this->cache_timeout;
		$this->account_info["users_groups"] = isset($data["users_groups"]) ?
			$data["users_groups"] : [];
		$this->account_info["task_types"] = isset($data["task_types"]) ? $data["task_types"] : [];
				
		/* Получить список воронок */
		$url = $this->getSearchUrl("leads/pipelines");
		list($out, $code, $response) = $this->sendApi($url);
		if ($response && isset($response["_embedded"]))
		{
			$data = $response["_embedded"];
			$this->account_info["pipelines"] = isset($data["pipelines"]) ? $data["pipelines"] : [];
		}
		
		/* Получить список полей контакта */
		$url = $this->getSearchUrl("contacts/custom_fields");
		list($out, $code, $response) = $this->sendApi($url);
		if ($response && isset($response["_embedded"]))
		{
			$data = $response["_embedded"];
			$this->account_info["contacts_custom_fields"] =
				isset($data["custom_fields"]) ? $data["custom_fields"] : [];
		}
		
		/* Сохранить */
		$this->saveAccountInfo();
	}
	
	
	/**
	 * Сохранить данные аккаунта
	 */
	public function saveAccountInfo()
	{
		update_option("amocrm_account_info", $this->account_info);
	}
	
	
	/**
	 * Обновляет данные AmoCRM
	 */
	public function updateAccountInfo()
	{
		if (!$this->isAuth()) return;
		
		/* Получаем данные аккаунта из кэша */
		if ($this->account_info && $this->account_info["timestamp"] > time())
		{
			return;
		}
		
		/* Обновляем данные, если они устарели */
		$this->getAccountInfo();
	}
	
	
	/**
	 * Чтение настроек
	 */
	function readSettings()
	{
		$settings = get_option("amocrm_settings");
		
		/* Auth */
		$this->auth = get_option("amocrm_auth");
		$this->auth_domain = isset($settings["auth_domain"]) ? $settings["auth_domain"] : "";
		$this->auth_id = isset($settings["auth_id"]) ? $settings["auth_id"] : "";
		$this->auth_key = isset($settings["auth_key"]) ? $settings["auth_key"] : "";
		$this->auth_redirect_uri = isset($settings["auth_redirect_uri"]) ?
			$settings["auth_redirect_uri"] : "";
		
		/* Account info */
		$this->account_info = get_option("amocrm_account_info");
	}
	
	
	/**
	 * Инициализация
	 */
	function init()
	{
		$this->readSettings();
		if ($this->isAuthFail()) return;
		
		/* Проверка авторизации */
		if ($this->isAuth())
		{
			if ($this->auth["server_time"] + $this->auth["expires_in"] < time() + 7 * 60 * 60)
			{
				$this->refresh_token = $this->auth["refresh_token"];
			}
		}
		
		/* Авторизация */
		if (!$this->isAuth() || $this->refresh_token) $this->auth();
	}
	
	
	const SEARCH_CONTACT = "contacts";
	const SEARCH_COMPANY = "companies";
	const SEARCH_LEADS = "leads";
	const SEARCH_NOTES = "notes";
	
	
	/**
     * @param string           $kind
     * @param string           $query
     * @param integer          $limit
     * @param integer          $offset
     * @param array|string|int $responsibleUsers
     * @param DateTime|null    $modifiedSince
     * @param bool             $isRaw
     * @param int|string|null  $pipelineIdOrName
     * @param array            $statuses
	 * @return array
     */
    public function search($params)
    {
		$kind = isset($params["kind"]) ? $params["kind"] : "";
		$type = isset($params["type"]) ? $params["type"] : "";
		$id = isset($params["id"]) ? (int)$params["id"] : 0;
		$element_id = isset($params["element_id"]) ? (int)$params["element_id"] : null;
		$query = isset($params["query"]) ? $params["query"] : "";
		$offset = isset($params["offset"]) ? (int)$params["offset"] : null;
		$limit = isset($params["limit"]) ? (int)$params["limit"] : null;
		$modified_since = isset($params["modified_since"]) ? (int)$params["modified_since"] : null;
		if ($limit > 250) $limit = 250;
		
		$url = $this->getSearchUrl($kind);
		
		$args = [];
		if ($id != 0) $args[] = "id=" . urlencode($id);
		if ($type) $args[] = "type=" . urlencode($type);
		if ($element_id) $args[] = "element_id=" . urlencode($element_id);
		if ($query) $args[] = "query=" . urlencode($query);
		if ($offset) $args[] = "limit_offset=" . urlencode($offset);
		if ($limit) $args[] = "limit_rows=" . urlencode($limit);
		
		if (count($args) > 0)
		{
			$url .= "?" . implode("&", $args);
		}
		
		$headers = [];
		if ($modified_since !== null)
		{
			$dt = new \DateTime();
			$dt->setTimestamp($modified_since);
			$headers[] = "IF-MODIFIED-SINCE: " . $dt->format(\DateTime::RFC1123);
		}
		
		list($out, $code, $response) = $this->sendApi($url, null, $headers);
		if ($response === null)
		{
			if ($code != 200 and $code != 204)
			{
				throw new \Exception("Amocrm search response error code " . $code);
			}
			return [];
		}
		
		return $response;
    }
	
	
	/**
	 * Returns deal
	 */
	public function getDeal($deal_id)
	{
		$items = $this->search([
			"kind"=>static::SEARCH_LEADS,
			"id"=>$deal_id,
		]);
		if ($items == null) return null;
		return array_shift($items);
	}
	
	
	/**
	 * Returns contact
	 */
	public function getContact($contact_id)
	{
		$response = $this->search([
			"kind"=>static::SEARCH_CONTACT,
			"id"=>$contact_id,
		]);
		if ($response == null) return [];
		
		/* Parse response */
		$items = isset($response["_embedded"]["contacts"]) ?
			$response["_embedded"]["contacts"] : null;
		if ($items == null) return null;
		
		/* Get item */
		$item = array_shift($items);
		if ($item == null) return null;
		
		/* Parse contact */
		$item = $this->parseContactsFields($item);
		return $item;
	}
	
	
	/**
	 * Returns contact by query
	 */
	public function findContact($query)
	{
		$response = $this->search([
			"kind"=>static::SEARCH_CONTACT,
			"query"=>$query,
		]);
		if ($response == null) return [];
		
		$items = isset($response["_embedded"]["contacts"]) ?
			$response["_embedded"]["contacts"] : null;
		if ($items == null) return [];
		
		return $items;
	}
	
	
	/**
	 * Find client
	 */
	public function findClient($client)
	{
		$name = isset($client["name"]) ? $client["name"] : "";
		$phone = isset($client["phone"]) ? $client["phone"] : "";
		$email = isset($client["email"]) ? $client["email"] : "";
		
		$result = [];
		if ($phone != "") $result = array_merge($result, $this->findContact($phone));
		if ($email != "") $result = array_merge($result, $this->findContact($email));
		
		$candidate = null;
		$candidate_grade = 0;
		foreach ($result as $item)
		{
			$item = $this->parseContactsFields($item);
			$grade = $this->clientCalcGrade($item, $client);
			if ($grade > $candidate_grade)
			{
				$candidate_grade = $grade;
				$candidate = $item;
			}
		}
		
		if ($candidate) $candidate["grade"] = $candidate_grade;
		return $candidate;
	}
	
	
	/**
	 * Create client
	 */
	public function createClient($client)
	{
		$name = isset($client["name"]) ? $client["name"] : "";
		$tags = isset($client["tags"]) ? $client["tags"] : "";
		$phone = isset($client["phone"]) ? $client["phone"] : "";
		$email = isset($client["email"]) ? $client["email"] : "";
		$manager_id = isset($client["manager_id"]) ? $client["manager_id"] : 0;
		
		$contact = [
			"name" => $name,
			"first_name" => $name,
			"created_at" => time(),
			"custom_fields_values" => [],
		];
		if ($manager_id > 0)
		{
			$contact["responsible_user_id"] = $manager_id;
		}
		
		$phone_id = $this->getCurrentPhone();
		if ($phone != "" and $phone_id > 0)
		{
			$contact["custom_fields_values"][] = [
				"field_id" => (int)$phone_id,
				"values" => array(
					array(
						"value" => $phone,
						//"enum" => "MOB"
					)
				),
			];
		}
		
		$email_id = $this->getCurrentEmail();
		if ($email != "" && $email_id > 0)
		{
			$contact["custom_fields_values"][] = [
				"field_id" => (int)$email_id,
				"values" => array(
					array(
						"value" => $email,
						//"enum" => "WORK"
					)
				),
			];
		}
		
		/* Send request */
		$url = $this->getSearchUrl("contacts");
		list($out, $code, $response) = $this->sendApi($url, [$contact]);
		if ($response)
		{
			$items = $response["_embedded"]["contacts"];
			$item = array_shift($items);
			if ($item)
			{
				return $item["id"];
			}
		}
		else
		{
			throw new \Exception("Create client error. Response error " . $code);
			//var_dump($code);
			//var_dump($out);
		}
		
		return 0;
	}
	
	
	/**
	 * Create client
	 */
	public function createDeal($deal)
	{
		$deal_name = isset($deal["deal_name"]) ? $deal["deal_name"] : "Заказ";
		$contact_id = isset($deal["contact_id"]) ? $deal["contact_id"] : 0;
		$pipeline_id = isset($deal["pipeline_id"]) ? $deal["pipeline_id"] : 0;
		$status_id = isset($deal["status_id"]) ? $deal["status_id"] : 0;
		$manager_id = isset($deal["manager_id"]) ? $deal["manager_id"] : 0;
		
		if ($contact_id == 0)
		{
			throw new \Exception("Create deal error. Contact id is null");
		}
		if ($pipeline_id == 0)
		{
			throw new \Exception("Create deal error. Pipeline id is null");
		}
		if ($status_id == 0)
		{
			throw new \Exception("Create deal error. Status id is null");
		}
		
		$send = [
			"name" => $deal_name,
			"created_at" => time(),
			"pipeline_id" => (int)$pipeline_id,
			"status_id" => (int)$status_id,
			"_embedded" => [
				"contacts" => [
					[
						"id" => (int)$contact_id,
						"is_main" => true,
					]
				],
			],
			"custom_fields_values" => [],
		];
		if ($manager_id > 0)
		{
			$send["responsible_user_id"] = $manager_id;
		}
		
		$url = $this->getSearchUrl("leads");
		list($out, $code, $response) = $this->sendApi($url, [$send]);
		if ($response)
		{
			$items = $response["_embedded"]["leads"];
			$item = array_shift($items);
			if ($item) return $item["id"];
		}
		else
		{
			throw new \Exception("Create deal error. Response error " . $code);
		}
		
		return 0;
	}
	
	
	/**
	 * Create note
	 */
	public function createTextNote($lead_id, $text)
	{
		$text = static::mb_trim($text);
		if ($lead_id == 0)
		{
			throw new \Exception("Lead id is null");
		}
		if ($text == "")
		{
			throw new \Exception("Text is null");
		}
		
		$send = [
			"entity_id" => (int)$lead_id,
			"note_type" => "common",
			"is_need_to_trigger_digital_pipeline" => false,
			"params" => [
				"text" => $text,
			]
		];
		
		$url = $this->getSearchUrl("leads/notes");
		list($out, $code, $response) = $this->sendApi($url, [$send]);
		if ($response)
		{
			$items = $response["_embedded"]["notes"];
			$item = array_shift($items);
			if ($item) return $item["id"];
		}
		else
		{
			throw new \Exception("Create note error. Response error " . $code);
		}
		
		return 0;
	}
	
	
	/**
	 * Trim UTF-8 string
	 */
	public static function mb_trim($string)
	{
		if ($string == null) return "";
		$whitespaceChars = [
			"\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07",
			"\x08", "\x09", "\x0A", "\x0B", "\x0C", "\x0D", "\x0E", "\x0F",
			"\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17",
			"\x18", "\x19", "\x1A", "\x1B", "\x1C", "\x1D", "\x1E", "\x1F",
			"\x7F", "\u{00A0}", "\u{200B}", "\u{FEFF}"
		];
		$string = trim($string);
		$string = str_replace($whitespaceChars, '', $string);
		return $string;
	}
	
	
	/**
	 * Returns field valueds
	 */
	public static function getItemFieldValue($item, $field_id)
	{
		$custom_fields = isset($item["custom_fields_values"]) ?
			$item["custom_fields_values"] : null;
		if ($custom_fields == null) return [];
		
		$res = [];
		foreach ($custom_fields as $field)
		{
			$values = isset($field["values"]) ? $field["values"] : null;
			if ($field["field_id"] == $field_id and $values != null)
			{
				if (gettype($values) == "array")
				{
					foreach ($values as $v)
					{
						$value = isset($v["value"]) ? $v["value"] : "";
						if ($value) $res[] = $value;
					}
				}
				break;
			}
		}
		
		return $res;
	}
	
	
	/**
	 * Parser contacts
	 */
	public function parseContactsFields($item)
	{
		$item["phones"] = static::getItemFieldValue($item, $this->getCurrentPhone());
		$item["emails"] = static::getItemFieldValue($item, $this->getCurrentEmail());
		return $item;
	}
	
	
	/**
	 * Получение оценки совпадения карточек
	 * $item - контакт из амосрм
	 * $client - данные клиента, которые нужно найти
	 */
	public function clientCalcGrade($item, $client)
	{
		$grade = 0;
		
		$client_name = static::mb_trim(isset($client["name"]) ? $client["name"] : "");
		$client_phone = static::mb_trim(isset($client["phone"]) ? $client["phone"] : "");
		$client_email = static::mb_trim(isset($client["email"]) ? $client["email"] : "");
		
		$item_name = static::mb_trim(isset($item["name"]) ? $item["name"] : "");
		$item_phones = isset($item["phones"]) ? $item["phones"] : [];
		$item_emails = isset($item["emails"]) ? $item["emails"] : [];
		
		$client_name = mb_strtolower($client_name);
		$client_email = mb_strtolower($client_email);
		$item_name = mb_strtolower($item_name);
		
		# Поиск по имени
		if ($client_name == $item_name && $item_name != "") $grade += 2;
		else if ($item_name == "" || $client_name == ""){}
		else
		{
			if ((strpos($item_name, $client_name) !== false ||
				strpos($client_name, $item_name) !== false) &&
				mb_strlen($item_name) > 2 && mb_strlen($client_name) > 2
			)
				$grade += 1;
		}
		
		# Поиск по телефону
		$find = false;
		$client_phone = preg_replace("/[^0-9]/", "", $client_phone);
		foreach ($item_phones as $val)
		{
			$val = preg_replace("/[^0-9]/", "", $val);
			if ($client_phone == $val && $val != "")
			{
				$grade += 7;
				$find = true;
				break;
			}
		}
		if (!$find && $client_phone != "")
		{
			$grade = -100;
		}
		
		# Поиск по email
		$find = false;
		foreach ($item_emails as $val)
		{
			$val = mb_strtolower(static::mb_trim($val));
			if ($client_email == $val && $val != "")
			{
				$grade += 4;
				$find = true;
				break;
			}
		}
		if (!$find && $client_email != "")
		{
			$grade = -100;
		}
		
		return $grade;
	}
}
