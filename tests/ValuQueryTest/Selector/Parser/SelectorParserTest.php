<?php
namespace ValuQueryTest\Selector\Parser;

use ValuQuery\Selector;
use ValuQuery\Selector\SimpleSelector\AbstractSelector;

class SelectorParserTest extends \PHPUnit_Framework_TestCase{

    public function testUniversalSelector(){
        $this->assertSelectorName('*', AbstractSelector::SELECTOR_UNIVERSAL);
    }
    
    public function testElementSelector(){
        
        $name = AbstractSelector::SELECTOR_ELEMENT;
        
        $this->assertSelectorName('a', $name);
        $this->assertSelector('a');
        
        $this->assertSelectorName('simple', $name);
        $this->assertSelector('simple');
        
        $this->assertSelectorName('complex-element', $name);
        $this->assertSelector('complex-element');
        
        $this->assertSelectorName('complex_element', $name);
        $this->assertSelector('complex_element');
    }
    
    public function testIdSelector(){
        $name = AbstractSelector::SELECTOR_ID;
    
        $this->assertSelectorName('#a', $name);
        $this->assertSelector('#a');
    
        $this->assertSelectorName('#ab', $name);
        $this->assertSelector('#ab');
    }
    
    public function testRoleSelector(){
        $name = AbstractSelector::SELECTOR_ROLE;
    
        $this->assertSelectorName('$role', $name);
        $this->assertSelector('$role');
    }
    
    public function testClassSelector(){
        $name = AbstractSelector::SELECTOR_CLASS;
        
        $this->assertSelectorName('.a', $name);
        $this->assertSelector('.a');
        
        $this->assertSelectorName('.ab', $name);
        $this->assertSelector('.ab');
    }
    
    public function testPseudoSelector(){
        $name = AbstractSelector::SELECTOR_PSEUDO;
    
        $this->assertSelectorName(':a', $name);
        $this->assertSelector(':a');
    
        $this->assertSelectorName(':nth-child(1)', $name);
        $this->assertSelector(':nth-child()', ':nth-child');
        
        $this->assertSelectorName(':last-child', $name);
        $this->assertSelector(':last-child');
    }
    
    public function testSimpleAttributeSelector(){
        $name = AbstractSelector::SELECTOR_ATTRIBUTE;
        
        $this->assertSelectorName('[abc]', $name);
        $this->assertSelector('[abc]');
    }
    
    public function testPrefixAttributeSelector()
    {
        $name = AbstractSelector::SELECTOR_ATTRIBUTE;
        
        $this->assertSelectorName('[abc^="1.002"]', $name);
        $this->assertSelector('[abc^="1.002"]');
    }
    
    public function testGtAttributeSelector()
    {
        $name = AbstractSelector::SELECTOR_ATTRIBUTE;
        
        $this->assertSelectorName('[abc>"1.002"]', $name);
        $this->assertSelector('[abc>"1.002"]');
    }
    
    public function testClassSequence(){
        $this->assertSequence(array(
            '.abc' => AbstractSelector::SELECTOR_CLASS,         
            '.def' => AbstractSelector::SELECTOR_CLASS,         
        ));
    }
    
    public function testMixedSequence(){
        $this->assertSequence(array(
            'abc' => AbstractSelector::SELECTOR_ELEMENT,
            '.abc' => AbstractSelector::SELECTOR_CLASS,
            '#def' => AbstractSelector::SELECTOR_ID,
            ':ghi' => AbstractSelector::SELECTOR_PSEUDO,
            '[yes="no"]' => AbstractSelector::SELECTOR_ATTRIBUTE,
        ));
    }
    
    public function testMixedSequenceWithUniversal(){
        $this->assertSequence(array(
                '*' => AbstractSelector::SELECTOR_UNIVERSAL,
                '#def' => AbstractSelector::SELECTOR_ID
        ));
    }
    
    public function testDescendentSelector(){
        $this->assertSelector('article comment');
    }
    
    public function testChildSelector(){
        $this->assertSelector('article>comment');
    }
    
    public function testChildSelectorWithAttribute(){
        $this->assertSelector('article>[attr="value"]');
    }
    
    public function testChildSelectorWithSpaces(){
        $this->assertSelector('article > .class', 'article>.class');
    }
    
    public function testChildSelectorWithParentAsAPath(){
        $this->assertSelector('/article>.class');
    }
    
    public function testImmediateChildSelector(){
        $this->assertSelector('article+comment');
    }
    
    public function testAnyChildSelector(){
        $this->assertSelector('article~comment');
    }
    
