<?php
use ValuQueryTest\Selector\Parser\AbstractParserTestCase;

use ValuQuery\Selector\Parser\PseudoParser;

/**
 * PathParser test case.
 */
class PseudoParserTest 
    extends AbstractParserTestCase
{

    /**
     * @var PseudoParser
     */
    private $parser;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->parser = new PseudoParser();
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        $this->parser = null;
        parent::tearDown();
    }

    /**
     * Tests PathParser->parse()
     */
    public function testParse()
    {
        $selector = $this->parser->parse('limit(5)');
        
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\Pseudo', $selector);
        $this->assertEquals('limit', $selector->getClassName());
        $this->assertEquals(5, $selector->getClassValue());
    }
    
    public function testParseNoClassValue()
    {
        $selector = $this->parser->parse('published');
        
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\Pseudo', $selector);
        $this->assertEquals('published', $selector->getClassName());
        $this->assertNull($selector->getClassValue());
    }
    
    public function testParseSort()
    {
        $selector = $this->parser->parse('sort(name desc)');
        
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\Pseudo\Sort', $selector);
        $this->assertEquals('sort', $selector->getClassName());
        $this->assertEquals('name', $selector->getAttribute());
        $this->assertFalse($selector->isAscending());
    }
    /**
     * @expectedException ValuQuery\Selector\Parser\Exception\InvalidPatternException
     */
    public function testParseInvalidPseudoSelector()
    {
        $this->parser->parse('');
    }
    
    /**
     * @expectedException ValuQuery\Selector\Parser\Exception\InvalidPatternException
     */
    public function testParsePseudoSelectorWithMissingEndingEncloser()
    {
        $this->parser->parse('limit(1');
    }
}

