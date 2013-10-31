<?php
use ValuQueryTest\Selector\Parser\AbstractParserTestCase;

use ValuQuery\Selector\Parser\SimpleSelectorParser;

/**
 * SimpleSelectorParser test case.
 */
class SimpleSelectorParserTest 
    extends AbstractParserTestCase
{

    /**
     *
     * @var SimpleSelectorParser
     */
    private $parser;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->parser = new SimpleSelectorParser();
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        $this->parser = null;
        parent::tearDown();
    }
    
    public function testParseUniversal()
    {
        $selector = $this->parser->parse('*');
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\Universal', $selector);
    }
    
    public function testParseElement()
    {
        $selector = $this->parser->parse('car');
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\Element', $selector);
    }
    
    public function testParseId()
    {
        $selector = $this->parser->parse('#123456');
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\Id', $selector);
    }
    
    public function testParseRole()
    {
        $selector = $this->parser->parse('$admin');
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\Role', $selector);
    }
    
    public function testParseClassName()
    {
        $selector = $this->parser->parse('.admin');
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\ClassName', $selector);
    }
    
    public function testParsePath()
    {
        $selector = $this->parser->parse('/privileges/admin');
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\Path', $selector);
    }
    
    public function testParseAttribute()
    {
        $selector = $this->parser->parse('[age>14]');
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\Attribute', $selector);
    }
    
    public function testParsePseudo()
    {
        $selector = $this->parser->parse(':pending');
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\Pseudo', $selector);
    }
}

