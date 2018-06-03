<?php

namespace Xesau\MyON;

use InvalidArgumentException;

abstract class Query
{
    protected $mainWhereGroup = null;
    protected $orderRules = [];

    protected $offset = false;
    protected $limit = false;

    /**
     * Where=( existingRules and (rule...) )
     *
     * @param string|string[] $field    The field that must follow the condition
     * @param string          $operator The operator (=, !=, etc.)
     * @param mixed|Selection $value    The value accompanying the operator
     * @param bool            $continue Whether to continue with the newly created group
     *
     * @return $this|WhereGroup See param $continue
     */
    public function where($field, $operator, $value, $continue = false)
    {
        if ($this->mainWhereGroup == null)
            $this->mainWhereGroup = new WhereGroup(null, $this);
        
        $n = new WhereGroup(new Where($field, $operator, $value), $this, null);
        $this->mainWhereGroup->andGroup($n);
        return $continue ? $n : $this;
    }
    
    /**
     * Where=( existingRules or (rule...) )
     *
     * @param string|string[] $field    The field that must follow the condition
     * @param string          $operator The operator (=, !=, etc.)
     * @param mixed|Selection $value    The value accompanying the operator
     * @param bool            $continue Whether to continue with the newly created group
     *
     * @return $this|WhereGroup See param $continue
     */
    public function orWhere($field, $operator, $value, $continue = false)
    {
        if ($this->mainWhereGroup == null)
            $this->mainWhereGroup = new WhereGroup(null, $this);
        
        $n = new WhereGroup(new Where($field, $operator, $value), $this, null);
        $this->mainWhereGroup->orGroup($n);
        return $continue ? $n : $this;
    }
    
    /** 
     * Where=( existingRules or (group) )
     * @return $this
     */
    public function whereGroup(WhereGroup $group) {
        if ($this->mainWhereGroup == null) {
            $this->mainWhereGroup = new WhereGroup(null, $this);
        }
        
        $this->mainWhereGroup->andGroup($group);
        
        return $this;
    }
    
    /**
     * Where=( existingRules and (group) )
     * @return $this
     */
    public function orWhereGroup(WhereGroup $group) {
        if ($this->mainWhereGroup == null) {
            $this->mainWhereGroup = new WhereGroup(null, $this);
        }
        
        $this->mainWhereGroup->orGroup($group);
        
        return $this;
    }
    
    /**
     * Where=( (existingRules) and (new) )
     * @return WhereGroup the new where group
     */
    public function whereNewGroup() {
        $g = new WhereGroup(null, $this);
        $this->whereExistingAndGroup($g);
        return $g;
    }
    
    /**
     * Where=( (existingRules) or (new) )
     * @return WhereGroup the new where group
     */
    public function orWhereNewGroup() {
        $g = new WhereGroup(null, $this);
        $this->whereExistingOrGroup($g);
        return $g;
    }
    
    /**
     * Where=( (existingRules) and (group) )
     * @param WhereGroup $g
     * @return $this
     */
    public function whereExistingAndGroup(WhereGroup $g) {
        if ($this->mainWhereGroup == null) {
            $this->mainWhereGroup = $g;
            return $this;
        }
        
        $main = new WhereGroup(null, $this);
        $main->andGroup($this->mainWhereGroup);
        $main->andGroup($g);
        
        $this->mainWhereGroup = $main;
        return $this;
    }
    
    /**
     * Where=( (existingRules) or (group) )
     * @param WhereGroup $g
     * @return $this
     */
    public function whereExistingOrGroup(WhereGroup $g) {
        if ($this->mainWhereGroup == null) {
            $this->mainWhereGroup = $g;
            return $this;
        }
        
        $main = new WhereGroup(null, $this);
        $main->orGroup($this->mainWhereGroup);
        $main->orGroup($g);
        
        $this->mainWhereGroup = $main;
        return $this;
    }

    public function asc($field)
    {
        $this->orderRules[] = new Order($field, 'asc');

        return $this;
    }

    public function desc($field)
    {
        $this->orderRules[] = new Order($field, 'desc');

        return $this;
    }

    public function orderField($field, array $values)
    {
        $this->orderRules[] = new OrderByField($field, $values);

        return $this;
    }
    
    public function rand() {
        $this->orderRules[] = new RandomOrder();
        
        return $this;
    }

    /**
     * Gets or sets the amount of rows offset in the result of this query.
     *
     * @param int|bool|null $offset The amount of rows to be offset. To disable the offset, specify FALSE.
     *                              To get the amount of rows to be offset, specify NULL.
     *
     * @return $this|int|bool If $offset is not specified, the currently set amount is returned, or FALSE
     *                        is returned if no amount has been set. If $offset is specified, the
     *                        current amount will be updated and the current object will be returned.
     */
    public function offset($offset = null)
    {
        if ($offset === null) {
            return $this->offset;
        }

        if ($offset !== false) {
            if (is_int($offset)) {
                if ($offset < 0) {
                    throw new InvalidArgumentException('Query.offset $offset must be an integer equal to or greater than 0, or FALSE to disable the offset.');
                }
            } else {
                throw new InvalidArgumentException('Query.offset $offset is not an int or FALSE.');
            }
        }

        $this->offset = $offset;

        return $this;
    }

    /**
     * Gets or sets the limit of the result of this query.
     *
     * @param int|bool|null $limit The limit to set. To disable the limit, specify FALSE. To get the limit, specify NULL.
     *
     * @return $this|int|bool If $limit is not specified, the currently set limit is returned, or FALSE
     *                        is returned if a limit has not been set. If $limit is specified, the
     *                        current limit will be updated and the current object will be returned.
     */
    public function limit($limit = null)
    {
        if ($limit === null) {
            return $this->limit;
        }

        if ($limit !== false) {
            if (is_int($limit)) {
                if ($limit < 1) {
                    throw new InvalidArgumentException('Query.limit $limit must be an integer greater than 0, or FALSE to disable the limit.');
                }
            } else {
                throw new InvalidArgumentException('Query.limit $limit is not an int or FALSE.');
            }
        }

        $this->limit = $limit;

        return $this;
    }
    
    public function page($page, $pageLength) {
        $page = max(1, $page);
        $pageLength = max(1, $pageLength);
        
        $this->limit($pageLength);
        $this->offset(($page - 1) * $pageLength);

        return $this;
    }
}
