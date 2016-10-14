<?php
namespace fur\bright\frontend;

use fur\bright\api\page\Page;
use fur\bright\api\tree\Tree;
use fur\bright\entities\OPage;
use fur\bright\entities\OTreeNode;
use fur\bright\exceptions\GenericException;

/**
 * Use this class to access Bright from the frontend. This class automatically localizes your content
 * @author Fur
 * @version 2.2
 * @package Frontend
 */
class Bright
{

    private $_lang;
    private $_langs;
    private $_tree;
    private $_config;

    public function __construct()
    {
        $this->_tree = new Tree();
        $this->_langs = explode(',', AVAILABLELANG);

        $_SESSION['languages'] = $this->_langs;
        if (isset($_SESSION['language'])) {
            $this->_lang = $_SESSION['language'];
        } else {
            $_SESSION['language'] =
            $this->_lang = $this->_langs[0];
        }
    }

    /**
     * Gets all the nodes with a specified template
     * @param string $type The name of the template
     * @param string $order The field to order the nodes. (t = TreeNode properties, p = page properties)
     * @param int $startIndex The index to begin returning with
     * @param int $maxItems The number of nodes to return
     * @param boolean $localize When true, all pages get localized
     * @return array An array of OTreeNodes
     */
    public function getChildrenByType($type = 'homepage', $order = 't.treeId', $startIndex = 0, $maxItems = 0, $localize = false)
    {

        $arr = $this->_tree->getChildrenByType($type, $order, $startIndex, $maxItems);
        foreach ($arr as &$child) {
            $child->path = BASEURL . $this->_lang . '/' . $child->path;
            if ($localize)
                $this->_localizeNode($child);
        }
        return $arr;
    }

    /**
     * Returns the full navigation, both as array and as tree
     * @return \stdClass An object containing 'arr' (a plain array of OTreeNodes) & 'tree' (Multidimensional array)
     */
    public function getFullNavigation()
    {
        $result = $this->_tree->getFullNavigation(true, true);
        foreach ($result->arr as $node) {
            $node->path = BASEURL . $this->_lang . '/' . $node->path;
            //$node -> page = $this -> _localize($node -> page);
        }

        return $result;
    }

    /**
     * Gets the navigation of the website, taking into account 'showinnavigation' and publication rules
     * @param int $parentId The id of the parent OTreeNode
     * @param int $depth The depth of navigation to get, depth 0 fetches all
     * @param OTreeNode $activePage The current page
     * @param boolean $generateList When true, an html list is generated, when false, a array of OTreeNodes (with subnodes) is returned
     * @param array $classes An array of classes to add to the li's, for every depth you can specify classes
     * @param array $ulWraps Optional wrapper around the sub-uls, This is an array, for every depth you can specify a different wrapper. Syntax: <div>|</div>. The | is replaced with the actual content
     * @param boolean $activeClassOnLink Indicates whether the a tag should get a class active or just the li should get '.active'
     * @param string $outerUlClassString Classes to add to the outer ul
     * @return mixed string or array
     */
    public function getNavigation($parentId, $depth = 0, $activePage = null, $generateList = true, $classes = null, $ulWraps = null, $activeClassOnLink = false, $outerUlClassString = 'nav')
    {
        $parent = $this->_tree->getChild($parentId, false, true);
        $path = $parent->path;
        $outerUlClassString = filter_var($outerUlClassString, FILTER_SANITIZE_STRING);

        if ($generateList) {
            $content = "<ul class='$outerUlClassString'>";
            $result = $this->_getNavigation($parentId, 0, $depth, 'list', $content, $path, $activePage, $classes, $ulWraps);
            if ($result == "<ul class='$outerUlClassString'>")
                return '';

            $result .= '</ul>';
        } else {
            return $this->_getNavigationObject($parentId, 0, $depth, 'object', new \stdClass(), $path, $activePage);
        }

        return $result;
    }

    private function _getNavigationObject($parentId, $curdepth, $maxdepth, $mode, $content, $path, $activepage)
    {
        $children = $this->_tree->getSimplyfiedChildren($parentId, true, true, true, array('title'), $this->_lang);
        foreach ($children as &$child) {
            if ($curdepth < $maxdepth && $child->numChildren > 0) {
                $child->children = $this->_getNavigationObject($child->treeId, $curdepth + 1, $maxdepth, $mode, '', $path . $child->label . '/', $activepage);
            }

        }
        return $children;
    }

