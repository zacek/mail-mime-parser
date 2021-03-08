<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace ZBateson\MailMimeParser;

use GuzzleHttp\Psr7;
use Psr\Http\Message\StreamInterface;
use ZBateson\MailMimeParser\Header\HeaderContainer;
use ZBateson\MailMimeParser\Message\MessageService;
use ZBateson\MailMimeParser\Message\IMimePart;
use ZBateson\MailMimeParser\Message\IMessagePart;
use ZBateson\MailMimeParser\Message\MimePart;
use ZBateson\MailMimeParser\Message\MessagePart;
use ZBateson\MailMimeParser\Message\PartChildrenContainer;
use ZBateson\MailMimeParser\Message\PartFilter;
use ZBateson\MailMimeParser\Message\Factory\PartFilterFactory;
use ZBateson\MailMimeParser\Message\PartStreamContainer;

/**
 * A parsed mime message with optional mime parts depending on its type.
 *
 * A mime message may have any number of mime parts, and each part may have any
 * number of sub-parts, etc...
 *
 * @author Zaahid Bateson
 */
class Message extends MimePart implements IMessage
{
    /**
     * @var MessageService helper class with various message manipulation
     *      routines.
     */
    protected $messageService;

    public function __construct(
        array $children = [],
        PartStreamContainer $streamContainer = null,
        HeaderContainer $headerContainer = null,
        PartFilterFactory $partFilterFactory = null,
        PartChildrenContainer $partChildrenContainer = null,
        MessageService $messageService = null
    ) {
        parent::__construct(
            $children,
            $streamContainer,
            $headerContainer,
            $partChildrenContainer,
            $partFilterFactory
        );
        if ($messageService === null) {
            $messageService = $di['\ZBateson\MailMimeParser\Message\MessageService'];
        }
        $this->messageService = $messageService;
    }

    /**
     * Convenience method to parse a handle or string into an IMessage without
     * requiring including MailMimeParser, instantiating it, and calling parse.
     *
     * @param resource|string $handleOrString the resource handle to the input
     *        stream of the mime message, or a string containing a mime message
     * @return IMessage
     */
    public static function from($handleOrString)
    {
        static $mmp = null;
        if ($mmp === null) {
            $mmp = new MailMimeParser();
        }
        return $mmp->parse($handleOrString);
    }

    /**
     * Returns the text/plain part at the given index (or null if not found.)
     *
     * @param int $index
     * @return MessagePart
     */
    public function getTextPart($index = 0)
    {
        return $this->getPart(
            $index,
            PartFilter::fromInlineContentType('text/plain')
        );
    }

    /**
     * Returns the number of text/plain parts in this message.
     *
     * @return int
     */
    public function getTextPartCount()
    {
        return $this->getPartCount(
            PartFilter::fromInlineContentType('text/plain')
        );
    }

    /**
     * Returns the text/html part at the given index (or null if not found.)
     *
     * @param int $index
     * @return MessagePart
     */
    public function getHtmlPart($index = 0)
    {
        return $this->getPart(
            $index,
            PartFilter::fromInlineContentType('text/html')
        );
    }

    /**
     * Returns the number of text/html parts in this message.
     *
     * @return int
     */
    public function getHtmlPartCount()
    {
        return $this->getPartCount(
            PartFilter::fromInlineContentType('text/html')
        );
    }

    /**
     * Returns the attachment part at the given 0-based index, or null if none
     * is set.
     *
     * @param int $index
     * @return MessagePart
     */
    public function getAttachmentPart($index)
    {
        return $this->getPart(
            $index,
            PartFilter::fromAttachmentFilter()
        );
    }

    /**
     * Returns all attachment parts.
     *
     * "Attachments" are any non-multipart, non-signature and any text or html
     * html part with a Content-Disposition set to  'attachment'.
     *
     * @return MessagePart[]
     */
    public function getAllAttachmentParts()
    {
        return $this->getAllParts(
            PartFilter::fromAttachmentFilter()
        );
    }

    /**
     * Returns the number of attachments available.
     *
     * @return int
     */
    public function getAttachmentCount()
    {
        return count($this->getAllAttachmentParts());
    }

    /**
     * Returns a Psr7 Stream for the 'inline' text/plain content at the passed
     * $index, or null if unavailable.
     *
     * @param int $index
     * @param string $charset
     * @return StreamInterface
     */
    public function getTextStream($index = 0, $charset = MailMimeParser::DEFAULT_CHARSET)
    {
        $textPart = $this->getTextPart($index);
        if ($textPart !== null) {
            return $textPart->getContentStream($charset);
        }
        return null;
    }

