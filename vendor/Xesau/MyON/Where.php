<?php

namespace Xesau\MyON;

use InvalidArgumentException;
use Traversable;

class Where {
	
	private $field;
	private $operator;
	private $value;
	
	public function __construct($field, $operator, $value) {
		if (!MyON::validateField($field)) {
			throw new InvalidArgumentException('Where $field must be a valid field name.');
		}
		
		if (!self::validateOperator($operator, $value)) {
			throw new InvalidArgumentException('Where $operator must be a valid operator, and the value must be compatible with this operator. (' . $operator . '; '. gettype($value) .')');
		}
		
		$this->field = $field;
		$this->operator = $operator;
		$this->value = $value;
	}
	
	public function __toString() {
		$sqlOp = self::getSqlOperator($this->operator);
		return MyON::escapeField($this->field) .' '. $sqlOp .' '. self::getOperatorVal($sqlOp, $this->value);
	}
	
	private static function validateOperator($operator, $value) {
		$operator = strtolower($operator);
		
		switch($operator) {
			// exact comparison
			case '=':
			case '!=':
				return is_scalar($value) || (is_object($value) && method_exists($value, 'objectInfo')) || is_null($value);
			
			// numeric comparison
			case '>':
			case '<':
			case '!>':
			case '!<':
			case '>=':
			case '<=':
				return is_int($value) || (is_object($value) && method_exists($value, 'objectInfo'));
			
			// array comparison
			case 'in':
			case '!in':
				return is_array($value) || $value instanceof Traversable;
			
			// regex comparison
			case 'regex':
				return is_string($value) || (is_object($value) && method_exists($value, 'objectInfo'));
		}
	}
	
	private static function getSqlOperator($operator) {
		$operator = strtolower($operator);
		switch($operator) {
			// Normal SQL operators
			case '=':
			case '!=':
			case '>':
			case '<':
			case '>=':
			case '<=':
			case 'in':
				return strtoupper($operator);
			
			// Special operators
			case '!in':
				return 'NOT IN';
			case '!>':
				return '<=';
			case '!<':
				return '>=';
			
			// Invalid operator?
			default:
				return false;
		}
	}
	
	private static function getOperatorVal($operator, $value) {
		switch($operator) {
			case 'IN': 
				// array $value
				$outVals = [];
				foreach($value as $v) {
					$outVals[] = MyON::escapeValue($v);
				}
				return '('. implode(', ', $outVals) .')';
			case '>':
			case '<':
			case '>=':
			case '<=':
			case '=':
			case '!=':
				return MyON::escapeValue($value);
		}
	}
	
	
}