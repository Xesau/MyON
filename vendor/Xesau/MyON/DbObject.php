<?php

namespace Xesau\MyON;

use RuntimeException;
use InvalidArgumentException;

trait DbObject {
    
    private static $objectInfo;
    
    private static $cachedData = [];
    private static $cachedObjects = [];
    
    private static $tableFields = false;
    
    private $key;
    private $changedFields = [];
    private function __construct($key) {
        $this->key = $key;
    }
    
    public function __destruct() {
        if (count($this->changedFields) !== 0) {
            $query = 'UPDATE '. MyON::escapeField(self::getOI()->getTableName()) .' SET ';
            $first = true;
            foreach($this->changedFields as $f) {
                if ($first == false)
                    $query .= ', ';
                else
                    $first = false;
                
                $query .= MyON::escapeField($f) .' = '. MyON::escapeValue(self::$cachedData[$this->key][$f]);
            }
            $query .= ' WHERE ';
            $whereStrings = [];
            $keyDecoded = json_decode($this->key);
            foreach(self::getOI()->getPrimaryFields() as $q => $pf) {
                $whereStrings[] = MyON::escapeField($pf) .' = '. MyON::escapeValue($keyDecoded[$q]);
            }
            $query .= implode(' AND ', $whereStrings);
            MyON::getPDO()->query($query);
        }
    }

    public function __isset($field) {
        return isset(self::$tableFields[$field]);
    }
    
    public function __get($field) {
        // Validate field name
        if (!self::__isset($field)) {        
            // Class Name Parts
            $cnp = explode('\\', __CLASS__);
            
            // Throw Exception: ClassName.__get $field must be a valid field.
            throw new InvalidArgumentException(end($cnp). '.__get $field must be a valid field.');
        }
        
        if (self::$tableFields[$field] !== false) {
            $refDestC = self::$tableFields[$field];
            
            // Parse complicated class references
            $destC = MyON::parseClassRef($refDestC, __CLASS__);
            
            return $destC::byPrim([self::$cachedData[$this->key][$field]]);
        } else {
            return self::$cachedData[$this->key][$field];
        }
    }
    
    public function __set($field, $value) {
        if (!isset(self::$tableFields[$field])) {
            // Class Name Parts
            $cnp = explode('\\', __CLASS__);
            
            // Throw Exception: ClassName.__get $field must be a valid field.
            throw new InvalidArgumentException(end($cnp). '.__set $field must be a valid field.');    
        }
        
        self::$cachedData[$this->key][$field] = $value;
        
        if (!in_array($field, $this->changedFields))
            $this->changedFields[] = $field;
    }
    
    protected static function objectInfo() {// Class Name Parts
        $cnp = explode('\\', __CLASS__);
        
        // Throw Exception: ClassName doesn't implement objectInfo() function
        throw new RuntimeException(end($cnp). ' doesn\'t implement objectInfo() function.');
    }
    
    /**
     * Gets the table source and structure information for this class.
     *
     * @return ObjectInfo The Object Info
     */
    public static function getOI() {
        if (self::$objectInfo === null) {
            self::$objectInfo = self::objectInfo();
        }
        
        return self::$objectInfo;
    }
    
    /**
     * Selects a row by the values of it's primary fields
     *
     * @param mixed|mixed[] $primaryFields The primary fields
     * @throws InvalidArgumentException When $primaryFields does not provide information for all primary fields.
     * @return <DbObject> 
     */
    public static function byPrim($primaryFields) {
        $origPfOrder = self::getOI()->getPrimaryFields();
        
        // Make $primaryFields an array
        if (!is_array($primaryFields)) {
            $primaryFields = (array)$primaryFields;
        }
        
        // If the array is not associative 
        if (array_values($primaryFields) == $primaryFields) {
            $pfCount = count($primaryFields);
            if ($pfCount == count($origPfOrder)) {
                $primFields = [];
                for($i = 0; $i < $pfCount; $i++)
                    $primFields[$origPfOrder[$i]] = $primaryFields[$i];
            } else {
                throw new InvalidArgumentException('DbObject.byPrim $primaryFields must contain all primary fields.');    
            }
        } else {
            $need = $origPfOrder;
            sort($need);
            $curr = array_keys($primaryFields);
            sort ($curr);
            if ($curr == $need) {
                $primFields = $primaryFields;
            } else {
                throw new InvalidArgumentException('DbObject.byPrim $primaryFields must contain all primary fields.');
            }
        }
        
        $key = json_encode(array_values($primFields));
        if (isset(self::$cachedObjects[$key])) {
            return self::$cachedObjects[$key];
        }
        else if (isset(self::$cachedData[$key])) {
            return new self($key);
        }
        else {
            $select = self::select();
            foreach($primFields as $f => $v)
                $select->where($f, '=', $v);
        
            return $select->first();
        }
    }
    
