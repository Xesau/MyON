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
     * Limits the selection to rows where the given field follows the given condition.
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
        if ($this->mainWhereGroup == null) {
            $this->mainWhereGroup = $nc = new WhereGroup(new Where($field, $operator, $value), $this);
        } else {
            $nc = $this->mainWhereGroup->andGroup($field, $operator, $value);
        }

        return $continue ? $nc : $this;
    }
    
    /**
     * Limits the selection to rows where the given field follows the given condition or the previous condition.
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
        if ($this->mainWhereGroup == null) {
            $this->mainWhereGroup = ($newGroup = new WhereGroup(new Where($field, $operator, $value), $this));
        } else {
            $newGroup = $this->mainWhereGroup->orGroup($field, $operator, $value);
            $this->mainWhereGroup = $newGroup;
        }

        return $continue ? $newGroup : $this;
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
                    throw new InvalidArgumentException('Query.offset $offset must be an integer equal to or greater than 0, or -1 to disable the offset.');
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
                    throw new InvalidArgumentException('Query.limit $limit must be an integer greater than 0, or -1 to disable the limit.');
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
