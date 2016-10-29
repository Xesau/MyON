<?php

namespace Xesau\MyON;

use InvalidArgumentException;
use Traversable;

class WhereGroup {
	
	/**
	 * @var mixed[][] $elements The parts of the query
	 * @var Query $query The query
	 */
	private $elements = [];
	private $query;
	private $parent;
	
	public function __construct($where, Query $query, WhereGroup $parent = null) {
		$this->query = $query;
		$this->parent = $parent;
		$this->elements[] = ['and', $where];
	}
	
	public function andWhere($field, $operator, $value) {
		$this->elements[] = ['and', new Where($field, $operator, $value)];
		return $this;
	}
	
	public function orWhere($field, $operator, $value) {
		$this->elements[] = ['or', new Where($field, $operator, $value)];
		return $this;
	}
	
	public function andGroup($fieldOrGroup, $operator, $value) {
		if ($fieldOrGroup instanceof WhereGroup) {
            $group = $fieldOrGroup;
        } else {    
            $group = new WhereGroup(new Where($fieldOrGroup, $operator, $value), $this->query, $this);
        }
        
		$this->elements[] = ['and', $group];
		return $group;
	}
	
	public function orGroup($field, $operator, $value) {
		if ($fieldOrGroup instanceof WhereGroup) {
            $group = $fieldOrGroup;
        } else {    
            $group = new WhereGroup(new Where($fieldOrGroup, $operator, $value), $this->query, $this);
        }
        
		$this->elements[] = ['or', $group];
		return $group;
	}
	
	public function closeGroup() {
		if ($this->parent == null) {
			throw new RuntimeException('WhereGroup.closeGroup Cannot close master group.');
		} else {
			return $this->parent;
		}
	}
	
	/**
	 * Returns the Query
	 * 
	 * @return Query $query
	 */
	public function closeWhere() {
		return $this->query;
	}
	
	public function __toString() {
		$cElements = count($this->elements);
		if ($cElements == 0) {
			return 'FALSE';
		}
		
		if ($cElements == 1) {
			return (string)$this->elements[0][1];
		}
		
		$sql = '('.(string)$this->elements[0][1];
		$pastFirst = false;
		foreach($this->elements as $element) {
			if (!$pastFirst) {
				$pastFirst = true;
			} else {
				$sql .= ' '. strtoupper($element[0]) .' '. (string)$element[1];
			}
		}
		
		return $sql.')';
	}
	
}