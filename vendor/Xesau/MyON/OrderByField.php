<?php

namespace Xesau\MyON;

class OrderByField {
	
	/** 
	 * @var string|string[] $field The field
	 * @var array $values The values
	 */
	private $field;
	private $values;
	
	/**
	 * Initiates a new ORDER BY FIELD rule
	 *
	 * @param string|string[] $field The field
	 * @param array|mixed $values The values
	 */
	public function __construct($field, $values) {
		if (!MyON::validateField($field)) {
			throw new InvalidArgumentException('OrderByField $field must be a valid field (string or string[]).');
		}
		
		$this->field = $field;
		
		if (!is_array($values)) {
			$this->values = [$values];	
		} else {
			$this->values = $values;
		}
	}
	
	public function __toString() {
		$valuesEscaped = [];
		foreach($this->values as $v)
			$valuesEscaped[] = MyON::getPDO()->quote($v);
		
		return 'FIELD('. MyON::escapeField($this->field) .', '. implode(', ', $valuesEscaped) .')';
	}
	
}