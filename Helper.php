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
	public static function loadAccountInfo($file)
	{
		if (!file_exists($file)) return [null, null, null, false];
		
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
	public static function getContactFieldValues($contact, $field_id)
	{
		$custom_fields = isset($contact['custom_fields']) ? $contact['custom_fields'] : null;
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
			}
		}
		
		return $res;
	}
	
}