    /**
     * Returns the primary fields
     *
     * @param bool $assoc Whether to return an associative array (with the field names as keys)
     * @return mixed[]|mixed[string] The priamry fields
     */
    public function primaryValues($assoc = true) {
        $values = [];
        $fields = self::getOI()->getPrimaryFields();
        if ($assoc === true) {
            foreach($fields as $field)
                $values[$field] = self::$cachedData[$this->key][$field];
        } else {
            foreach($fields as $field) {
                $values[] = self::$cachedData[$this->key][$field];
            }
        }
        return $values;
    }
    
    /**
     * Inserts data into the table
     *
     * @param array|array[] $data The data
     * @throws InvalidArgumentException When $data is empty
     * @throws InvalidArgumentException When more fields are provided than are in table. (is only checked if data has been selected earlier this request)
     * @return void
     */
    protected static function insert(array $data) {
        if (count($data) == 0) {
            throw new InvalidArgumentException('DbObject.insert $data cannot be empty.');
        }
        
        $isMultiple = isset($data[0]) && is_array($data[0]);
        if ($isMultiple) {
            $fields = [];
            foreach($data as $entry) {
                foreach($entry as $k => $v) {
                    if (!in_array($k, $fields)) {
                        $fields[] = $k;
                    }
                }
            }
        } else {
            $fields = array_keys($data);
        }
        $countFields = count($fields);
       
        if (self::$tableFields !== false) {
            if ($countFields > count(self::$tableFields)) {
                throw new InvalidArgumentException('DbObject.insert $data contains more fields than are in table.');
            }
        }
        
        $sql = 'INSERT INTO '. MyON::escapeField(self::getOI()->getTableName()) .' (';
        for($i = 0; $i < $countFields; $i++) {
            if ($i !== 0)
                $sql .= ', ';
            $sql .= MyON::escapeField($fields[$i]);
        }
        $sql .= ') VALUES ';
        if ($isMultiple) {
            $countEntries = count($data);
            for($i = 0; $i < $countEntries; $i++) {
                if ($i !== 0)
                    $sql .= ', ';
                $sql .= self::generateInsertEntry($fields, $data[$i]);
            }
        } else {
            $sql .= self::generateInsertEntry($fields, $data);
        }
        
        try {
            MyON::getPDO()->query($sql);
            $lastId = MyON::getPDO()->lastInsertId();
            
            // If multiple entries were inserted, return an arrya of the IDs for all the new rows
            if ($isMultiple) {
                return range($lastId, $lastId + count($data) - 1);
            }
            
            // If only one entry was inserted, return the ID of the new row
            else {
                return $lastId;
            }
        } catch (Exception $ex) {
            throw new RuntimeException('DbObject.insert PDO error '. $ex->getCode() .' occurred: '. $ex->getMessage(). ' in '. $ex->getFile() .':'. $ex->getLine(). '. '. $ex->getTrace());
        }
    }
    
    private static function generateInsertEntry(array $fields, array $data) {
        $countFields = count($fields);
        
        $sql = '(';
        for ($i = 0; $i < $countFields; $i++) {
            // Seperate values with a ,
            if ($i !== 0)
                $sql .= ', ';
            
            $sql .= MyON::escapeValue(isset($data[$fields[$i]]) ? $data[$fields[$i]] : null);
        }
        return $sql .')';
    }
    
    /**
     * Injects data into the cache
     *
     * @param array $data      The data
     * @param bool  $overwrite Whether to overwrite the existing data (if any)
     *
     * @return void
     */
    public static function inject(array $data, $overwrite = false) {
        $oi = self::getOI();
        
        foreach($data as $entry) {
            if (self::$tableFields === false) {
                // Make string(field name) --> bool(is reference) map for table fields, 
                self::$tableFields = [];
                $refFields = $oi->getReferencedFields();
                foreach(array_keys($entry) as $field) {
                    self::$tableFields[$field] = isset($refFields[$field]) ? $refFields[$field] : false;
                }
            }
            
            $primaryFieldValues = [];
            foreach($oi->getPrimaryFields() as $pf) {
                $primaryFieldValues[] = $entry[$pf];
            }
            $key = json_encode($primaryFieldValues);
            
            if ($overwrite || !isset(self::$cachedData[$key]))
                self::$cachedData[$key] = $entry;
        }
    }
    
    /**
     * Get the primary values of the cached objects
     *
     * @param bool $asJSON Whether to return the keys JSON-encoded
     * @return mixed[][]
     */
    public static function getCachedObjectPrims($asJSON = false) {
        if ($asJSON !== true) {
            $out = [];
            foreach(self::$cachedData as $key => $v) {
                $out[] = json_decode($key);
            }
            return $out;
        } else {
            return array_keys(self::$cachedData);
        }
    }

    /** 
     * Creates a selector for this object type
     *
     * @param bool|string $refs Whether to load the references, if 'deep' deepload references.
     * @return Selection
     */
    public static function select($refs = false) {
        $select = new Selection(__CLASS__);
        
        if ($refs !== false)
            $select->loadReferences($refs !== false, $refs == 'deep');
        
        return $select;
    }
    
}
