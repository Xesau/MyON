<?php

namespace Xesau\MyON;

use PDO;
use InvalidArgumentException;

class MyON {
	
	private static $pdo;
	private static $prefix = '';
	
	public static function init(PDO $pdo, $prefix = '') {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
		self::$pdo = $pdo;
		self::$prefix = $prefix;
	}
    
	public static function getPDO() {
		if (self::$pdo === null) {
			throw new RuntimeException('MyONConfig has not been initialized yet.');
		}
		
		return self::$pdo;
	}
	
	public static function getPrefix($escaped = false) {
		if (self::$pdo === null) {
			throw new RuntimeException('MyONConfig has not been initialized yet.');
		}
		
        if ($escaped)
            return str_replace('`' , '``', self::$prefix);
        
		return self::$prefix;
	}
	
    public static function execute($sql, array $parameters = []) {
        $sql = preg_replace('/(?<!\\\\)\$\#/', self::$prefix, $sql);
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($parameters);
        return $stmt;
    }
    
	public static function escapeValue($value) {
		if ($value instanceof Query) {
			return '('. (string)$value. ')';
		} elseif (is_object($value) && method_exists($value, 'objectInfo')) {
			return self::escapeValue($value->primaryValues(false)[0]);
		} elseif (is_int($value) || is_float($value)) {
			return (string)$value;
        } elseif (is_null($value)) {
            return 'NULL';
        } elseif (is_array($value)) {
            $vals = [];
            foreach($value as $v)
                $vals[] = self::escapeValue($v);
            return '['. implode(', ', $vals) .']';
		} else {
			return self::$pdo->quote($value);
		}
	}
	
	/**
	 * Verifies a field value strucutre
	 *
	 * @param string|string[] $field The field
	 * @return bool Whether the field value is valid.
	 */
	public static function validateField($field) {
		if (is_string($field)) {
			if (strlen($field) !== 0) {
				return true;
			}
		} elseif (is_array($field)) {
			if (count($field) === 0) {
				return false;
			}
			
			foreach ($field as $f) {
				if (!is_string($f) || strlen($f) == 0) {
					return false;
				}
			}
            
            return true;
		}
		
		return false;
	}
	
    /**
     * Escapes a SQL name
     *
     * @param string|string[] $field The field name, or [table, field] or [database, table, field] or [database, table].
     * @return string The SQL name, escaped and glued together into a usable string.
     * @throws InvalidArgumentException When $field is not of type string or string[].
     */
    public static function escapeField($field) {
        if (!self::validateField($field))
			throw new InvalidArgumentException('MyON.escapeField $field must be of type string or string[].');
		
		$replacer = function($f) {
            return '`'. str_replace('`' , '``', $f) . '`';
        };
        
        if (is_array($field)) {
            $newStrings = [];
            foreach($field as $f) {
				$newStrings[] = $replacer($f);
            }
            return implode('.', $newStrings);
        } elseif (is_string($field)) {
            return $replacer($field);
        }
    }
	
    public static function parseClassRef($ref, $own) {
        if ($ref == '')
            return false;
        
        if ($ref == '.self')
            return $own;
        
        if ($ref[0] == '~') {
            $ownNamespace = substr($own, 0, strrpos($own, '\\'));
            return $ownNamespace .'\\'. substr($ref, 1);
        }
		
		return $ref;
    }
}
