<?php

namespace Xesau\MyON;

use PDO;
use Iterator;
use Countable;

use RuntimeException;

/**
 * Traversable MySQL SELECT
 */
class Selection extends Query implements Iterator, Countable {
    
    private $mode = 'select';
    
    private $oi;
    private $destinationClass;
    
    private $iterating = false;
    private $currentIndex = 0;
    private $results = null;
    
    private $loadReferences = false;
    private $loadDeepReferences = false;
        
    public function __construct($destinationClass) {
        if (!class_exists($destinationClass)) {
            throw new InvalidArgumentException('Selection.destinationClass is not a valid class, got '. $destinationClass .'.');
        }
        
        $this->oi = $destinationClass::getOI();
        $this->destinationClass = $destinationClass;
    }
    
    public function getOI() {
        return $this->oi;
    }
    
    public function getModelClass() {
        return $this->destinationClass;
    }
    
    /**
     * Sets whether to (deep) parse references (useful for bulkloading)
     *
     * @param bool $parse Whether to parse references
     * @param bool $deep  Whether to parse deep references (references in referenced objects)
     * @return $this
     */
    public function loadReferences($parse = true, $deep = false) {
        $this->loadReferences = (bool)$parse;
        $this->loadDeepReferences = (bool)$deep;
        return $this;
    }
    
    public function __toString() {
        // SELECT ... FROM ...
        if ($this->mode == 'count') {
            $sql = 'SELECT COUNT(*)';
        } elseif ($this->mode == 'select') {
            $sql = 'SELECT *';
        } elseif ($this->mode == 'select_prims') {
            $sql = 'SELECT ';
            $first = true;
            foreach($this->oi->getPrimaryFields() as $f) {
                if ($first) {
                    $sql .= MyON::escapeField($f);
                    $first = false;
                } else {
                    $sql .= ', '. MyON::escapeField($f);
                }
            }
        } elseif ($this->mode == 'delete') {
            $sql = 'DELETE';
        }
        
        $sql .= ' FROM '. MyON::escapeField($this->oi->getTableName());
        
        // WHERE ...
        if ($this->mainWhereGroup !== null) {
            $sql .= ' WHERE ' . (string)$this->mainWhereGroup;
        }
        
        // ORDER BY ...
        if (count($this->orderRules) !== 0) {
            $sql .= ' ORDER BY ' . implode(', ', (string)$orderRule);
        }
        
        // LIMIT ...
        if ($this->limit !== false) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        
        // OFFSET ...
        if ($this->offset !== false) {
            $sql .= ' OFFSET '. $this->offset;
        }
        
        return $sql;
    }
    
    /**
     * Counts the amount of rows that would be retrieved from this query
     *
     * @return int The amount
     */
    public function count() {
        $this->mode = 'count';
        $stmt = MyON::getPDO()->query($this->__toString());
        $res = $stmt->fetch(PDO::FETCH_NUM);
        return (int)$res[0];
    }
    
    /** 
     * Removes the selected rows
     *
     * @return int The amount of rows deleted
     */
    public function delete() {
        $this->mode = 'delete';
        $stmt = MyON::getPDO()->query($this->__toString());
        return $stmt->rowCount();
    }
    
    public function current() {
        if (!$this->iterating)
            $this->perform();
        if (count($this->results) == 0)
            return null;
        
        if (!isset($this->results[$this->currentIndex]))
            return null;
        
        return $this->results[$this->currentIndex];
    }
    public function key() {
        if ($this->iterating) {
            $pfs = $this->oi->getPrimaryFields();
            if (count($pfs) == 1)
                return $this->current()->primaryValues()[$pfs[0]];
            else
                return $this->current()->primaryValues();
        } else {
            throw new RuntimeException('Cannot ask key before starting iteration.');
        }
    }
    
    public function next() {
        if (!$this->iterating)
            $this->perform();
        $this->currentIndex++;
    }
    public function rewind() {
        if (!$this->iterating)
            $this->perform();
        $this->currentIndex = 0;
    }
    public function valid() {
        if (!$this->iterating)
            $this->perform();
        return $this->currentIndex < count($this->results);
    }
    
    /**
     * Returns the first result.
     * If the selection had not been performed yet, it will automatically limit the result to 1.
     *
     * @return DbObject The object
     */
    public function first() {
        if ($this->iterating) {   
            $this->rewind();
            return $this->current();
        } else {
            $this->limit(1);
            $this->perform();
            return $this->current();
        }
    }
    
    /**
     * Selects the primary fields
     *
     * @return array
     */
    public function prims() {
        $this->mode = 'select_prims';
        $this->iterating = true;
        $stmt = MyON::getPDO()->query($this->__toString());
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Performs the query and injects data to DbObject
     *
     * @param bool $loadObjects Whether to store the results as objects
     */
    public function perform($loadObjects = true) {
        $this->mode = 'select';
        $this->iterating = true;
        $this->results = [];
        $stmt = MyON::getPDO()->query($this->__toString());
        
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Inject entries into DbObject but don't overwrite
        $destC = $this->destinationClass;
        $destC::inject($entries, false);
        
        // Load objects and store results if needed
        if ($loadObjects === true) {
            foreach($entries as $entry) {
                $pfVals = [];
                foreach($this->oi->getPrimaryFields() as $field) {
                    $pfVals[$field] = $entry[$field];
                }
                $this->results[] = $destC::byPrim($pfVals);
            }
        }
        
        if ($this->loadReferences) {
            $referencedFields = $this->oi->getReferencedFields();
            foreach ($referencedFields as $field => $refDestCBase) {
                // Parse complicated class reference
                $refDestC = MyON::parseClassRef($refDestCBase, $destC);
                
                if (count($refDestC::getOI()->getPrimaryFields()) > 1) {
                    throw new RuntimeException('Cannot make single-field references to objects with multilpe primary fields.');
                }
                
                // To prevent duplicates, create a hashmap for the values
                $refFieldValMap = [];
                foreach($entries as $entry) {
                    $refFieldValMap[$entry[$field]] = true;
                }
 
                // Don't load already-loaded entries
                foreach($refDestC::getCachedObjectPrims() as $cachedObjectPrims) {
                    unset($refFieldValMap[$cachedObjectPrims[0]]);
                }
                
                if (count($refFieldValMap) !== 0) {                
                    // Get the keys to get the reference field values
                    $refFieldVals = array_keys($refFieldValMap);
                    
                    // Select referenced values
                    $selection = new Selection($refDestC);
                    $selection->where($refDestC::getOI()->getPrimaryFields()[0], 'in', $refFieldVals);
                    
                    // Deepload references if needed
                    if ($this->loadDeepReferences)
                        $selection->loadReferences(true, true);
                    
                    // Perform selection & load them into cache for future calling
                    $selection->perform();
                }
            }
        }
    }
}
