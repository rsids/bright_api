<?php
namespace fur\bright\entities;
/**
 * This class defines the Page object
 * Version history:
 * 2.1 20120820
 * - Added createdby, modifiedby, creationdate
 * @author fur
 * @version 2.1
 * @package Bright
 * @subpackage objects
 */
class OPage extends OBaseObject
{

    /**
     * @var string The explicit Remoting type
     */
    public $_explicitType = 'OPage';

    function __construct()
    {
        // Strong type vars...
        // Any normal programming language calls the constructor before filling vars....
        // ... except PHP :-)
        $this->pageId = (int)$this->pageId;
        $this->itemType = (int)$this->itemType;
        $this->alwayspublished = ((int)$this->alwayspublished == 1);
        $this->publicationdate = (float)$this->publicationdate;
        $this->modificationdate = (float)$this->modificationdate;
        $this->expirationdate = (float)$this->expirationdate;
        $this->showinnavigation = ((int)$this->showinnavigation == 1);
        $this->createdby = (int)$this->createdby;
        $this->modifiedby = (int)$this->modifiedby;
        $this->creationdate = (int)$this->creationdate;
        $this->usecount = (int)$this->usecount;
    }

    /**
     * @var int The id of the page
     */
    public $pageId = 0;
    /**
     * @var string The unique label of the page
     */
    public $label = '';
    /**
     * @var int The publicationdate as UNIX timestamp
     */
    public $publicationdate = 0;
    /**
     * @var int The expirationdate as UNIX timestamp
     */
    public $expirationdate = 0;
    /**
     * @var int The modificationdate as UNIX timestamp
     */
    public $modificationdate = 0;
    /**
     * @var boolean Indicates whether publication rules (by date) should be taken in account
     */
    public $alwayspublished = false;
    /**
     * @var int The id of the template
     */
    public $itemType = 0;
    /**
     * @var string The label of the template (for historical reasons, this is still called item instead of template)
     */
    public $itemLabel = '';
    /**
     * @var string A string indicating how long this page may be cached by the server (eg. '4 hours')
     */
    public $lifetime = '';
    /**
     * @var boolean Indicates whether the page should be shown in the main navigation
     */
    public $showinnavigation;
    /**
     * @var stdClass An object containing all the template specific content
     */
    public $content = null;

    public $usecount = 0;

    /**
     * @since 2.1
     * @var int The id of the creator
     */
    public $createdby = null;

    /**
     * @since 2.1
     * @var int The id of the person who last modified the page
     */
    public $modifiedby = null;

    /**
     * @since 2.1
     * @var int Timestamp of creation
     */
    public $creationdate = null;

    public function __clone()
    {
        $this->content = clone $this->content;
    }
}