<?php

namespace Xesau\MyON;

use PDO;
use Iterator;
use RuntimeException;

/**
 * Traversable MySQL SELECT
 */
class Selection extends Query implements Iterator {
	
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
		$sql = 'SELECT * FROM '. MyON::escapeField($this->oi->getTableName());
		
		// WHERE ...
		if ($this->mainWhereGroup !== null) {
			$sql .= ' WHERE ' . (string)$this->mainWhereGroup;
		}
		
		// ORDER BY ...
		if (count($this->orderRules) !== 0) {
			$sql .= ' ORDER BY ';
			foreach($this->orderRules as $orderRule) {
				$sql .= (string)$orderRule;
			}
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
	
	public function currentExtra() {
		if (!$this->iterating)
			$this->perform();
	}
	
	public function current() {
		if (!$this->iterating)
			$this->perform();
        if (count($this->results) == 0)
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
     * Performs the query and injects data to DbObject
     *
     * @param bool $loadObjects Whether to store the results as objects
     */
	public function perform($loadObjects = true) {
        $this->iterating = true;
        $this->results = [];
		$stmt = MyON::getPDO()->query($this->__toString());
		
        static $n;
        $n++;
        if ($n > 20)
            return;
        
		$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
        // Inject entries into DbObject but don't overwrite
        $destC = $this->destinationClass;
        $destC::inject($entries, false);
		
        foreach($entries as $entry) {
            // Load objects and store results if needed
            if ($loadObjects === true) {
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