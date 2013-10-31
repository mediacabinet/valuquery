<?php
namespace ValuQuery\Selector\SimpleSelector\Pseudo;

use ValuQuery\Selector\SimpleSelector\Pseudo;

class Sort extends Pseudo
{
    /**
     * Sort attribute
     * 
     * @var string
     */
    protected $attribute = null;
    
    /**
     * Sort order
     * 
     * @var string
     */
    protected $order = null;
    
    public function __construct($attribute, $order)
    {
        $this->setAttribute($attribute);
        $this->setOrder($order);
        
        parent::__construct('sort', $this->getClassValue());
    }
    
    /**
     * Get sort attribute
     * 
     * @return string
     */
	public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * Set sort attribute
     * 
     * @param string $attribute
     */
	public function setAttribute($attribute)
    {
        if ($attribute !== $this->attribute && $this->attribute !== null) {
            $this->classValue = null;
        }
        
        $this->attribute = $attribute;
    }

    /**
     * Test whether sort order is ascending
     * 
     * @return boolean
     */
	public function isAscending()
    {
        return $this->getOrder() == 'asc';
    }
    
    /**
     * Get sort order
     *
     * @return string
     */
    public function getOrder(){
        return $this->order;
    }

    /**
     * Set sort order ascending/descending
     * 
     * @param boolean $ascending
     */
	public function setOrder($order)
    {
        if ($order !== $this->order && $this->order !== null) {
            $this->classValue = null;
        }
        
        $this->order = $order;
    }
    
    /**
     * (non-PHPdoc)
     * @see ValuQuery\Selector\SimpleSelector.Pseudo::getClassValue()
     */
    public function getClassValue()
    {
        if (parent::getClassValue() == null) {
            $this->setClassValue($this->makeClassValue());
        }
        
        return parent::getClassValue();
    }
    
    /**
     * Make class value
     * 
     * @return string
     */
    protected function makeClassValue(){
        return $this->getAttribute() . ' ' . $this->getOrder();
    }
}