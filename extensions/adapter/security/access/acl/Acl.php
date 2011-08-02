<?php
namespace li3_access\extensions\adapter\security\access\acl;

//use lithium\core\Libraries;
//use UnexpectedValueException;
//use lithium\security\Password;
use lithium\data\Connections;

class Acl extends \lithium\data\Model {

/**
 * Retrieves the Aro/Aco node for this model
 *
 * @param mixed $ref Array with 'model' and 'foreign_key', model object, or string value
 * @return array Node found in database
 * @access public
 */
	public static function node($ref = null) {
		$type =  self::_name(); //Aros
		$db = self::connection(); //db postgres connection
		$meta = self::meta(); //meta (array)
		$key = self::key(); // id

		$result = null;

		$table = $meta['source']; // aros

		if (empty($ref)) {
			return null;
		} elseif (is_string($ref)) {
			$path = explode('/', $ref);
			$start = $path[0];
			unset($path[0]);

			$queryData = array(
				'conditions' => array(
					"{$type}.lft" . ' <= ' . "{$type}0.lft",
					"{$type}.rght" . ' >= ' . "{$type}0.rght",
				'fields' => array('id', 'parent_id', 'model', 'foreign_key', 'alias'),
				'joins' => array(array(
					'table' => $table,
					'alias' => "{$type}0",
					'type' => 'LEFT',
					'conditions' => array("{$type}0.alias" => $start)
				)),
				'order' => "{$type}.lft" . ' DESC'
			));

			foreach ($path as $i => $alias) {
				$j = $i - 1;

				$queryData['joins'][] = array(
					//'table' => $db->fullTableName($this),
					'table' => $table,
					'alias' => "{$type}{$i}",
					'type'  => 'LEFT',
					'conditions' => array(
						$db->name("{$type}{$i}.lft") . ' > ' . $db->name("{$type}{$j}.lft"),
						$db->name("{$type}{$i}.rght") . ' < ' . $db->name("{$type}{$j}.rght"),
						$db->name("{$type}{$i}.alias") . ' = ' . $db->value($alias, array('type'=>'string')),
						$db->name("{$type}{$j}.id") . ' = ' . $db->name("{$type}{$i}.parent_id")
					)
				);

				$queryData['conditions'] = array('or' => array(
					$db->name("{$type}.lft") . ' <= ' . $db->name("{$type}0.lft") . ' AND ' . $db->name("{$type}.rght") . ' >= ' . $db->name("{$type}0.rght"),
					$db->name("{$type}.lft") . ' <= ' . $db->name("{$type}{$i}.lft") . ' AND ' . $db->name("{$type}.rght") . ' >= ' . $db->name("{$type}{$i}.rght"))
				);
			}
			$result = $db->read($this, $queryData, -1);
			$path = array_values($path);

			if (
				!isset($result[0][$type]) ||
				(!empty($path) && $result[0][$type]['alias'] != $path[count($path) - 1]) ||
				(empty($path) && $result[0][$type]['alias'] != $start)
			) {
				return false;
			}
		} elseif (is_object($ref) && is_a($ref, 'Model')) {
			$ref = array('model' => $ref->alias, 'foreign_key' => $ref->id);
		} elseif (is_array($ref) && !(isset($ref['model']) && isset($ref['foreign_key']))) {
			$name = key($ref);

			if (PHP5) {
				$model = ClassRegistry::init(array('class' => $name, 'alias' => $name));
			} else {
				$model =& ClassRegistry::init(array('class' => $name, 'alias' => $name));
			}

			if (empty($model)) {
				trigger_error(sprintf(__("Model class '%s' not found in AclNode::node() when trying to bind %s object", true), $type, $this->alias), E_USER_WARNING);
				return null;
			}

			$tmpRef = null;
			if (method_exists($model, 'bindNode')) {
				$tmpRef = $model->bindNode($ref);
			}
			if (empty($tmpRef)) {
				$ref = array('model' => $name, 'foreign_key' => $ref[$name][$model->primaryKey]);
			} else {
				if (is_string($tmpRef)) {
					return $this->node($tmpRef);
				}
				$ref = $tmpRef;
			}
		}
		if (is_array($ref)) {
			if (is_array(current($ref)) && is_string(key($ref))) {
				$name = key($ref);
				$ref = current($ref);
			}
			foreach ($ref as $key => $val) {
				if (strpos($key, $type) !== 0 && strpos($key, '.') === false) {
					unset($ref[$key]);
					$ref["{$type}0.{$key}"] = $val;
				}
			}
			$queryData = array(
				'conditions' => $ref,
				'fields' => array('id', 'parent_id', 'model', 'foreign_key', 'alias'),
				'joins' => array(array(
					'table' => $table,
					'alias' => "{$type}0",
					'type' => 'LEFT',
					'conditions' => array(
						$db->name("{$type}.lft") . ' <= ' . $db->name("{$type}0.lft"),
						$db->name("{$type}.rght") . ' >= ' . $db->name("{$type}0.rght")
					)
				)),
				'order' => $db->name("{$type}.lft") . ' DESC'
			);
			$result = $db->read($this, $queryData, -1);

			if (!$result) {
				trigger_error(sprintf(__("AclNode::node() - Couldn't find %s node identified by \"%s\"", true), $type, print_r($ref, true)), E_USER_WARNING);
			}
		}
		return $result;
	}
}
?>