    /**
     * Returns the content of the inline text/plain part at the given index.
     *
     * Reads the entire stream content into a string and returns it.  Returns
     * null if the message doesn't have an inline text part.
     *
     * @param int $index
     * @param string $charset
     * @return string
     */
    public function getTextContent($index = 0, $charset = MailMimeParser::DEFAULT_CHARSET)
    {
        $part = $this->getTextPart($index);
        if ($part !== null) {
            return $part->getContent($charset);
        }
        return null;
    }

    /**
     * Returns a Psr7 Stream for the 'inline' text/html content at the passed
     * $index, or null if unavailable.
     *
     * @param int $index
     * @param string $charset
     * @return StreamInterface
     */
    public function getHtmlStream($index = 0, $charset = MailMimeParser::DEFAULT_CHARSET)
    {
        $htmlPart = $this->getHtmlPart($index);
        if ($htmlPart !== null) {
            return $htmlPart->getContentStream($charset);
        }
        return null;
    }

    /**
     * Returns the content of the inline text/html part at the given index.
     *
     * Reads the entire stream content into a string and returns it.  Returns
     * null if the message doesn't have an inline html part.
     *
     * @param int $index
     * @param string $charset
     * @return string
     */
    public function getHtmlContent($index = 0, $charset = MailMimeParser::DEFAULT_CHARSET)
    {
        $part = $this->getHtmlPart($index);
        if ($part !== null) {
            return $part->getContent($charset);
        }
        return null;
    }

    /**
     * Returns true if either a Content-Type or Mime-Version header are defined
     * in this Message.
     *
     * @return bool
     */
    public function isMime()
    {
        $contentType = $this->getHeaderValue('Content-Type');
        $mimeVersion = $this->getHeaderValue('Mime-Version');
        return ($contentType !== null || $mimeVersion !== null);
    }

    /**
     * Sets the text/plain part of the message to the passed $stringOrHandle,
     * either creating a new part if one doesn't exist for text/plain, or
     * assigning the value of $stringOrHandle to an existing text/plain part.
     *
     * The optional $charset parameter is the charset for saving to.
     * $stringOrHandle is expected to be in UTF-8 regardless of the target
     * charset.
     *
     * @param string|resource|StreamInterface $resource
     * @param string $charset
     */
    public function setTextPart($resource, $charset = 'UTF-8')
    {
        $this->messageService
            ->getMultipartHelper()
            ->setContentPartForMimeType(
                $this, 'text/plain', $resource, $charset
            );
    }

    /**
     * Sets the text/html part of the message to the passed $stringOrHandle,
     * either creating a new part if one doesn't exist for text/html, or
     * assigning the value of $stringOrHandle to an existing text/html part.
     *
     * The optional $charset parameter is the charset for saving to.
     * $stringOrHandle is expected to be in UTF-8 regardless of the target
     * charset.
     *
     * @param string|resource|StreamInterface $resource
     * @param string $charset
     */
    public function setHtmlPart($resource, $charset = 'UTF-8')
    {
        $this->messageService
            ->getMultipartHelper()
            ->setContentPartForMimeType(
                $this, 'text/html', $resource, $charset
            );
    }

    /**
     * Removes the text/plain part of the message at the passed index if one
     * exists.  Returns true on success.
     *
     * @param int $index
     * @return bool true on success
     */
    public function removeTextPart($index = 0)
    {
        return $this->messageService
            ->getMultipartHelper()
            ->removePartByMimeType(
                $this, 'text/plain', $index
            );
    }

    /**
     * Removes all text/plain inline parts in this message, optionally keeping
     * other inline parts as attachments on the main message (defaults to
     * keeping them).
     *
     * @param bool $keepOtherPartsAsAttachments
     * @return bool true on success
     */
    public function removeAllTextParts($keepOtherPartsAsAttachments = true)
    {
        return $this->messageService
            ->getMultipartHelper()
            ->removeAllContentPartsByMimeType(
                $this, 'text/plain', $keepOtherPartsAsAttachments
            );
    }

    /**
     * Removes the html part of the message if one exists.  Returns true on
     * success.
     *
     * @param int $index
     * @return bool true on success
     */
    public function removeHtmlPart($index = 0)
    {
        return $this->messageService
            ->getMultipartHelper()
            ->removePartByMimeType(
                $this, 'text/html', $index
            );
    }

