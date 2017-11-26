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
        $this->value = is_object($value) ? clone($value) : $value;
    }
    
    public function __toString() {
        $sqlOp = self::getSqlOperator($this->operator);
        $val = self::getOperatorVal($sqlOp, $this->value);
        if ($val === null)
            return '1';
        
        if ($val == 'NULL') {
            if ($sqlOp == '=')
                $sqlOp = 'IS';
            if ($sqlOp == '!=')
                $sqlOp = 'IS NOT';
        }
        
        return MyON::escapeField($this->field) .' '. $sqlOp .' '. $val;
    }
    
    private static function validateSpecialSelect(array $value) {
        $c = count($value);
        
        if ($c == 1)
            if ($value[0] instanceof Query)
                return false;
        
        if (count($value) !== 2)
            return true;
        
        if ($value[0] instanceof Query)
            return is_string($value[1]);
        
        return true;
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
                return (is_array($value) && self::validateSpecialSelect($value)) || $value instanceof Traversable;
            
            // regex comparison
            case 'regex':
                return is_string($value) || (is_object($value) && method_exists($value, 'objectInfo'));
                
            // other
            case 'like':
            case '%':
                return is_scalar($value);
                break;
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
            case 'not in':
            case 'like':
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
            case 'NOT IN':
                if ($value === [])
                    return null;
                
                if ($value instanceof Selection) {
                    $primaryFields = $value->getOI()->getPrimaryFields();
                    if (count($primaryFields) !== 1)
                        return null;

                    $value = [$value, $primaryFields[0]];
                }
                
                if ($value[0] instanceof Query) {
                    $s = $value[0]->getQuery('select');
                    return '(SELECT '. MyON::escapeField($value[1]) . substr($s, 8) . ')';
                }
                
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
            case 'LIKE':
                return MyON::escapeValue($value);
        }
    }
    
    
}
