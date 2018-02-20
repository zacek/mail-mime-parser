<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace ZBateson\MailMimeParser\Header\Part;

use ZBateson\MailMimeParser\Header\Part\HeaderPart;
use ZBateson\StreamDecorators\Util\CharsetConverter;

/**
 * Holds a string value token that will require additional processing by a
 * consumer prior to returning to a client.
 * 
 * A Token is meant to hold a value for further processing -- for instance when
 * consuming an address list header (like From or To) -- before it's known what
 * type of HeaderPart it is (could be an email address, could be a name, or
 * could be a group.)
 *
 * @author Zaahid Bateson
 */
class Token extends HeaderPart
{
    /**
     * Initializes a token.
     * 
     * @param CharsetConverter $charsetConverter
     * @param string $value the token's value
     */
    public function __construct(CharsetConverter $charsetConverter, $value)
    {
        parent::__construct($charsetConverter);
        $this->value = $value;
    }
    
    /**
     * Returns true if the value of the token is equal to a single space.
     * 
     * @return bool
     */
    public function isSpace()
    {
        return ($this->value === ' ');
    }
    
    /**
     * Returns true if the value is a space.
     * 
     * @return bool
     */
    public function ignoreSpacesBefore()
    {
        return $this->isSpace();
    }
    
    /**
     * Returns true if the value is a space.
     * 
     * @return bool
     */
    public function ignoreSpacesAfter()
    {
        return $this->isSpace();
    }
}
