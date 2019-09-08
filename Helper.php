<?php

/*!
 *  Elberos AmoCRM API Client
 *
 *  (c) Copyright 2019 "Ildar Bikmamatov" <support@bayrell.org>
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

namespace Elberos\Amo;

use Elberos\Core\Struct;


class Helper
{
	
	/**
	 * Save account info to file
	 */
	public static function saveAccountInfo($file, $users, $custom_fields, $pipelines)
	{
		$json = [
			"users"=>$users,
			"custom_fields"=>$custom_fields,
			"pipelines"=>$pipelines,
		];
		$json = json_encode($json);
		file_put_contents($file, $json);
	}
	
	
	
	/**
	 * Load account info from file
	 */
	public static function loadAccountInfo($file, $cache_timeout)
	{
		if (!file_exists($file)) return [null, null, null, false];
		
		$last = filemtime($file);
		if ($last + $cache_timeout < time()) return [null, null, null, false];
		
		$json = file_get_contents($file);
		$json = @json_decode($json);
		if (!$json)
		{
			return [null, null, null, false];
		}
		$users = $json->users;
		$custom_fields = $json->custom_fields;
		$pipelines = $json->pipelines;
		return [$users, $custom_fields, $pipelines, true];
	}
	
	
	
	/**
	 * Returns field valueds
	 */
	public static function getItemFieldValue($item, $field_id)
	{
		$custom_fields = isset($item['custom_fields']) ? $item['custom_fields'] : null;
		if ($custom_fields == null) return [];
		
		$res = [];
		foreach ($custom_fields as $field)
		{
			$values = isset($field['values']) ? $field['values'] : null;
			if ($field['id'] == $field_id and $values != null)
			{
				if (gettype($values) == 'array')
				{
					foreach ($values as $v)
					{
						$value = isset($v['value']) ? $v['value'] : "";
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
	public static function parseContactsFields($item, $phone_id, $email_id)
	{
		$result = [
			'item' => $item,
		];
		
		$result['id'] = isset($item['id']) ? $item['id'] : '';
		$result['name'] = isset($item['name']) ? $item['name'] : '';
		$result['phones'] = static::getItemFieldValue($item, $this->phone_id);
		$result['emails'] = static::getItemFieldValue($item, $this->email_id);
		
		return $result;
	}
	
	
	
	/**
	 * Получение оценки совпадения карточек
	 * $item - контакт из амосрм
	 * $client - данные клиента, которые нужно найти
	 */
	public static function clientCalcGrade($item, $client)
	{
		$grade = 0;
		
		$client_name = mb_trim(isset($client['name']) ? $client['name'] : '');
		$client_phone = mb_trim(isset($client['phone']) ? $client['phone'] : '');
		$client_email = mb_trim(isset($client['email']) ? $client['email'] : '');
		
		$item_name = mb_trim(isset($item['name']) ? $item['name'] : '');
		$item_phones = isset($item['phones']) ? $item['phones'] : [];
		$item_emails = isset($item['emails']) ? $item['emails'] : [];
		
		$client_name = mb_strtolower($client_name);
		$client_email = mb_strtolower($client_email);
		$item_name = mb_strtolower($item_name);
		
		# Поиск по имени
		if ($client_name == $item_name && $item_name != "") $grade += 2;
		else if ($item_name == "" || $client_name == "") {}
		else
		{
			if ( (strpos($item_name, $client_name) !== false || strpos($client_name, $item_name) !== false) &&
				mb_strlen($item_name) > 2 && mb_strlen($client_name) > 2
			)
				$grade += 1;
		}
		
		# Поиск по телефону
		$find = false;
		$client_phone = preg_replace("/[^0-9]/", '', $client_phone);
		foreach ($item_phones as $val)
		{
			$val = preg_replace("/[^0-9]/", '', $val);
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
			$val =  mb_strtolower(mb_trim($val));
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



/**
 * Trim UTF-8 string
 */
function mb_trim($name)
{
	if ($name == null) return "";
	$name = preg_replace('/^[\x00-\x1F\x7F\s]+/u', '', $name);
	$name = preg_replace('/[\x00-\x1F\x7F\s]+$/u', '', $name); 
	return $name;
}