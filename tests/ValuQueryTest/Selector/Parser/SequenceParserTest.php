<?php
use ValuQueryTest\Selector\Parser\AbstractParserTestCase;

use ValuQuery\Selector\Parser\SequenceParser;

/**
 * SequenceParser test case.
 */
class SequenceParserTest 
    extends AbstractParserTestCase
{

    /**
     *
     * @var SequenceParser
     */
    private $parser;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->parser = new SequenceParser();
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        $this->parser = null;
        parent::tearDown();
    }
    
    public function testParse()
    {
        $sequence = $this->parser->parse('[name="John"]');
        
        $this->assertInstanceOf('ValuQuery\Selector\Sequence', $sequence);
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\Attribute', $sequence->shiftItem());
        $this->assertNull($sequence->popItem());
    }
    
    public function testParseWithMultipleSimpleSelectors()
    {
        $sequence = $this->parser->parse('[name="John"].old');
        
        $this->assertInstanceOf('ValuQuery\Selector\Sequence', $sequence);
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\Attribute', $sequence->shiftItem());
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\ClassName', $sequence->shiftItem());
        $this->assertNull($sequence->popItem());
    }
    
    public function testParseWithPath()
    {
        $sequence = $this->parser->parse('/$people/old:sort(age desc)');
        
        $this->assertInstanceOf('ValuQuery\Selector\Sequence', $sequence);
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\Path', $sequence->shiftItem());
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\Pseudo\Sort', $sequence->shiftItem());
        $this->assertNull($sequence->popItem());
    }
}

