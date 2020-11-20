<?php

namespace ValuQueryTest\Selector\Parser;

use ValuQuery\Selector\Parser\AttributeSelectorParser;

class AttributeSelectorParserTest
    extends AbstractParserTestCase
{

    public static function setUpBeforeClass()
    {
        setlocale(LC_NUMERIC, 'en_US');
    }

    public function testAttributeOnly()
    {
        $selector = 'abc';
        $this->assertAttribute($selector, $selector);
    }

    public function testComplexAttribute()
    {
        $selector = 'a-b1_c';
        $this->assertAttribute($selector, $selector);
    }

    public function testChildAttribute()
    {
        $selector = 'a.b';
        $this->assertAttribute($selector, $selector);
    }

    public function testEqualsOperator()
    {
        $selector = 'abc="def"';
        $this->assertPattern($selector);
    }

    public function testChildAttributeEquals()
    {
        $selector = 'a.b=1';
        $this->assertPattern($selector, $selector);
    }

    public function testInListOperator()
    {
        $selector = 'abc~=def ghi';
        $this->assertPattern($selector, 'abc~="def" "ghi"');
    }

    public function testInListOperatorWithQuotedValues()
    {
        $selector = 'abc~="def" "ghi"';
        $this->assertPattern($selector);
    }

    public function testInListOperatorWithDoubleSpaceSeparator()
    {
        $selector = 'abc~="def"  "yzx"';
        $this->assertPattern($selector, 'abc~="def" "yzx"');
    }

    public function testInListOperatorWithMixedTypes()
    {
        $selector = 'abc~=true "abc" 2.4 def 5';
        $this->assertPattern($selector, 'abc~=1 "abc" 2.4 "def" 5');
    }

    public function testPrefixOperator()
    {
        $selector = 'abc^="def"';
        $this->assertPattern($selector);
    }

    public function testNotPrefixOperator()
    {
        $selector = 'abc!^="def"';
        $this->assertPattern($selector);
    }

    public function testSuffixOperator()
    {
        $selector = 'abc$="def"';
        $this->assertPattern($selector);
    }

    public function testNotSuffixOperator()
    {
        $selector = 'abc!$="def"';
        $this->assertPattern($selector);
    }

    public function testWildcardOperator()
    {
        $selector = 'abc*="def"';
        $this->assertPattern($selector);
    }

    public function testGtOperator()
    {
        $selector = 'abc>1.2';
        $this->assertPattern($selector);
    }

    public function testGteOperator()
    {
        $selector = 'abc>=1.2';
        $this->assertPattern($selector);
    }

    public function testLtOperator()
    {
        $selector = 'abc<1.2';
        $this->assertPattern($selector);
    }

    public function testLteOperator()
    {
        $selector = 'abc<=1.2';
        $this->assertPattern($selector);
    }

    public function testWhitespace()
    {
        $selector = ' abc = "def ghi" ';
        $this->assertPattern($selector, 'abc="def ghi"');
    }

    public function testEscapedCondition()
    {
        $selector = 'abc*="de\"f"';
        $this->assertPattern($selector);
    }

    public function testBooleanTrueCondition()
    {
        $this->assertCondition('a=true', true);
    }

    public function testBooleanTrueUcCondition()
    {
        $this->assertCondition('a=TRUE', true);
    }

    public function testBooleanFalseCondition()
    {
        $this->assertCondition('a=false', false);
    }

    public function testBooleanFalseUcCondition()
    {
        $this->assertCondition('a=FALSE', false);
    }

    public function testNullCondition()
    {
        $this->assertCondition('a=null', null);
    }

    public function testNullUcCondition()
    {
        $this->assertCondition('a=NULL', null);
    }

    public function testIntCondition()
    {
        $this->assertCondition('a=1', 1);
    }

    public function testSignedIntCondition()
    {
        $this->assertCondition('a=-1', -1);
    }

    public function testFloatCondition()
    {
        $this->assertCondition('a=1.00095', 1.00095);
    }

    public function testSignedFloatCondition()
    {
        $this->assertCondition('a=-0.00095', -0.00095);
    }

    /**
     * Assert that parsed pattern matches with expected
     *
     * @param string $value
     * @param string|null $expected
     */
    protected function assertPattern($pattern, $expected = null)
    {
        if (is_null($expected)) {
            $expected = $pattern;
        }

        $parser = new AttributeSelectorParser();
        $selector = $parser->parse($pattern);

        $this->assertEquals('[' . $expected . ']', $selector->__toString());
    }

    protected function assertAttribute($pattern, $expected)
    {
        $parser = new AttributeSelectorParser();
        $selector = $parser->parse($pattern);

        $this->assertEquals($expected, $selector->getAttribute());
    }

    protected function assertCondition($pattern, $expected)
    {
        $parser = new AttributeSelectorParser();
        $selector = $parser->parse($pattern);

        $this->assertEquals($expected, $selector->getCondition());
    }
}