    /**
     * Removes all text/html inline parts in this message, optionally keeping
     * other inline parts as attachments on the main message (defaults to
     * keeping them).
     *
     * @param bool $keepOtherPartsAsAttachments
     * @return bool true on success
     */
    public function removeAllHtmlParts($keepOtherPartsAsAttachments = true)
    {
        return $this->messageService
            ->getMultipartHelper()
            ->removeAllContentPartsByMimeType(
                $this, 'text/html', $keepOtherPartsAsAttachments
            );
    }

    /**
     * Adds an attachment part for the passed raw data string or handle and
     * given parameters.
     *
     * @param string|resource|StreamInterface $resource
     * @param string $mimeType
     * @param string $filename
     * @param string $disposition
     * @param string $encoding defaults to 'base64', only applied for a mime
     *        email
     */
    public function addAttachmentPart($resource, $mimeType, $filename = null, $disposition = 'attachment', $encoding = 'base64')
    {
        $this->messageService
            ->getMultipartHelper()
            ->createAndAddPartForAttachment($this, $resource, $mimeType, $disposition, $filename, $encoding);
    }

    /**
     * Adds an attachment part using the passed file.
     *
     * Essentially creates a file stream and uses it.
     *
     * @param string $filePath
     * @param string $mimeType
     * @param string $filename
     * @param string $disposition
     */
    public function addAttachmentPartFromFile($filePath, $mimeType, $filename = null, $disposition = 'attachment', $encoding = 'base64')
    {
        $handle = Psr7\stream_for(fopen($filePath, 'r'));
        if ($filename === null) {
            $filename = basename($filePath);
        }
        $this->addAttachmentPart($handle, $mimeType, $filename, $disposition, $encoding);
    }

    /**
     * Removes the attachment with the given index
     *
     * @param int $index
     */
    public function removeAttachmentPart($index)
    {
        $part = $this->getAttachmentPart($index);
        $this->removePart($part);
    }

    /**
     * Returns a stream that can be used to read the content part of a signed
     * message, which can be used to sign an email or verify a signature.
     *
     * The method simply returns the stream for the first child.  No
     * verification of whether the message is in fact a signed message is
     * performed.
     *
     * Note that unlike getSignedMessageAsString, getSignedMessageStream doesn't
     * replace new lines.
     *
     * @return StreamInterface or null if the message doesn't have any children
     */
    public function getSignedMessageStream()
    {
        return $this
            ->messageService
            ->getPrivacyHelper()
            ->getSignedMessageStream($this);
    }

    /**
     * Returns a string containing the entire body of a signed message for
     * verification or calculating a signature.
     *
     * Non-CRLF new lines are replaced to always be CRLF.
     *
     * @return string or null if the message doesn't have any children
     */
    public function getSignedMessageAsString()
    {
        return $this
            ->messageService
            ->getPrivacyHelper()
            ->getSignedMessageAsString($this);
    }

    /**
     * Returns the signature part of a multipart/signed message or null.
     *
     * The signature part is determined to always be the 2nd child of a
     * multipart/signed message, the first being the 'body'.
     *
     * Using the 'protocol' parameter of the Content-Type header is unreliable
     * in some instances (for instance a difference of x-pgp-signature versus
     * pgp-signature).
     *
     * @return MimePart
     */
    public function getSignaturePart()
    {
        return $this
            ->messageService
            ->getPrivacyHelper()
            ->getSignaturePart($this);
    }

    /**
     * Turns the message into a multipart/signed message, moving the actual
     * message into a child part, sets the content-type of the main message to
     * multipart/signed and adds an empty signature part as well.
     *
     * After calling setAsMultipartSigned, call getSignedMessageAsString to
     * return a
     *
     * @param string $micalg The Message Integrity Check algorithm being used
     * @param string $protocol The mime-type of the signature body
     */
    public function setAsMultipartSigned($micalg, $protocol)
    {
        $this
            ->messageService
            ->getPrivacyHelper()
            ->setMessageAsMultipartSigned($this, $micalg, $protocol);
    }

    /**
     * Sets the signature body of the message to the passed $body for a
     * multipart/signed message.
     *
     * @param string $body
     */
    public function setSignature($body)
    {
        $this->messageService->getPrivacyHelper()
            ->setSignature($this, $body);
    }
}