    private function _getNavigation($parentId, $curdepth, $maxdepth, $mode, $content, $path, $activepage, $classes = null, $ulwraps = null, $activeclassonlink = false)
    {
        $children = $this->_tree->getSimplyfiedChildren($parentId, true, true, true, array('title'), $this->_lang);
        $i = 0;
        $len = count($children);
        foreach ($children as $child) {
            switch ($mode) {
                case 'list':
                    $linkclass = '';
                    $content .= '<li';
                    $classarr = array();
                    if ($classes && count($classes) > $curdepth) {
                        $classarr[] = $classes[$curdepth];
                    }
                    if ($i == 0) {
                        $classarr[] = 'first';
                    }
                    if ($i == $len - 1) {
                        $classarr[] = 'last';
                    }
                    $classarr[] = 'menu-' . $i;
                    if ($activepage) {
                        if ($activepage->treeId == (int)$child->treeId || $activepage->treeId == (int)$child->shortcut) {
                            $classarr[] = 'active';
                            $linkclass = 'active';
                        } else {
                            if (strpos($activepage->path, $path . $child->label) === 0) {
                                $classarr[] = 'activesubpage';
                            }
                        }
                    }

                    if (count($classarr) > 0) {
                        $content .= ' class="' . join(' ', $classarr) . '"';
                    }
                    $quotedtitle = htmlspecialchars($child->title);
                    $child->title = str_replace(array('>', '<'), array('&gt;', '&lt;'), $child->title);
                    $bright_path = $this->_tree->getPath($child->treeId);
                    $url = (USEPREFIX === true) ? BASEURL . $_SESSION['language'] . '/' . $bright_path : BASEURL . $bright_path;
                    $content .= "><a href='{$url}' title=\"$quotedtitle\" class='$linkclass'>{$child -> title}</a>";
                    if ($curdepth < $maxdepth && $child->numChildren > 0) {
                        $content .= "\r\n\t";
                        $content .= str_repeat("\t", $curdepth);
                        $endwrap = '';
                        if ($ulwraps !== null) {
                            $ulwrap = '|';
                            if (is_array($ulwraps) && $ulwraps[$curdepth]) {
                                $ulwrap = $ulwraps[$curdepth];
                            } else if (is_string($ulwraps)) {
                                $ulwrap = $ulwraps;
                            }
                            $content .= array_pop(array_slice(explode('|', $ulwrap), 0, 1));
                            $endwrap = array_pop(array_slice(explode('|', $ulwrap), 1, 1));
                        }
                        $sub = '<ul class="sub_depth_' . $curdepth . '">';
                        $sub .= "\r\n\t" . str_repeat("\t", $curdepth + 1);
                        $items = $this->_getNavigation($child->treeId, $curdepth + 1, $maxdepth, $mode, '', $path . $child->label . '/', $activepage, $classes, $ulwraps);
                        $sub .= $items;
                        $sub .= '</ul>' . $endwrap . "\r\n" . str_repeat("\t", $curdepth);
                        // NumChildren is not reliable, because some children are not published
                        if (strlen(trim($items)) > 0)
                            $content .= $sub;
                    }
                    $content .= '</li>' . "\r\n\t" . str_repeat("\t", $curdepth);
                    break;
                case 'object':
                    break;
            }
            $i++;
        }
        return $content;
    }

    /**
     * Gets a localized Resource by it's name from the Resource.php class
     * @see Resource.php (found in the config dir where config.ini is placed)
     * @param string $name The name of the resource (case-insensitive)
     * @return string The resource
     */
    public function getResource($name)
    {
        throw new GenericException(GenericException::NOT_IMPLEMENTED);
//		return Resources::getResource($name, $this -> _lang);
    }

