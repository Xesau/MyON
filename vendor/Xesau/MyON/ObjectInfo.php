<?php

namespace Xesau\MyON;

use InvalidArgumentException;

class ObjectInfo
{
	
	/**
	 * @var string $tableName The name of the table in the database.
	 * @var string[] $primaryFields The names of the primary fields of the table.
	 * @var bool $usePrefix Whether to use the global table prefix as set in MyONConfig.
	 * @var string[string] $references The class names of the type references.
	 */
	private $tableName;
	private $primaryFields;
	private $usePrefix;
	private $references = [];
	
	public function __construct($tableName, $primaryFields = null, $usePrefix = true) {
		if (!is_string($tableName)) {
			throw new InvalidArgumentException('ObjectInfo $tableName ought to be a string, got '. gettype($tableName) .'.');
		}
		
		$this->tableName = $tableName;
		$this->usePrefix = (bool)$usePrefix;
		
		$primFieldOK = is_string($primaryFields);
		if (!$primFieldOK && is_array($primaryFields)) {
			$primFieldOK = true;
			foreach($primaryFields as $v) {
				if (!is_string($v)) {
					$primFieldOK = false;
					break;
				}
			}
		}
		
		if ($primFieldOK) {
			$this->primaryFields = (array)$primaryFields;
		} else {
			throw new InvalidArgumentException('ObjectInfo $primaryFields ought to be string or string[], got '. gettype($primaryFields) .'.');
		}
	}
	
	public function getTableName() {
		return ($this->usePrefix ? MyON::getPrefix() : '') . $this->tableName;
	}
	
	public function getPrimaryFields() {
		return $this->primaryFields;
	}
    
    public function getReferencedFields() {
        return $this->references;
    }
	
	/**
	 * Adds a type reference for a field in the table
	 * So that when value of that field is requested, an object is returned
	 * with the type for this field. This allows for structures like $group->leader->name.
	 *
	 * @param string $fieldName The name of the field in the table
	 * @param string $className The class name of the type, or .self to refer to the same class, or ~.... to refer to a class in the same namespace.
	 * @return $this
	 */
	public function ref($fieldName, $className) {
		if ($className !== '.self') {
			if (!is_string($className) || strlen($className) == 0 || !class_exists($className)) {
                if ($className[0] !== '~') {
                    throw new InvalidArgumentException('ObjectInfo.ref: $className ought to be a valid class name, got '. gettype($className) .($className === null ? '' : ' '. strip_tags($className)).'.');
                }
            }
		}
	
		if (isset ($this->references[$fieldName])) {
			throw new InvalidArgumentException('ObjectInfo.ref: Referene for field "'. strip_tags($fieldName) .'" already set.');
		} else {
			$this->references[$fieldName] = $className;
		}
		
		return $this;
	}
	
}
