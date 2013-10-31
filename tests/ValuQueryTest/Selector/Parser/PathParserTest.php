<?php
use ValuQueryTest\Selector\Parser\AbstractParserTestCase;

use ValuQuery\Selector\Parser\PathParser;

/**
 * PathParser test case.
 */
class PathParserTest 
    extends AbstractParserTestCase
{

    /**
     *
     * @var PathParser
     */
    private $parser;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->parser = new PathParser();
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
        $selector = $this->parser->parse('path/to');
        
        $this->assertInstanceOf('ValuQuery\Selector\SimpleSelector\Path', $selector);
        $this->assertEquals('path/to', $selector->getPath());
    }

    /**
     * Tests PathParser->parse()
     */
    public function testParseBasicPath()
    {
        $this->assertPattern('path/to');
    }
    
    public function testParseEmptyPath()
    {
        $this->assertEquals([], $this->parser->parse('')->getPathItems());
    }
    
    public function testParsePathWithEscapedCharacters()
    {
        $this->assertPattern('path\\ is\\ \\>\\ road\\:\\~\\[impossible]');
    }
    
    public function testParsePathWithIdChildSelector()
    {
        $selector = $this->parser->parse('#id/to');
        
        $this->assertInstanceOf('\ValuQuery\Selector\SimpleSelector\Id', $selector->getRootSelector());
        $items = $selector->getPathItems();
        $this->assertEquals('to', $items[1]);
    }
    
    /**
     * Assert that parsed pattern matches with expected
     *
     * @param string $value
     * @param string|null $expected
     */
    protected function assertPattern($pattern, $expected = null){
        if(is_null($expected)) $expected = '/'.$pattern;
    
        $selector = $this->parser->parse($pattern);
        $this->assertEquals($expected, $selector->__toString());
    }
}