    /**
     *
     * Gets the (localized) children of a specified node
     * @param int $parentId The id of the parent page
     * @param boolean $includePath Defines whether or not to include the full path to the child
     * @param boolean $onlyPublished Display only published pages?
     * @param boolean $includeIcon Fetch the template icon?
     * @param boolean $showInNav When true, only pages with showinnavigation enabled are returned
     * @param string $order Defines the column to sort on
     * @param boolean $localize
     * @return array An array of OTreenodes
     */
    public function getChildren($parentId, $includePath = true, $onlyPublished = true, $includeIcon = false, $showInNav = null, $order = 't.index ASC', $localize = false)
    {
        $result = $this->_tree->getChildren($parentId, $includePath, $onlyPublished, $includeIcon, $showInNav, $order);
        if (!$result)
            return null;

        foreach ($result as &$child) {
            $prefix = (USEPREFIX) ? BASEURL . $this->_lang . '/' : BASEURL;
            $child->path = $prefix . $child->path;

            if ($localize)
                $child->page = $this->localize($child->page);
        }
        return $result;
    }

    /**
     * Searches for $query
     *
     * **NOTE** As of 2.1, this returns an object instead of an array
     *
     * @param string $query The string to match
     * @param int $offset The record to start from (for paging)
     * @param int $limit The maximum number of records to return (for paging)
     * @param bool $localize
     * @return \stdClass An object containing information about the search result and an array of pages with content matching $query
     */
    public function search($query, $offset, $limit, $localize = false)
    {
        $page = new Page();
        $result = $page->search($query, $offset, $limit, false, true);

        if (!$result)
            return null;

        foreach ($result->results as &$node) {
            $prefix = (USEPREFIX) ? BASEURL . $this->_lang . '/' : BASEURL;
            $node->path = $prefix . $node->path;
            if ($localize)
                $node->page = $this->localize($node->page);
        }
        return $result;
    }


    /**
     * Gets the localized child with the selected Id,
     * @param int $childId The id of the child
     * @param boolean $includeNC Specifies whether to include the number of Children
     * @param boolean $includePath When true, the full path is generated
     * @param boolean $localize When true, the content gets localized
     * @return OTreeNode The child as OTreeNode
     */
    public function getChild($childId, $includeNC = false, $includePath = false, $localize = false)
    {
        $child = $this->_tree->getChild($childId, $includeNC, $includePath);
        if (!$child)
            return null;

        if ($includePath) {
            $prefix = (USEPREFIX) ? BASEURL . $this->_lang . '/' : BASEURL;
            $child->path = $prefix . $child->path;
        }

        if ($localize)
            $this->_localizeNode($child);

        return $child;
    }

    /**
     * Gets the localized root node
     * @return OTreeNode The root node
     */
    public function getRoot()
    {
        $root = $this->_tree->getRoot();
        //$root -> page = $this -> _localize($root -> page);
        return $root;
    }

    /**
     * Gets the breadcrumbs to this page
     * @param int $treeId The id of this page
     * @param boolean $localize When true, all pages get localized
     * @return array
     */
    public function getBreadCrumb($treeId, $localize = false)
    {
        $items = $this->_tree->getBreadCrumb($treeId);
        if ($localize)
            array_walk($items, array($this, '_localizeNode'));
        return $items;
    }

    /**
     * Gets a page by it's id
     * @param int $id The id of the page
     * @return OPage The page
     */
    public function getPage($pageId)
    {
        $page = new Page();
        //$result = $this -> _localize($page -> getPageById($pageId));
        $result = $page->getPageById($pageId);
        return $result;
    }

    /**
     * Gets all the pages of the specific type, whether they reside in the tree or not
     * @param string $itemType The name of the template
     * @return array An array of OPages
     */
    public function getPagesByType($itemType)
    {
        $page = new Page();
        $result = $page->getPagesByType($itemType);
        foreach ($result as &$p) {
            $p = $this->localize($p);
        }
        return $result;
    }

    /**
     * Localize the page, the content prop is inspected. If the selected language exists, it will be selected,<br/>
     * otherwise, the first language is selected (because the first language is the default language)
     * @param OPage $page The page to localize
     * @return OPage The localized page
     */
    public function localize($page)
    {
        if (isset($page->content) && $page->content !== null) {
            foreach ($page->content as $key => $val) {
                $page->content->{$key} = $page->content->{'loc_' . $key};
            }
        }
        return $page;
    }

    /**
     * Localizes a treeNode
     * @param OTreeNode $treeNode
     */
    private function _localizeNode(OTreeNode &$treeNode)
    {
        $treeNode->page = $this->localize($treeNode->page);
    }
}