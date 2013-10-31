<?php
namespace ValuQuery\Selector\SimpleSelector;

abstract class AbstractSelector 
    implements SimpleSelectorInterface
{

    /**
     * Default selectors
     *
     * @var string
     */
    const SELECTOR_UNIVERSAL = 'universal';

    const SELECTOR_ELEMENT = 'element';

    const SELECTOR_ID = 'id';

    const SELECTOR_ROLE = 'role';

    const SELECTOR_CLASS = 'class';

    const SELECTOR_ATTRIBUTE = 'attribute';

    const SELECTOR_PATH = 'path';

    const SELECTOR_PSEUDO = 'pseudo';

    /**
     * Selector name
     *
     * @var string
     */
    protected $name;

    /**
     * Selector value
     *
     * @var string
     */
    protected $value = null;

    /**
     * Selector pattern
     *
     * @var string
     */
    protected $pattern = null;

    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * Retrieve complete simple selector pattern
     *
     * @return string
     */
    public function getPattern()
    {
        if (is_null($this->pattern)) {
            $class = get_class($this);
            $enclosure = call_user_func($class . '::getEnclosure');
            
            if (sizeof($enclosure) > 1) {
                $this->pattern = $enclosure[0] . $this->getRawValue() .
                         $enclosure[1];
            } else {
                $this->pattern = $enclosure[0] . $this->getRawValue();
            }
        }
        
        return $this->pattern;
    }

    /**
     * Retrieve simple selector type
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Retrieve simple selector value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->getRawValue();
    }
    
    /**
     * (non-PHPdoc)
     * @see \ValuQuery\Selector\SimpleSelector\SimpleSelectorInterface::getRawValue()
     */
    public function getRawValue()
    {
        return $this->value;
    }

    public function __toString()
    {
        return $this->getPattern();
    }

    /**
     * Get selector enclosure characters
     *
     * @return array
     */
    public static function getEnclosure()
    {
        return array();
    }
}