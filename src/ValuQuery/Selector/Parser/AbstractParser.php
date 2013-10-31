<?php
namespace ValuQuery\Selector\Parser;

abstract class AbstractParser implements \Iterator 
{
    protected $cursor = 0;
    
    protected $pattern;
    
    protected $length = 0;
    
    protected $escaped = array();
    
    protected $enclosers = array(
        '"' => '"',
        "'" => "'",
        '[' => ']',
        '(' => ')',
        '{' => '}',
    );
    
    /**
     * (non-PHPdoc)
     * @see Iterator::rewind()
     */
    public function rewind() {
        $this->cursor = 0;
    }
    
    /**
     * (non-PHPdoc)
     * @see Iterator::current()
     */
    public function current() {
        return $this->pattern[$this->cursor];
    }
    
    /**
     * (non-PHPdoc)
     * @see Iterator::key()
     */
    public function key() {
        return $this->cursor;
    }
    
    /**
     * (non-PHPdoc)
     * @see Iterator::next()
     */
    public function next() 
    {
        ++$this->cursor;
    }
    
    /**
     * Set cursor position to next key character
     * 
     * @return boolean
     */
    public function nextKeyChar()
    {
        $newCursor = $this->seekKeyCursorLocation(true, $this->key()+1);
        
        if ($newCursor !== false) {
            $this->cursor = $newCursor;
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Move cursor backwards one character
     */
    public function prev()
    {
        --$this->cursor;    
    }
    
    /**
     * (non-PHPdoc)
     * @see Iterator::valid()
     */
    public function valid()
    {
        return isset($this->pattern[$this->cursor]);
    }
    
    /**
     * Unescape value
     *
     * @param mixed $value
     */
    public function unescape($value)
    {
        if (is_string($value)) {
            return stripslashes($value);
        } else {
            return $value;
        }
    }
    
    /**
     * Set pattern for parsing
     * 
     * @param string $pattern
     */
    protected function setPattern($pattern){
        $this->pattern   = trim($pattern);
        $this->length    = strlen($this->pattern);
        $this->escaped   = array();
        
        $this->rewind();
    }
    
    /**
     * Find character position
     * 
     * @param string $character Character
     * @param int $offset Start offset
     * @param boolean $findEscaped Set true to find also escaped chars
     */
    protected function findChar($character, $offset = null, $findEscaped = false){
        return $this->findAny(array($character), $offset, $findEscaped);
    }
    
    /**
     * Find any of the provided characters
     * 
     * @param array $characters
     * @param int $offset
     * @param boolean $findEscaped
     */
    protected function findAny(array $characters, $offset = null, $findEscaped = false)
    {
        if (is_null($offset)) {
            $offset = $this->key();
        }
        
        // Remember current cursor position
        $cursor = $this->cursor;
        
        // Set new cursor position
        $this->cursor = $offset;
        
        $foundOffset = false;
    
        while ($this->valid()) {
            
            // Test if current character matches
            if (in_array($this->current(), $characters)) {
                
                // Test if escaped
                $escaped = $this->currentIsEscaped();
                
                if(!$escaped || ($escaped && $findEscaped)){
                    $foundOffset = $this->key();
                    break;
                }
            }
            
            // Skip if inside a quoted string
            if ($this->cursorAtStartEncloser() && !$this->currentIsEscaped()) {
                
                //find ending encloser
                $newOffset = $this->findChar($this->enclosers[$this->current()], ($this->key()+1));
                
                if($newOffset !== false){
                    $this->cursor = $newOffset;
                    continue;
                }
            }
    
            $this->next();
        }
    
        // Restore cursor position
        $this->cursor = $cursor;
        
        return $foundOffset;
    }

    /**
     * Seek for key cursor location
     * 
     * @param boolean $moveForward
     * @param int $offset Start offset
     */
    protected function seekKeyCursorLocation($moveForward = true, $offset = null)
    {
        $location       = false;
        $cursor         = $this->cursor;
        
        if (is_null($offset)) {
            $offset = $this->key();
        } else {
            $this->cursor = $offset;
        }
    
        while ($this->valid()) {
            if ($this->cursorAtWhitespace()) {
                if($moveForward) $this->next();
                else $this->prev();
            } else {
                $location = $this->key();
                break;
            }
        }
    
        $this->cursor = $cursor;
    
        return $location;
    }
    
    /**
     * Test whether or not current character is escaped
     * 
     * @return boolean
     */
    protected function currentIsEscaped()
    {
        if (!isset($this->escaped[$this->key()])) {
        
            if ($this->key() == 0) {
                return false;
            }
            
            $escaped = false;
            
            $this->prev();
            if ($this->cursorAtEscape() && !$this->currentIsEscaped()) {
                $escaped = true;
            }
            $this->next();
            
            $this->escaped[$this->key()] = $escaped;
        }
        
        return $this->escaped[$this->key()];
    }
    
    /**
     * Test whether or not current character is quote
     * 
     * @return boolean
     */
    protected function cursorAtQuote()
    {
        return in_array($this->current(), array("'", '"'));
    }
    
    /**
     * Test whether or not current character is one
     * of enclosing characters
     *
     * @return boolean
     */
    protected function cursorAtStartEncloser()
    {
        return isset($this->enclosers[$this->current()]);
    }
    
    /**
     * Test whether or not current character is one
     * of enclosing characters
     *
     * @return boolean
     */
    protected function cursorAtEndEncloser()
    {
        return in_array($this->current(), $this->enclosers);
    }
    
    /**
     * Test whether or not current character is escape character
     * 
     * @return boolean
     */
    protected function cursorAtEscape()
    {
        return ($this->current() == "\\");
    }
    
    /**
     * Test if cursor is currently in a whitespace character
     * 
     * Following characters are considered whitespace:
     * - space
     * - tab
     * - line feed
     * - carriage return
     * - form feed
     * 
     * @return boolean
     */
    protected function cursorAtWhitespace()
    {
        return preg_match(
            '/\x{0020}|\x{0009}|\x{000A}|\x{000D}|\x{000C}/u', 
            $this->current());
    }
}