    public function testCombinatorWhitespace(){
        $this->assertSelector(' article', 'article');
        $this->assertSelector('article + comment', 'article+comment');
        $this->assertSelector('article +comment', 'article+comment');
        $this->assertSelector('article+ comment', 'article+comment');
    }
    
    public function testCompleteSelectorPath(){
        $this->assertSequencePath(array(
            array('*' => AbstractSelector::SELECTOR_UNIVERSAL),
            array(' ' => Selector\Selector::COMBINATOR_DESCENDENT),
            array('abc' => AbstractSelector::SELECTOR_ELEMENT),        
        ));
    }
    
    public function testComplexCompleteSelectorPath(){
        $this->assertSequencePath(array(
            array('*' => AbstractSelector::SELECTOR_UNIVERSAL),
            array('>' => Selector\Selector::COMBINATOR_CHILD),
            array(
                '#a_b-c-1' => AbstractSelector::SELECTOR_ID,
            ),
            array('~' => Selector\Selector::COMBINATOR_ANY_SIBLING),
            array(
                'abc' => AbstractSelector::SELECTOR_ELEMENT,
                '[a$="bcd"]' => AbstractSelector::SELECTOR_ATTRIBUTE,
                '.def' => AbstractSelector::SELECTOR_CLASS,
                ':ghi(15)' => AbstractSelector::SELECTOR_PSEUDO,
            ),
        ));
    }
    
    public function testCompleteSelector(){
        $this->assertSelector('* abc>#def+.ghi~[jkl="mno"]:pqr');
    }
    
    public function testCompleteSelectorWithPathAndChild(){
        $this->assertSequencePath(array(
            array('/test-path' => AbstractSelector::SELECTOR_PATH),
            array('>' => Selector\Selector::COMBINATOR_CHILD),
            array('[name="file.ext"]' => AbstractSelector::SELECTOR_ATTRIBUTE),
        ));
    }
    
    /**
     * Assert that given selector string matches with
     * parsed selector string
     * 
     * @param string $value
     * @param string|null $expected
     */
    protected function assertSelector($value, $expected = null){
        if(is_null($expected)) $expected = $value;
        
        $selector = Selector\Parser\SelectorParser::parseSelector($value);
        $this->assertEquals($expected, $selector->__toString());
    }
    
    /**
     * Assert that simple selector string matches with expexted
     * simple selector name 
     * 
     * @param string $selector Simple selector
     * @param string $expected Expected name
     */
    protected function assertSelectorName($selector, $expected){
        $selector = Selector\Parser\SelectorParser::parseSelector($selector);
        $simpleSelector = $selector->getSequence(0)->getItem(0);
        
        $this->assertEquals($expected, $simpleSelector->getName());
    }
    
    /**
     * Assert that given sequence pattern matches against
     * internally parsed sequence string
     * 
     * @param array $pattern
     */
    protected function assertSequence(array $pattern){
        $selector = implode('', array_keys($pattern));
        $selector = Selector\Parser\SelectorParser::parseSelector($selector);
        $sequence = $selector->getSequence(0);
        
        $this->assertSequenceAgainstPattern($sequence, $pattern);
    }
    
    /**
     * Assert that sequence matches with pattern
     * 
     * @param Selector\Sequence $sequence    Sequence to match against
     * @param array $pattern                 Pattern, where each key is a simple selector 
     *                                       and its value matching selector name
     */
    protected function assertSequenceAgainstPattern(Selector\Sequence $sequence, array $pattern){
        $i = 0;
        foreach ($pattern as $item => $name){
            $this->assertEquals(
                    $name,
                    $sequence->getItem($i)->getName()
            );
        
            $this->assertEquals(
                    $item,
                    $sequence->getItem($i)->getPattern()
            );
        
            $i++;
        }
    }
    
    /**
     * Assert that sequence path matches against 
     * internally parsed sequence string
     * 
     * @param array $path
     */
    protected function assertSequencePath(array $path){
        
        $selector = '';
        foreach($path as $key => $specs){
            $keys = array_keys($specs);
            
            if($key == 0 || $key % 2 == 0) $selector .= implode('', array_keys($specs));
            else $selector .= array_pop($keys);
        }
        
        $selector = Selector\Parser\SelectorParser::parseSelector($selector);
        
        $s = 0;
        $c = 1;
        foreach($path as $key => $specs){
            if($key == 0 || $key % 2 == 0) {
                $sequence = $selector->getSequence($s);
                $this->assertSequenceAgainstPattern($sequence, $specs);
                $s++;
            }
            else{
                $this->assertEquals(array_pop($specs), $sequence->getChildCombinator());
                $c++;
            }
        }
    }
}