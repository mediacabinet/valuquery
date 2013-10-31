<?php
namespace ValuQuery\Selector\SimpleSelector;

use ValuQuery\Selector\SimpleSelector\AbstractSelector;

class Attribute extends AbstractSelector
{

    const OPERATOR_EQUALS = '=';
    
    const OPERATOR_NOT_EQUALS = '!=';
    
    const OPERATOR_IN_LIST = '~=';
    
    const OPERATOR_SUBSTR_PREFIX = '^=';
    
    const OPERATOR_SUBSTR_SUFFIX = '$=';
    
    const OPERATOR_REG_EXP = '?=';
    
    const OPERATOR_SUBSTR_MATCH = '*=';
    
    const OPERATOR_GREATER_THAN = '>';
    
    const OPERATOR_GREATER_THAN_OR_EQUAL = '>=';
    
    const OPERATOR_LESS_THAN = '<';
    
    const OPERATOR_LESS_THAN_OR_EQUAL = '<=';
    
    protected $name = AbstractSelector::SELECTOR_ATTRIBUTE;
    
    protected $attribute;
    
    protected $operator;
    
    protected $condition;
    
    protected static $operators = array(
        Attribute::OPERATOR_EQUALS,
        Attribute::OPERATOR_NOT_EQUALS,
        Attribute::OPERATOR_IN_LIST,
        Attribute::OPERATOR_REG_EXP,
        Attribute::OPERATOR_SUBSTR_PREFIX,
        Attribute::OPERATOR_SUBSTR_SUFFIX,
        Attribute::OPERATOR_SUBSTR_MATCH,
        Attribute::OPERATOR_GREATER_THAN,
        Attribute::OPERATOR_GREATER_THAN_OR_EQUAL,
        Attribute::OPERATOR_LESS_THAN,
        Attribute::OPERATOR_LESS_THAN_OR_EQUAL,
    );
    
    public function __construct($attribute, $operator = null, $condition = null){
    
        if(!$attribute || !is_string($attribute)){
            throw new \InvalidArgumentException('Attribute must be a non-empty string');
        }
    
        $this->setAttribute($attribute)
             ->setOperator($operator)
             ->setCondition($condition);
    }    
    
    /**
     * Get attribute
     * 
     * @return string
     */
    public function getAttribute()
    {
        return $this->attribute;
    }
    
    /**
     * Set attribute name
     * 
     * @param string $attribute
     * @return Attribute
     */
    public function setAttribute($attribute)
    {
        $this->value     = null;
        $this->attribute = $attribute;
        
        return $this;
    }
    
    /**
     * Get operator
     * 
     * Operator may be omitted if no condition is
     * specified.
     * 
     * @return string|null
     */
    public function getOperator()
    {
        return $this->operator;
    }
    
    public function setOperator($operator)
    {
        $this->value    = null;
        $this->operator = $operator;
        
        return $this;
    }
    
    /**
     * Get condition
     * 
     * Condition may be string, boolean, null
     * or float.
     * 
     * @return mixed
     */
    public function getCondition()
    {
        return $this->condition;
    }
    
    public function setCondition($condition)
    {
        $this->value    = null;
        $this->condition= $condition;
        
        return $this;
    }
    
    public function getEscapedValue()
    {
        if($this->value == null){
            if($this->getOperator()){
            
                $condition = $this->getCondition();
                
                if(is_string($condition)){
                    $condition = '"'.self::escapeCondition($condition).'"';
                }
            
                $this->value = $this->getAttribute() . $this->getOperator() . $condition;
            }
            else{
                $this->value = $this->getAttribute();
            }
        }
        
        return $this->value;
    }
    
    /**
     * Retrieve array of supported operators
     * 
     * @return array
     */
    public static function getSupportedOperators(){
        return self::$operators;
    }
    
    public static function getEnclosure()
    {
        return array('[', ']');
    }
    
    /**
     * Escape condition value for attribute selector
     * 
     * @param string $condition
     * @return string Escaped value
     */
    public static function escapeCondition($condition)
    {
        return str_replace('"', '\\"', $condition);
    }
}