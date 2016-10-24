<?php

namespace Xesau\MyON;

use InvalidArgumentException;

class Order {
	
	/** 
	 * @var string|string[] $field The field
	 * @var string $mode The mode
	 */
	private $field;
	private $values;
	
	/**
	 * Initiates a new ORDER BY FIELD rule
	 *
	 * @param string|string[] $field The field
	 * @param string $mode The mode
	 */
	public function __construct($field, $mode) {
		$mode = strtoupper($mode);
		
		if (!MyON::validateField($field)) {
			throw new InvalidArgumentException('OrderByField $field must be a valid field (string or string[]).');
		}
		
		if ($mode !== 'ASC' && $mode !== 'DESC') {
			throw new InvalidArgumentException('OrderByField $mode must be a ASC or DESC.');
		}
		
		$this->field = $field;
		$this->mode = $mode;
	}
	
	public function __toString() {
		return MyON::escapeField($this->field) .' '. strtoupper($this->mode);
	}
	
}